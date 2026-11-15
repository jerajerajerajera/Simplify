<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Support;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * "Send: on days of the week" as plain functions, so they are tested without a panel (Khion 15-11). An announcement
 * goes out on each chosen day at one time, in the time zone of the admin who wrote it, and stays a bar for a few hours.
 * Its "Active from" and "Active until" limit the weeks it does that in.
 */
final class Weekly
{
    public const SEND_ON = 'weekly';

    /** How long the bar stays each time, in hours. */
    public const HOURS = [1, 2, 3, 6, 12, 24];

    public const DEFAULT_HOURS = 24;

    /**
     * The chosen days as ISO numbers (1 Monday … 7 Sunday), each once, in week order.
     *
     * @return list<int>
     */
    public static function days(mixed $days): array
    {
        $clean = [];
        foreach (is_array($days) ? $days : [] as $day) {
            if (is_numeric($day) && (int) $day >= 1 && (int) $day <= 7 && (float) $day === (float) (int) $day) {
                $clean[(int) $day] = (int) $day;
            }
        }
        ksort($clean);

        return array_values($clean);
    }

    /** The time of day as "HH:MM", from "H:MM", "HH:MM" or "HH:MM:SS"; null when it is not a time. */
    public static function time(mixed $at): ?string
    {
        if (! is_string($at) || preg_match('~^([01]?\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$~', trim($at), $match) !== 1) {
            return null;
        }

        return sprintf('%02d:%s', (int) $match[1], $match[2]);
    }

    public static function hours(mixed $hours): int
    {
        return is_numeric($hours) && in_array((int) $hours, self::HOURS, true) ? (int) $hours : self::DEFAULT_HOURS;
    }

    /** A time zone PHP knows, or UTC. */
    public static function zone(mixed $zone): string
    {
        if (! is_string($zone) || $zone === '') {
            return 'UTC';
        }
        try {
            return (new DateTimeZone($zone))->getName();
        } catch (Throwable) {
            return 'UTC';
        }
    }

    /**
     * The most recent time it went out, at or before now and inside its active period; null when there is none yet.
     * Times are Unix seconds.
     *
     * @param  list<int>  $days
     */
    public static function last(array $days, string $at, string $zone, int $now, ?int $from = null, ?int $until = null): ?int
    {
        return self::find($days, $at, $zone, $now, $from, $until, -1);
    }

    /**
     * The next time it goes out, after now and inside its active period; null when it never goes out again.
     *
     * @param  list<int>  $days
     */
    public static function next(array $days, string $at, string $zone, int $now, ?int $from = null, ?int $until = null): ?int
    {
        // Before its period starts, the first turn is on or after the start.
        $after = $from !== null && $from > $now ? $from - 1 : $now;
        $next = self::find($days, $at, $zone, $after, $from, $until, 1);

        return $next !== null && $next > $now ? $next : null;
    }

    /**
     * For "When a server starts": whether a start now counts on these days, in this time zone. No days: every day.
     *
     * @param  list<int>  $days
     */
    public static function onDay(array $days, string $zone, int $now): bool
    {
        $days = self::days($days);
        if ($days === []) {
            return true;
        }
        $today = (int) (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone(self::zone($zone)))->format('N');

        return in_array($today, $days, true);
    }

    /** Whether the bar of the turn that went out at $last still shows. */
    public static function showing(?int $last, int $hours, int $now): bool
    {
        return $last !== null && $last <= $now && $now < $last + self::hours($hours) * 3600;
    }

    /** @param  list<int>  $days */
    private static function find(array $days, string $at, string $zone, int $now, ?int $from, ?int $until, int $direction): ?int
    {
        $days = self::days($days);
        $time = self::time($at);
        if ($days === [] || $time === null) {
            return null;
        }
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $today = (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone(self::zone($zone)));
        // Eight days covers a full week from either side of today's time.
        for ($i = 0; $i <= 7; $i++) {
            $day = $today->modify(($direction * $i >= 0 ? '+' : '').($direction * $i).' days');
            if (! in_array((int) $day->format('N'), $days, true)) {
                continue;
            }
            $turn = $day->setTime($hour, $minute)->getTimestamp();
            $right = $direction < 0 ? $turn <= $now : $turn > $now;
            if ($right && ($from === null || $turn >= $from) && ($until === null || $turn < $until)) {
                return $turn;
            }
        }

        return null;
    }
}
