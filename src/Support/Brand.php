<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

use Filament\Panel;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * The panel's own name, logo and accent colour from Simplify's settings, applied to every Filament panel.
 * Anything left empty keeps Pelican's own look.
 */
final class Brand
{
    /** Uploaded logos live on the local (private) disk and are served by the simplify.brand route. */
    public const DIRECTORY = 'simplify/branding';

    public const FILE_PATTERN = '[A-Za-z0-9_-]{1,80}\.(png|jpe?g|webp)';

    /** The fonts and styles for the panel name, as the settings offer them (simplify.css, .s-brand-*). */
    public const FONTS = 'default,rounded,serif,mono,pixel';

    public const STYLES = 'plain,accent,gradient,glow,shadow,caps';

    /** The logo heights to choose from, in pixels. */
    public const HEIGHTS = [24, 28, 32, 40, 48];

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
        } elseif ($settings['brand_font'] !== 'default' || $settings['brand_style'] !== 'plain') {
            // The name in its font and style; Filament puts an Htmlable logo in the top bar as it is.
            $panel->brandLogo(fn () => new HtmlString(sprintf(
                '<span class="%s"%s>%s</span>',
                e(self::classes($settings)),
                $settings['accent'] !== null ? ' style="--s-brand-tint: '.e($settings['accent']).'"' : '',
                e(filament()->getBrandName()),
            )))->brandLogoHeight('auto');
        }
    }

    /**
     * The classes that give the panel name its font and style.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function classes(array $settings): string
    {
        return 's-brand s-brand-font-'.$settings['brand_font'].' s-brand-style-'.$settings['brand_style'];
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
