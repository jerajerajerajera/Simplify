<?php

declare(strict_types=1);

namespace Simplify\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
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
