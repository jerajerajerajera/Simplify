<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Support;

use App\Models\Server;
use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Models\GameCommand;
use Simplify\Core\Announcements\Services\Bell;
use Simplify\Core\Announcements\Services\TaskDelivery;
use Simplify\Core\Announcements\Services\TaskWaits;
use Simplify\Core\Models\PushDevice;
use Simplify\Core\Push\Push;
use Simplify\Core\Support\SafeCache;
use Simplify\Core\Support\Scheduler;
use Throwable;

/**
 * Why an announcement does or does not go out, without sending anything: the scheduler, the time zones, and for each
 * announcement what the next scheduler run would do with it. For `simplify:announcements --diagnose` and, in the debug
 * build only, the Test announcements button on the overview. The log holds no keys, tokens or addresses.
 */
final class Diagnose
{
    /** The Test announcements button on the overview. Only the debug build turns it on; never in a release. */
    public const BUTTON = false;

    /** Whether this build shows the Test announcements button (only the separate debug build does). */
    public static function button(): bool
    {
        return self::BUTTON;
    }

    /** Scheduler runs kept for the log. */
    public const TRACE = 30;

    private const TRACE_KEY = 'simplify-announcements.trace';

    /** Remembers one scheduler run (simplify:announcements), so the log shows what the last runs did. */
    public static function trace(string $line): void
    {
        $lines = SafeCache::get(self::TRACE_KEY, []);
        $lines = is_array($lines) ? $lines : [];
        array_unshift($lines, gmdate('Y-m-d H:i:s').'Z '.$line);
        SafeCache::put(self::TRACE_KEY, array_slice($lines, 0, self::TRACE), 7 * 86400);
    }

    /**
     * @return array{checks: list<array{level: string, title: string, detail: string}>, log: string}
     */
    public static function run(?User $viewer = null, ?string $browserZone = null): array
    {
        $checks = [];
        $log = [];
        $now = now();
        $appZone = (string) config('app.timezone', 'UTC');
        $profileZone = $viewer?->timezone ?: $appZone;
        $browserZone = self::zone($browserZone);

        $log[] = 'simplify '.self::version().' · pelican '.self::pelican().' · php '.PHP_VERSION;
        $log[] = 'checked '.$now->clone()->utc()->format('Y-m-d H:i:s').'Z';
        $log[] = 'app tz '.$appZone.' · profile '.$profileZone.' ('.self::offset($profileZone).')'.($browserZone !== null ? ' · browser '.$browserZone.' ('.self::offset($browserZone).')' : '');

        $beat = (int) SafeCache::get('simplify.scheduler-at', 0);
        $ago = $beat > 0 ? time() - $beat : null;
        $log[] = 'scheduler last beat '.($ago === null ? 'never' : $ago.'s ago').' · queue '.config('queue.default').' · cache '.config('cache.default')
            .' · push '.(Push::enabled() ? 'on ('.self::devices().' devices)' : 'off');
        $checks[] = Scheduler::stalled()
            ? self::check('bad', 'Scheduler does not run', $ago === null ? 'No scheduler run was seen. Add the cron line: '.Scheduler::cron() : 'Last run '.self::ago($ago).' ago. Announcements only go out through it.')
            : self::check('ok', 'Scheduler runs', 'Last run '.self::ago((int) $ago).' ago.');

        $zones = array_unique(array_filter([self::offset($appZone), self::offset($profileZone), $browserZone !== null ? self::offset($browserZone) : null]));
        $checks[] = count($zones) > 1
            ? self::check('warn', 'Time zones differ', "Panel $appZone, your profile $profileZone".($browserZone !== null ? ", this browser $browserZone" : '').'. Times in the form follow your profile.')
            : self::check('ok', 'Time zones match', "Panel $appZone, your profile $profileZone.");

        $waits = self::count(TaskWaits::TABLE);
        if ($waits > 0) {
            $log[] = "schedule tasks waiting for their server: $waits";
        }

        $announcements = Announcement::query()->orderBy('id')->get();
        if ($announcements->isEmpty()) {
            $checks[] = self::check('warn', 'No announcements', 'Make one under Admin › Announcements, then test again.');
        }
        foreach ($announcements as $announcement) {
            try {
                [$check, $lines] = self::announcement($announcement, $now, $profileZone);
            } catch (Throwable $error) {
                [$check, $lines] = [self::check('bad', '"'.$announcement->title.'"', 'Could not be checked: '.self::error($error)), []];
            }
            $checks[] = $check;
            array_push($log, ...$lines);
        }

        $trace = SafeCache::get(self::TRACE_KEY, []);
        $log[] = 'trace (last scheduler runs, newest first):';
        foreach (is_array($trace) && $trace !== [] ? array_slice($trace, 0, 20) : ['none yet'] as $line) {
            $log[] = '  '.$line;
        }

        return ['checks' => $checks, 'log' => implode("\n", $log)];
    }

