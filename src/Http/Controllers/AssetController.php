<?php

declare(strict_types=1);

namespace Simplify\Core\Http\Controllers;

use Illuminate\Http\Request;
use Simplify\Core\Support\Assets;
use Symfony\Component\HttpFoundation\Response;

/** Serves Simplify's stylesheets, scripts and images. Open without login: they hold nothing private. */
final class AssetController
{
    public function __invoke(Request $request, string $name): Response
    {
        $path = Assets::path($name);
        abort_if($path === null || ! is_file($path), 404);
        $headers = [
            'Content-Type' => Assets::type($name),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Vary' => 'Accept-Encoding',
        ];
        $gzip = str_contains((string) $request->header('Accept-Encoding'), 'gzip') ? Assets::gzip($name) : null;
        if ($gzip !== null) {
            return response($gzip, 200, [...$headers, 'Content-Encoding' => 'gzip']);
        }

        return response()->file($path, $headers);
    }
}
