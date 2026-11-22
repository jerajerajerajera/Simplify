<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Services;

use Illuminate\Support\Carbon;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Support\Diagnose;
use Simplify\Core\Announcements\Support\Rules;
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
            // "Only the next time" went out: it waits for its bar to end, or to be ended.
            if ($announcement->start_once && $announcement->weekly_from !== null) {
                continue;
            }
            $ends = $announcement->ends_at?->getTimestamp();
            $turn = Weekly::last(
                Weekly::days($announcement->weekly_days),
                Weekly::times($announcement->weekly_times, $announcement->weekly_at),
                Weekly::zone($announcement->weekly_zone),
                $now,
                $announcement->starts_at?->getTimestamp(),
                $announcement->ends_at?->getTimestamp(),
            );
            if ($turn === null || $announcement->weekly_from?->getTimestamp() === $turn) {
                continue;
            }
            $until = Rules::windowEnd($turn, $announcement->stay, $announcement->stay_minutes, $ends);
            // A turn missed by more than a day does not go out late: one made on Tuesday for every Monday waits for
            // Monday, also when its bar stays until it is ended.
            if ($now >= $until || $now - $turn > 86400) {
                continue;
            }
            Bell::forget($announcement->id);
            $announcement->dismissedBy()->detach();
            $announcement->forceFill([
                'weekly_from' => Carbon::createFromTimestamp($turn),
                'weekly_until' => Carbon::createFromTimestamp($until),
                'in_bell_at' => null,
                'in_game_at' => null,
                'in_game_result' => null,
                // Devices reached counts this turn's devices again (K-B87).
                'pushed_to' => 0,
            ])->saveQuietly();
            // Only the next time: it ends with the bar of this turn.
            if ($announcement->start_once && $announcement->stay === 'for') {
                $announcement->forceFill(['ends_at' => Carbon::createFromTimestamp($until)])->saveQuietly();
            }
            Diagnose::trace('weekly #'.$announcement->id.' turn '.gmdate('Y-m-d H:i', (int) $turn).' UTC until '.gmdate('Y-m-d H:i', $until));
            $started++;
        }

        return $started;
    }
}
