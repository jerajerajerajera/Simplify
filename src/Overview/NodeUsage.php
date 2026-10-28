<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

use App\Models\Node;
use App\Models\Server;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Live numbers for many servers at once: one request per node for all its servers, every node at the same time,
 * instead of one request per server. Shared by everyone for a few seconds, so ten open overviews cost one request.
 */
final class NodeUsage
{
    private const FRESH = 5;

    /** An unreachable node is asked again a little later, so the overview does not wait on it every refresh. */
    private const UNREACHABLE = 20;

    /**
     * @param  Collection<int, Server>  $servers
     * @return array<string, array{state: string, cpu: ?float, memory: ?int, disk: ?int, rx: ?int, tx: ?int}> by server uuid
     */
    public static function read(Collection $servers): array
    {
        $usage = [];
        foreach (self::lists($servers->pluck('node')->filter()->unique('id')) as $numbers) {
            $usage += $numbers ?: [];
        }

        return $usage;
    }

    /**
     * All traffic of a node's servers since they started, and per second since the last look; null when the node
     * does not answer.
     *
     * @return array{rx_bytes: float, tx_bytes: float, rx_per_second: float, tx_per_second: float}|null
     */
    public static function traffic(Node $node): ?array
    {
        $numbers = self::lists(collect([$node]))[$node->id] ?? false;
        if ($numbers === false) {
            return null;
        }
        $now = ['rx_bytes' => 0.0, 'tx_bytes' => 0.0, 'at' => microtime(true)];
        foreach ($numbers as $server) {
            $now['rx_bytes'] += (float) $server['rx'];
            $now['tx_bytes'] += (float) $server['tx'];
        }
        $before = Cache::get('simplify.node-traffic.'.$node->id);
        Cache::put('simplify.node-traffic.'.$node->id, $now, 300);
        $seconds = is_array($before) ? $now['at'] - $before['at'] : 0;
        // A counter that went down means a server restarted, and the first look has nothing to compare: both read 0 B/s.
        $rate = fn (string $key) => $seconds > 0 && $seconds <= 120 ? max(0.0, ($now[$key] - $before[$key]) / $seconds) : 0.0;

        return ['rx_bytes' => $now['rx_bytes'], 'tx_bytes' => $now['tx_bytes'], 'rx_per_second' => $rate('rx_bytes'), 'tx_per_second' => $rate('tx_bytes')];
    }

    /**
     * @param  Collection<int, Node>  $nodes
     * @return array<int, array<string, array<string, mixed>>|false> numbers per node id, false for a node that did not answer
     */
    private static function lists(Collection $nodes): array
    {
        $lists = [];
        $ask = [];
        foreach ($nodes->reject(fn (Node $node) => $node->isUnderMaintenance()) as $node) {
            $cached = Cache::get(self::key($node));
            if (is_array($cached) || $cached === false) {
                $lists[$node->id] = $cached;
            } else {
                $ask[] = $node;
            }
        }
        if ($ask === []) {
            return $lists;
        }
        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (Node $node) => $pool->as((string) $node->id)->acceptJson()->withToken($node->daemon_token)
                ->withOptions(['verify' => app()->environment('production')])
                ->connectTimeout(2)->timeout(4)->get(rtrim($node->getConnectionAddress(), '/').'/api/servers'),
            $ask,
        ));
        foreach ($ask as $node) {
            $response = $responses[(string) $node->id] ?? null;
            $list = $response instanceof Response && $response->successful() && is_array($response->json()) ? $response->json() : null;
            $numbers = $list === null ? false : self::fromList($list);
            Cache::put(self::key($node), $numbers, $numbers === false ? self::UNREACHABLE : self::FRESH);
            $lists[$node->id] = $numbers;
        }

        return $lists;
    }

    /**
     * Wings' list of all servers on a node (GET /api/servers), as numbers per server uuid.
     *
     * @param  array<mixed>  $list
     * @return array<string, array{state: string, cpu: ?float, memory: ?int, disk: ?int, rx: ?int, tx: ?int}>
     */
    public static function fromList(array $list): array
    {
        $usage = [];
        foreach ($list as $server) {
            $uuid = is_array($server) ? ($server['configuration']['uuid'] ?? null) : null;
            if (! is_string($uuid) || ! is_string($server['state'] ?? null)) {
                continue;
            }
            $usage[$uuid] = Usage::fromWings($server['state'], is_array($server['utilization'] ?? null) ? $server['utilization'] : [])->toArray();
        }

        return $usage;
    }

    private static function key(Node $node): string
    {
        return 'simplify.node-usage.'.$node->id;
    }
}
