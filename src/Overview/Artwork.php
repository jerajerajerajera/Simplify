<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

use App\Models\Server;
use Simplify\Core\Support\Assets;

/**
 * The picture behind a server's card: the banner its owner uploaded, else one of Simplify's own. A Minecraft server
 * always gets the same of the four Minecraft scenes, any other game the controller.
 */
final class Artwork
{
    public const SCENES = ['bg-valley.jpg', 'bg-harbor.jpg', 'bg-village.jpg', 'bg-survival.jpg', 'bg-town.jpg'];

    public const OTHER = 'bg-gaming.svg';

    /** Bedrock has no Java server.properties, status ping or EULA, so it counts as any other game. */
    public static function minecraft(Server $server): bool
    {
        $egg = (string) $server->egg?->name;

        return preg_match('/minecraft|vanilla|paper|purpur|spigot|modrinth|curseforge|ftb|tekkit|hexxit|blightfall|b-team|forge|fabric|quilt|pack/i', $egg) === 1
            && preg_match('/bedrock|pocketmine|nukkit/i', $egg) !== 1;
    }

    public static function background(Server $server, ?string $banner): string
    {
        if ($banner !== null) {
            return $banner;
        }
        if (! self::minecraft($server)) {
            return Assets::url(self::OTHER);
        }

        return Assets::url(self::SCENES[hexdec(substr(hash('sha256', $server->uuid), 0, 8)) % count(self::SCENES)]);
    }
}
