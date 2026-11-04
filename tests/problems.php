<?php

// The log behind Simplify's own errors for an add-on such as Explain (ADDONS.md, Errors), without a panel.
declare(strict_types=1);

require __DIR__.'/../src/Problems/InstallProblems.php';

use Simplify\Core\Problems\InstallProblems;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

$uuid = '0b3c2a9e-1f4d-4e8a-9c7b-5d6e7f809a1b';
check(InstallProblems::uuid('simplify:install:'.$uuid) === $uuid, 'an install id gives its server uuid');
check(InstallProblems::uuid('simplify:install:__ID__') === null, 'a template id is refused');
check(InstallProblems::uuid('simplify:install:'.$uuid.'/x') === null && InstallProblems::uuid('simplify:install:'.strtoupper($uuid)) === null, 'only a plain uuid counts');
check(InstallProblems::uuid('simplify:health:queue') === null && InstallProblems::uuid('simplify-eggupdates:test:'.$uuid) === null, 'other ids are not this source');
check(strlen('simplify:install:'.$uuid) <= 128 && preg_match('/^[A-Za-z0-9_.-]+(:[A-Za-z0-9_.-]+){1,4}$/', 'simplify:install:'.$uuid) === 1, 'the id keeps to the contract');

$lines = InstallProblems::lines("\e[1;33mInstalling\e[0m\r\n\nDownloading\n\e[31minstall failed: disk full\e[0m\n");
check($lines === ['Installing', 'Downloading', 'install failed: disk full'], 'terminal colours and empty lines are left out: '.json_encode($lines));
$long = InstallProblems::lines(implode("\n", array_map(fn (int $n) => "line $n", range(1, 250))));
check(count($long) === InstallProblems::LINES && $long[0] === 'line 151' && end($long) === 'line 250', 'only the last 100 lines, oldest first');
check(InstallProblems::lines('') === [], 'no log, no lines');

$root = dirname(__DIR__);
$view = (string) file_get_contents($root.'/resources/views/pages/server.blade.php');
check(preg_match('/class="ps-problem" x-show="installFailed\(\)">\{\{ \\\\Filament\\\\Support\\\\Facades\\\\FilamentView::renderHook\(\'simplify::problem\.actions\', scopes: \[\'simplify-problem\', \'simplify-problem:\'\.\\\\Simplify\\\\Core\\\\Problems\\\\InstallProblems::PREFIX\.\$server->uuid\]\) \}\}/', $view) === 1, 'the server page has the spot while the installation failed, with the server uuid');
check(str_contains((string) file_get_contents($root.'/src/Providers/SimplifyServiceProvider.php'), "\$this->app->tag([InstallProblems::class], 'simplify.problems');"), 'the source is tagged simplify.problems');
check(str_contains((string) file_get_contents($root.'/ADDONS.md'), '`simplify:install:<server uuid>`'), 'ADDONS.md names the install id');

$source = (string) file_get_contents($root.'/src/Problems/InstallProblems.php');
check(str_contains($source, 'Installation::FAILED') && str_contains($source, 'SubuserPermission::ControlConsole') && str_contains($source, 'isRootAdmin()'), 'only a failed installation, only for someone who may use the console');

if ($failures !== []) {
    echo 'FAIL problems: '.implode('; ', $failures)."\n";
    exit(1);
}
echo "PASS problems\n";
