<?php

// Host health without a panel: how node numbers add up to one state.
declare(strict_types=1);

require __DIR__.'/../src/Health/HostHealth.php';

use Simplify\Core\Health\HostHealth;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}
function node(string $name, ?array $stats, bool $maintenance = false, ?array $problem = null): array
{
    return ['name' => $name, 'maintenance' => $maintenance, 'stats' => $stats ?? [], 'problem' => $problem];
}
$calm = ['cpu_percent' => 10, 'memory_used' => 2, 'memory_total' => 8, 'disk_used' => 10, 'disk_total' => 100];

$none = HostHealth::summarize([]);
check($none['state'] === 'unavailable' && $none['hosts'] === 0 && $none['cpu'] === null, 'no nodes is unavailable');

$one = HostHealth::summarize([node('a', $calm)]);
check($one['state'] === 'healthy' && $one['available'] === 1 && $one['cpu'] === 10.0 && $one['memory_total'] === 8, 'a calm node is healthy: '.json_encode($one));

$two = HostHealth::summarize([node('a', $calm), node('b', [...$calm, 'cpu_percent' => 30, 'disk_used' => 90])]);
check($two['state'] === 'warning' && $two['cpu'] === 20.0 && $two['disk_used'] === 100 && $two['nodes'][1]['state'] === 'warning', 'a node at 90% disk is a warning and CPU is averaged');

$full = HostHealth::summarize([node('a', [...$calm, 'memory_used' => 8])]);
check($full['state'] === 'critical', 'a node with all memory in use is critical');

$down = HostHealth::summarize([node('a', $calm), node('b', null, problem: ['timeout', []])]);
check($down['state'] === 'partly' && $down['available'] === 1 && $down['nodes'][1]['problem'] === ['timeout', []], 'a node that does not answer makes it partly unavailable');

$broken = HostHealth::summarize([node('a', ['cpu_percent' => 'x', 'memory_total' => 0])]);
check($broken['state'] === 'unavailable' && $broken['nodes'][0]['problem'] === ['no_stats', []], 'broken numbers count as no numbers');

$maintenance = HostHealth::summarize([node('a', $calm, maintenance: true)]);
check($maintenance['state'] === 'maintenance', 'a node in maintenance says so');

check(HostHealth::httpProblem(403) === ['refused', ['status' => 403]] && HostHealth::httpProblem(502)[0] === 'unreachable' && HostHealth::httpProblem(404)[0] === 'http', 'HTTP errors get a plain reason');

if ($failures !== []) {
    echo 'FAIL host health: '.implode('; ', $failures)."\n";
    exit(1);
}
echo "PASS host health\n";
