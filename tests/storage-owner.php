<?php

// The storage notice for root admins: a panel folder that belongs to someone else, or cannot be written, counts
// (Khion 15-11: a 500 after an update as root, fixed with chown www-data).
declare(strict_types=1);

require __DIR__.'/../src/Support/SafeCache.php';

use Simplify\Core\Support\SafeCache;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

// Two folders that are there (stand-ins for the compiled views and bootstrap/cache) and one that is not.
$dir = __DIR__;
$views = $dir.'/fixtures';
$paths = [$views, $dir, $dir.'/missing'];
$writable = fn (string $path): bool => true;
$owners = [$views => 33, $dir => 33];
$owner = function (string $path) use (&$owners): int|false {
    return $owners[$path] ?? false;
};

check(! SafeCache::foreign($paths, 33, $owner, $writable), 'everything belongs to the web server: no notice');
check(! SafeCache::foreign([$dir.'/missing'], 33, $owner, $writable), 'a folder that is not there is fine');
$owners[$dir] = 0;
check(SafeCache::foreign($paths, 33, $owner, $writable), 'bootstrap/cache belongs to root: notice');
$owners[$dir] = 33;
check(SafeCache::foreign($paths, 33, $owner, fn (string $path): bool => $path !== $views), 'the compiled views cannot be written: notice');

if ($failures) {
    foreach ($failures as $failure) {
        echo "FAIL $failure\n";
    }
    exit(1);
}
echo "PASS the storage notice also looks at the compiled views and bootstrap/cache\n";
