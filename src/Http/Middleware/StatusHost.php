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
 * root is the list as a page (/simplify/embed?page=1 goes there), the list's own files load as usual, and every other path is not found there, so the
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
        // HEAD as GET, without the body (K-B100): link checkers and uptime monitors ask that way.
        if ($path === '' && in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            $request->query->set('page', '1');

            return app(EmbedController::class)->frame($request);
        }
        if ($path === 'feed.xml' && in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return app(EmbedController::class)->feed();
        }
        // The page under its long address goes to the clean one, with any other choice kept.
        if ((($path === 'simplify/embed' && $request->query('page') === '1') || $path === 'status') && in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            $query = http_build_query(array_diff_key($request->query(), ['page' => 1]));

            // On this host, not the panel's address that url('/') would give.
            return redirect()->to($request->getSchemeAndHttpHost().'/'.($query === '' ? '' : '?'.$query), 301);
        }
        if (str_starts_with($path, 'simplify/embed') || str_starts_with($path, 'simplify/assets/')) {
            return $next($request);
        }
        abort(404);
    }
}
