<?php

// Every decision number (V-n) that the rules, bugs or role briefs refer to must have exactly one row in the
// "Genummerd" table of docs/besluiten.md, so a number never means two things (WR-8).

$root = dirname(__DIR__);
$fail = 0;

function must(bool $ok, string $message): void
{
    global $fail;
    if (! $ok) {
        $fail++;
        echo "FAIL: {$message}\n";
    }
}

$decisions = (string) file_get_contents("{$root}/docs/besluiten.md");
$numbered = preg_split('/^## /m', $decisions)[1] ?? '';
$defined = [];
foreach (explode("\n", $numbered) as $line) {
    if (! preg_match('/^\|\s*V(\d+)(?:\s*[–-]\s*V(\d+))?\s*\|/u', $line, $m)) {
        continue;
    }
    $to = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : (int) $m[1];
    for ($n = (int) $m[1]; $n <= $to; $n++) {
        must(! isset($defined[$n]), "V{$n} has more than one row in docs/besluiten.md");
        $defined[$n] = true;
    }
}
must($defined !== [], 'docs/besluiten.md has no numbered decisions');

foreach (array_slice(preg_split('/^## /m', $decisions), 2) as $section) {
    must(! preg_match('/^\|[^|]*\|\s*V\d+:/m', $section), 'docs/besluiten.md gives a number outside the table "Genummerd"');
}

foreach (['docs/regels.md', 'docs/bugs.md', 'docs/prompts/startbrieven.md'] as $file) {
    preg_match_all('/\bV(\d+)\b/', (string) file_get_contents("{$root}/{$file}"), $refs);
    foreach (array_unique($refs[1]) as $n) {
        must(isset($defined[(int) $n]), "{$file} refers to V{$n}, which has no row in docs/besluiten.md");
    }
}

echo $fail === 0 ? "decisions: ok\n" : "decisions: {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
