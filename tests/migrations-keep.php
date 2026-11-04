<?php

// Uninstalling Simplify keeps its data: Pelican runs every down() of a plugin's migrations on uninstall, so no
// migration may drop a table or a column there. checks.yml uninstalls and installs again on a real panel.

$failed = 0;
foreach (glob(__DIR__.'/../database/migrations/*.php') as $file) {
    $code = (string) file_get_contents($file);
    if (! preg_match('/public function down\(\): void\s*\{(.*?)\n    \}|public function down\(\): void \{\}/s', $code, $match)) {
        echo 'FAIL no down() in '.basename($file).PHP_EOL;
        $failed++;

        continue;
    }
    if (preg_match('/drop|truncate|delete/i', $match[1] ?? '')) {
        echo 'FAIL down() removes data in '.basename($file).PHP_EOL;
        $failed++;
    }
}

if ($failed > 0) {
    exit(1);
}
echo 'PASS no migration removes data on uninstall'.PHP_EOL;
