<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

use Throwable;

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
        'game-minecraft.svg' => ['img/game-minecraft.svg', 'image/svg+xml'],
        'app-icon-180.png' => ['img/app-icon-180.png', 'image/png'],
        'app-icon-192.png' => ['img/app-icon-192.png', 'image/png'],
        'app-icon-512.png' => ['img/app-icon-512.png', 'image/png'],
        'maskable-512.png' => ['img/maskable-512.png', 'image/png'],
        'app-badge-96.png' => ['img/app-badge-96.png', 'image/png'],
        'tokens.css' => ['css/tokens.css', 'text/css; charset=utf-8'],
        'components.css' => ['css/components.css', 'text/css; charset=utf-8'],
        'dashboard.css' => ['css/dashboard.css', 'text/css; charset=utf-8'],
        'seasons.css' => ['css/seasons.css', 'text/css; charset=utf-8'],
        'core.js' => ['js/core.js', 'text/javascript; charset=utf-8'],
        'motd.js' => ['js/motd.js', 'text/javascript; charset=utf-8'],
        'seasons.js' => ['js/seasons.js', 'text/javascript; charset=utf-8'],
        'dashboard.js' => ['js/dashboard.js', 'text/javascript; charset=utf-8'],
        'server.js' => ['js/server.js', 'text/javascript; charset=utf-8'],
        'frame.js' => ['js/frame.js', 'text/javascript; charset=utf-8'],
        'pull.js' => ['js/pull.js', 'text/javascript; charset=utf-8'],
        'sheet.js' => ['js/sheet.js', 'text/javascript; charset=utf-8'],
        'unsaved.js' => ['js/unsaved.js', 'text/javascript; charset=utf-8'],
        'sort.js' => ['js/sort.js', 'text/javascript; charset=utf-8'],
        'row-actions.js' => ['js/row-actions.js', 'text/javascript; charset=utf-8'],
        'bg-valley.jpg' => ['img/bg-valley.jpg', 'image/jpeg'],
        'bg-harbor.jpg' => ['img/bg-harbor.jpg', 'image/jpeg'],
        'bg-village.jpg' => ['img/bg-village.jpg', 'image/jpeg'],
        'bg-survival.jpg' => ['img/bg-survival.jpg', 'image/jpeg'],
        'bg-town.jpg' => ['img/bg-town.jpg', 'image/jpeg'],
        'bg-gaming.svg' => ['img/bg-gaming.svg', 'image/svg+xml'],
        'brand-rounded.woff2' => ['fonts/brand-rounded.woff2', 'font/woff2'],
        'brand-serif.woff2' => ['fonts/brand-serif.woff2', 'font/woff2'],
        'brand-mono.woff2' => ['fonts/brand-mono.woff2', 'font/woff2'],
        // Monocraft, a choice for the panel name (SIL Open Font License, resources/fonts/Monocraft-OFL.txt).
        'monocraft-regular.woff2' => ['fonts/monocraft-regular.woff2', 'font/woff2'],
        'monocraft-bold.woff2' => ['fonts/monocraft-bold.woff2', 'font/woff2'],
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

    /** The plugin's version, for the footer and the settings page. */
    public static function version(): string
    {
        try {
            return (string) (json_decode((string) file_get_contents(dirname(__DIR__, 2).'/plugin.json'), true)['version'] ?? '');
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * A text file (styles, scripts, SVG) gzipped, for a browser that accepts that: Pelican's web server compresses only
     * HTML, so a first visit fetched about 400 KB of styles and scripts uncompressed. A few milliseconds, and only
     * when a browser does not have the file yet (url() lets it keep each version for a year). Null for images and
     * fonts, which are compressed already.
     */
    public static function gzip(string $name): ?string
    {
        $path = self::path($name);
        $type = self::type($name);
        if ($path === null || ! is_file($path) || ! (str_starts_with($type, 'text/') || str_starts_with($type, 'image/svg'))) {
            return null;
        }

        return gzencode((string) file_get_contents($path), 6) ?: null;
    }

    public static function url(string $name): string
    {
        $hash = self::$hashes[$name] ??= substr((string) hash_file('xxh3', (string) self::path($name)), 0, 12);

        return route('simplify.asset', ['name' => $name]).'?v='.$hash;
    }
}
