<?php

declare(strict_types=1);

namespace Simplify\Core\Server;

use App\Models\Server;
use Simplify\Core\Support\SafeCache;

/**
 * The installation view on the server page (as in Simplify 0.7.0): when the installation started and how long this
 * egg usually takes on this panel. Try again runs Pelican's own reinstall.
 */
final class Installation
{
    /** Pelican's states while a server is not usable yet. */
    public const STATES = ['installing', 'install_failed', 'reinstall_failed', 'restoring_backup'];

    public const FAILED = ['install_failed', 'reinstall_failed'];

    /** @return array{setup: ?string, install_since: ?int, install_typical: ?int} */
    public static function info(Server $server): array
    {
        $state = $server->status?->value;
        if (! in_array($state, self::STATES, true)) {
            return ['setup' => null, 'install_since' => null, 'install_typical' => null];
        }

        return [
            'setup' => $state,
            'install_since' => $state === 'installing' ? $server->updated_at?->getTimestamp() : null,
            'install_typical' => $state === 'installing' ? self::typical($server) : null,
        ];
    }

    /** The middle duration in seconds of recent first installations of this egg, or null with too few. */
    public static function typical(Server $server): ?int
    {
        if (! $server->egg_id) {
            return null;
        }

        return SafeCache::remember('simplify.install-typical.'.$server->egg_id, 600, function () use ($server): ?int {
            $times = Server::query()->where('egg_id', $server->egg_id)->whereNull('status')->whereNotNull('installed_at')
                ->latest('installed_at')->limit(15)->get(['created_at', 'installed_at'])
                ->map(fn (Server $other) => $other->installed_at->getTimestamp() - $other->created_at->getTimestamp())
                // Longer gaps are reinstalls months later, not an installation time.
                ->filter(fn (int $seconds) => $seconds >= 5 && $seconds <= 7200)->sort()->values();

            return $times->count() >= 2 ? (int) $times[intdiv($times->count(), 2)] : null;
        });
    }
}
