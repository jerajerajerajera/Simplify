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
