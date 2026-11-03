<?php

// Simplify's styles and scripts go to the browser gzipped: Pelican's web server compresses only HTML. The gzip holds
// exactly the file, and images and fonts (compressed already) go as they are. Run: php tests/assets.php
declare(strict_types=1);

require __DIR__.'/../src/Support/Assets.php';

use Simplify\Core\Support\Assets;

$failures = [];
$raw = 0;
$sent = 0;
foreach (Assets::FILES as $name => [, $type]) {
    $gzip = Assets::gzip($name);
    if (! (str_starts_with($type, 'text/') || str_starts_with($type, 'image/svg'))) {
        if ($gzip !== null) {
            $failures[] = "$name is compressed already but is gzipped again";
        }

        continue;
    }
    $original = (string) file_get_contents((string) Assets::path($name));
    if ($gzip === null || gzdecode($gzip) !== $original) {
        $failures[] = "$name: the gzip is not the file";

        continue;
    }
    if (preg_match('~\.(css|js)$~', $name) === 1 && ! in_array($name, ['seasons.css', 'seasons.js', 'frame.js'], true)) {
        $raw += strlen($original);
        $sent += strlen($gzip);
    }
}
if (Assets::gzip('nothing.css') !== null) {
    $failures[] = 'a file Simplify does not serve was gzipped';
}
if ($sent === 0 || $sent > $raw / 3) {
    $failures[] = "a first page load still sends $sent of $raw bytes";
}

if ($failures !== []) {
    echo 'FAIL '.implode("\nFAIL ", $failures)."\n";
    exit(1);
}
printf("PASS Simplify's styles and scripts go out gzipped: %d KB instead of %d KB on a first page load\n", intdiv($sent, 1024), intdiv($raw, 1024));
