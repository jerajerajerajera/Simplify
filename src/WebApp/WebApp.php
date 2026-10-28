<?php

declare(strict_types=1);

namespace Simplify\Core\WebApp;

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
        if ($own !== null) {
            return [url('/simplify/brand/'.$own), 'image/'.(str_ends_with($own, '.png') ? 'png' : (str_ends_with($own, '.webp') ? 'webp' : 'jpeg'))];
        }

        return [Assets::url('app-icon-'.$size.'.png'), 'image/png'];
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
            'background_color' => self::LIGHT,
            'theme_color' => self::LIGHT,
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
            '__VERSION__' => substr(hash('xxh3', $source.self::icon(192)[0].$name), 0, 12),
            '__NAME__' => $name,
            '__ICON__' => $quote(self::icon(192)[0]),
            '__BADGE__' => $quote(Assets::url('app-badge-96.png')),
        ]);
    }
}
