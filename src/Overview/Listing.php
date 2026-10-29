<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

use App\Enums\SubuserPermission;
use App\Models\Server;
use App\Models\User;
use Filament\Facades\Filament;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Addons\Button;
use Simplify\Core\Server\Looks;
use Simplify\Core\Subdomains\Services\Games;
use Simplify\Core\Subdomains\Subdomains;
use Throwable;

/**
 * Everything a server card shows, for every server the user sees: one database query, one Wings request per node,
 * and what add-ons know about the game.
 */
final class Listing
{
    /**
     * @param  bool  $badges  add-on badges are asked once per visit; the refreshes leave them out
     * @return list<array<string, mixed>>
     */
    public static function for(User $user, bool $badges = true): array
    {
        $servers = ServerQuery::for($user)->get();
        $live = NodeUsage::read($servers->filter(fn (Server $server) => $server->status === null));
        $looks = Looks::forMany($servers->pluck('id')->all());
        $mine = $user->directAccessibleServers()->pluck('servers.id')->all();
        $subdomains = $badges ? Subdomains::addresses($servers) : [];

        return $servers->map(fn (Server $server) => [
            ...self::card($user, $server, $live[$server->uuid] ?? null, $looks[$server->id] ?? [], in_array($server->id, $mine, true)),
            ...$badges ? ['badges' => self::badges($server, $subdomains[$server->uuid] ?? null)] : [],
        ])->all();
    }

    /**
     * One server for its own page: the card, plus what the header there shows.
     *
     * @return array<string, mixed>
     */
    public static function one(User $user, Server $server): array
    {
        $live = $server->status === null ? NodeUsage::read(collect([$server]))[$server->uuid] ?? null : null;
        $allocation = $server->allocation;

        return [
            ...self::card($user, $server, $live, Looks::for($server), true),
            'badges' => self::badges($server, Subdomains::address($server)),
            'address' => $allocation === null ? null : ($allocation->alias ?: $allocation->ip).':'.$allocation->port,
            'minecraft' => Games::minecraft($server),
            'can_edit' => Looks::canEdit($user, $server),
            'links' => array_map(fn (Button $link) => $link->toArray(), app(AddonRegistry::class)->collect(fn (Addon $addon) => $addon->serverLinks($server))),
        ];
    }

    /**
     * The subdomain address first, then what add-ons put on the tile.
     *
     * @return list<string>
     */
    private static function badges(Server $server, ?string $subdomain): array
    {
        return [...$subdomain === null ? [] : [$subdomain], ...app(AddonRegistry::class)->collect(fn (Addon $addon) => $addon->tileBadges($server))];
    }

    /**
     * @param  array<string, mixed>|null  $live
     * @param  array{color?: ?string, banner?: ?string}  $look
     * @return array<string, mixed>
     */
    private static function card(User $user, Server $server, ?array $live, array $look, bool $mine): array
    {
        // Installing or restoring: Wings has nothing useful to add yet.
        $status = match ($server->status?->value) {
            null => $live['state'] ?? 'missing',
            'installing' => 'installing',
            'install_failed', 'reinstall_failed' => 'install failed',
            'restoring_backup' => 'restoring',
            default => $server->status->value,
        };
        $minecraft = Games::minecraft($server);
        $info = in_array($status, ['offline', 'installing', 'install failed'], true) ? ['players' => 0] : self::info($server);
        $egg = $server->egg?->icon;

        return [
            'uuid' => $server->uuid,
            'name' => $server->name,
            'color' => $look['color'] ?? null,
            'background' => Artwork::background($server, $look['banner'] ?? null),
            'software' => $server->egg?->name,
            'game' => $minecraft ? 'minecraft' : 'other',
            'game_label' => $minecraft ? 'Minecraft' : ($server->egg?->name ?: __('simplify::dashboard.page.other_game')),
            'status' => $status,
            'mine' => $mine,
            'node_id' => (int) $server->node_id,
            'node_name' => (string) $server->node?->name,
            'created' => $server->created_at?->getTimestamp(),
            'port' => $server->allocation?->port,
            'url' => Filament::getPanel('server')->getUrl($server),
            'permissions' => [
                'control.start' => $user->can(SubuserPermission::ControlStart, $server),
                'control.stop' => $user->can(SubuserPermission::ControlStop, $server),
                'control.restart' => $user->can(SubuserPermission::ControlRestart, $server),
            ],
            'server_icon' => $info['icon'] ?? null,
            'egg_icon' => is_string($egg) && $egg !== '' ? $egg : null,
            'players' => $info['players'] ?? null,
            'max_players' => $info['max_players'] ?? null,
            'motd' => $info['motd'] ?? '',
            'reported_version' => $info['version'] ?? '',
            'usage' => [
                'cpu_percent' => $live['cpu'] ?? null,
                'memory_bytes' => $live['memory'] ?? null,
                'disk_bytes' => $live['disk'] ?? null,
                'network_rx_bytes' => $live['rx'] ?? null,
                'network_tx_bytes' => $live['tx'] ?? null,
                'network_sampled_at' => microtime(true),
                'cpu_limit' => (float) $server->cpu,
                'memory_limit_bytes' => (int) $server->memory * 1048576,
                'disk_limit_bytes' => (int) $server->disk * 1048576,
            ],
        ];
    }

    /** @return array<string, mixed> the first value each add-on knows, in add-on order */
    public static function info(Server $server): array
    {
        $info = [];
        foreach (app(AddonRegistry::class)->all() as $addon) {
            try {
                $info += array_filter($addon->serverInfo($server), fn ($value) => $value !== null && $value !== '');
            } catch (Throwable $error) {
                report($error);
            }
        }

        return $info;
    }
}
