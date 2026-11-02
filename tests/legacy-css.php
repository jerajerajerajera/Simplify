<?php

// K-B5: Simplify restyles Pelican's tables (short rows on a phone) only inside Simplify: the admin frame, a server
// page with Simplify's top, or an admin page with Simplify's header. The legacy panel keeps Pelican's own tables.
declare(strict_types=1);

$css = (string) file_get_contents(__DIR__.'/../resources/css/simplify.css');
$css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
$failures = [];
$checked = 0;
preg_match_all('~([^{}]+)\{~', $css, $blocks);
foreach ($blocks[1] as $selectors) {
    if (str_contains($selectors, '@media') || ! str_contains($selectors, '.fi-ta-table')) {
        continue;
    }
    foreach (preg_split('~,(?![^()]*\))~', $selectors) as $selector) {
        $selector = trim($selector);
        if (! str_contains($selector, '.fi-ta-table')) {
            continue;
        }
        $checked++;
        if (! preg_match('~ps-frame|\.ps-server-top|\.ps-admin-top~', $selector)) {
            $failures[] = 'not scoped to Simplify: '.$selector;
        }
    }
}

if ($checked === 0) {
    $failures[] = 'no table rules found; the test reads the wrong file';
}
if ($failures !== []) {
    echo 'FAIL '.implode("\nFAIL ", $failures)."\n";
    exit(1);
}
echo "PASS Pelican's tables keep their own look on the legacy panel ($checked selectors scoped to Simplify)\n";
