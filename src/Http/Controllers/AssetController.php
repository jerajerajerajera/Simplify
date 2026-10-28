<?php

declare(strict_types=1);

namespace Simplify\Core\Http\Controllers;

use Simplify\Core\Support\Assets;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Serves Simplify's stylesheet and logo. Open without login: they hold nothing private. */
final class AssetController
{
    public function __invoke(string $name): BinaryFileResponse
    {
        $path = Assets::path($name);
        abort_if($path === null || ! is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => Assets::type($name),
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
