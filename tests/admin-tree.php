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
        'settings' => '[["servers",["serverresource","eggresource"]],["nodes",["noderesource"]],["people",["people"]],["integrations",["apikeyresource"]],["panel",["general","announcementresource","embedsettings","settings:addons"]],["pelican",["mail","pluginresource","listlogs"]],["addons",["maker","hosting","eggupdates"]],["health",["health"]],["extensions",["sitedirectory"]]]',
        'none' => '[["servers",["serverresource","eggresource"]],["nodes",["noderesource"]],["people",["people"]],["integrations",["apikeyresource"]],["panel",["settings","pluginresource","listlogs"]],["extensions",["announcementresource","embedsettings","servermaker","eggupdates","hosting","sitedirectory"]],["health",["health"]]]',
    ];
    $failures = [];
    foreach (['settings' => $data['menu'], 'none' => []] as $name => $menu) {
        $got = $shape(AdminTree::group($data['items'], $menu));
        if ($got !== $want[$name]) {
            $failures[] = "with $name settings menu: $got";
        }
    }
    // Simplify add-ons stand apart under the menu, Pelican plugins follow (Jeroen 3-11); People & roles is one leaf.
    $tree = AdminTree::group($data['items'], $data['menu']);
    $apart = array_column(array_filter($tree, fn (array $s) => $s['apart']), 'key');
    if ($apart !== ['addons']) {
        $failures[] = 'only Simplify add-ons starts the part apart: '.json_encode($apart);
    }
    $none = AdminTree::group($data['items'], []);
    if (array_column(array_filter($none, fn (array $s) => $s['apart']), 'key') !== ['extensions']) {
        $failures[] = 'without the settings menu Pelican plugins stands apart';
    }
    if ($tree[2]['label'] !== 'People & roles') {
        $failures[] = 'the People group is named after People & roles: '.$tree[2]['label'];
    }
    $withoutPeople = AdminTree::group(array_values(array_filter($data['items'], fn (array $i) => $i['key'] !== 'people')), $data['menu']);
    if ($shape([$withoutPeople[2]]) !== '[["people",["userresource","roleresource"]]]') {
        $failures[] = 'without People & roles the tree keeps Users and Roles: '.$shape([$withoutPeople[2]]);
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