    /** @return array{0: array{level: string, title: string, detail: string}, 1: list<string>} */
    private static function announcement(Announcement $announcement, CarbonInterface $now, string $zone): array
    {
        $title = '"'.$announcement->title.'"';
        $start = $announcement->send_on === StartTrigger::START;
        $title .= $start ? ' (when a server starts)' : ' (at a time)';
        $state = $announcement->state();
        $lines = ['#'.$announcement->id.' '.$title.' state='.$state.' audience='.$announcement->audience
            .' starts='.self::time($announcement->starts_at).' ends='.self::time($announcement->ends_at)];
        $when = $announcement->starts_at ? ' from '.$announcement->starts_at->clone()->setTimezone($zone)->format('j M H:i') : '';
        $until = $announcement->ends_at ? ' until '.$announcement->ends_at->clone()->setTimezone($zone)->format('j M H:i') : '';

        if ($state !== 'live') {
            $why = $state === 'upcoming' ? 'Starts'.$when.' (your profile time). Nothing goes out before then.' : 'Ended'.$until.'. Nothing goes out any more.';

            return [self::check($state === 'upcoming' ? 'warn' : 'warn', $title, $why), $lines];
        }

        $parts = [];
        $level = 'ok';
        $audience = User::query()->get()->filter(fn (User $user) => $announcement->audience !== 'admins' || $user->isAdmin());
        $lines[] = '   audience '.$audience->count().' accounts';

        if ($start) {
            $ids = StartTrigger::servers($announcement->start_servers);
            $seen = is_array($announcement->start_seen) ? $announcement->start_seen : [];
            $servers = Server::query()->whereKey($ids)->get();
            $running = [];
            foreach ($servers as $server) {
                $running[(int) $server->id] = TaskDelivery::running($server);
            }
            $look = StartTrigger::look($seen, $running);
            $lines[] = '   servers '.json_encode($ids).' seen '.json_encode($seen).' running now '.json_encode($running).' once='.(int) $announcement->start_once;
            if ($ids === []) {
                $level = 'bad';
                $parts[] = 'No servers are picked.';
            } elseif ($servers->count() < count($ids)) {
                $level = 'warn';
                $parts[] = (count($ids) - $servers->count()).' picked server(s) no longer exist.';
            }
            foreach ($servers as $server) {
                $was = $seen[$server->id] ?? $seen[(string) $server->id] ?? null;
                $parts[] = $server->name.': '.match (true) {
                    in_array((int) $server->id, $look['started'], true) => 'started since the last look, the next run sends it.',
                    $was === null => ($running[$server->id] ? 'running' : 'offline').', not looked at yet (the first look only remembers it).',
                    $running[$server->id] => 'running, and already was at the last look: no start yet. Stop and start it to test.',
                    default => 'offline; a start will be seen within a minute.',
                };
            }
        } else {
            $expected = $announcement->starts_at ?? $announcement->created_at;
            if ($announcement->in_bell) {
                $parts[] = $announcement->in_bell_at
                    ? 'Bell: set for '.self::local($expected, $zone).', arrived '.self::local($announcement->in_bell_at, $zone).' ('.self::late($expected, $announcement->in_bell_at).').'
                    : 'Bell: goes out on the next scheduler run.';
                $lines[] = '   bell expected='.self::time($expected).' delivered='.self::time($announcement->in_bell_at).' '.self::late($expected, $announcement->in_bell_at);
                if ($announcement->in_bell_at && $expected && $announcement->in_bell_at->getTimestamp() - $expected->getTimestamp() > 180) {
                    $level = 'warn';
                }
            }
            if ($announcement->in_game && $announcement->in_game_at) {
                $lines[] = '   game expected='.self::time($expected).' last posted='.self::time($announcement->in_game_at).' '.self::late($expected, $announcement->in_game_at);
            }
        }

        if ($announcement->in_bell) {
            $have = $audience->filter(fn (User $user) => Bell::has($announcement->id, $user))->count();
            $lines[] = '   bell items now '.$have.'/'.$audience->count();
        }
        if ($announcement->push) {
            if (! Push::enabled()) {
                $level = $level === 'ok' ? 'warn' : $level;
                $parts[] = 'Devices: app notifications are off for the panel (Settings).';
            } else {
                $devices = PushDevice::query()->whereIn('user_id', $audience->filter(fn (User $user) => Push::wants($user, 'messages'))->pluck('id'))->count();
                $parts[] = "Devices: $devices that want messages.";
                $lines[] = '   push devices wanting messages '.$devices.' pushed_to='.$announcement->pushed_to;
                if ($devices === 0) {
                    $level = $level === 'ok' ? 'warn' : $level;
                }
            }
        }
        if ($announcement->in_game) {
            $game = self::game($announcement, $start);
            $parts[] = $game['text'];
            $lines[] = '   game '.$game['log'];
            if ($game['level'] !== 'ok') {
                $level = $level === 'bad' ? 'bad' : $game['level'];
            }
        }
        if (! $announcement->in_bell && ! $announcement->push && ! $announcement->in_game) {
            $parts[] = $start ? 'Nothing is ticked to send (game chat, bell or devices).' : 'Shows as a bar only.';
            if ($start) {
                $level = 'bad';
            }
        }

        return [self::check($level, $title, 'Live'.$when.$until.'. '.implode(' ', $parts)), $lines];
    }

