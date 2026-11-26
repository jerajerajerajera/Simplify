<?php

declare(strict_types=1);

namespace Simplify\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Simplify\Core\Embed\EmbedController;
use Simplify\Core\Embed\Services\StatusAddress;
use Throwable;

/**
 * On the status page's own address (Settings › Website › Status page) the panel only shows the website list: the
 * root is the list as a page, the list's own files load as usual, and every other path is not found there, so the
 * panel's sign-in never shows on that address.
 */
final class StatusHost
{
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $host = StatusAddress::host();
        } catch (Throwable) {
            $host = '';
        }
        // Never on the panel's own address, however the setting got there: the panel would only show the list.
        if ($host === '' || strtolower($request->getHost()) !== $host || $host === StatusAddress::panelHost()) {
            return $next($request);
        }
        $path = trim($request->path(), '/');
        if ($path === '' && $request->isMethod('GET')) {
            $request->query->set('page', '1');

            return app(EmbedController::class)->frame($request);
        }
        if (str_starts_with($path, 'simplify/embed') || str_starts_with($path, 'simplify/assets/')) {
            return $next($request);
        }
        abort(404);
    }
}
