<?php

declare(strict_types=1);

namespace Simplify\Core\Account;

use App\Models\User;
use Livewire\Component;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;

/**
 * The rows of the Connections page: what the add-ons give for this user (Addon::connections), only Livewire components.
 * The page and its item in the account menu exist only while there is at least one row.
 */
final class Connections
{
    /** @return list<class-string<Component>> */
    public static function rows(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        // Inside collect(), so an add-on that throws loses its own rows only.
        $rows = app(AddonRegistry::class)->collect(fn (Addon $addon) => method_exists($addon, 'connections')
            ? array_values(array_filter($addon->connections($user), fn ($row) => is_string($row) && is_subclass_of($row, Component::class)))
            : []);

        return array_values(array_unique($rows));
    }
}