    /** @return array{level: string, text: string, log: string} */
    private static function game(Announcement $announcement, bool $start): array
    {
        $ids = $start ? StartTrigger::servers($announcement->start_servers) : ($announcement->in_game_servers ?? []);
        $servers = Server::query()->with('egg')->when($ids !== [], fn ($q) => $q->whereKey($ids))->orderBy('id')->limit(200)->get();
        $own = GameCommand::query()->pluck('command', 'egg_id')->all();
        $count = ['running' => 0, 'offline' => 0, 'no_command' => 0];
        foreach ($servers as $server) {
            if (GameChat::commandFor((string) data_get($server, 'egg.name'), $own[$server->egg_id] ?? null) === null) {
                $count['no_command']++;
            } elseif (TaskDelivery::running($server)) {
                $count['running']++;
            } else {
                $count['offline']++;
            }
        }
        $result = is_array($announcement->in_game_result) ? json_encode($announcement->in_game_result) : '-';
        $text = 'In game: '.$servers->count().' server(s), '.$count['running'].' running, '.$count['offline'].' offline'
            .($count['no_command'] > 0 ? ', '.$count['no_command'].' without a chat command for their egg' : '').'.';

        return [
            'level' => $count['no_command'] > 0 || $servers->isEmpty() ? 'warn' : 'ok',
            'text' => $text,
            'log' => 'targets '.$servers->count().' running '.$count['running'].' offline '.$count['offline'].' no_command '.$count['no_command']
                .' last='.self::time($announcement->in_game_at).' result='.$result,
        ];
    }

    /** Title of the announcements the tests make, so they are easy to find and delete. */
    public const TEST_TITLE = '[Test] Simplify announcement test';

    /** One real item in this account's own bell and on its own devices, right now. Returns what it reached. */
    public static function sendToMe(User $user): string
    {
        $announcement = new Announcement(['title' => self::TEST_TITLE, 'body' => 'Sent now from Test announcements.', 'category' => 'info']);
        $announcement->id = 0;
        Bell::send($announcement, $user, Bell::EVERYONE);
        $devices = Push::send([$user], 'messages', ['title' => self::TEST_TITLE, 'body' => 'Sent now from Test announcements.', 'tag' => 'simplify-test-'.time()]);
        self::trace('test to account '.$user->id.': bell 1, devices '.$devices);

        return 'Sent now: 1 item in your bell, '.$devices.' device(s).';
    }

