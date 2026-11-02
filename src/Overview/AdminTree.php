<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

use Simplify\Core\Filament\Admin\Pages\Settings;

/**
 * The admin pages as the groups of Admin tools and host mode: Pelican's pages, Simplify's settings tabs and the add-ons'
 * pages, each once, in a fixed order. Add-ons with a host mode of their own (Simplify-Hosting) read it here too, so the
 * grouping lives in one place.
 */
final class AdminTree
{
    /** Group key, label key, icon, colour and Pelican's pages in it (AdminMenu keys). */
    private const PLAN = [
        ['servers', 'servers', 'host', '#5fb87a', ['serverresource', 'eggresource', 'mountresource']],
        ['nodes', 'nodes', 'monitor', '#5b9cf0', ['noderesource', 'databasehostresource', 'backuphostresource']],
        ['people', 'people', 'players', '#a78bfa', ['userresource', 'roleresource', 'people']],
        ['integrations', 'integrations', 'link', '#f0a35b', ['apikeyresource', 'webhookresource']],
        ['panel', 'panel', 'gear', '#94a3b8', ['settings', 'pluginresource', 'listlogs']],
        ['extensions', null, 'plugins', '#2dd4bf', []],
        ['health', 'health', 'heart', '#f87171', ['health']],
    ];

    /**
     * @return list<array{key: string, label: string, icon: string, color: string, pages: list<array<string, mixed>>}>
     */
    public static function build(): array
    {
        $items = array_merge(...array_column(AdminMenu::items(), 'items')) ?: [];
        $menu = Settings::canAccess() ? Settings::menu() : [];

        return self::group($items, $menu);
    }

    /**
     * @param  list<array<string, mixed>>  $items  AdminMenu items
     * @param  list<array<string, mixed>>  $menu  Settings::menu() tabs, each with a group (panel, addons or pelican)
     * @return list<array{key: string, label: string, icon: string, color: string, pages: list<array<string, mixed>>}>
     */
    public static function group(array $items, array $menu): array
    {
        $label = fn (?string $key) => $key === null ? __('simplify::js.dashboard.other_plugins') : __('simplify::js.dashboard.admin_groups.'.$key);
        $plan = array_map(fn (array $p) => ['key' => $p[0], 'label' => $label($p[1]), 'icon' => $p[2], 'color' => $p[3], 'keys' => $p[4], 'tabs' => []], self::PLAN);
        // The settings are leaves of the tree: Simplify's tabs, the add-ons' pages and Pelican's tabs, each in its group.
        if ($menu !== []) {
            $tabs = fn (string $group) => array_values(array_map(fn (array $tab) => [...$tab, 'settings' => true], array_filter($menu, fn (array $tab) => ($tab['group'] ?? null) === $group)));
            $at = array_search('panel', array_column($plan, 'key'), true);
            array_splice($plan, (int) $at, 1, [
                ['key' => 'panel', 'label' => $label('settings'), 'icon' => 'gear', 'color' => '#94a3b8', 'keys' => [], 'tabs' => $tabs('panel')],
                ['key' => 'addons', 'label' => $label('addons'), 'icon' => 'plugins', 'color' => '#b48cf2', 'keys' => ['pluginresource'], 'tabs' => $tabs('addons')],
                ['key' => 'pelican', 'label' => $label('pelican_settings'), 'icon' => 'settings', 'color' => '#5b9cf0', 'keys' => ['listlogs'], 'tabs' => $tabs('pelican')],
            ]);
        }
        $known = [...array_merge(...array_column($plan, 'keys')), 'settings'];
        // An add-on's settings page that is also in Pelican's menu (Hosting) shows once, under Add-ons; an add-on page
        // with the same name as the add-on's settings (Server Maker's own page) shows once.
        $tabUrls = array_column($menu, 'url');
        $tabLabels = array_column($menu, 'label');
        $loose = fn (array $item) => ! in_array($item['key'], $known, true) && ! in_array($item['url'], $tabUrls, true);
        // Simplify's own pages join its settings, the add-ons' other pages join Add-ons: Other plugins is only for plugins
        // that are not Simplify.
        $own = fn (string $origin) => array_values(array_filter($items, fn (array $item) => $item['origin'] === $origin && $loose($item) && ! in_array($item['label'], $tabLabels, true)));
        $extra = ['panel' => $menu === [] ? [] : $own('core'), 'addons' => $own('addon')];
        $byKey = array_column($items, null, 'key');

        $tree = [];
        foreach ($plan as $section) {
            $pages = $section['key'] === 'extensions'
                ? array_values(array_filter($items, fn (array $item) => $loose($item) && ! ($menu !== [] && $item['origin'] !== '')))
                : [...$section['tabs'], ...$extra[$section['key']] ?? [], ...array_values(array_filter(array_map(fn (string $key) => $byKey[$key] ?? null, $section['keys'])))];
            if ($pages !== []) {
                $tree[] = ['key' => $section['key'], 'label' => (string) $section['label'], 'icon' => $section['icon'], 'color' => $section['color'], 'pages' => $pages];
            }
        }

        return $tree;
    }
}
