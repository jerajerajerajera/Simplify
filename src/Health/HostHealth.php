<?php

declare(strict_types=1);

namespace Simplify\Core\Health;

use App\Models\Node;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Simplify\Core\Overview\NodeUsage;
use Spatie\Health\ResultStores\ResultStore;
use Throwable;

/**
 * How the machines behind the panel are doing: CPU, memory, disk and network of every node, from Wings. One snapshot
 * is shared by every viewer for half a minute, so an open overview never adds load to Wings.
 */
final class HostHealth
{
    private const CACHE_KEY = 'simplify.host-health';

    private const CACHE_SECONDS = 30;

    /** A refresh click drops the snapshot only when it is older than this, so clicks cannot flood Wings. */
    private const REFRESH_GAP = 5;

    /** @return array<string, mixed> labels and problems in the viewer's language */
    public static function snapshot(): array
    {
        return self::present(Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => self::collect()));
    }

    /**
     * The latest stored results of Pelican's health checks, for the marks in the menus. Reads only, never runs a check.
     *
     * @return list<array{label: string, status: string, message: string}>
     */
    public static function checks(): array
    {
        try {
            $results = app(ResultStore::class)->latestResults();
        } catch (Throwable) {
            return [];
        }

        return $results?->storedCheckResults->map(fn ($result) => [
            'label' => (string) $result->label,
            'status' => strtolower((string) $result->status),
            'message' => trim((string) ($result->notificationMessage ?: $result->shortSummary)),
        ])->values()->all() ?? [];
    }

    /** @return array<string, mixed> the shared snapshot when one is kept, else nothing: the page asks for it after first paint */
    public static function cached(): array
    {
        $stored = Cache::get(self::CACHE_KEY);

        return is_array($stored) ? self::present($stored) : [];
    }

    /** @return array<string, mixed> */
    public static function refresh(): array
    {
        $stored = Cache::get(self::CACHE_KEY);
        if (is_array($stored) && time() - (int) ($stored['checked_at'] ?? 0) >= self::REFRESH_GAP) {
            Cache::forget(self::CACHE_KEY);
        }

        return self::snapshot();
    }

    /** @return array<string, mixed> */
    private static function collect(): array
    {
        $samples = [];
        $nodes = [];
        $traffic = ['rx_bytes' => 0.0, 'tx_bytes' => 0.0, 'rx_per_second' => 0.0, 'tx_per_second' => 0.0];
        foreach (Node::query()->orderBy('name')->get() as $node) {
            try {
                $maintenance = (bool) $node->isUnderMaintenance();
            } catch (Throwable) {
                $maintenance = false;
            }
            [$stats, $problem] = self::statistics($node);
            $sample = ['maintenance' => $maintenance, 'stats' => $stats];
            $samples[] = $sample;
            $network = NodeUsage::traffic($node);
            foreach ($traffic as $key => $sum) {
                $traffic[$key] = $sum === null || ($network[$key] ?? null) === null ? null : $sum + $network[$key];
            }
            $health = self::summarize([$sample]);
            $health['metric_states']['network'] = $network === null ? 'unavailable' : 'healthy';
            // Names and numbers only, never addresses or daemon credentials.
            $nodes[] = ['id' => $node->id, 'name' => (string) $node->name, ...$health, ...self::network($network), 'problem' => $problem];
        }
        $result = self::summarize($samples);
        $failing = array_values(array_filter($nodes, fn (array $node) => $node['problem'] !== null));
        $result['problem'] = match (count($failing)) {
            0 => null,
            1 => count($nodes) > 1 ? ['node_problem', ['node' => $failing[0]['name'], 'problem' => $failing[0]['problem']]] : $failing[0]['problem'],
            default => ['nodes_silent', ['count' => count($failing)]],
        };
        $result['metric_states']['network'] = $nodes !== [] && $traffic['rx_bytes'] !== null ? 'healthy' : 'unavailable';

        return [...$result, ...self::network($nodes === [] ? null : $traffic), 'nodes' => $nodes, 'checked_at' => time()];
    }

    /**
     * @param  array<string, ?float>|null  $traffic
     * @return array<string, ?float>
     */
    private static function network(?array $traffic): array
    {
        $out = [];
        foreach (['rx_bytes', 'tx_bytes', 'rx_per_second', 'tx_per_second'] as $key) {
            $out['network_'.$key] = $traffic[$key] ?? null;
        }

        return $out;
    }

    /**
     * Wings' own numbers for the machine, with a short reason when they do not come. Pelican's Node::statistics()
     * gives up after one second, which a node behind a tunnel misses now and then.
     *
     * @return array{0: array<string, mixed>, 1: ?array{0: string, 1: array<string, int>}}
     */
    private static function statistics(Node $node): array
    {
        try {
            $response = Http::daemon($node)->connectTimeout(3)->timeout(5)->get('/api/system/utilization');
        } catch (ConnectionException $e) {
            return [[], [str_contains(strtolower($e->getMessage()), 'timed out') ? 'timeout' : 'connect', []]];
        } catch (Throwable) {
            return [[], ['connect', []]];
        }
        if (! $response->successful()) {
            return [[], self::httpProblem($response->status())];
        }
        $data = $response->json();

        return is_array($data) && ! empty($data['memory_total']) ? [$data, null] : [[], ['no_stats', []]];
    }

    /** @return array{0: string, 1: array<string, int>} a translation key under dashboard.health.problems and its values */
    public static function httpProblem(int $status): array
    {
        return match (true) {
            $status === 401 || $status === 403 => ['refused', ['status' => $status]],
            $status >= 500 => ['unreachable', ['status' => $status]],
            default => ['http', ['status' => $status]],
        };
    }

    /**
     * The shared snapshot with its labels and problems in the viewer's language.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function present(array $result): array
    {
        $say = static function (mixed $problem) use (&$say): ?string {
            if (! is_array($problem)) {
                return null;
            }
            $values = $problem[1] ?? [];
            if (isset($values['problem'])) {
                $values['problem'] = $say($values['problem']);
            }

            return $problem[0] === 'nodes_silent'
                ? trans_choice('pelican-simplify::dashboard.health.problems.nodes_silent', (int) $values['count'])
                : __('pelican-simplify::dashboard.health.problems.'.$problem[0], $values);
        };
        $result['label'] = __('pelican-simplify::dashboard.health.labels.'.$result['label_key']);
        $result['problem'] = $say($result['problem'] ?? null);
        foreach ($result['nodes'] ?? [] as $index => $node) {
            $result['nodes'][$index]['label'] = __('pelican-simplify::dashboard.health.labels.'.$node['label_key']);
            $result['nodes'][$index]['problem'] = $say($node['problem'] ?? null);
        }

        return $result;
    }

    /**
     * Adds up the nodes. Anything over 85% is a warning, over 95% critical.
     *
     * @param  list<array{maintenance: bool, stats: array<string, mixed>}>  $samples
     * @return array<string, mixed>
     */
    public static function summarize(array $samples): array
    {
        $number = static fn (mixed $value): bool => is_numeric($value) && is_finite((float) $value) && (float) $value >= 0;
        $result = [
            'hosts' => count($samples), 'available' => 0, 'state' => 'unavailable', 'label_key' => 'unavailable',
            'cpu_percent' => null, 'memory_used' => null, 'memory_total' => null, 'disk_used' => null, 'disk_total' => null,
            'metric_states' => ['cpu' => 'unavailable', 'memory' => 'unavailable', 'disk' => 'unavailable'], 'problem' => null,
        ];
        $levels = ['cpu' => 0, 'memory' => 0, 'disk' => 0];
        $sum = ['cpu' => 0.0, 'memory_used' => 0.0, 'memory_total' => 0.0, 'disk_used' => 0.0, 'disk_total' => 0.0];
        $maintenance = false;
        foreach ($samples as $sample) {
            $s = $sample['stats'];
            $maintenance = $maintenance || $sample['maintenance'];
            if (! $number($s['cpu_percent'] ?? null) || ! $number($s['memory_used'] ?? null) || ! $number($s['disk_used'] ?? null)
                || ! $number($s['memory_total'] ?? null) || (float) $s['memory_total'] <= 0 || ! $number($s['disk_total'] ?? null) || (float) $s['disk_total'] <= 0) {
                continue;
            }
            $result['available']++;
            $values = ['cpu' => (float) $s['cpu_percent'], 'memory' => $s['memory_used'] / $s['memory_total'] * 100, 'disk' => $s['disk_used'] / $s['disk_total'] * 100];
            foreach ($values as $key => $value) {
                $levels[$key] = max($levels[$key], $value >= 95 ? 2 : ($value >= 85 ? 1 : 0));
            }
            $sum['cpu'] += $values['cpu'];
            foreach (['memory_used', 'memory_total', 'disk_used', 'disk_total'] as $key) {
                $sum[$key] += (float) $s[$key];
            }
        }
        if ($result['available'] === 0) {
            return $result;
        }
        $result = array_replace($result, ['cpu_percent' => $sum['cpu'] / $result['available'], 'memory_used' => $sum['memory_used'],
            'memory_total' => $sum['memory_total'], 'disk_used' => $sum['disk_used'], 'disk_total' => $sum['disk_total']]);
        $partly = $result['available'] < $result['hosts'];
        foreach ($levels as $key => $level) {
            // While a node is silent a calm total says nothing: the meter shows it is incomplete.
            $result['metric_states'][$key] = $partly && $level === 0 ? 'unavailable' : ['healthy', 'warning', 'critical'][$level];
        }
        $worst = max($levels);
        [$result['state'], $result['label_key']] = match (true) {
            $partly => ['unavailable', 'partly_unavailable'],
            $worst === 2 => ['critical', 'critical'],
            $maintenance => ['warning', 'maintenance'],
            $worst === 1 => ['warning', 'high'],
            default => ['healthy', 'healthy'],
        };

        return $result;
    }
}
