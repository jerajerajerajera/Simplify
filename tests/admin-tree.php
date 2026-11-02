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
        'settings' => '[["servers",["serverresource","eggresource"]],["nodes",["noderesource"]],["people",["userresource","roleresource","people"]],["integrations",["apikeyresource"]],["panel",["general","announcementresource","embedsettings"]],["addons",["maker","hosting","eggupdates","pluginresource"]],["pelican",["mail","listlogs"]],["extensions",["sitedirectory"]],["health",["health"]]]',
        'none' => '[["servers",["serverresource","eggresource"]],["nodes",["noderesource"]],["people",["userresource","roleresource","people"]],["integrations",["apikeyresource"]],["panel",["settings","pluginresource","listlogs"]],["extensions",["announcementresource","embedsettings","servermaker","eggupdates","hosting","sitedirectory"]],["health",["health"]]]',
    ];
    $failures = [];
    foreach (['settings' => $data['menu'], 'none' => []] as $name => $menu) {
        $got = $shape(AdminTree::group($data['items'], $menu));
        if ($got !== $want[$name]) {
            $failures[] = "with $name settings menu: $got";
        }
    }
    $tabs = AdminTree::group($data['items'], $data['menu'])[4]['pages'][0];
    if (($tabs['settings'] ?? false) !== true) {
        $failures[] = 'a settings tab is marked as one';
    }
    foreach ($failures as $failure) {
        echo "FAIL $failure\n";
    }
    echo $failures === [] ? "PASS admin tree: groups and every page once\n" : '';
    exit($failures === [] ? 0 : 1);
}
