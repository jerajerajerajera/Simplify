<?php

declare(strict_types=1);

namespace Simplify\Core\Http\Middleware;

use App\Filament\App\Resources\Servers\Pages\ListServers;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Support\PanelSettings;
use Throwable;

/**
 * The overview as start page: opening the panel, also right after signing in, shows the overview instead of Pelican's
 * server list, as in PelicanSimplify 0.7.0. A link to any other page still opens that page.
 */
final class StartPage
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->isMethod('GET') || user() === null) {
            return $next($request);
        }
        // An add-on's start page for this user (API 5, such as Simplify-Hosting's host mode) instead of the overview.
        if ($request->routeIs(self::serverList(), Overview::getRouteName(Filament::getPanel('app')))) {
            $start = self::startUrl(user());
            if ($start !== null && rtrim($start, '/') !== rtrim($request->url(), '/')) {
                return redirect($start);
            }
        }
        if ($request->routeIs(self::serverList()) && PanelSettings::get('start_page') === 'overview') {
            return redirect(Overview::getUrl(panel: 'app'));
        }

        return $next($request);
    }

    private static function startUrl(User $user): ?string
    {
        foreach (app(AddonRegistry::class)->all() as $addon) {
            try {
                $url = $addon->startUrl($user);
            } catch (Throwable) {
                continue;
            }
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    /** Pelican's server list sits at the panel's root; its route name differs between Pelican versions. */
    private static function serverList(): string
    {
        try {
            return ListServers::getRouteName(Filament::getPanel('app'));
        } catch (Throwable) {
            return 'filament.app.resources.servers.index';
        }
    }
}
