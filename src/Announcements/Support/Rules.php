<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Support;

/**
 * The rules of an announcement as plain functions, so they are tested without a panel.
 */
final class Rules
{
    public const CATEGORIES = ['info', 'warning', 'success'];

    /** "server": the owner and the people with access to the server its moment is about. */
    public const AUDIENCES = ['everyone', 'admins', 'server'];

    public const SERVER_AUDIENCE = 'server';

    /** How long the bar stays: for a while, until a date (one at a time only), or until it is ended. */
    public const STAYS = ['for', 'until', 'ended'];

    public const UNITS = ['minutes' => 1, 'hours' => 60, 'days' => 1440];

    /** The longest "for a while": 30 days. */
    public const STAY_MINUTES = 43200;

    public const DEFAULT_STAY_MINUTES = 180;

    public const TITLE_LENGTH = 120;

    public const BODY_LENGTH = 1000;

    public const LABEL_LENGTH = 40;

    public const URL_LENGTH = 2048;

    /** A link that starts with https:// or with / for a page of this panel. */
    public const URL_PATTERN = '~^(https://[^\s/]+(/\S*)?|/(?!/)\S*)$~';

    /** Filament color and Tabler icon per category. */
    private const LOOKS = [
        'info' => ['info', 'tabler-info-circle'],
        'warning' => ['warning', 'tabler-alert-triangle'],
        'success' => ['success', 'tabler-circle-check'],
    ];

    /** Live from its start (or right away) until its end (or until it is ended). Times are Unix seconds. */
    public static function isLive(?int $startsAt, ?int $endsAt, int $now): bool
    {
        return ($startsAt === null || $startsAt <= $now) && ($endsAt === null || $endsAt > $now);
    }

    /** "scheduled", "live" or "ended". */
    public static function state(?int $startsAt, ?int $endsAt, int $now): string
    {
        if ($endsAt !== null && $endsAt <= $now) {
            return 'ended';
        }

        return $startsAt !== null && $startsAt > $now ? 'scheduled' : 'live';
    }

    /** A form field that holds something. */
    public static function has(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }

    public static function stay(mixed $stay): string
    {
        return in_array($stay, self::STAYS, true) ? $stay : 'ended';
    }

    /** Minutes from an amount and a unit ("3", "hours"), between one minute and 30 days. */
    public static function minutes(mixed $amount, mixed $unit = 'minutes'): int
    {
        $minutes = is_numeric($amount) ? (int) round((float) $amount * (self::UNITS[$unit] ?? 1)) : self::DEFAULT_STAY_MINUTES;

        return max(1, min(self::STAY_MINUTES, $minutes));
    }

    /**
     * Minutes as the largest unit that fits whole: 180 is 3 hours, 90 stays 90 minutes.
     *
     * @return array{0: int, 1: string}
     */
    public static function amount(mixed $minutes): array
    {
        $minutes = self::minutes($minutes);
        foreach (['days', 'hours'] as $unit) {
            if ($minutes % self::UNITS[$unit] === 0) {
                return [intdiv($minutes, self::UNITS[$unit]), $unit];
            }
        }

        return [$minutes, 'minutes'];
    }

    /**
     * Until when the bar of a turn stays that went out at $from: for its minutes, otherwise until the announcement
     * ends (or is ended). Never past the announcement's own end. Times are Unix seconds.
     */
    public static function windowEnd(int $from, mixed $stay, mixed $minutes, ?int $endsAt): int
    {
        $end = self::stay($stay) === 'for' ? $from + self::minutes($minutes) * 60 : $from + self::STAY_MINUTES * 60 * 120;

        return $endsAt !== null && $endsAt > $from ? min($end, $endsAt) : $end;
    }

    public static function isLink(string $url): bool
    {
        return strlen($url) <= self::URL_LENGTH && preg_match(self::URL_PATTERN, $url) === 1;
    }

    public static function color(string $category): string
    {
        return (self::LOOKS[$category] ?? self::LOOKS['info'])[0];
    }

    public static function icon(string $category): string
    {
        return (self::LOOKS[$category] ?? self::LOOKS['info'])[1];
    }
}
