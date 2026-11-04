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
        ['people', 'people', 'players', '#a78bfa', ['userresource', 'roleresource']],
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
        // Once per request: the header (AdminTop) and the menu beside the page (AdminSide) both draw it.
        return once(function () {
            $items = array_merge(...array_column(AdminMenu::items(), 'items')) ?: [];

            return self::group($items, Settings::canAccess() ? Settings::menu() : []);
        });
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
            // Simplify settings (with the add-on list last) and Pelican settings (with its plugin list) above, then apart
            // under the menu what does not come with Pelican: Simplify add-ons and Pelican plugins (Jeroen 3-11).
            $list = array_values(array_filter($tabs('addons'), fn (array $tab) => $tab['key'] === 'settings:addons'));
            array_splice($plan, (int) $at, 1, [
                ['key' => 'panel', 'label' => $label('settings'), 'icon' => 'gear', 'color' => '#94a3b8', 'keys' => [], 'tabs' => $tabs('panel'), 'last' => $list],
                ['key' => 'pelican', 'label' => $label('pelican_settings'), 'icon' => 'settings', 'color' => '#5b9cf0', 'keys' => ['pluginresource', 'listlogs'], 'tabs' => $tabs('pelican')],
                ['key' => 'addons', 'label' => $label('addons'), 'icon' => 'plugins', 'color' => '#b48cf2', 'keys' => [], 'tabs' => array_values(array_filter($tabs('addons'), fn (array $tab) => $tab['key'] !== 'settings:addons')), 'apart' => true],
            ]);
            // Pelican plugins comes after the add-ons.
            $plan = [...array_filter($plan, fn (array $p) => $p['key'] !== 'extensions'), ...array_filter($plan, fn (array $p) => $p['key'] === 'extensions')];
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
                : [...$section['tabs'], ...$extra[$section['key']] ?? [], ...array_values(array_filter(array_map(fn (string $key) => $byKey[$key] ?? null, $section['keys']))), ...$section['last'] ?? []];
            if ($pages !== []) {
                // The first group of what is not Pelican's own stands apart, under the rest of the menu.
                $apart = ($section['apart'] ?? false) || ($section['key'] === 'extensions' && ! in_array(true, array_column($tree, 'apart'), true));
                $tree[] = ['key' => $section['key'], 'label' => (string) $section['label'], 'icon' => $section['icon'], 'color' => $section['color'], 'pages' => $pages, 'apart' => $apart];
            }
        }

        return $tree;
    }
}
