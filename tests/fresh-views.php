<?php

// K-B57: after an update Laravel kept a compiled copy of an old Simplify page (a 1.5.x overview asked for
// dashboard.host, which 2.0 no longer has: 500 on the overview). A new version throws those copies away once.
declare(strict_types=1);

require __DIR__.'/../src/Support/FreshViews.php';

use Simplify\Core\Support\FreshViews;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

$root = dirname(__DIR__);
$views = FreshViews::files($root.'/resources/views');
$overview = $root.'/resources/views/pages/overview.blade.php';
check(in_array($overview, $views, true) && count($views) > 20, "every one of Simplify's view files is found");
check(FreshViews::files($root.'/missing') === [], 'a folder that is not there has no views');

// The compiled folder as a list: the old overview from 1.5.5, and a page of Pelican's.
$compiledPath = fn (string $path): string => 'compiled/'.sha1($path).'.php';
$run = function (string $version, ?string $known, array &$folder, ?string &$remembered) use ($views, $compiledPath): bool {
    return FreshViews::refresh(
        $version,
        $known,
        $views,
        $compiledPath,
        function (string $path) use (&$folder) {
            unset($folder[$path]);
        },
        function (string $version) use (&$remembered) {
            $remembered = $version;
        },
    );
};
$folder = [$compiledPath($overview) => "make('simplify::dashboard.host')", 'compiled/pelican-page.php' => 'pelican'];
$remembered = null;
check($run('2.0.0-beta.7', '1.5.5', $folder, $remembered), 'a new version throws the old copies away');
check(! isset($folder[$compiledPath($overview)]), 'the old compiled overview is gone, so Laravel compiles the new one');
check(isset($folder['compiled/pelican-page.php']), "a compiled page that is not Simplify's stays");
check($remembered === '2.0.0-beta.7', 'the new version is remembered');

$folder[$compiledPath($overview)] = 'compiled by 2.0.0-beta.7';
$remembered = null;
check(! $run('2.0.0-beta.7', '2.0.0-beta.7', $folder, $remembered), 'the same version leaves the copies alone');
check(isset($folder[$compiledPath($overview)]) && $remembered === null, 'pages are not compiled again on every request');
check($run('2.0.0-beta.7', null, $folder, $remembered), 'a cleared view cache (no version known) refreshes once');
check(! $run('', '1.5.5', $folder, $remembered), 'without a version of its own nothing is removed');

$provider = (string) file_get_contents($root.'/src/Providers/SimplifyServiceProvider.php');
check((bool) preg_match('/public function boot\(\): void\s*\{[^}]*FreshViews::boot\(Assets::version\(\)\)/s', $provider), 'the plugin refreshes its views first thing on boot');

if ($failures !== []) {
    echo "FAIL fresh views (K-B57):\n - ".implode("\n - ", $failures)."\n";
    exit(1);
}
echo "PASS fresh views (K-B57): a new Simplify version throws away the old compiled pages once\n";
