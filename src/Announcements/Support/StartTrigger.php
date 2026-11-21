<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Support;

/**
 * "Send: when a server starts" as plain functions, so they are tested without a panel. A server counts as started when
 * it was not running at the last look (a minute before) and runs now, or when it ran at both looks but started again
 * in between (a quick restart, K-B81). A server seen for the first time only gets remembered: Simplify cannot tell
 * whether it just started.
 */
final class StartTrigger
{
    public const TIME = 'time';

    public const START = 'start';

    /** At most this many servers per announcement: each one is asked about every minute. */
    public const SERVERS = 20;

    /** A start read this many seconds apart is the same start: Pelican keeps a server's numbers for 15 seconds. */
    public const SAME_START = 30;

    /**
     * A server's state is false when it is not running, the time it started when it runs (from Wings' uptime), or true
     * when it runs and Wings does not say since when.
     *
     * @param  array<int|string, bool|int>  $seen  server id => its state at the last look
     * @param  array<int, bool|int>  $running  server id => its state now
     * @return array{started: list<int>, seen: array<int, bool|int>}
     */
    public static function look(array $seen, array $running): array
    {
        $started = [];
        foreach ($running as $id => $now) {
            if ($now === false || ! array_key_exists($id, $seen)) {
                continue;
            }
            $was = $seen[$id];
            if ($was === false || (is_int($was) && is_int($now) && $now - $was > self::SAME_START)) {
                $started[] = $id;
            }
        }

        return ['started' => $started, 'seen' => $running];
    }

    /** A running server's state from its uptime in milliseconds, as Wings reports it (0 or missing: unknown). */
    public static function since(mixed $uptime, int $now): bool|int
    {
        return is_numeric($uptime) && $uptime > 0 ? $now - intdiv((int) $uptime, 1000) : true;
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
