<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

/**
 * The files Simplify serves to the browser. Each URL carries a hash of the file, so browsers can keep it for a year
 * and still get a new version right after an update.
 */
final class Assets
{
    public const FILES = [
        'simplify.css' => ['css/simplify.css', 'text/css; charset=utf-8'],
        'logo.svg' => ['img/logo.svg', 'image/svg+xml'],
        'favicon.svg' => ['img/favicon.svg', 'image/svg+xml'],
        'app-icon-180.png' => ['img/app-icon-180.png', 'image/png'],
        'app-icon-192.png' => ['img/app-icon-192.png', 'image/png'],
        'app-icon-512.png' => ['img/app-icon-512.png', 'image/png'],
        'maskable-512.png' => ['img/maskable-512.png', 'image/png'],
        'app-badge-96.png' => ['img/app-badge-96.png', 'image/png'],
        'tokens.css' => ['css/tokens.css', 'text/css; charset=utf-8'],
        'components.css' => ['css/components.css', 'text/css; charset=utf-8'],
        'dashboard.css' => ['css/dashboard.css', 'text/css; charset=utf-8'],
        'core.js' => ['js/core.js', 'text/javascript; charset=utf-8'],
        'motd.js' => ['js/motd.js', 'text/javascript; charset=utf-8'],
        'dashboard.js' => ['js/dashboard.js', 'text/javascript; charset=utf-8'],
        'server.js' => ['js/server.js', 'text/javascript; charset=utf-8'],
        'bg-valley.jpg' => ['img/bg-valley.jpg', 'image/jpeg'],
        'bg-harbor.jpg' => ['img/bg-harbor.jpg', 'image/jpeg'],
        'bg-village.jpg' => ['img/bg-village.jpg', 'image/jpeg'],
        'bg-survival.jpg' => ['img/bg-survival.jpg', 'image/jpeg'],
        'bg-town.jpg' => ['img/bg-town.jpg', 'image/jpeg'],
        'bg-gaming.svg' => ['img/bg-gaming.svg', 'image/svg+xml'],
    ];

    /** @var array<string, string> */
    private static array $hashes = [];

    public static function path(string $name): ?string
    {
        if (! isset(self::FILES[$name])) {
            return null;
        }

        return dirname(__DIR__, 2).'/resources/'.self::FILES[$name][0];
    }

    public static function type(string $name): string
    {
        return self::FILES[$name][1] ?? 'application/octet-stream';
    }

    public static function url(string $name): string
    {
        $hash = self::$hashes[$name] ??= substr((string) hash_file('xxh3', (string) self::path($name)), 0, 12);

        return route('simplify.asset', ['name' => $name]).'?v='.$hash;
    }
}
