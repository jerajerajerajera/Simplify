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
        return match (self::key($check)) {
            'nodeversions' => ($url = self::nodes()) ? ['label' => __('simplify::dashboard.health.page.open_nodes'), 'url' => $url, 'external' => false] : null,
            'panelversion' => ['label' => __('simplify::dashboard.health.page.release_notes'), 'url' => self::RELEASES, 'external' => true],
            default => null,
        };
    }

    /**
     * The check's name as one plain word, for example "Node Versions" → "nodeversions". It is the last part of the
     * check's error id, simplify:health:<key> (ADDONS.md, Errors).
     */
    public static function key(string $check): string
    {
        return substr((string) preg_replace('/[^a-z0-9_.-]+/', '', mb_strtolower($check)), 0, 64);
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
