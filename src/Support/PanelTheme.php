<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Throwable;

/**
 * A theme plugin (Nord, Catppuccin, Theme Customizer, ...) changes the panel's Filament colours, font or stylesheet.
 * Simplify then takes its palette from Filament's colour variables (tokens.css, .ps-panel-theme) to match the panel.
 */
final class PanelTheme
{
    /** Pelican's own colours (FilamentServiceProvider) and font; anything else comes from a theme. */
    private const PELICAN = ['gray' => Color::Zinc, 'primary' => Color::Blue, 'success' => Color::Green, 'warning' => Color::Amber, 'danger' => Color::Red, 'info' => Color::Sky];

    public static function active(): bool
    {
        try {
            $panel = filament()->getCurrentPanel();
            if ($panel === null) {
                return false;
            }
            $colors = FilamentColor::getColors();
            foreach (self::PELICAN as $name => $palette) {
                if (isset($colors[$name][500]) && $colors[$name][500] !== $palette[500]) {
                    return true;
                }
            }

            return collect((array) $panel->getViteTheme())->contains(fn ($path) => str_starts_with((string) $path, 'plugins/'))
                || ! in_array($panel->getFontFamily(), ['Inter', 'Inter Variable'], true);
        } catch (Throwable) {
            return false;
        }
    }
}
