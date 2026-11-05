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

// K-B62: a view of a newer version can ask an older Assets class (still in PHP's opcache) for a file it does not
// know. That broke the head of every page ("Path must not be empty"); now its URL carries Simplify's version.
try {
    $unknown = Assets::hash('row-actions-from-a-newer-version.js');
    if (strlen($unknown) !== 12 || $unknown === Assets::hash('core.js')) {
        $failures[] = 'a file Assets does not know gets a version of its own (K-B62)';
    }
} catch (Throwable $error) {
    $failures[] = 'a file Assets does not know breaks the page: '.$error->getMessage().' (K-B62)';
}
if (Assets::hash('core.js') !== substr((string) hash_file('xxh3', (string) Assets::path('core.js')), 0, 12)) {
    $failures[] = "a known file's URL still carries the hash of the file";
}

if ($failures !== []) {
    echo 'FAIL '.implode("\nFAIL ", $failures)."\n";
    exit(1);
}
printf("PASS Simplify's styles and scripts go out gzipped: %d KB instead of %d KB on a first page load\n", intdiv($sent, 1024), intdiv($raw, 1024));
