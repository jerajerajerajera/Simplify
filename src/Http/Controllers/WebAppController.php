<?php

declare(strict_types=1);

namespace Simplify\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Simplify\Core\Support\Assets;
use Simplify\Core\WebApp\WebApp;

/** The web app manifest and service worker. Open without login: browsers fetch them without cookies. */
final class WebAppController
{
    public function manifest(): JsonResponse
    {
        return response()->json(WebApp::manifest(), 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** The admin's own app icon as a square PNG; Simplify's own icon when there is none or it cannot be read. */
    public function icon(int $size): Response|RedirectResponse
    {
        $png = WebApp::ownIconPng($size);
        if ($png === null) {
            return redirect(Assets::url('app-icon-'.$size.'.png'));
        }

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function worker(): Response
    {
        return response(WebApp::worker(), 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            // Browsers check the worker for updates themselves; it must never be served stale.
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => WebApp::scope(),
        ]);
    }
}
