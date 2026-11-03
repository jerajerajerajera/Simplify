<?php

declare(strict_types=1);

namespace Simplify\Core\Http\Controllers;

use Simplify\Core\Embed\Services\EmbedDetails;
use Simplify\Core\Server\Looks;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Serves server banners, and the images uploaded on a server's Website page, to signed-in people. */
final class BannerController
{
    public function __invoke(string $file): BinaryFileResponse
    {
        $path = Looks::path($file) ?? EmbedDetails::path($file);
        abort_if($path === null, 404);

        // Every upload gets a new random name, so a name never changes content.
        return response()->file($path, ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }
}
