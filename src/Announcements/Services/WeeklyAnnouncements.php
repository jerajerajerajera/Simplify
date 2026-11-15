<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Services;

use Illuminate\Support\Carbon;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Support\Diagnose;
use Simplify\Core\Announcements\Support\Weekly;

/**
 * Starts the next turn of the announcements "on days of the week". A new turn takes last week's bell items away and
 * shows the bar again, also to accounts that unpinned it; Bell, Push and InGame then send it like one at a time.
 */
final class WeeklyAnnouncements
{
    /** Returns the announcements that started a turn. */
    public static function runDue(): int
    {
        $started = 0;
        $now = time();
        foreach (Announcement::query()->live()->where('send_on', Weekly::SEND_ON)->get() as $announcement) {
            $hours = Weekly::hours($announcement->weekly_hours);
            $turn = Weekly::last(
                Weekly::days($announcement->weekly_days),
                (string) $announcement->weekly_at,
                Weekly::zone($announcement->weekly_zone),
                $now,
                $announcement->starts_at?->getTimestamp(),
                $announcement->ends_at?->getTimestamp(),
            );
            if (! Weekly::showing($turn, $hours, $now) || $announcement->weekly_from?->getTimestamp() === $turn) {
                continue;
            }
            Bell::forget($announcement->id);
            $announcement->dismissedBy()->detach();
            $announcement->forceFill([
                'weekly_from' => Carbon::createFromTimestamp($turn),
                'weekly_until' => Carbon::createFromTimestamp($turn + $hours * 3600),
                'in_bell_at' => null,
                'in_game_at' => null,
                'in_game_result' => null,
            ])->saveQuietly();
            Diagnose::trace('weekly #'.$announcement->id.' turn '.gmdate('Y-m-d H:i', (int) $turn).' UTC for '.$hours.'h');
            $started++;
        }

        return $started;
    }
}
