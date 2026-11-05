<?php

declare(strict_types=1);

namespace Simplify\Core\Server;

use App\Models\Server;
use Livewire\Component;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;

/**
 * A server an add-on keeps to itself (Addon::lockedServer), for example Egg Updates' test server: its page is locked
 * for everyone. The first add-on that locks it gives the line under the buttons and its own buttons.
 */
final class LockedServer
{
    /** @return array{notice: string, actions: class-string<Component>|null}|null */
    public static function for(Server $server): ?array
    {
        $found = app(AddonRegistry::class)->collect(function (Addon $addon) use ($server) {
            $lock = method_exists($addon, 'lockedServer') ? $addon->lockedServer($server) : null;

            return is_array($lock) ? [$lock] : [];
        });
        $lock = null;
        foreach ($found as $candidate) {
            $notice = $candidate['notice'] ?? null;
            $actions = $candidate['actions'] ?? null;
            // Only a real Livewire component of the add-on; a broken answer still locks, without buttons.
            $lock = [
                'notice' => is_string($notice) ? $notice : '',
                'actions' => is_string($actions) && is_subclass_of($actions, Component::class) ? $actions : null,
            ];
            break;
        }

        return $lock;
    }

    public static function locked(Server $server): bool
    {
        return self::for($server) !== null;
    }
}
