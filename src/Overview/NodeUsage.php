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
     * @return array<string, array{state: string, cpu: ?float, memory: ?int, disk: ?int}> by server uuid
     */
    public static function read(Collection $servers): array
    {
        $nodes = $servers->pluck('node')->filter()->unique('id')->reject(fn (Node $node) => $node->isUnderMaintenance());
        $usage = [];
        $ask = [];
        foreach ($nodes as $node) {
            $cached = Cache::get(self::key($node));
            if (is_array($cached)) {
                $usage += $cached;
            } else {
                $ask[] = $node;
            }
        }
        if ($ask === []) {
            return $usage;
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
            $numbers = $list === null ? [] : self::fromList($list);
            Cache::put(self::key($node), $numbers, $list === null ? self::UNREACHABLE : self::FRESH);
            $usage += $numbers;
        }

        return $usage;
    }

    /**
     * Wings' list of all servers on a node (GET /api/servers), as numbers per server uuid.
     *
     * @param  array<mixed>  $list
     * @return array<string, array{state: string, cpu: ?float, memory: ?int, disk: ?int}>
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
