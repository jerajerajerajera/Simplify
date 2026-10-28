<?php

// Overview numbers without a panel: what Wings sends, what it leaves out, and how tiles print it.
declare(strict_types=1);

require __DIR__.'/../src/Overview/Usage.php';
require __DIR__.'/../src/Overview/Format.php';
require __DIR__.'/../src/Overview/Power.php';

use Simplify\Core\Overview\Format;
use Simplify\Core\Overview\Power;
use Simplify\Core\Overview\Usage;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

$running = Usage::fromWings('running', ['cpu_absolute' => 12.345, 'memory_bytes' => 1073741824, 'disk_bytes' => '2048']);
check($running->toArray() === ['state' => 'running', 'cpu' => 12.3, 'memory' => 1073741824, 'disk' => 2048], 'a running server keeps its numbers: '.json_encode($running->toArray()));

$partial = Usage::fromWings('running', ['cpu_absolute' => -1, 'memory_bytes' => 'abc']);
check($partial->cpuPercent === null && $partial->memoryBytes === null && $partial->diskBytes === null, 'missing or broken numbers stay unknown');

$offline = Usage::fromWings('offline', ['disk_bytes' => 500]);
check($offline->cpuPercent === 0.0 && $offline->memoryBytes === 0 && $offline->diskBytes === 500, 'an offline server uses no CPU or memory but keeps its disk');

check(Usage::unknown()->state === 'missing', 'an unreachable server is "missing"');

check(Format::bytes(null) === '-', 'unknown bytes print as a dash');
check(Format::bytes(512) === '512 B', 'bytes: '.Format::bytes(512));
check(Format::bytes(1536) === '1.5 KB', 'kilobytes: '.Format::bytes(1536));
check(Format::bytes(1073741824 * 12) === '12 GB', 'gigabytes: '.Format::bytes(1073741824 * 12));
check(Format::limit(0) === '∞', 'a limit of 0 is unlimited');
check(Format::limit(4096) === '4.0 GB', 'a limit in MiB: '.Format::limit(4096));
check(Format::percent(12.6) === '13%' && Format::percent(null) === '-', 'percent');

check(Power::actionsFor('offline') === ['start'] && Power::actionsFor('running') === ['restart', 'stop'], 'power buttons follow the state');
check(Power::actionsFor('stopping') === ['kill'] && Power::actionsFor(null) === [] && Power::actionsFor('installing') === [], 'no buttons while loading or installing; kill only while stopping');

if ($failures) {
    echo "FAIL overview:\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo "PASS overview numbers\n";
