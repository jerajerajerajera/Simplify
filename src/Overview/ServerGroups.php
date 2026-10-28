<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

/**
 * A user's own server groups, such as "Survival" or "Friends", and the server order, saved with the account so every
 * device shows the same. A server sits in one group at most.
 *
 * @phpstan-type Group array{id: string, name: string, servers: list<string>, sort: string, color?: string}
 */
final class ServerGroups
{
    public const LIMIT = 30;

    public const NAME_LENGTH = 40;

    /** Name A to Z, oldest first, lowest port first. */
    public const ORDERS = ['name', 'created', 'port'];

    /** Inside a group: the overview's order, the user's own order, or one of the orders above. */
    public const GROUP_ORDERS = ['overview', 'manual', ...self::ORDERS];

    private const SERVERS = 500;

    /** @return array{groups: list<Group>, order: string} */
    public static function clean(mixed $saved): array
    {
        $saved = is_array($saved) ? $saved : [];

        return [
            'groups' => self::groups($saved['groups'] ?? []),
            'order' => in_array($saved['order'] ?? null, self::ORDERS, true) ? $saved['order'] : 'name',
        ];
    }

    /**
     * Keeps only what is valid: a short id, a name, servers by uuid and each once, an order, a hex colour or none.
     *
     * @return list<Group>
     */
    private static function groups(mixed $input): array
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
            $servers = [];
            foreach (is_array($group['servers'] ?? null) ? $group['servers'] : [] as $uuid) {
                if (count($seen) < self::SERVERS && is_string($uuid) && preg_match('/^[A-Za-z0-9-]{1,64}$/', $uuid) && ! isset($seen[$uuid])) {
                    $seen[$uuid] = true;
                    $servers[] = $uuid;
                }
            }
            $clean = [
                'id' => $id,
                'name' => mb_substr($name, 0, self::NAME_LENGTH) ?: 'Group',
                'servers' => $servers,
                'sort' => in_array($group['sort'] ?? null, self::GROUP_ORDERS, true) ? $group['sort'] : 'overview',
            ];
            if (is_string($group['color'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $group['color'])) {
                $clean['color'] = strtolower($group['color']);
            }
            $groups[] = $clean;
        }

        return $groups;
    }
}
