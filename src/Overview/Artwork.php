<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

use App\Models\Server;
use Simplify\Core\Subdomains\Services\Games;
use Simplify\Core\Support\Assets;

/**
 * The picture behind a server's card: the banner its owner uploaded, else one of Simplify's own. A Minecraft server
 * always gets the same one of the five Minecraft scenes, any other game the controller.
 */
final class Artwork
{
    public const SCENES = ['bg-valley.jpg', 'bg-harbor.jpg', 'bg-village.jpg', 'bg-survival.jpg', 'bg-town.jpg'];

    public const OTHER = 'bg-gaming.svg';

    public static function background(Server $server, ?string $banner): string
    {
        if ($banner !== null) {
            return $banner;
        }
        if (! Games::minecraft($server)) {
            return Assets::url(self::OTHER);
        }

        return Assets::url(self::SCENES[hexdec(substr(hash('sha256', $server->uuid), 0, 8)) % count(self::SCENES)]);
    }
}
