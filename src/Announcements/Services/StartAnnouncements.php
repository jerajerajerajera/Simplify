<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Services;

use App\Models\Server;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Models\GameCommand;
use Simplify\Core\Announcements\Support\Diagnose;
use Simplify\Core\Announcements\Support\GameChat;
use Simplify\Core\Announcements\Support\Rules;
use Simplify\Core\Announcements\Support\StartTrigger;
use Simplify\Core\Announcements\Support\Weekly;
use Simplify\Core\Push\Push as AppPush;
use Simplify\Core\Support\SafeCache;
use Throwable;

/**
 * "When a server starts, stops or crashes": every minute the scheduler looks at the servers of these announcements,
 * so a start from the console or after a crash counts too. Each moment that counts sends the announcement to the
 * places it asks for: the game chat, the bell, devices, and a bar for as long as it stays.
 */
final class StartAnnouncements
{
    /** Looks at every live start announcement. Returns the starts it sent an announcement for. */
    public static function runDue(): int
    {
        $sent = 0;
        foreach (Announcement::query()->live()->where('send_on', StartTrigger::START)->get() as $announcement) {
            // "Only the next time" went out: it waits for its bar to end, or to be ended.
            if ($announcement->start_once && $announcement->weekly_from !== null) {
                continue;
            }
            // One look per announcement and minute, even when two schedulers overlap.
            if (! SafeCache::add('simplify-announcements.start.'.$announcement->id.'.'.intdiv(time(), 60), true, 120)) {
                continue;
            }
            try {
                $sent += self::look($announcement);
            } catch (Throwable $error) {
                report($error);
                Diagnose::trace('start #'.$announcement->id.' failed: '.Diagnose::error($error));
            }
        }

        return $sent;
    }

    private static function look(Announcement $announcement): int
    {
        $servers = Server::query()->with('egg')->whereKey(StartTrigger::servers($announcement->start_servers))->get();
        $running = [];
        foreach ($servers as $server) {
            $running[(int) $server->id] = self::state($server);
        }
        $look = StartTrigger::look(is_array($announcement->start_seen) ? $announcement->start_seen : [], $running);
        // Remembered first, so an error halfway never sends the same start twice.
        $announcement->forceFill(['start_seen' => $look['seen']])->saveQuietly();
        if ($look['started'] !== [] || $look['seen'] !== $announcement->getOriginal('start_seen')) {
            Diagnose::trace('start #'.$announcement->id.' running '.json_encode($running).' started '.json_encode($look['started']));
        }
        $event = StartTrigger::event($announcement->start_event);
        $counted = match ($event) {
            StartTrigger::START => $look['started'],
            'stop' => $look['stopped'],
            default => self::crashed($announcement, $servers, $look['stopped']),
        };
        $sent = 0;
        foreach ($counted as $id) {
            $server = $servers->firstWhere('id', $id);
            $sent += $server !== null && self::startedToday($announcement, $server) ? 1 : 0;
            // "Only the next time" is done after the first one that counted.
            if ($announcement->start_once && $announcement->weekly_from !== null) {
                break;
            }
        }

        return $sent;
    }

    /**
     * The stops nobody asked for. A stop that was just seen waits (start_pending): Wings tells Pelican who pressed
     * Stop about once a minute. After that it is a crash when the server's activity holds no Stop, Restart or Kill and
     * no stop command in the minutes before it.
     *
     * @param  Collection<int, Server>  $servers
     * @param  list<int>  $stopped
     * @return list<int>
     */
    private static function crashed(Announcement $announcement, Collection $servers, array $stopped): array
    {
        $now = time();
        $pending = is_array($announcement->start_pending) ? $announcement->start_pending : [];
        foreach ($stopped as $id) {
            $pending[$id] ??= $now;
        }
        $waited = StartTrigger::due($pending, $now);
        if ($waited['pending'] != $announcement->start_pending) {
            // Remembered first, so an error halfway never sends the same crash twice.
            $announcement->forceFill(['start_pending' => $waited['pending'] === [] ? null : $waited['pending']])->saveQuietly();
        }
        $crashed = [];
        foreach ($waited['due'] as $id => $seenAt) {
            $server = $servers->firstWhere('id', $id);
            if ($server === null) {
                continue;
            }
            $activity = $server->activity()
                ->where('activity_logs.timestamp', '>=', Carbon::createFromTimestamp($seenAt - StartTrigger::CRASH_LOOK_BACK))
                ->get()
                ->map(fn ($log): array => ['event' => (string) $log->event, 'command' => $log->properties['command'] ?? null]);
            $asked = StartTrigger::asked($activity, $server->egg->config_stop);
            Diagnose::trace('start #'.$announcement->id.' stop of server '.$id.($asked ? ' was asked for' : ' counts as a crash'));
            if (! $asked) {
                $crashed[] = $id;
            }
        }

        return $crashed;
    }

