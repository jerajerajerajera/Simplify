<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Support;

/**
 * "Send: when a server starts" as plain functions, so they are tested without a panel. A server counts as started when
 * it was not running at the last look (a minute before) and runs now, or when it ran at both looks but started again
 * in between (a quick restart, K-B81). A server seen for the first time only gets remembered: Simplify cannot tell
 * whether it just started. A look without an answer from Wings changes nothing (K-B86).
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
     * A server's state is false when it is not running, the time it started when it runs (from Wings' uptime), true
     * when it runs and Wings does not say since when, or null when Wings did not answer. An unknown state keeps what
     * the last look saw: taken as "off", the answer a minute later was a start, and the announcement went out again
     * and again for a server that never stopped (K-B86).
     *
     * @param  array<int|string, bool|int>  $seen  server id => its state at the last look
     * @param  array<int, bool|int|null>  $running  server id => its state now
     * @return array{started: list<int>, seen: array<int, bool|int>}
     */
    public static function look(array $seen, array $running): array
    {
        $started = [];
        $next = [];
        foreach ($running as $id => $now) {
            $known = array_key_exists($id, $seen);
            if ($now === null) {
                if ($known) {
                    $next[$id] = $seen[$id];
                }

                continue;
            }
            $next[$id] = $now;
            $was = $known ? $seen[$id] : null;
            if ($now !== false && $known && ($was === false || (is_int($was) && is_int($now) && $now - $was > self::SAME_START))) {
                $started[] = $id;
            }
        }

        return ['started' => $started, 'seen' => $next];
    }

    /**
     * Whether a server runs, from the state Pelican reads from Wings; null for "missing", which Pelican gives when
     * Wings did not answer within a second, when the node is in maintenance and when Wings does not have the server.
     */
    public static function runs(string $status): ?bool
    {
        return $status === 'missing' ? null : $status === 'running';
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
