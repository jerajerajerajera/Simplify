<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Services;

use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Simplify\Core\Announcements\Support\StartTrigger;
use Simplify\Core\Push\Push;
use Simplify\Core\Support\SafeCache;
use Simplify\Core\WebApp\WebApp;
use Throwable;

/**
 * The status page's incidents: one opens by itself when a server on the website is unreachable for a few minutes and
 * closes when it is back (IncidentRules decides). A stop asked for in the panel is Planned and sends no alert; any
 * other incident goes to Discord (one message, edited when it closes), the RSS feed and the root admins' bell.
 */
final class Incidents
{
    public const TABLE = 'simplify_core_embed_incidents';

    /** The Discord webhook, encrypted, in the embed's settings table; never in the public list. */
    public const WEBHOOK = 'alert_webhook';

    /**
     * Follows the servers after Uptime::record(): opens, closes and reopens incidents and sends their alerts.
     *
     * @param  array<int, bool>  $online  server id => runs now
     * @param  Collection<int, Server>  $servers
     */
    public static function follow(array $online, Collection $servers, int $now): void
    {
        $options = EmbedOptions::all();
        $ids = array_keys($online);
        $stretches = [];
        foreach (DB::table(Uptime::TABLE)->whereIn('server_id', $ids)->where('seen_at', '>=', $now - Uptime::GAP)->orderBy('started_at')->get() as $row) {
            $stretches[(int) $row->server_id] = ['online' => (bool) $row->online, 'since' => (int) $row->started_at];
        }
        $open = [];
        foreach (DB::table(self::TABLE)->whereNull('ended_at')->get() as $row) {
            $open[(int) $row->server_id] = (int) $row->id;
        }
        // A server taken off the website closes its incident without an alert.
        $shown = EmbedServers::ids();
        foreach (array_diff_key($open, array_flip($shown)) as $incident) {
            DB::table(self::TABLE)->where('id', $incident)->update(['ended_at' => $now]);
        }
        $closed = [];
        foreach (DB::table(self::TABLE)->whereIn('server_id', $ids)->whereNotNull('ended_at')->where('ended_at', '>=', $now - 86400)->orderBy('ended_at')->get() as $row) {
            $closed[(int) $row->server_id] = ['id' => (int) $row->id, 'ended' => (int) $row->ended_at];
        }
        $byId = $servers->keyBy('id');
        foreach (IncidentRules::decide($online, $stretches, $open, $closed, $now, $options['incident_after'] * 60) as $action) {
            $server = $byId->get($action['server']);
            if (! $server instanceof Server) {
                continue;
            }
            try {
                match ($action['do']) {
                    'open' => self::open($server, (int) $action['at'], $options),
                    'close' => self::close((int) $action['incident'], (int) $action['at']),
                    'reopen' => self::reopen((int) $action['incident']),
                };
            } catch (Throwable $error) {
                report($error);
            }
        }
        DB::table(self::TABLE)->where('started_at', '<', $now - IncidentRules::KEEP_DAYS * 86400)->whereNotNull('ended_at')->delete();
        SafeCache::forget(ServerList::CACHE);
        SafeCache::forget(ServerList::INCIDENTS);
    }

    /** @param  array{planned_stops: bool}  $options */
    private static function open(Server $server, int $since, array $options): void
    {
        $planned = $options['planned_stops'] && self::asked($server, $since);
        $id = (int) DB::table(self::TABLE)->insertGetId(['server_id' => $server->id, 'started_at' => $since, 'planned' => $planned]);
        if (! $planned) {
            self::alert($id);
        }
    }

    private static function close(int $id, int $at): void
    {
        DB::table(self::TABLE)->where('id', $id)->update(['ended_at' => $at]);
        if (! (bool) DB::table(self::TABLE)->where('id', $id)->value('planned')) {
            self::alert($id);
        }
    }

    private static function reopen(int $id): void
    {
        DB::table(self::TABLE)->where('id', $id)->update(['ended_at' => null]);
        if (! (bool) DB::table(self::TABLE)->where('id', $id)->value('planned')) {
            self::alert($id);
        }
    }

    /**
     * Whether someone asked for the stop: Pelican's activity holds a Stop, Restart or Kill (or the stop command) of the
     * server in the ten minutes before, a schedule of the server with a power task ran then, or Pelican itself keeps
     * it off (installing, suspended, restoring a backup).
     */
    private static function asked(Server $server, int $since): bool
    {
        if ($server->status !== null) {
            return true;
        }
        try {
            $activity = $server->activity()
                ->where('activity_logs.timestamp', '>=', Carbon::createFromTimestamp($since - StartTrigger::CRASH_LOOK_BACK))
                ->where('activity_logs.timestamp', '<=', Carbon::createFromTimestamp($since + 60))
                ->get()
                ->map(fn ($log): array => ['event' => (string) $log->getAttribute('event'), 'command' => $log->getAttribute('properties')['command'] ?? null]);
            if (StartTrigger::asked($activity, $server->egg?->config_stop)) {
                return true;
            }
            $runs = $server->schedules()->whereHas('tasks', fn ($tasks) => $tasks->where('action', 'power')->whereIn('payload', ['stop', 'restart', 'kill']))
                ->whereNotNull('last_run_at')->pluck('last_run_at')
                ->map(fn ($at) => Carbon::parse($at)->getTimestamp())->all();

            return IncidentRules::scheduled($runs, $since);
        } catch (Throwable $error) {
            report($error);

            return false;
        }
    }