    /**
     * False when the server is not running, null when Wings did not say (StartTrigger::runs), otherwise when it
     * started (StartTrigger::since): a restart between two looks leaves it running at both, and only its uptime tells
     * (K-B81).
     */
    public static function state(Server $server): bool|int|null
    {
        try {
            $runs = $server->isSuspended() ? false : StartTrigger::runs($server->retrieveStatus()->value);
        } catch (Throwable) {
            return null;
        }
        if ($runs !== true) {
            return $runs;
        }
        try {
            return StartTrigger::since($server->retrieveResources()['uptime'] ?? null, time());
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * A start the scheduler saw: sent only on the announcement's days, if it has any (Khion 15-11), and in its hours.
     * "Once per day" sends only the first start of the day per server, every chosen day again (Khion 22-11). A start
     * that does not count is remembered as seen, so it does not go out later either.
     */
    public static function startedToday(Announcement $announcement, Server $server, ?int $now = null): bool
    {
        $now ??= time();
        $zone = Weekly::zone($announcement->weekly_zone);
        if (! Weekly::onDay(Weekly::days($announcement->weekly_days), $zone, $now)) {
            Diagnose::trace('start #'.$announcement->id.' of server '.$server->id.' not on its days today');

            return false;
        }
        if (! Weekly::inHours($announcement->start_from, $announcement->start_until, $zone, $now)) {
            Diagnose::trace('start #'.$announcement->id.' of server '.$server->id.' outside its hours');

            return false;
        }
        if ($announcement->start_daily) {
            $sent = is_array($announcement->start_sent) ? $announcement->start_sent : [];
            $today = Weekly::day($zone, $now);
            if (($sent[$server->id] ?? null) === $today) {
                Diagnose::trace('start #'.$announcement->id.' of server '.$server->id.' already sent today');

                return false;
            }
            // Remembered first, so an error halfway never sends it twice on one day.
            $sent[$server->id] = $today;
            $announcement->forceFill(['start_sent' => $sent])->saveQuietly();
        }
        self::started($announcement, $server);

        return true;
    }

    /**
     * A server of this announcement started: send it, and end it when it goes out only once. Ending keeps what was just
     * sent: the bell item is the message of that start (the model only clears the bell of an ended timed announcement).
     */
    public static function started(Announcement $announcement, Server $server): void
    {
        Diagnose::trace('start #'.$announcement->id.' sending for server '.$server->id);
        $now = now();
        // The bar of this moment, for as long as it stays: also for accounts that unpinned the last one. Set before
        // sending, so the chat of "that server" and the people of the server follow this moment.
        $until = Rules::windowEnd($now->getTimestamp(), $announcement->stay, $announcement->stay_minutes, $announcement->ends_at?->getTimestamp());
        $announcement->forceFill(['weekly_from' => $now, 'weekly_until' => $announcement->bar ? Carbon::createFromTimestamp($until) : $now, 'shown_server' => $server->id])->saveQuietly();
        if ($announcement->bar) {
            $announcement->dismissedBy()->detach();
        }
        self::send($announcement, $server);
        // Only the next time: without a bar it ends now, with a bar for a while it ends with that bar, and with a bar
        // until it is ended it waits for the admin (runDue skips it from here on).
        if ($announcement->start_once && $announcement->state() !== 'ended' && (! $announcement->bar || $announcement->stay === 'for')) {
            $announcement->forceFill(['ends_at' => $announcement->bar ? Carbon::createFromTimestamp($until) : now()])->save();
        }
    }

    private static function send(Announcement $announcement, Server $server): void
    {
        if ($announcement->in_game && $announcement->in_game_target === 'servers') {
            // The chosen servers, or all of them, as an announcement at a time does.
            $result = InGame::send($announcement);
            $announcement->forceFill(['in_game_at' => now(), 'in_game_result' => $result])->saveQuietly();
        } elseif ($announcement->in_game) {
            $own = GameCommand::query()->where('egg_id', $server->egg_id)->value('command');
            $command = GameChat::commandFor((string) $server->egg->name, is_string($own) ? $own : null);
            // A server that stopped or crashed has no chat to post in.
            $result = match (true) {
                $command === null => 'unsupported',
                StartTrigger::event($announcement->start_event) !== StartTrigger::START => 'offline',
                default => 'sent',
            };
            if ($result === 'sent') {
                try {
                    $server->send(GameChat::render($command, GameChat::message(__('simplify::announcements.in_game.prefix'), $announcement->title, $announcement->body)));
                } catch (Throwable $error) {
                    // Wings did not answer: the bell and devices still get it.
                    $result = 'failed';
                    Diagnose::trace('start #'.$announcement->id.' game chat on '.$server->id.' failed: '.Diagnose::error($error));
                }
            }
            // What the list shows for this start; it said "Waits for start" after the start had gone out (K-B89).
            $announcement->forceFill(['in_game_at' => now(), 'in_game_result' => [...['sent' => 0, 'offline' => 0, 'unsupported' => 0, 'failed' => 0], $result => 1]])->saveQuietly();
        }
        if (! $announcement->in_bell && ! $announcement->push) {
            return;
        }
        $title = StartTrigger::title($server->name, $announcement->title);
        // Everyone, admins, or the owner of this server and the people with access to it (K-B94).
        $users = Bell::audience($announcement, $server)->get();
        if ($announcement->in_bell) {
            // The item of the previous start goes, so the bell holds the latest one.
            Bell::forget($announcement->id, kind: Bell::EVERYONE);
            foreach ($users as $user) {
                Bell::send($announcement, $user, Bell::EVERYONE, $title);
            }
            $announcement->forceFill(['in_bell_at' => now()])->saveQuietly();
            Diagnose::trace('start #'.$announcement->id.' bell reached '.$users->count().' accounts');
        }
        if ($announcement->push && Push::available()) {
            $reached = AppPush::send($users, 'messages', [
                'title' => $title,
                'body' => (string) $announcement->body,
                'url' => $announcement->button_url ?: url('/server/'.$server->uuid_short),
                'tag' => 'simplify-announcement-'.$announcement->id.'-'.$server->id.'-'.time(),
            ]);
            // The devices of this start, not of every start added up: one device and twelve starts read as "Devices
            // reached: 12" (K-B87).
            $announcement->forceFill(['pushed_to' => $reached])->saveQuietly();
            Diagnose::trace('start #'.$announcement->id.' push reached '.$reached.' devices');
        }
    }
}
