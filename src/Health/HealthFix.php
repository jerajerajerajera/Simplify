<?php

declare(strict_types=1);

namespace Simplify\Core\Health;

use Filament\Facades\Filament;
use Throwable;

/**
 * The button next to a health check that needs attention: where an admin fixes it. Only checks with one clear place
 * get a button.
 */
final class HealthFix
{
    private const RELEASES = 'https://github.com/pelican-dev/panel/releases';

    /** @return array{label: string, url: string, external: bool}|null */
    public static function for(string $check): ?array
    {
        return match (preg_replace('/\s+/', '', mb_strtolower($check))) {
            'nodeversions' => ($url = self::nodes()) ? ['label' => __('simplify::dashboard.health.page.open_nodes'), 'url' => $url, 'external' => false] : null,
            'panelversion' => ['label' => __('simplify::dashboard.health.page.release_notes'), 'url' => self::RELEASES, 'external' => true],
            default => null,
        };
    }

    /** Pelican's node list, found by name so a moved class in a later Pelican does not break the page. */
    private static function nodes(): ?string
    {
        try {
            foreach (Filament::getPanel('admin')->getResources() as $class) {
                if (class_basename($class) === 'NodeResource' && $class::canViewAny()) {
                    return $class::getUrl('index', panel: 'admin');
                }
            }
        } catch (Throwable) {
        }

        return null;
    }
}
