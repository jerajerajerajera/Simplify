<?php

// Health: every check that needs attention has the spot for an add-on's button after its error (ADDONS.md, Errors).
declare(strict_types=1);

require __DIR__.'/../src/Health/HealthFix.php';

use Simplify\Core\Health\HealthFix;

function __(string $key): string
{
    return $key;
}

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
check(substr_count($blade, "renderHook('simplify::problem.actions'") === 1, 'the spot is rendered once in the page');
check(str_contains($loop, "renderHook('simplify::problem.actions'"), 'the spot is rendered per check that needs attention, not for fine checks');
check(str_contains($loop, "scopes: ['simplify-problem', 'simplify-problem:simplify:health:'.\$fix::key((string) \$result->label)]"), 'the spot has the general scope and the id simplify:health:<key>');

$fix = strpos($loop, 's-health-fix');
$hook = strpos($loop, 's-health-actions');
$pill = strpos($loop, 's-health-pill');
check($fix !== false && $hook !== false && $pill !== false && $fix < $hook && $hook < $pill, 'the spot sits after the fix button and before the status');

// The key is a plain word, so the id keeps to the contract: [A-Za-z0-9_.-] per part.
check(HealthFix::key('Queue') === 'queue', 'Queue gives queue');
check(HealthFix::key('Node Versions') === 'nodeversions', 'a name with a space gives one word');
check(HealthFix::key('Used Disk: Space (%)') === 'useddiskspace', 'other signs are left out: '.HealthFix::key('Used Disk: Space (%)'));
check(strlen(HealthFix::key(str_repeat('a', 300))) === 64, 'a key is at most 64 characters');
check(preg_match('/^[a-z0-9_.-]*$/', HealthFix::key('Ünïcode Chëck/1')) === 1, 'a key holds only allowed signs');
check(HealthFix::for('Panel Version') !== null, 'the fix button still finds its check by the same key');

check(str_contains($css, '.s-health-actions:not(:has(*)) { display: none; }'), 'an empty spot takes no room');
check(str_contains($css, '.s-health-text + .s-health-actions { margin-inline-start: 44px; }'), 'on a phone the button lines up under the text when there is no fix button');

check(str_contains($doc, '## Errors') && str_contains($doc, "renderHook('simplify::problem.actions'") && str_contains($doc, '`simplify:health:<check>`'), 'ADDONS.md describes the spot and the Health ids');

if ($failures !== []) {
    echo 'FAIL health hook: '.implode('; ', $failures)."\n";
    exit(1);
}
echo "PASS health hook\n";
