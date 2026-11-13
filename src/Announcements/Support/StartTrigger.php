<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Support;

/**
 * "Send: when a server starts" as plain functions, so they are tested without a panel. A server counts as started when
 * it was not running at the last look (a minute before) and runs now. A server seen for the first time only gets
 * remembered: Simplify cannot tell whether it just started.
 */
final class StartTrigger
{
    public const TIME = 'time';

    public const START = 'start';

    /** At most this many servers per announcement: each one is asked about every minute. */
    public const SERVERS = 20;

    /**
     * @param  array<int|string, bool>  $seen  server id => running at the last look
     * @param  array<int, bool>  $running  server id => running now
     * @return array{started: list<int>, seen: array<int, bool>}
     */
    public static function look(array $seen, array $running): array
    {
        $started = [];
        foreach ($running as $id => $now) {
            if ($now && array_key_exists($id, $seen) && $seen[$id] === false) {
                $started[] = $id;
            }
        }

        return ['started' => $started, 'seen' => $running];
    }

    /**
     * The servers an announcement watches: whole ids, each once, at most SERVERS.
     *
     * @return list<int>
     */
    public static function servers(mixed $ids): array
    {
        $clean = [];
        foreach (is_array($ids) ? $ids : [] as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $clean[(int) $id] = (int) $id;
            }
        }

        return array_slice(array_values($clean), 0, self::SERVERS);
    }

    /** The title in the bell and on devices: which server started, then the announcement's title. */
    public static function title(string $server, string $title): string
    {
        return $server.' · '.$title;
    }
}