    /**
     * A real announcement for admins that starts in two minutes and ends ten minutes later: in the bell, on devices
     * and, when a server is given, in that server's game chat. The scheduler sends it; the log then shows set time
     * against arrival.
     */
    public static function scheduleTest(User $author, ?int $server = null): string
    {
        $start = now()->addMinutes(2)->startOfMinute();
        $announcement = new Announcement;
        $announcement->forceFill([
            'title' => self::TEST_TITLE,
            'body' => 'Set for '.$start->clone()->setTimezone($author->timezone ?: (string) config('app.timezone', 'UTC'))->format('H:i').'. If you see this, timed announcements work.',
            'category' => 'info',
            'audience' => 'admins',
            'send_on' => StartTrigger::TIME,
            'starts_at' => $start,
            'ends_at' => $start->clone()->addMinutes(10),
            'in_bell' => true,
            'push' => Push::enabled(),
            'in_game' => $server !== null,
            'in_game_servers' => $server !== null ? [$server] : [],
            'in_game_repeat' => 0,
            'created_by' => $author->id,
        ])->save();
        self::trace('test #'.$announcement->id.' set for '.$start->clone()->utc()->format('H:i').'Z');

        return 'Test set for '.$start->clone()->setTimezone($author->timezone ?: (string) config('app.timezone', 'UTC'))->format('H:i').' (your profile time). Wait 3 minutes, then test again to see when it arrived.';
    }

    /** A real announcement for admins on the next start of one server. Restart that server, then test again. */
    public static function startTest(User $author, int $server): string
    {
        $name = Server::query()->whereKey($server)->value('name');
        if (! is_string($name)) {
            return 'That server does not exist.';
        }
        $announcement = new Announcement;
        $announcement->forceFill([
            'title' => self::TEST_TITLE,
            'body' => 'If you see this, announcements on a server start work.',
            'category' => 'info',
            'audience' => 'admins',
            'send_on' => StartTrigger::START,
            'start_servers' => [$server],
            'start_once' => true,
            'in_bell' => true,
            'push' => Push::enabled(),
            'in_game' => true,
            'created_by' => $author->id,
        ])->save();
        self::trace('test #'.$announcement->id.' waits for a start of server '.$server);

        return 'Waiting for the next start of '.$name.'. Wait a minute, restart it, wait another minute, then test again.';
    }

    /** @return array{level: string, title: string, detail: string} */
    private static function check(string $level, string $title, string $detail): array
    {
        return ['level' => $level, 'title' => $title, 'detail' => $detail];
    }

    private static function zone(?string $zone): ?string
    {
        return is_string($zone) && in_array($zone, DateTimeZone::listIdentifiers(), true) ? $zone : null;
    }

    private static function offset(string $zone): string
    {
        try {
            return now($zone)->format('P');
        } catch (Throwable) {
            return '?';
        }
    }

    private static function local(?CarbonInterface $time, string $zone): string
    {
        return $time ? $time->clone()->setTimezone($zone)->format('j M H:i:s') : '-';
    }

    /** How long after the set time something arrived: "on time" within the scheduler's minute. */
    private static function late(?CarbonInterface $expected, ?CarbonInterface $actual): string
    {
        if ($expected === null || $actual === null) {
            return '';
        }
        $seconds = $actual->getTimestamp() - $expected->getTimestamp();

        return $seconds <= 75 ? 'on time, +'.max(0, $seconds).'s' : '+'.self::ago($seconds).' late';
    }

    private static function time(?CarbonInterface $time): string
    {
        return $time ? $time->clone()->utc()->format('Y-m-d H:i').'Z' : '-';
    }

    private static function ago(int $seconds): string
    {
        return $seconds < 120 ? $seconds.' seconds' : intdiv($seconds, 60).' minutes';
    }

    private static function count(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private static function devices(): int
    {
        try {
            return PushDevice::query()->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /** An error's class and the start of its message, without anything that looks like an address or a key. */
    public static function error(Throwable $error): string
    {
        $message = preg_replace(['~https?://\S+~', '~\b\d{1,3}(\.\d{1,3}){3}(:\d+)?\b~', '~[A-Za-z0-9_\-]{32,}~'], ['[url]', '[ip]', '[key]'], $error->getMessage()) ?? '';

        return class_basename($error).': '.mb_substr($message, 0, 200);
    }

    private static function version(): string
    {
        $file = dirname(__DIR__, 3).'/plugin.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) ? (string) ($data['version'] ?? '?') : '?';
    }

    private static function pelican(): string
    {
        return (string) config('app.version', '?');
    }
}
