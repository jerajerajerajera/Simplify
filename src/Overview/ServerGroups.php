<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

/**
 * A user's own server groups, such as "Survival" or "Friends". A server sits in one group at most; servers in no
 * group show under "Other servers".
 *
 * @phpstan-type Group array{id: string, name: string, color: ?string, servers: list<int>}
 */
final class ServerGroups
{
    public const LIMIT = 30;

    public const NAME_LENGTH = 40;

    /**
     * Keeps only what is valid: a short id, a name, a hex colour or none, each server once.
     *
     * @return list<Group>
     */
    public static function clean(mixed $input): array
    {
        $groups = [];
        $ids = [];
        $seen = [];
        foreach (is_array($input) ? array_values($input) : [] as $group) {
            if (count($groups) >= self::LIMIT) {
                break;
            }
            $id = is_array($group) ? ($group['id'] ?? null) : null;
            if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) || isset($ids[$id])) {
                continue;
            }
            $ids[$id] = true;

            $name = is_string($group['name'] ?? null) ? trim((string) preg_replace('/\s+/u', ' ', $group['name'])) : '';
            $color = is_string($group['color'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $group['color'])
                ? strtolower($group['color'])
                : null;

            $servers = [];
            foreach (is_array($group['servers'] ?? null) ? $group['servers'] : [] as $server) {
                if (is_numeric($server) && (int) $server > 0 && ! isset($seen[(int) $server])) {
                    $seen[(int) $server] = true;
                    $servers[] = (int) $server;
                }
            }

            $groups[] = [
                'id' => $id,
                'name' => mb_substr($name, 0, self::NAME_LENGTH) ?: 'Group',
                'color' => $color,
                'servers' => $servers,
            ];
        }

        return $groups;
    }

    /**
     * Splits the servers on screen into the user's groups, in group order, and the rest. Empty groups are left out.
     *
     * @template T of object{id: int}
     *
     * @param  list<Group>  $groups
     * @param  iterable<T>  $servers  already sorted
     * @return array{groups: list<array{group: Group, servers: list<T>}>, rest: list<T>}
     */
    public static function split(array $groups, iterable $servers): array
    {
        $groupOf = [];
        foreach ($groups as $index => $group) {
            foreach ($group['servers'] as $id) {
                $groupOf[$id] = $index;
            }
        }
        $buckets = array_fill(0, count($groups), []);
        $rest = [];
        foreach ($servers as $server) {
            if (isset($groupOf[$server->id])) {
                $buckets[$groupOf[$server->id]][] = $server;
            } else {
                $rest[] = $server;
            }
        }
        $split = [];
        foreach ($groups as $index => $group) {
            if ($buckets[$index] !== []) {
                $split[] = ['group' => $group, 'servers' => $buckets[$index]];
            }
        }

        return ['groups' => $split, 'rest' => $rest];
    }
}
