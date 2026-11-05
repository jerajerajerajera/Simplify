<?php

declare(strict_types=1);

namespace Simplify\Core\Http\Middleware;

use App\Models\Server;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Simplify\Core\Server\LockedServer;

/**
 * A locked server (Addon::lockedServer) has only its console: every other page of it, also one typed into the address
 * bar, goes back to the console. Runs after Filament knew the server (tenant middleware), on page loads.
 */
final class LockedServerPages
{
    public const CONSOLE = 'filament.server.pages.console';

    public function handle(Request $request, Closure $next): mixed
    {
        $server = Filament::getTenant();
        if ($server instanceof Server && ! $request->routeIs(self::CONSOLE) && LockedServer::locked($server)) {
            return redirect()->route(self::CONSOLE, ['tenant' => $server]);
        }

        return $next($request);
    }
}
