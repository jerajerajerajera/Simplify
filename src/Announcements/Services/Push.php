<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Services;

use App\Models\User;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Support\Diagnose;
use Simplify\Core\Announcements\Support\Weekly;
use Simplify\Core\Push\Push as AppPush;

/** App notifications for announcements, through Simplify's push. Hidden while a root admin turned push off. */
final class Push
{
    /** Devices that come online later, or turn on app notifications later, still get it this long after it starts. */
    public const CATCH_UP_SECONDS = 86400;

    public static function available(): bool
    {
        return AppPush::enabled();
    }

    /**
     * Sends the live announcements every minute during their first day. Each one goes once per device (by its tag),
     * so a device that missed it gets it on a later run. Returns the devices reached this time.
     */
    public static function sendDue(): int
    {
        if (! self::available()) {
            return 0;
        }

        $reached = 0;
        $since = now()->subSeconds(self::CATCH_UP_SECONDS);
        $due = Announcement::query()
            ->live()
            ->timed()
            ->where('push', true)
            ->where(fn ($q) => $q->where('starts_at', '>=', $since)
                ->orWhere(fn ($q) => $q->whereNull('starts_at')->where('created_at', '>=', $since))
                // On days of the week: each turn is new, from when it went out.
                ->orWhere('weekly_from', '>=', $since))
            ->get();
        foreach ($due as $announcement) {
            $users = Bell::audience($announcement)->get()->filter(fn (User $user) => $announcement->audience !== 'admins' || $user->isAdmin());
            $sent = AppPush::send($users, 'messages', [
                'title' => $announcement->title,
                'body' => (string) $announcement->body,
                'url' => $announcement->button_url,
                // One tag per turn, so each week's turn reaches each device once.
                'tag' => 'simplify-announcement-'.$announcement->id.($announcement->send_on === Weekly::SEND_ON && $announcement->weekly_from ? '-'.$announcement->weekly_from->getTimestamp() : ''),
            ]);
            if ($sent > 0) {
                Diagnose::trace('push #'.$announcement->id.' reached '.$sent.' devices');
                $announcement->increment('pushed_to', $sent);
                $reached += $sent;
            }
        }

        return $reached;
    }
}
