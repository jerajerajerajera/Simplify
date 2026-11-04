<?php

// Health: every check that needs attention has one place for an add-on's buttons (ADDONS.md, Health).
declare(strict_types=1);

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

$root = dirname(__DIR__);
$blade = (string) file_get_contents($root.'/resources/overrides/filament/pages/health.blade.php');
$css = (string) file_get_contents($root.'/resources/css/simplify.css');
$doc = (string) file_get_contents($root.'/ADDONS.md');

$loop = preg_match('/@foreach \(\$bad as \$result\)(.*?)@endforeach/s', $blade, $m) ? $m[1] : '';
check($loop !== '', 'the Health page has a loop over the checks that need attention');
check(substr_count($blade, "renderHook('simplify::health.check.actions'") === 1, 'the hook is rendered once in the page');
check(str_contains($loop, "renderHook('simplify::health.check.actions'"), 'the hook is rendered per check that needs attention, not for fine checks');
check(str_contains($loop, "'simplify-health-check'") && str_contains($loop, "'simplify-health-check:'."), 'the hook has a scope for every check and one per check');

$fix = strpos($loop, 's-health-fix');
$hook = strpos($loop, 's-health-actions');
$pill = strpos($loop, 's-health-pill');
check($fix !== false && $hook !== false && $pill !== false && $fix < $hook && $hook < $pill, 'the hook sits after the fix button and before the status');

// The scope is the label in lower case without spaces, as ADDONS.md says.
$scope = 'simplify-health-check:'.preg_replace('/\s+/', '', mb_strtolower('Queue Jobs'));
check($scope === 'simplify-health-check:queuejobs', 'a label with a space and capitals gives a plain scope: '.$scope);
check(str_contains($loop, "preg_replace('/\\s+/', '', mb_strtolower((string) \$result->label))"), 'the page builds the scope the same way as this test');

check(preg_match('/\.s-health-actions :is\(button, a\) \{[^}]*min-width: 32px; height: 32px;/', $css) === 1, 'an add-on button in the row is 32 px');
check(str_contains($css, '.s-health-actions:not(:has(*)) { display: none; }'), 'an empty place takes no room');
check(str_contains($css, '.s-health-text + .s-health-actions { margin-inline-start: 44px; }'), 'on a phone the add-on button lines up under the text when there is no fix button');

check(str_contains($doc, '## Health') && str_contains($doc, '`simplify::health.check.actions`') && str_contains($doc, '`simplify-health-check:<label>`'), 'ADDONS.md describes the hook and its scopes');

if ($failures !== []) {
    echo 'FAIL health hook: '.implode('; ', $failures)."\n";
    exit(1);
}
echo "PASS health hook\n";
