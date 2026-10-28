<?php

declare(strict_types=1);

namespace Simplify\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Host mode and the settings page show panel pages in a frame. Pelican forbids every frame (SetSecurityHeaders keeps a
 * header that is already set), so signed-in pages allow frames from the panel itself; other sites stay blocked.
 */
final class FramePolicy
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);
        if ($request->isMethod('GET') && user() !== null) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }
}
