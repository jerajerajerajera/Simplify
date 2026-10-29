<?php

declare(strict_types=1);

namespace Simplify\Core\Subdomains\Services;

use App\Models\Server;

/** Which servers are Minecraft Java: they get artwork, a status ping and an SRV record. */
final class Games
{
    /**
     * Minecraft Java servers, from the egg's name and features. Bedrock has no Java server.properties, status ping
     * or SRV lookup, so it counts as any other game: players add the port.
     */
    public static function minecraft(Server $server): bool
    {
        return self::minecraftJava((string) $server->egg?->name, (array) ($server->egg?->features ?? []));
    }

    /** @param  list<string>  $features */
    public static function minecraftJava(string $eggName, array $features = []): bool
    {
        if (preg_match('/bedrock|pocketmine|nukkit/i', $eggName) === 1) {
            return false;
        }

        return in_array('eula', $features, true)
            || preg_match('/minecraft|vanilla|paper|purpur|spigot|bukkit|folia|velocity|bungee|waterfall|modrinth|curseforge|ftb|tekkit|hexxit|blightfall|b-team|forge|fabric|quilt|technic|pack/i', $eggName) === 1;
    }
}
