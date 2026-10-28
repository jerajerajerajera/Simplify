<?php

// Overview numbers without a panel: what Wings sends and what it leaves out.
declare(strict_types=1);

require __DIR__.'/../src/Overview/Usage.php';
require __DIR__.'/../src/Overview/Power.php';
require __DIR__.'/../src/Overview/NodeUsage.php';

use Simplify\Core\Overview\NodeUsage;
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

$running = Usage::fromWings('running', ['cpu_absolute' => 12.345, 'memory_bytes' => 1073741824, 'disk_bytes' => '2048', 'network' => ['rx_bytes' => 300, 'tx_bytes' => 400]]);
check($running->toArray() === ['state' => 'running', 'cpu' => 12.3, 'memory' => 1073741824, 'disk' => 2048, 'rx' => 300, 'tx' => 400], 'a running server keeps its numbers: '.json_encode($running->toArray()));

$partial = Usage::fromWings('running', ['cpu_absolute' => -1, 'memory_bytes' => 'abc']);
check($partial->cpuPercent === null && $partial->memoryBytes === null && $partial->diskBytes === null, 'missing or broken numbers stay unknown');

$offline = Usage::fromWings('offline', ['disk_bytes' => 500]);
check($offline->cpuPercent === 0.0 && $offline->memoryBytes === 0 && $offline->diskBytes === 500, 'an offline server uses no CPU or memory but keeps its disk');

check(Usage::unknown()->state === 'missing', 'an unreachable server is "missing"');

check(Power::actionsFor('offline') === ['start'] && Power::actionsFor('running') === ['restart', 'stop'], 'power buttons follow the state');
check(Power::actionsFor('stopping') === ['kill'] && Power::actionsFor(null) === [] && Power::actionsFor('installing') === [], 'no buttons while loading or installing; kill only while stopping');

$node = NodeUsage::fromList([
    ['state' => 'running', 'utilization' => ['cpu_absolute' => 5, 'memory_bytes' => 10, 'disk_bytes' => 20], 'configuration' => ['uuid' => 'a']],
    ['state' => 'offline', 'configuration' => ['uuid' => 'b']],
    ['state' => 'running', 'configuration' => []],
    'not a server',
]);
check($node === [
    'a' => ['state' => 'running', 'cpu' => 5.0, 'memory' => 10, 'disk' => 20, 'rx' => null, 'tx' => null],
    'b' => ['state' => 'offline', 'cpu' => 0.0, 'memory' => 0, 'disk' => null, 'rx' => 0, 'tx' => 0],
], 'one node list gives numbers per server uuid: '.json_encode($node));

if ($failures) {
    echo "FAIL overview:\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo "PASS overview numbers\n";
