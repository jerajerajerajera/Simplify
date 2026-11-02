<?php

declare(strict_types=1);

namespace Simplify\Core\Hub;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Reads the Pelican Hub's public JSON API. Browse Hub only reads: it never downloads or installs anything.
 */
final class HubApi
{
    public const HUB = 'https://hub.pelican.dev';

    private const AGENT = 'Simplify (+https://github.com/jerajerajerajera/simplify)';

    /** @return array<mixed> */
    public static function get(string $path): array
    {
        return (array) Http::timeout(10)->connectTimeout(4)->acceptJson()->withHeaders(['User-Agent' => self::AGENT])
            ->get(self::HUB.$path)->throw()->json();
    }

    /**
     * Several paths at once, so the themes' screenshots cost one round trip. A path that fails is left out.
     *
     * @param  list<string>  $paths
     * @return array<string, array<mixed>> path => answer
     */
    public static function many(array $paths): array
    {
        $responses = Http::pool(fn (Pool $pool) => array_map(fn (string $path) => $pool->as($path)
            ->timeout(10)->connectTimeout(4)->acceptJson()->withHeaders(['User-Agent' => self::AGENT])
            ->get(self::HUB.$path), $paths), concurrency: 8);
        $answers = [];
        foreach ($responses as $path => $response) {
            if ($response instanceof Response && $response->successful()) {
                $answers[$path] = (array) $response->json();
            }
        }

        return $answers;
    }
}
