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

    /**
     * Font choices for the panel name; only these stacks ever reach a style attribute. Inter is the panel's own font.
     * Rounded, serif and mono ship with Simplify (resources/fonts, simplify.css) so they look the same everywhere.
     */
    public const FONTS = [
        'inter' => null,
        'system' => 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
        'rounded' => '"Simplify Rounded", ui-rounded, "SF Pro Rounded", Nunito, system-ui, sans-serif',
        'serif' => '"Simplify Serif", ui-serif, Georgia, Cambria, "Times New Roman", serif',
        'mono' => '"Simplify Mono", ui-monospace, "JetBrains Mono", "Cascadia Code", Menlo, Consolas, monospace',
        'monocraft' => 'Monocraft, ui-monospace, monospace',
        'condensed' => '"Roboto Condensed", "Arial Narrow", "Helvetica Neue", sans-serif',
        'display' => 'Impact, Haettenschweiler, "Arial Black", sans-serif',
    ];

    /** The logo heights the size slider stops at, in pixels. */
    public const HEIGHTS = [24, 28, 32, 40, 48];

    /** The letter spacings to choose from, in hundredths of an em. */
    public const SPACINGS = [-5, 0, 8, 20];

    /** Ready-made styles for the panel name: one click sets these settings, and they can be fine-tuned after. */
    public const PRESETS = [
        'clean' => ['brand_font' => 'inter', 'brand_weight' => 700, 'brand_colour' => 'solid', 'brand_effect' => 'plain', 'brand_spacing' => 0, 'brand_uppercase' => false],
        'soft' => ['brand_font' => 'rounded', 'brand_weight' => 800, 'brand_colour' => 'accent', 'brand_effect' => 'plain', 'brand_spacing' => 0, 'brand_uppercase' => false],
        'classic' => ['brand_font' => 'serif', 'brand_weight' => 700, 'brand_colour' => 'solid', 'brand_effect' => 'shadow', 'brand_spacing' => 0, 'brand_uppercase' => false],
        'code' => ['brand_font' => 'mono', 'brand_weight' => 700, 'brand_colour' => 'accent', 'brand_effect' => 'plain', 'brand_spacing' => 0, 'brand_uppercase' => false],
        'pixel' => ['brand_font' => 'monocraft', 'brand_weight' => 400, 'brand_colour' => 'accent', 'brand_effect' => 'glow', 'brand_spacing' => 0, 'brand_uppercase' => false],
        'neon' => ['brand_font' => 'display', 'brand_weight' => 400, 'brand_colour' => 'gradient', 'brand_effect' => 'glow', 'brand_spacing' => 8, 'brand_uppercase' => true],
    ];

    /**
     * The panel name's style attribute from the settings; the defaults add nothing, so the name keeps the header's
     * own look. Every value is checked by PanelSettings, so it is safe in an attribute. The setup's preview builds
     * the same style in the browser (look-preview.blade.php).
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
        $accent = ($settings['accent'] ?: null) ?? 'var(--ps-brand, var(--s-accent, var(--primary-500, #2563eb)))';
        $ink = ($settings['brand_colour1'] ?: null) ?? 'var(--ps-ink, currentColor)';
        $gradient = $settings['brand_colour'] === 'gradient';
        if ($gradient) {
            $end = ($settings['brand_colour2'] ?: null) ?? $accent;
            $style[] = "background-image: linear-gradient(110deg, {$ink} 40%, {$end} 100%); -webkit-background-clip: text; background-clip: text; color: transparent; -webkit-text-fill-color: transparent";
        } elseif ($settings['brand_colour'] === 'accent') {
            $style[] = 'color: '.$accent;
        } elseif ($settings['brand_colour1']) {
            $style[] = 'color: '.$ink;
        }
        // A text shadow does not show on gradient text, so that gets a drop shadow on the whole name instead.
        if ($settings['brand_effect'] === 'glow') {
            $style[] = $gradient
                ? "filter: drop-shadow(0 0 6px {$accent})"
                : "text-shadow: 0 0 10px color-mix(in srgb, {$accent} 80%, transparent), 0 0 2px {$accent}";
        } elseif ($settings['brand_effect'] === 'shadow') {
            $style[] = $gradient ? 'filter: drop-shadow(0 2px 2px rgb(0 0 0 / .45))' : 'text-shadow: 0 2px 0 rgb(0 0 0 / .35), 0 3px 8px rgb(0 0 0 / .35)';
        }

        return implode('; ', $style);
    }

    /**
     * What the top bar shows: the logo, the name or both. Without an uploaded logo it is always the name.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function show(array $settings): string
    {
        return self::logoFile($settings['brand_logo']) === null ? 'text' : $settings['brand_show'];
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
        $show = self::show($settings);
        $style = self::textStyle($settings);
        if ($show === 'logo') {
            $panel->brandLogo(fn () => url('/simplify/brand/'.$logo))->brandLogoHeight($settings['logo_height'].'px');
        } elseif ($show === 'both' || $style !== '') {
            // The name in its own style, after the logo when both show; Filament puts an Htmlable logo in the top bar as it is.
            $panel->brandLogo(fn () => new HtmlString(
                ($show === 'both' ? sprintf('<img src="%s" alt="" style="height: %dpx">', e(url('/simplify/brand/'.$logo)), (int) $settings['logo_height']) : '')
                .sprintf('<span class="s-brand" style="%s">%s</span>', e($style), e(filament()->getBrandName())),
            ))->brandLogoHeight('auto');
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
