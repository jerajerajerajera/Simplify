<?php

// P-34: the version in plugin.json and CHANGELOG.md agree. "Unreleased" is the first section, the version in
// plugin.json has its own section right under it, no version has two sections, and every section has at least one line.

$failed = 0;
$fail = function (string $why) use (&$failed): void {
    echo 'FAIL changelog: '.$why.PHP_EOL;
    $failed++;
};

$plugin = json_decode((string) file_get_contents(__DIR__.'/../plugin.json'), true);
$version = is_array($plugin) ? (string) ($plugin['version'] ?? '') : '';
$text = (string) file_get_contents(__DIR__.'/../CHANGELOG.md');
preg_match_all('/^## (.+)$/m', $text, $headings);
$headings = array_map('trim', $headings[1]);

if (($headings[0] ?? null) !== 'Unreleased') {
    $fail('the first section is not "## Unreleased"');
}
if ($version === '' || ! in_array($version, $headings, true)) {
    $fail('plugin.json has version "'.$version.'" but CHANGELOG.md has no "## '.$version.'"');
} elseif (($headings[1] ?? null) !== $version) {
    $fail('the section under Unreleased is "'.($headings[1] ?? '').'", not the version in plugin.json ('.$version.')');
}
foreach (array_count_values($headings) as $heading => $count) {
    if ($count > 1) {
        $fail('"## '.$heading.'" is there '.$count.' times');
    }
}
foreach (preg_split('/^## /m', $text) ?: [] as $i => $section) {
    if ($i === 0) {
        continue;
    }
    [$heading, $body] = array_pad(explode("\n", $section, 2), 2, '');
    if (trim($heading) !== 'Unreleased' && trim($body) === '') {
        $fail('"## '.trim($heading).'" has no lines');
    }
}

if ($failed > 0) {
    exit(1);
}
echo 'PASS changelog: Unreleased on top, then '.$version.' as in plugin.json'.PHP_EOL;
