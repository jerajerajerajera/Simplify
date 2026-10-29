<?php

declare(strict_types=1);

namespace Simplify\Core\Http\Middleware;

use App\Filament\App\Resources\Servers\Pages\ListServers;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
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
        if ($request->isMethod('GET') && user() !== null && $request->routeIs(self::serverList()) && PanelSettings::get('start_page') === 'overview') {
            return redirect(Overview::getUrl(panel: 'app'));
        }

        return $next($request);
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
