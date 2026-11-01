<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

use App\Models\Server;

/**
 * What kind of server an egg runs, for its icon when nothing else has one (after the Minecraft block, before the
 * cube): from the egg's name, its tags and the server's Docker image. Null when nothing matches.
 */
final class EggKind
{
    /** Checked in this order: the first that matches wins. */
    private const KINDS = [
        'discord' => '/discord|red-?bot/i',
        'python' => '/python/i',
        'nodejs' => '/\bnode(js|\.js)?\b|yolks:nodejs/i',
        'golang' => '/golang|\bgo\b|yolks:go_/i',
        'database' => '/mariadb|mysql|postgres|redis|mongo/i',
        'steam' => '/steamcmd|\bsteam\b/i',
        'java' => '/\bjava\b|yolks:java_/i',
        'game' => '/\bgames?\b|\/games:|wine|proton/i',
    ];

    public static function of(Server $server): ?string
    {
        $tags = $server->egg?->tags ?? [];

        return self::match(implode(' ', [(string) $server->egg?->name, implode(' ', is_array($tags) ? $tags : []), (string) $server->image]));
    }

    public static function match(string $text): ?string
    {
        foreach (self::KINDS as $kind => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $kind;
            }
        }

        return null;
    }
}
