<?php

// The words for a host's health come from one file per language (dashboard.php health.labels): the scripts get them
// from there, so js.php never holds a second copy that can say something else (Jeroen 5-11: one place to translate).
declare(strict_types=1);

require __DIR__.'/../src/Support/JsText.php';

use Simplify\Core\Support\JsText;

$failures = [];
foreach (glob(__DIR__.'/../lang/*', GLOB_ONLYDIR) ?: [] as $folder) {
    $language = basename($folder);
    $js = require $folder.'/js.php';
    $labels = (require $folder.'/dashboard.php')['health']['labels'];
    foreach (JsText::FROM_HEALTH as $key => $label) {
        if (array_key_exists($key, $js['dashboard'])) {
            $failures[] = "$language: js.php dashboard.$key is a second copy of dashboard.php health.labels.$label";
        }
    }
    $merged = JsText::merge($js, $labels);
    foreach (JsText::FROM_HEALTH as $key => $label) {
        if (($merged['dashboard'][$key] ?? null) !== $labels[$label]) {
            $failures[] = "$language: the scripts' dashboard.$key is not health.labels.$label";
        }
    }
    if (($merged['dashboard']['cpu_load'] ?? null) !== $js['dashboard']['cpu_load']) {
        $failures[] = "$language: the other texts of js.php stay as they are";
    }
}
$view = (string) file_get_contents(__DIR__.'/../resources/views/dashboard/assets.blade.php');
if (! str_contains($view, 'JsText::all()')) {
    $failures[] = 'the page hands the scripts JsText::all(), not js.php alone';
}

if ($failures) {
    echo "FAIL js texts:\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo "PASS js texts: health words come from one file per language\n";
