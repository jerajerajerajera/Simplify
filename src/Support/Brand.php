<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

use Filament\Panel;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Storage;

/**
 * The panel's own name, logo and accent colour from Simplify's settings, applied to every Filament panel.
 * Anything left empty keeps Pelican's own look.
 */
final class Brand
{
    /** Uploaded logos live on the local (private) disk and are served by the simplify.brand route. */
    public const DIRECTORY = 'simplify/branding';

    public const FILE_PATTERN = '[A-Za-z0-9_-]{1,80}\.(png|jpe?g|webp)';

    public static function apply(Panel $panel): void
    {
        $settings = PanelSettings::all();
        if ($settings['accent'] !== null) {
            $panel->colors(['primary' => Color::hex($settings['accent'])]);
        }
        if ($settings['brand_name'] !== null && trim($settings['brand_name']) !== '') {
            $panel->brandName(trim($settings['brand_name']));
        }
        $logo = self::logoFile($settings['brand_logo']);
        if ($logo !== null) {
            $panel->brandLogo(fn () => url('/simplify/brand/'.$logo))->brandLogoHeight($settings['logo_height'].'px');
        }
    }

    /** The file name of the uploaded logo, if it is still there. */
    public static function logoFile(?string $path): ?string
    {
        if ($path === null || ! str_starts_with($path, self::DIRECTORY.'/') || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return basename($path);
    }

    public static function path(string $file): ?string
    {
        $path = self::DIRECTORY.'/'.$file;

        return preg_match('#^'.self::FILE_PATTERN.'$#', $file) === 1 && Storage::disk('local')->exists($path)
            ? Storage::disk('local')->path($path)
            : null;
    }
}
