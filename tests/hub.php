<?php

// Browse Hub without a panel: Hub rows are cleaned up, makers are counted and credited, and the code only reads the
// Hub's list (a shop window: it never downloads or installs). Run: php tests/hub.php
declare(strict_types=1);

foreach (['HubApi', 'HubText', 'HubCatalog'] as $class) {
    require __DIR__.'/../src/Hub/'.$class.'.php';
}

use Simplify\Core\Hub\HubCatalog;
use Simplify\Core\Hub\HubText;

$failures = [];
$checks = 0;
function same(string $what, mixed $want, mixed $have): void
{
    global $failures, $checks;
    $checks++;
    if ($want !== $have) {
        $failures[] = $what.': wanted '.var_export($want, true).', got '.var_export($have, true);
    }
}

$nord = [
    'slug' => 'nord-theme', 'title' => 'Nord Theme', 'type' => 'theme', 'author_name' => 'boy132', 'pricing_type' => 'free',
    'description' => "A light and dark arctic Nord theme.\n\n_Originally created by [Andréia Bohner](https://github.com/andreia) as [Filament Theme](https://github.com/andreia/filament-nord-theme), under MIT License._",
    'downloads_count' => 89, 'latest_version' => '1.0.0', 'last_version_at' => '2026-05-29T08:32:43+00:00', 'license' => null,
    'votes_count' => 9, 'review_percentage' => 100, 'is_featured' => false,
    'screenshots' => ['https://peli.nyc3.digitaloceanspaces.com/a.png', 'http://insecure.example/b.png', 'javascript:alert(1)'],
];
$item = HubCatalog::normalize($nord);
same('the summary leaves out the credit line', 'A light and dark arctic Nord theme.', $item['summary']);
same('the original maker is credited', ['name' => 'Andréia Bohner', 'url' => 'https://github.com/andreia'], $item['based_on']);
same('only https screenshots are kept', ['https://peli.nyc3.digitaloceanspaces.com/a.png'], $item['screenshots']);
same('the date is a day', '2026-05-29', $item['updated']);
same('the button opens the Hub page', 'https://hub.pelican.dev/plugins/nord-theme', $item['url']);
same('the rating is kept', [9, 100], [$item['votes'], $item['rating']]);
same('no rating without one', null, HubCatalog::normalize([...$nord, 'review_percentage' => null])['rating']);
same('a rating stays within 0-100', 100, HubCatalog::normalize([...$nord, 'review_percentage' => 900])['rating']);
same('paid items are left out', null, HubCatalog::normalize([...$nord, 'pricing_type' => 'paid']));
same('unknown types are left out', null, HubCatalog::normalize([...$nord, 'type' => 'egg']));
same('odd slugs are left out', null, HubCatalog::normalize([...$nord, 'slug' => '../etc']));
same('html in a title is removed', 'Nord', HubCatalog::normalize([...$nord, 'title' => '<b>Nord</b>'])['title']);

$items = HubCatalog::build([
    $nord,
    [...$nord, 'title' => 'Duplicate'],
    ['slug' => 'sakura', 'type' => 'theme', 'author_name' => 'boy132', 'downloads_count' => 10],
    ['slug' => 'aurora', 'type' => 'theme', 'author_name' => 'ankit', 'downloads_count' => 500],
    ['slug' => 'tickets', 'type' => 'plugin', 'author_name' => 'ankit', 'downloads_count' => 5],
]);
same('one item per slug', [4, 'Nord Theme'], [count($items), $items[0]['title']]);
$makers = HubCatalog::makers($items, 'theme');
same('makers with the most items come first', ['boy132', 'ankit'], array_column($makers, 'name'));
same('downloads add up per maker', 99, $makers[0]['downloads']);

same('long text is cut at a sentence', str_repeat('Word ', 19).'Word end.', HubText::summary(str_repeat('Word ', 20).'end. '.str_repeat('More words here ', 20)));
same('markdown becomes plain text', 'Bold link', HubText::summary("# Title\n![img](x.png)\n- **Bold** [link](https://x)"));

// A shop window only: nothing in Browse Hub downloads, unpacks or installs a plugin.
foreach (array_merge(glob(__DIR__.'/../src/Hub/*.php'), glob(__DIR__.'/../src/Hub/*/*.php'), glob(__DIR__.'/../resources/views/hub/*.php')) as $file) {
    $code = file_get_contents($file);
    foreach (['PluginService', 'download_url', 'update.json', 'ZipArchive', 'installPlugin', 'sink('] as $word) {
        same(basename($file).' has no '.$word, false, str_contains($code, $word));
    }
}

if ($failures) {
    echo "FAIL\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo "PASS $checks Browse Hub checks\n";
