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

    /** Font choices for the panel name; only these stacks ever reach a style attribute. Inter is the panel's own font. */
    public const FONTS = [
        'inter' => null,
        'system' => 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
        'rounded' => 'ui-rounded, "SF Pro Rounded", Nunito, "Varela Round", system-ui, sans-serif',
        'serif' => 'ui-serif, Georgia, Cambria, "Times New Roman", serif',
        'mono' => 'ui-monospace, "JetBrains Mono", "Cascadia Code", Menlo, Consolas, monospace',
        'monocraft' => 'Monocraft, ui-monospace, monospace',
        'condensed' => '"Roboto Condensed", "Arial Narrow", "Helvetica Neue", sans-serif',
        'display' => 'Impact, Haettenschweiler, "Arial Black", sans-serif',
    ];

    /**
     * The panel name's style attribute from the settings; the defaults add nothing, so the name keeps the header's
     * own look. Every value is checked by PanelSettings, so it is safe in an attribute.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function textStyle(array $settings): string
    {
        $style = [];
        if (($font = self::FONTS[$settings['brand_font']] ?? null) !== null) {
            $style[] = 'font-family: '.$font;
        }
        if ((int) $settings['brand_weight'] !== 700) {
            $style[] = 'font-weight: '.(int) $settings['brand_weight'];
        }
        if ((int) $settings['brand_spacing'] !== 0) {
            $style[] = 'letter-spacing: '.((int) $settings['brand_spacing'] / 100).'em';
        }
        if ($settings['brand_uppercase']) {
            $style[] = 'text-transform: uppercase';
        }
        $ink = ($settings['brand_colour1'] ?: null) ?? 'var(--ps-ink, currentColor)';
        if ($settings['brand_colour'] === 'gradient') {
            $end = ($settings['brand_colour2'] ?: null) ?? ($settings['accent'] ?: null) ?? 'var(--ps-brand, var(--s-accent, #2563eb))';
            $style[] = "background-image: linear-gradient(110deg, {$ink} 40%, {$end} 100%); -webkit-background-clip: text; background-clip: text; color: transparent; -webkit-text-fill-color: transparent";
        } elseif ($settings['brand_colour1']) {
            $style[] = 'color: '.$ink;
        }

        return implode('; ', $style);
    }

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
