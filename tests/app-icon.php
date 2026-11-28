<?php

// Every size the app icon is asked for (manifest, worker, the iPhone's apple-touch-icon) is a size its address answers
// and Simplify ships: the iPhone asked for 180, which the admin's own icon could not serve (K-B102). Run: php tests/app-icon.php
declare(strict_types=1);

$root = dirname(__DIR__);
$provider = (string) file_get_contents($root.'/src/Providers/SimplifyServiceProvider.php');
preg_match("/app-icon-\\{size\\}\\.png'.*?whereIn\\('size', \\[([^\\]]*)\\]\\)/", $provider, $route);
$allowed = array_map(fn (string $size) => (int) trim($size, " '"), explode(',', $route[1] ?? ''));
$asked = [];
foreach (['src', 'resources/views'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir)) as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
            preg_match_all('/(?:WebApp|self)::icon\((\d+)\)/', (string) file_get_contents($file->getPathname()), $found);
            foreach ($found[1] as $size) {
                $asked[(int) $size] = str_replace($root.'/', '', $file->getPathname());
            }
        }
    }
}
$failures = [];
if ($asked === [] || $allowed === [0]) {
    $failures[] = 'no icon sizes found';
}
foreach ($asked as $size => $where) {
    if (! in_array($size, $allowed, true)) {
        $failures[] = "$where asks for a $size px icon, the app icon address answers only ".implode(', ', $allowed);
    }
    if (! is_file($root."/resources/img/app-icon-$size.png")) {
        $failures[] = "Simplify ships no app-icon-$size.png for $where";
    }
}
if ($failures) {
    echo "FAIL app icon:\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo 'PASS app icon: sizes '.implode(', ', array_keys($asked))." are served\n";