    /**
     * The incidents the page and the feed show: the last DAYS days, newest first, of servers still on the website.
     *
     * @return list<array{id: int, server: string, name: string, started: int, ended: ?int, planned: bool, note: ?string}>
     */
    public static function recent(int $now, ?int $limit = null): array
    {
        $rows = DB::table(self::TABLE)
            ->join('servers', 'servers.id', '=', self::TABLE.'.server_id')
            ->whereIn(self::TABLE.'.server_id', EmbedServers::ids())
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $now - IncidentRules::DAYS * 86400))
            ->orderByDesc('started_at')->limit($limit ?? IncidentRules::SHOWN)
            ->get([self::TABLE.'.*', 'servers.name', 'servers.uuid_short']);

        return $rows->map(fn ($row) => [
            'id' => (int) $row->id,
            'server' => (string) $row->uuid_short,
            'name' => mb_substr((string) $row->name, 0, 80),
            'started' => (int) $row->started_at,
            'ended' => $row->ended_at === null ? null : (int) $row->ended_at,
            'planned' => (bool) $row->planned,
            'note' => IncidentRules::note($row->note),
        ])->values()->all();
    }

    /**
     * The admin's notes and Planned marks from Settings › Website.
     *
     * @param  array<int|string, mixed>  $edits  incident id => {note, planned}
     */
    public static function edit(array $edits): void
    {
        foreach ($edits as $id => $edit) {
            if (! is_numeric($id) || ! is_array($edit)) {
                continue;
            }
            DB::table(self::TABLE)->where('id', (int) $id)->update(['note' => IncidentRules::note($edit['note'] ?? null), 'planned' => (bool) ($edit['planned'] ?? false)]);
        }
        SafeCache::forget(ServerList::CACHE);
        SafeCache::forget(ServerList::INCIDENTS);
    }

    /** The saved webhook, or '' (also when it cannot be decrypted, after the app key changed). */
    public static function webhook(): string
    {
        $value = DB::table(EmbedOptions::TABLE)->where('key', self::WEBHOOK)->value('value');
        try {
            $url = is_string($value) && $value !== '' ? Crypt::decryptString((string) json_decode($value, true)) : '';
        } catch (Throwable) {
            return '';
        }

        return is_string($url) && IncidentRules::validWebhook($url) ? $url : '';
    }

    /** Saves a new webhook (encrypted), or removes it with ''. */
    public static function saveWebhook(string $url): void
    {
        if ($url === '') {
            DB::table(EmbedOptions::TABLE)->where('key', self::WEBHOOK)->delete();

            return;
        }
        if (IncidentRules::validWebhook($url)) {
            DB::table(EmbedOptions::TABLE)->updateOrInsert(['key' => self::WEBHOOK], ['value' => json_encode(Crypt::encryptString($url))]);
        }
    }

    /** Sends a test message to the webhook; null when Discord took it, else why not, in plain words. */
    public static function test(string $url): ?string
    {
        $name = self::pageName();
        $payload = IncidentRules::discord($name, __('simplify::embed-list.alert_test'), __('simplify::embed-list.alert_test_body'), StatusAddress::pageUrl(), false);

        return self::post($url, $payload)['error'];
    }

    /** Discord, the bell and the devices of root admins for one incident, as it is now. */
    private static function alert(int $id): void
    {
        $row = DB::table(self::TABLE)->join('servers', 'servers.id', '=', self::TABLE.'.server_id')
            ->where(self::TABLE.'.id', $id)->first([self::TABLE.'.*', 'servers.name']);
        if ($row === null) {
            return;
        }
        $options = EmbedOptions::all();
        $down = $row->ended_at === null;
        $name = (string) $row->name;
        $link = StatusAddress::pageUrl();
        $webhook = self::webhook();
        if ($webhook !== '') {
            $locale = (string) config('app.locale', 'en');
            $title = __('simplify::embed-list.'.($down ? 'alert_down' : 'alert_back'), ['name' => $name], $locale);
            $body = $down
                ? __('simplify::embed-list.alert_down_body', ['time' => '<t:'.(int) $row->started_at.':f>'], $locale)
                : __('simplify::embed-list.alert_back_body', ['from' => '<t:'.(int) $row->started_at.':t>', 'to' => '<t:'.(int) $row->ended_at.':t>',
                    'duration' => self::duration((int) $row->ended_at - (int) $row->started_at, $locale)], $locale);
            $sent = self::post($webhook, IncidentRules::discord(self::pageName(), $title, $body, $link, $down), is_string($row->message) ? $row->message : null);
            if ($sent['message'] !== null && $sent['message'] !== $row->message) {
                DB::table(self::TABLE)->where('id', $id)->update(['message' => $sent['message']]);
            }
        }
        if ($options['bell']) {
            self::bell($id, $name, $down, (int) $row->started_at, $row->ended_at === null ? null : (int) $row->ended_at);
        }
    }

    private static function bell(int $id, string $name, bool $down, int $started, ?int $ended): void
    {
        $admins = User::role(Role::ROOT_ADMIN)->get();
        foreach ($admins->groupBy(fn (User $admin) => $admin->language ?: config('app.locale')) as $locale => $group) {
            $locale = (string) $locale;
            $title = __('simplify::embed-list.'.($down ? 'alert_down' : 'alert_back'), ['name' => $name], $locale);
            $body = $down
                ? __('simplify::embed-list.alert_down_body', ['time' => self::time($started, $locale)], $locale)
                : __('simplify::embed-list.alert_back_body', ['from' => self::time($started, $locale), 'to' => self::time((int) $ended, $locale), 'duration' => self::duration((int) $ended - $started, $locale)], $locale);
            foreach ($group as $admin) {
                $notification = Notification::make()->title($title)->body($body)->viewData(['simplify_push' => true]);
                $admin->notifyNow(($down ? $notification->danger() : $notification->success())->toDatabase());
            }
            Push::send($group, 'updates', ['title' => $title, 'body' => $body, 'url' => StatusAddress::pageUrl(), 'tag' => 'incident-'.$id.($down ? '-down-'.$started : '-back-'.$ended)]);
        }
    }

    /**
     * Posts a new message, or edits the incident's message when there is one (a removed message gets a new one). The
     * webhook's address holds its secret, so it never goes into a log: only what Discord answered does.
     *
     * @param  array<string, mixed>  $payload
     * @return array{message: ?string, error: ?string}
     */
    private static function post(string $url, array $payload, ?string $message = null): array
    {
        try {
            if ($message !== null && preg_match('/^[0-9]{5,30}$/', $message) === 1) {
                $response = Http::timeout(8)->acceptJson()->patch($url.'/messages/'.$message, $payload);
                if ($response->successful()) {
                    return ['message' => $message, 'error' => null];
                }
            }
            $response = Http::timeout(8)->acceptJson()->post($url.'?wait=true', $payload);
        } catch (Throwable) {
            Log::warning('Simplify status alerts: Discord could not be reached.');

            return ['message' => null, 'error' => __('simplify::embed.settings.alert_unreachable')];
        }
        if (! $response->successful()) {
            Log::warning('Simplify status alerts: Discord answered HTTP '.$response->status().'.');

            return ['message' => null, 'error' => __('simplify::embed.settings.alert_refused', ['code' => $response->status()])];
        }
        $id = $response->json('id');

        return ['message' => is_scalar($id) ? (string) $id : null, 'error' => null];
    }

    /** The RSS feed: an item when an unplanned incident opened and one when it closed. */
    public static function feed(): string
    {
        $locale = (string) config('app.locale', 'en');
        $items = [];
        foreach (self::recent(time()) as $incident) {
            if ($incident['planned']) {
                continue;
            }
            $note = $incident['note'] === null ? '' : ' '.$incident['note'];
            $items[] = ['guid' => 'incident-'.$incident['id'].'-down-'.$incident['started'], 'at' => $incident['started'],
                'title' => __('simplify::embed-list.alert_down', ['name' => $incident['name']], $locale),
                'body' => __('simplify::embed-list.alert_down_body', ['time' => self::time($incident['started'], $locale)], $locale).$note];
            if ($incident['ended'] !== null) {
                $items[] = ['guid' => 'incident-'.$incident['id'].'-back-'.$incident['ended'], 'at' => $incident['ended'],
                    'title' => __('simplify::embed-list.alert_back', ['name' => $incident['name']], $locale),
                    'body' => __('simplify::embed-list.alert_back_body', ['from' => self::time($incident['started'], $locale), 'to' => self::time($incident['ended'], $locale),
                        'duration' => self::duration($incident['ended'] - $incident['started'], $locale)], $locale).$note];
            }
        }
        $name = self::pageName();

        return IncidentRules::feed($name, StatusAddress::pageUrl(), StatusAddress::pageUrl('feed.xml'), __('simplify::embed-list.feed_description', ['name' => $name], $locale), $items);
    }

    /** The status page's title: its own, or "<panel> servers". */
    public static function pageName(): string
    {
        $title = EmbedOptions::all()['page_title'];

        return $title !== '' ? $title : __('simplify::embed-list.title', ['name' => WebApp::name()]);
    }

    private static function time(int $at, string $locale): string
    {
        return Carbon::createFromTimestamp($at, (string) config('app.timezone', 'UTC'))->locale($locale)->isoFormat('D MMM HH:mm (z)');
    }

    private static function duration(int $seconds, string $locale): string
    {
        return CarbonInterval::seconds(max(60, $seconds))->cascade()->locale($locale)->forHumans(['parts' => 2]);
    }
}
