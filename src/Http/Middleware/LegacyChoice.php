<?php

declare(strict_types=1);

namespace Simplify\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Simplify\Core\Support\Legacy;

/**
 * ?simplify_legacy=1 opens the legacy panel for this session, ?simplify_legacy=0 goes back to Simplify (Legacy). Only a
 * choice of how pages look for the signed-in user; it changes no data, so a plain link may set it.
 */
final class LegacyChoice
{
    public function handle(Request $request, Closure $next): mixed
    {
        $choice = $request->query(Legacy::KEY);
        if ($request->isMethod('GET') && user() !== null && in_array($choice, ['0', '1'], true)) {
            $choice === '1' ? session([Legacy::KEY => true]) : session()->forget(Legacy::KEY);

            return redirect($request->fullUrlWithoutQuery([Legacy::KEY]));
        }

        return $next($request);
    }
}
