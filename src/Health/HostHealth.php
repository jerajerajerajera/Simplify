<?php

declare(strict_types=1);

namespace Simplify\Core\Health;

use App\Models\Node;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * How the machines behind the panel are doing: CPU, memory and disk of every node, from Wings. One snapshot is shared
 * by every viewer for half a minute, so an open overview never adds load to Wings.
 */
final class HostHealth
{
    private const CACHE_KEY = 'simplify.host-health';

    private const CACHE_SECONDS = 30;

    /** A refresh click drops the snapshot only when it is older than this, so clicks cannot flood Wings. */
    private const REFRESH_GAP = 5;

    /** @return array<string, mixed> */
    public static function snapshot(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => self::collect());
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
        foreach (Node::query()->orderBy('name')->get() as $node) {
            [$stats, $problem] = self::statistics($node);
            try {
                $maintenance = (bool) $node->isUnderMaintenance();
            } catch (Throwable) {
                $maintenance = false;
            }
            // Names and numbers only, never addresses or daemon credentials.
            $samples[] = ['name' => (string) $node->name, 'maintenance' => $maintenance, 'stats' => $stats, 'problem' => $problem];
        }

        return [...self::summarize($samples), 'checked_at' => time()];
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

    /** @return array{0: string, 1: array<string, int>} a translation key under health.problem and its values */
    public static function httpProblem(int $status): array
    {
        return match (true) {
            $status === 401 || $status === 403 => ['refused', ['status' => $status]],
            $status >= 500 => ['unreachable', ['status' => $status]],
            default => ['http', ['status' => $status]],
        };
    }

    /**
     * Adds up the nodes. A node over 85% on anything is a warning, over 95% critical.
     *
     * @param  list<array{name: string, maintenance: bool, stats: array<string, mixed>, problem: ?array}>  $samples
     * @return array<string, mixed>
     */
    public static function summarize(array $samples): array
    {
        $number = static fn (mixed $value): bool => is_numeric($value) && is_finite((float) $value) && (float) $value >= 0;
        $result = [
            'hosts' => count($samples), 'available' => 0, 'state' => 'unavailable',
            'cpu' => null, 'memory_used' => null, 'memory_total' => null, 'disk_used' => null, 'disk_total' => null,
            'nodes' => [],
        ];
        $cpu = $memoryUsed = $memoryTotal = $diskUsed = $diskTotal = 0.0;
        $worst = 0;
        $maintenance = false;
        foreach ($samples as $sample) {
            $stats = $sample['stats'];
            $maintenance = $maintenance || $sample['maintenance'];
            $node = ['name' => $sample['name'], 'state' => 'unavailable', 'problem' => $sample['problem']];
            $usable = $number($stats['cpu_percent'] ?? null)
                && $number($stats['memory_used'] ?? null) && $number($stats['memory_total'] ?? null) && (float) $stats['memory_total'] > 0
                && $number($stats['disk_used'] ?? null) && $number($stats['disk_total'] ?? null) && (float) $stats['disk_total'] > 0;
            if ($usable) {
                $result['available']++;
                $cpu += (float) $stats['cpu_percent'];
                $memoryUsed += (float) $stats['memory_used'];
                $memoryTotal += (float) $stats['memory_total'];
                $diskUsed += (float) $stats['disk_used'];
                $diskTotal += (float) $stats['disk_total'];
                $highest = max(
                    (float) $stats['cpu_percent'],
                    (float) $stats['memory_used'] / (float) $stats['memory_total'] * 100,
                    (float) $stats['disk_used'] / (float) $stats['disk_total'] * 100,
                );
                $level = $highest >= 95 ? 2 : ($highest >= 85 ? 1 : 0);
                $worst = max($worst, $level);
                $node['state'] = ['healthy', 'warning', 'critical'][$level];
            } elseif ($node['problem'] === null) {
                $node['problem'] = ['no_stats', []];
            }
            $result['nodes'][] = $node;
        }
        if ($result['available'] === 0) {
            return $result;
        }
        $result = array_replace($result, [
            'cpu' => $cpu / $result['available'],
            'memory_used' => (int) $memoryUsed, 'memory_total' => (int) $memoryTotal,
            'disk_used' => (int) $diskUsed, 'disk_total' => (int) $diskTotal,
        ]);
        $result['state'] = match (true) {
            $result['available'] < $result['hosts'] => 'partly',
            $worst === 2 => 'critical',
            $maintenance => 'maintenance',
            $worst === 1 => 'warning',
            default => 'healthy',
        };

        return $result;
    }
}
