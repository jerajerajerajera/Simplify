<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

/**
 * Whether Pelican's scheduler runs: announcements, app notifications and update notices only go out through it. The
 * announcements command beats every minute; five quiet minutes means the cron job is missing or stopped.
 */
final class Scheduler
{
    private const KEY = 'simplify.scheduler-at';

    public static function beat(): void
    {
        SafeCache::put(self::KEY, time(), 7 * 86400);
    }

    public static function stalled(): bool
    {
        return (int) SafeCache::get(self::KEY, 0) < time() - 300;
    }

    /** The cron line Pelican's documentation gives, with this panel's own path. */
    public static function cron(): string
    {
        return '* * * * * php '.base_path('artisan').' schedule:run >> /dev/null 2>&1';
    }
}
