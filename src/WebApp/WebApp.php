<?php

declare(strict_types=1);

namespace Simplify\Core\WebApp;

use Illuminate\Support\Facades\Cache;
use Simplify\Core\Support\Assets;
use Simplify\Core\Support\Brand;
use Simplify\Core\Support\PanelSettings;

/**
 * The panel as an app on a phone or desktop: a manifest with the panel's name and icons, and a small service worker
 * that shows an offline page when the panel cannot be reached and shows app notifications. Nothing else is cached.
 */
final class WebApp
{
    /** Splash screen and status bar colours, close to Filament's own page background. */
    public const LIGHT = '#fafafa';

    public const DARK = '#09090b';

    public static function name(): string
    {
        $name = trim((string) PanelSettings::get('brand_name'));

        return $name !== '' ? $name : ((string) config('app.name') ?: 'Pelican');
    }

    /** The panel root path ("/" unless Pelican runs in a folder): the scope of the app and its worker. */
    public static function scope(): string
    {
        return rtrim((string) (parse_url(url('/'), PHP_URL_PATH) ?: ''), '/').'/';
    }

    /** @return array{0: string, 1: string} URL and type of the app icon: the admin's own, or Simplify's */
    public static function icon(int $size): array
    {
        $own = Brand::logoFile(PanelSettings::get('app_icon'));
        if ($own !== null && function_exists('imagecreatefromstring')) {
            // Chrome only offers "Install" with a square PNG, WebP or SVG of at least 144px: the admin's own icon is
            // served as a square PNG in exactly the size the manifest names, whatever was uploaded.
            return [route('simplify.app-icon', ['size' => $size, 'v' => substr(md5($own), 0, 8)]), 'image/png'];
        }
        if ($own !== null) {
            return [url('/simplify/brand/'.$own), 'image/'.(str_ends_with($own, '.png') ? 'png' : (str_ends_with($own, '.webp') ? 'webp' : 'jpeg'))];
        }

        return [Assets::url('app-icon-'.$size.'.png'), 'image/png'];
    }

    /** The admin's own app icon as a square PNG of $size pixels (cropped to its middle), or null without one. */
    public static function ownIconPng(int $size): ?string
    {
        $own = Brand::logoFile(PanelSettings::get('app_icon'));
        $path = $own !== null ? Brand::path($own) : null;
        if ($path === null || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $png = Cache::rememberForever('simplify.app-icon.'.$own.'.'.$size, function () use ($path, $size) {
            $source = @imagecreatefromstring((string) file_get_contents($path));
            if ($source === false) {
                return null;
            }
            $side = min(imagesx($source), imagesy($source));
            $icon = imagecreatetruecolor($size, $size);
            imagealphablending($icon, false);
            imagesavealpha($icon, true);
            imagefill($icon, 0, 0, imagecolorallocatealpha($icon, 0, 0, 0, 127));
            imagecopyresampled($icon, $source, 0, 0, intdiv(imagesx($source) - $side, 2), intdiv(imagesy($source) - $side, 2), $size, $size, $side, $side);
            ob_start();
            imagepng($icon);

            return base64_encode((string) ob_get_clean());
        });

        return is_string($png) ? (base64_decode($png, true) ?: null) : null;
    }

    /**
     * The small icon in Android's status bar. Android only uses its transparency and paints it white, so the admin's
     * own status bar icon comes first, then an app icon that is a PNG with a transparent background, then the pelican.
     */
    public static function badge(): string
    {
        $own = Brand::logoFile(PanelSettings::get('app_badge'));
        if ($own !== null) {
            return url('/simplify/brand/'.$own);
        }
        $icon = Brand::logoFile(PanelSettings::get('app_icon'));
        if ($icon !== null && str_ends_with($icon, '.png')
            && Cache::rememberForever('simplify.see-through.'.$icon, fn () => self::seeThrough((string) Brand::path($icon)))) {
            return url('/simplify/brand/'.$icon);
        }

        return Assets::url('app-badge-96.png');
    }

    /** Whether a PNG has transparent parts; an opaque image would show as a white square. */
    private static function seeThrough(string $path): bool
    {
        $image = function_exists('imagecreatefrompng') && is_file($path) ? @imagecreatefrompng($path) : false;
        if ($image === false) {
            return false;
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(1, intdiv(min($width, $height), 64));
        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 64) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public static function manifest(): array
    {
        $name = self::name();
        [$icon192, $type192] = self::icon(192);
        [$icon512, $type512] = self::icon(512);
        $own = $icon512 !== Assets::url('app-icon-512.png');

        return [
            'id' => self::scope(),
            'name' => $name,
            'short_name' => mb_strlen($name) > 12 ? mb_substr($name, 0, 12) : $name,
            'start_url' => url('/'),
            'scope' => self::scope(),
            'display' => 'standalone',
            // The splash screen has one colour for both themes: the dark one, like Pelican's own default.
            'background_color' => self::DARK,
            'theme_color' => self::DARK,
            'icons' => [
                ['src' => $icon192, 'sizes' => '192x192', 'type' => $type192, 'purpose' => 'any'],
                ['src' => $icon512, 'sizes' => '512x512', 'type' => $type512, 'purpose' => 'any'],
                ['src' => $own ? $icon512 : Assets::url('maskable-512.png'), 'sizes' => '512x512', 'type' => $own ? $type512 : 'image/png', 'purpose' => 'maskable'],
            ],
        ];
    }

    /** The service worker with the panel's name, icons and a version filled in. */
    public static function worker(): string
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/worker.js');
        // The name lands in HTML inside a JavaScript template string: escaped for both.
        $name = strtr(htmlspecialchars(self::name(), ENT_QUOTES), ['`' => '&#96;', '$' => '&#36;', '\\' => '&#92;']);
        $quote = fn (string $url) => str_replace(['\\', "'"], '', $url);

        return strtr($source, [
            '__VERSION__' => substr(hash('xxh3', $source.self::icon(192)[0].self::badge().$name), 0, 12),
            '__NAME__' => $name,
            '__ICON__' => $quote(self::icon(192)[0]),
            '__BADGE__' => $quote(self::badge()),
        ]);
    }
}
