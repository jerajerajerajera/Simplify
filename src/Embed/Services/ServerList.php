<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Services;

use App\Enums\ContainerStatus;
use App\Models\Server;
use Illuminate\Support\Facades\Event;
use Simplify\Core\Models\ServerLook;
use Simplify\Core\Server\Looks;
use Simplify\Core\Subdomains\Subdomains;
use Simplify\Core\Support\Assets;
use Simplify\Core\Support\SafeCache;
use Simplify\Core\WebApp\WebApp;
use Throwable;

/**
 * The public list: only what a visitor needs (name, game, status, players, a public join address, the server's
 * description and its banner). Never ids, owners, nodes or private addresses.
 *
 * Other plugins can add details through the "simplify.embed.server" event. A listener receives the Server and
 * returns an array with any of: game, software, version, players, max_players, motd, address, icon, download and
 * modpack (name, version, url, icon, download). Simplify Toolkit adds players and modpacks this way. The address
 * of Simplify's subdomain is used when it works. What the owner fills in on the server's Website page (description,
 * image, download, second address, position) comes first; download falls back to the listener's download, then the
 * modpack's.
 */
final class ServerList
{
    public const CACHE = 'simplify-embed.servers';

    public const INCIDENTS = 'simplify-embed.incidents';

    public const EVENT = 'simplify.embed.server';

    /** Seconds the list is cached, so visitors never reach Wings or a game server. */
    public const TTL = 60;

    /** At most this many servers are published, so one request never walks a whole host. */
    public const LIMIT = 100;

