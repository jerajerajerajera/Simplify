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

        return $found === [] ? null : self::clean($found[0]);
    }

    /**
     * What an add-on gave, as Simplify uses it: only a real Livewire component of the add-on; a broken answer still
     * locks, without buttons.
     *
     * @return array{notice: string, actions: class-string<Component>|null}
     */
    private static function clean(mixed $lock): array
    {
        $notice = is_array($lock) ? ($lock['notice'] ?? null) : null;
        $actions = is_array($lock) ? ($lock['actions'] ?? null) : null;

        return [
            'notice' => is_string($notice) ? $notice : '',
            'actions' => is_string($actions) && is_subclass_of($actions, Component::class) ? $actions : null,
        ];
    }

    public static function locked(Server $server): bool
    {
        return self::for($server) !== null;
    }
}
