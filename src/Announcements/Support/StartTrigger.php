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

    /** What a "When a server…" announcement waits for: it starts, it stops, or it crashes. */
    public const EVENTS = ['start', 'stop', 'crash'];

    /**
     * A stop counts as a crash when nobody asked for it. Wings tells Pelican who pressed Stop about once a minute, so
     * the answer waits this many seconds after the stop was seen.
     */
    public const CRASH_WAIT = 75;

    /** A stop is asked for when Pelican logged a Stop, Kill or Restart, or the stop command, this long before it. */
    public const CRASH_LOOK_BACK = 600;

    private const ASKED = ['server:power.stop', 'server:power.kill', 'server:power.restart'];

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
     * @return array{started: list<int>, stopped: list<int>, seen: array<int, bool|int>}
     */
    public static function look(array $seen, array $running): array
    {
        $started = [];
        $stopped = [];
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
            $restarted = is_int($was) && is_int($now) && $now - $was > self::SAME_START;
            if ($now !== false && $known && ($was === false || $restarted)) {
                $started[] = $id;
            }
            // It ran at the last look and does not now, or it started again in between: it stopped.
            if ($known && $was !== false && ($now === false || $restarted)) {
                $stopped[] = $id;
            }
        }

        return ['started' => $started, 'stopped' => $stopped, 'seen' => $next];
    }

    public static function event(mixed $event): string
    {
        return in_array($event, self::EVENTS, true) ? $event : self::START;
    }

    /**
     * The stops that waited long enough to say whether someone asked for them, and the ones that still wait.
     *
     * @param  array<int|string, mixed>  $pending  server id => when its stop was seen
     * @return array{due: array<int, int>, pending: array<int, int>}
     */
    public static function due(array $pending, int $now): array
    {
        $due = [];
        $wait = [];
        foreach ($pending as $id => $seenAt) {
            if (! is_numeric($id) || ! is_int($seenAt)) {
                continue;
            }
            $now - $seenAt >= self::CRASH_WAIT ? $due[(int) $id] = $seenAt : $wait[(int) $id] = $seenAt;
        }

        return ['due' => $due, 'pending' => $wait];
    }

    /**
     * From when the activity of a server counts for a stop seen at $seenAt: the ten minutes before it, but never
     * from before the server last started. A Stop or Restart from before that start was for an earlier stop, and hid
     * a crash that came within ten minutes of it.
     *
     * @param  mixed  $since  what the last look held for the server: since when it ran, if Wings said
     */
    public static function lookBack(int $seenAt, mixed $since): int
    {
        $from = $seenAt - self::CRASH_LOOK_BACK;

        return is_int($since) && $since > $from && $since <= $seenAt ? $since : $from;
    }

    /**
     * Whether someone asked for a stop: Pelican's activity of the server holds a Stop, Kill or Restart, or its stop
     * command typed in the console. A stop from inside the game is not known to Pelican and counts as a crash.
     *
     * @param  iterable<array{event: string, command?: mixed}>  $activity
     */
    public static function asked(iterable $activity, ?string $stopCommand): bool
    {
        $stop = self::command($stopCommand);
        foreach ($activity as $entry) {
            if (in_array($entry['event'], self::ASKED, true)) {
                return true;
            }
            if ($entry['event'] === 'server:console.command' && $stop !== '' && self::command($entry['command'] ?? null) === $stop) {
                return true;
            }
        }

        return false;
    }

    private static function command(mixed $command): string
    {
        return is_string($command) ? strtolower(ltrim(trim($command), '/')) : '';
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