    /** @return array{options: array<string, mixed>, servers: list<array<string, mixed>>, name: string, texts: array<string, string>} */
    public static function payload(): array
    {
        $options = EmbedOptions::all();
        if ($options['accent'] === '') {
            $options['accent'] = EmbedOptions::ACCENT;
        }

        return [
            'options' => array_diff_key($options, array_flip(EmbedOptions::PRIVATE)),
            'servers' => SafeCache::remember(self::CACHE, self::TTL, fn (): array => self::collect()),
            // The incidents of the last days, for the status page's banner and log (Incidents).
            'incidents' => $options['incidents'] ? SafeCache::remember(self::INCIDENTS, self::TTL, fn (): array => self::incidents()) : [],
            // The panel's own name from Simplify's settings; it said Pelican on a panel with a name of its own (K-B91).
            'name' => WebApp::name(),
            'texts' => self::texts(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function collect(): array
    {
        $servers = Server::query()
            ->whereIn('id', EmbedServers::ids())
            ->with(['allocation', 'egg', 'node'])
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();
        $details = EmbedDetails::many($servers->modelKeys());
        $options = EmbedOptions::all();
        $uptime = $options['uptime'] ? self::uptime($servers->modelKeys()) : [];
        $out = [];
        foreach ($servers as $server) {
            try {
                $out[] = [...self::entry($server, $details[(int) $server->id] ?? [], $options['logos'], $uptime[(int) $server->id] ?? null),
                    'pinned' => in_array((int) $server->id, $options['pinned'], true)];
            } catch (Throwable $error) {
                report($error);
            }
        }

        return self::sorted($out, $options['online_first']);
    }

    /**
     * Only what a visitor sees: which server, when, planned or not, and the admin's note.
     *
     * @return list<array{server: string, name: string, started: int, ended: ?int, planned: bool, note: ?string}>
     */
    private static function incidents(): array
    {
        try {
            return array_map(fn (array $incident) => array_diff_key($incident, ['id' => 1]), Incidents::recent(time()));
        } catch (Throwable $error) {
            // Before the migration has run: the page shows, without incidents.
            report($error);

            return [];
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{since: ?int, online: ?bool, percent: ?float, days: list<?float>}>
     */
    private static function uptime(array $ids): array
    {
        try {
            return Uptime::summary($ids, time());
        } catch (Throwable $error) {
            // Before the migration has run: the list still shows, without uptime.
            report($error);

            return [];
        }
    }

    /**
     * Pinned servers first, then by the position in Settings › Website (none comes last), then by name; with
     * $onlineFirst, online servers first (after the pinned ones).
     *
     * @param  list<array<string, mixed>>  $servers
     * @return list<array<string, mixed>>
     */
    public static function sorted(array $servers, bool $onlineFirst = false): array
    {
        $key = fn (array $server) => [empty($server['pinned']), $onlineFirst && $server['state'] !== 'online', $server['position'] ?? PHP_INT_MAX, mb_strtolower((string) $server['name'])];
        usort($servers, fn (array $a, array $b) => $key($a) <=> $key($b));

        return $servers;
    }

    /**
     * @param  array<string, mixed>  $details  what the owner filled in on the server's Website page (EmbedDetails)
     * @param  array<int, string>  $logos  logos uploaded per egg (EmbedOptions)
     * @param  array{since: ?int, online: ?bool, percent: ?float, days: list<?float>}|null  $uptime  Uptime::summary(), null when off
     * @return array<string, mixed>
     */
    public static function entry(Server $server, array $details = [], array $logos = [], ?array $uptime = null): array
    {
        $state = self::state($server);
        $extra = self::extra($server, $state === 'online');
        $allocation = $server->allocation;
        $port = (int) ($allocation?->port ?? 0);

        return self::clean([
            'id' => (string) $server->uuid_short,
            'name' => (string) $server->name,
            'game' => (string) ($extra['game'] ?? $server->egg?->name ?? 'Game'),
            'software' => $extra['software'] ?? null,
            'version' => $extra['version'] ?? null,
            'state' => $state,
            'players' => $state === 'online' ? ($extra['players'] ?? null) : null,
            'max_players' => $state === 'online' ? ($extra['max_players'] ?? null) : null,
            'motd' => $extra['motd'] ?? null,
            'address' => PublicAddress::first([
                is_string($extra['address'] ?? null) ? $extra['address'] : null,
                self::subdomain($server),
                PublicAddress::join($allocation?->ip_alias, $port),
                PublicAddress::join($allocation?->ip, $port),
            ]),
            // What a listener knows (a server icon, a modpack's) is the server's own; else the logo of its egg.
            'icon' => $extra['icon'] ?? $extra['modpack']['icon'] ?? ($logo = EggLogo::for($server, $logos))['url'] ?? null,
            'logo' => ! isset($extra['icon']) && ! isset($extra['modpack']['icon']) && isset($logo),
            'uptime' => $uptime === null ? null : self::uptimeFor($uptime, $state),
            'modpack' => $extra['modpack'] ?? null,
            'description' => $details['description'] ?? $server->description,
            'banner' => self::banner($server, $details['banner'] ?? null),
            'download' => self::safeUrl($details['download'] ?? null) ?? self::safeUrl($extra['download'] ?? null) ?? ($extra['modpack']['download'] ?? null),
            'address2' => PublicAddress::first([$details['address2'] ?? null]),
            'position' => $details['position'] ?? null,
        ]);
    }

    /**
     * The image for the website as a public https link: the one picked on the Website page, else the banner the
     * server shows in Simplify. A scene links to Simplify's asset, an upload to the embed's own address, which
     * serves only images of servers on the website. Null for the automatic scene.
     */
    private static function banner(Server $server, ?string $picked): ?string
    {
        $banner = $picked ?? ServerLook::query()->where('server_id', $server->id)->value('banner');

        return match (true) {
            ! is_string($banner) || $banner === '' => null,
            str_starts_with($banner, Looks::SCENE) => Assets::url(substr($banner, strlen(Looks::SCENE))),
            default => route('simplify.embed.banner', ['file' => basename($banner)]),
        };
    }

    /**
     * The uptime as the card shows it: since when the server is in the state it shows (only when the record agrees),
     * the share of the last 30 days and per day.
     *
     * @param  array{since: ?int, online: ?bool, percent: ?float, days: list<?float>}  $uptime
     * @return array{since: ?int, percent: ?float, days: list<?float>}
     */
    private static function uptimeFor(array $uptime, string $state): array
    {
        $agrees = $uptime['online'] !== null && $uptime['online'] === ($state === 'online') && in_array($state, ['online', 'offline'], true);

        return ['since' => $agrees ? $uptime['since'] : null, 'percent' => $uptime['percent'], 'days' => $uptime['days']];
    }

    /** The subdomain when it works. */
    private static function subdomain(Server $server): ?string
    {
        try {
            return Subdomains::address($server);
        } catch (Throwable $error) {
            report($error);

            return null;
        }
    }

    /** online, starting, stopping, installing or offline. */
    public static function state(Server $server): string
    {
        if ($server->status !== null) {
            return str_contains((string) $server->status->value, 'install') ? 'installing' : 'offline';
        }

        try {
            $status = $server->retrieveStatus();
        } catch (Throwable) {
            // Wings cannot be reached: visitors see the server as offline instead of losing the whole list.
            return 'offline';
        }

        return match ($status) {
            ContainerStatus::Running => 'online',
            ContainerStatus::Starting, ContainerStatus::Restarting => 'starting',
            ContainerStatus::Stopping => 'stopping',
            default => 'offline',
        };
    }

    /**
     * What other plugins add for this server. $running tells listeners whether asking the game server makes sense.
     *
     * @return array<string, mixed>
     */
    private static function extra(Server $server, bool $running): array
    {
        if (! Event::hasListeners(self::EVENT)) {
            return [];
        }
        $extra = [];
        foreach (Event::dispatch(self::EVENT, [$server, $running]) ?? [] as $result) {
            if (is_array($result)) {
                $extra = [...$extra, ...$result];
            }
        }

        return $extra;
    }

    /**
     * Cuts every text to a sane length and drops links that are not https, whatever a listener returned.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    public static function clean(array $entry): array
    {
        $text = fn ($value, int $length): ?string => is_scalar($value) && trim((string) $value) !== ''
            ? mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $value) ?? ''), 0, $length)
            : null;
        $count = fn ($value): ?int => is_numeric($value) && (int) $value >= 0 ? min((int) $value, 1000000) : null;
        $pack = is_array($entry['modpack'] ?? null) && $text($entry['modpack']['name'] ?? null, 80) !== null ? [
            'name' => $text($entry['modpack']['name'], 80),
            'version' => $text($entry['modpack']['version'] ?? null, 40),
            'url' => self::safeUrl($entry['modpack']['url'] ?? null),
            'icon' => self::safeUrl($entry['modpack']['icon'] ?? null),
            'download' => self::safeUrl($entry['modpack']['download'] ?? null),
        ] : null;

        return [
            'id' => (string) $entry['id'],
            'name' => $text($entry['name'], 80) ?? '',
            'game' => $text($entry['game'], 60) ?? 'Game',
            'software' => $text($entry['software'] ?? null, 60),
            'version' => $text($entry['version'] ?? null, 40),
            'state' => in_array($entry['state'], ['online', 'starting', 'stopping', 'installing'], true) ? $entry['state'] : 'offline',
            'players' => $count($entry['players'] ?? null),
            'max_players' => $count($entry['max_players'] ?? null),
            'motd' => $text($entry['motd'] ?? null, 200),
            'address' => $entry['address'] ?? null,
            'icon' => self::safeUrl($entry['icon'] ?? null),
            'logo' => (bool) ($entry['logo'] ?? false),
            'uptime' => is_array($entry['uptime'] ?? null) ? [
                'since' => is_int($entry['uptime']['since'] ?? null) ? $entry['uptime']['since'] : null,
                'percent' => is_numeric($entry['uptime']['percent'] ?? null) ? (float) $entry['uptime']['percent'] : null,
                'days' => array_map(fn ($day) => is_numeric($day) ? (float) $day : null, array_slice(array_values((array) ($entry['uptime']['days'] ?? [])), 0, Uptime::DAYS)),
            ] : null,
            'modpack' => $pack,
            'description' => $text($entry['description'] ?? null, EmbedDetails::DESCRIPTION),
            'banner' => self::safeUrl($entry['banner'] ?? null),
            'download' => self::safeUrl($entry['download'] ?? null),
            'address2' => $text($entry['address2'] ?? null, 120),
            'position' => $count($entry['position'] ?? null),
        ];
    }

    /** An https link, or null. */
    public static function safeUrl(mixed $url): ?string
    {
        if (! is_string($url) || strlen($url) > 500 || ! str_starts_with($url, 'https://')) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * The games of the servers on the website, for "only this game" in the code.
     *
     * @return list<string>
     */
    public static function games(): array
    {
        $games = array_unique(array_column(SafeCache::remember(self::CACHE, self::TTL, fn (): array => self::collect()), 'game'));
        sort($games);

        return array_values($games);
    }

    /**
     * The texts embed.js shows, in the panel's language.
     *
     * @return array<string, string>
     */
    private static function texts(): array
    {
        $texts = [];
        foreach (['online', 'starting', 'stopping', 'installing', 'offline', 'players', 'copy', 'copied', 'selected', 'view', 'empty', 'copy_address',
            'online_for', 'offline_for', 'uptime', 'last_days', 'days_ago', 'today', 'day_tip', 'day_none', 'unit_d', 'unit_h', 'unit_m',
            'pinned', 'alert_down', 'banner_since', 'incidents', 'incidents_none', 'ongoing', 'resolved', 'planned'] as $key) {
            $texts[$key] = __('simplify::embed-list.'.$key);
        }

        return $texts;
    }
}
