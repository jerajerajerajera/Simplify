<?php

declare(strict_types=1);

namespace Simplify\Core\Http\Controllers;

use Simplify\Core\Support\Brand;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Serves the panel logo an admin uploaded. Open without login, like the logo on Pelican's own login page. */
final class BrandController
{
    public function __invoke(string $file): BinaryFileResponse
    {
        $path = Brand::path($file);
        abort_if($path === null, 404);

        // Every upload gets a new random name, so a name never changes content.
        return response()->file($path, ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }
}
