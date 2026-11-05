<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

/**
 * The texts Simplify's scripts use (lang/<language>/js.php). Words the page itself also shows come from one file:
 * the health of a host and of each of its parts (Healthy, High load, Critical load) from dashboard.php, so a
 * translation is changed in one place and the live bar never says something else than the page.
 */
final class JsText
{
    /** js.php key under "dashboard" => key under dashboard.health.labels */
    public const FROM_HEALTH = ['healthy' => 'healthy', 'high_load' => 'high', 'critical_load' => 'critical'];

    /**
     * @param  array<string, mixed>  $js  The texts of js.php.
     * @param  array<string, mixed>  $labels  dashboard.health.labels.
     * @return array<string, mixed>
     */
    public static function merge(array $js, array $labels): array
    {
        $dashboard = is_array($js['dashboard'] ?? null) ? $js['dashboard'] : [];
        foreach (self::FROM_HEALTH as $key => $label) {
            if (is_string($labels[$label] ?? null)) {
                $dashboard[$key] = $labels[$label];
            }
        }
        $js['dashboard'] = $dashboard;

        return $js;
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $js = __('simplify::js');
        $labels = __('simplify::dashboard.health.labels');

        return self::merge(is_array($js) ? $js : [], is_array($labels) ? $labels : []);
    }
}
