<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

use App\Enums\SubuserPermission;
use App\Facades\Activity;
use App\Models\Server;
use App\Models\User;
use App\Repositories\Daemon\DaemonServerRepository;

/** Start, stop, restart and kill from the overview, with the same permissions as Pelican's console. */
final class Power
{
    public const ACTIONS = ['start', 'stop', 'restart', 'kill'];

    /** Which buttons a tile shows for a state. */
    public static function actionsFor(?string $state): array
    {
        return match ($state) {
            'offline', 'exited', 'dead' => ['start'],
            'running' => ['restart', 'stop'],
            'starting', 'restarting' => ['stop'],
            'stopping' => ['kill'],
            default => [],
        };
    }

    public static function allowed(User $user, Server $server, string $action): bool
    {
        $permission = match ($action) {
            'start' => SubuserPermission::ControlStart,
            'restart' => SubuserPermission::ControlRestart,
            'stop', 'kill' => SubuserPermission::ControlStop,
            default => null,
        };

        return $permission !== null && $user->can($permission, $server) && ! $server->isInConflictState();
    }

    public static function send(Server $server, string $action): void
    {
        app(DaemonServerRepository::class)->setServer($server)->power($action)->throw();
        Activity::event('server:power.'.$action)->subject($server)->log();
        // Pelican keeps the state for 15 seconds; drop it so the tile shows the change on the next refresh.
        cache()->forget("servers.$server->uuid.status");
        cache()->forget("servers.$server->uuid.resources");
    }
}
