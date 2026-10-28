<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;

/** The servers a user sees in Simplify: the ones Pelican lets them open, minus servers that add-ons hide. */
final class ServerQuery
{
    /** @return Builder<Server> */
    public static function for(User $user): Builder
    {
        $query = $user->accessibleServers()->with(['allocation', 'egg:id,name', 'node', 'user:id,username']);
        $prefixes = app(AddonRegistry::class)->collect(fn (Addon $addon) => $addon->hiddenServerPrefixes());
        foreach (array_unique($prefixes) as $prefix) {
            $query->where(fn (Builder $q) => $q
                ->whereNull('servers.external_id')
                ->orWhere('servers.external_id', 'not like', addcslashes($prefix, '%_\\').'%'));
        }

        return $query->orderBy('servers.name');
    }
}
