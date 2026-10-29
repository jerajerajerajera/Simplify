<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Support;

/**
 * The rules of an announcement as plain functions, so they are tested without a panel.
 */
final class Rules
{
    public const CATEGORIES = ['info', 'warning', 'success'];

    public const AUDIENCES = ['everyone', 'admins'];

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
