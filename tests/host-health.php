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
function node(?array $stats, bool $maintenance = false): array
{
    return ['maintenance' => $maintenance, 'stats' => $stats ?? []];
}
$calm = ['cpu_percent' => 10, 'memory_used' => 2, 'memory_total' => 8, 'disk_used' => 10, 'disk_total' => 100];

$none = HostHealth::summarize([]);
check($none['state'] === 'unavailable' && $none['hosts'] === 0 && $none['cpu_percent'] === null, 'no nodes is unavailable');

$one = HostHealth::summarize([node($calm)]);
check($one['state'] === 'healthy' && $one['available'] === 1 && $one['cpu_percent'] === 10.0 && $one['memory_total'] === 8.0, 'a calm node is healthy: '.json_encode($one));

$two = HostHealth::summarize([node($calm), node([...$calm, 'cpu_percent' => 30, 'disk_used' => 90])]);
check($two['state'] === 'warning' && $two['label_key'] === 'high' && $two['cpu_percent'] === 20.0 && $two['disk_used'] === 100.0 && $two['metric_states']['disk'] === 'warning', 'a node at 90% disk is a warning and CPU is averaged');

$full = HostHealth::summarize([node([...$calm, 'memory_used' => 8])]);
check($full['state'] === 'critical' && $full['metric_states']['memory'] === 'critical', 'a node with all memory in use is critical');

$down = HostHealth::summarize([node($calm), node(null)]);
check($down['state'] === 'unavailable' && $down['label_key'] === 'partly_unavailable' && $down['available'] === 1 && $down['metric_states']['cpu'] === 'unavailable', 'a node that does not answer makes it partly unavailable');

$broken = HostHealth::summarize([node(['cpu_percent' => 'x', 'memory_total' => 0])]);
check($broken['state'] === 'unavailable' && $broken['available'] === 0, 'broken numbers count as no numbers');

$maintenance = HostHealth::summarize([node($calm, maintenance: true)]);
check($maintenance['state'] === 'warning' && $maintenance['label_key'] === 'maintenance', 'a node in maintenance says so');

check(HostHealth::httpProblem(403) === ['refused', ['status' => 403]] && HostHealth::httpProblem(502)[0] === 'unreachable' && HostHealth::httpProblem(404)[0] === 'http', 'HTTP errors get a plain reason');

if ($failures !== []) {
    echo 'FAIL host health: '.implode('; ', $failures)."\n";
    exit(1);
}
echo "PASS host health\n";
