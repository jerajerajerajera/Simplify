<?php

// The groups of Admin tools and host mode (AdminTree) without a panel: every page once, in its group.
declare(strict_types=1);

namespace {
    function __(string $key): string
    {
        return $key;
    }
}

namespace Simplify\Core\Filament\Admin\Pages {
    final class Settings {}
}

namespace {
    require __DIR__.'/../src/Overview/AdminTree.php';

    use Simplify\Core\Overview\AdminTree;

    $data = json_decode((string) file_get_contents(__DIR__.'/admin-tree.json'), true);
    $shape = fn (array $tree) => json_encode(array_map(fn (array $s) => [$s['key'], array_map(fn (array $p) => $p['key'] ?? $p['label'], $s['pages'])], $tree));
    $want = [
        'settings' => '[["servers",["serverresource","eggresource","backupresource"]],["nodes",["noderesource"]],["people",["userresource","roleresource"]],["integrations",["apikeyresource"]],["panel",["general","announcementresource","embedsettings","settings:addons"]],["pelican",["mail","pluginresource","listlogs","activityresource"]],["addons",["maker","hosting","eggupdates"]],["health",["health"]],["extensions",["sitedirectory"]]]',
        'none' => '[["servers",["serverresource","eggresource","backupresource"]],["nodes",["noderesource"]],["people",["userresource","roleresource"]],["integrations",["apikeyresource"]],["panel",["settings","pluginresource","listlogs","activityresource"]],["extensions",["announcementresource","embedsettings","servermaker","eggupdates","hosting","sitedirectory"]],["health",["health"]]]',
    ];
    $failures = [];
    foreach (['settings' => $data['menu'], 'none' => []] as $name => $menu) {
        $got = $shape(AdminTree::group($data['items'], $menu));
        if ($got !== $want[$name]) {
            $failures[] = "with $name settings menu: $got";
        }
    }
    // K-B72: the pages Pelican added in rc.3 (Activity) and rc.4 (Backups) stand with Pelican's pages, not under plugins.
    // Simplify add-ons stand apart under the menu, Pelican plugins follow (Jeroen 3-11).
    $tree = AdminTree::group($data['items'], $data['menu']);
    $apart = array_column(array_filter($tree, fn (array $s) => $s['apart']), 'key');
    if ($apart !== ['addons']) {
        $failures[] = 'only Simplify add-ons starts the part apart: '.json_encode($apart);
    }
    $none = AdminTree::group($data['items'], []);
    if (array_column(array_filter($none, fn (array $s) => $s['apart']), 'key') !== ['extensions']) {
        $failures[] = 'without the settings menu Pelican plugins stands apart';
    }
    // V46: Simplify's People & roles is gone; Pelican's Users and Roles are the People group.
    if ($shape([$tree[2]]) !== '[["people",["userresource","roleresource"]]]') {
        $failures[] = 'the People group holds Pelican\'s Users and Roles: '.$shape([$tree[2]]);
    }
    // K-B96: one add-on with its own page (Themes) shows under its own name, not as "Simplify add-ons".
    $one = array_values(array_filter($data['menu'], fn (array $tab) => ($tab['group'] ?? null) !== 'addons' || $tab['key'] === 'settings:addons'));
    $one[] = ['key' => 'addon:themes', 'group' => 'addons', 'label' => 'Themes', 'icon' => 'plugins', 'url' => '/admin/themes'];
    $items = array_values(array_filter($data['items'], fn (array $item) => ($item['origin'] ?? '') !== 'addon'));
    $addons = array_values(array_filter(AdminTree::group($items, $one), fn (array $s) => $s['key'] === 'addons'))[0] ?? null;
    if ($addons === null || ($addons['single'] ?? true) || array_column($addons['pages'], 'label') !== ['Themes']) {
        $failures[] = 'one add-on page stands under its own name: '.json_encode($addons);
    }
    if (! array_values(array_filter($tree, fn (array $s) => $s['key'] === 'health'))[0]['single'] ?? false) {
        $failures[] = 'Health, one page, still opens under the group\'s name';
    }
    $tabs = $tree[4]['pages'][0];
    if (($tabs['settings'] ?? false) !== true) {
        $failures[] = 'a settings tab is marked as one';
    }
    foreach ($failures as $failure) {
        echo "FAIL $failure\n";
    }
    echo $failures === [] ? "PASS admin tree: groups and every page once\n" : '';
    exit($failures === [] ? 0 : 1);
}
