<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Services;

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Simplify\Core\Announcements\Models\Announcement;

/**
 * App notifications through Simplify's push (see Simplify's ADDONS.md). Without Simplify, or with its push turned off,
 * nobody listens to the event and the option is hidden.
 */
final class Push
{
    public const EVENT = 'simplify.push';

    /** Devices that come online later, or turn on app notifications later, still get it this long after it starts. */
    public const CATCH_UP_SECONDS = 86400;

    public static function available(): bool
    {
        return Event::hasListeners(self::EVENT);
    }

    /**
     * Offers the live announcements to Simplify every minute during their first day. Simplify sends each one once per
     * device (by its tag), so a device that missed it gets it on a later run. Returns the devices reached this time.
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
            ->where('push', true)
            ->where(fn ($q) => $q->where('starts_at', '>=', $since)->orWhere(fn ($q) => $q->whereNull('starts_at')->where('created_at', '>=', $since)))
            ->get();
        foreach ($due as $announcement) {
            $sent = (int) array_sum(array_map('intval', event(self::EVENT, [self::payload($announcement)])));
            if ($sent > 0) {
                $announcement->increment('pushed_to', $sent);
                $reached += $sent;
            }
        }

        return $reached;
    }

    /** @return array{users: list<int>, title: string, body: string, url: ?string, tag: string} */
    public static function payload(Announcement $announcement): array
    {
        $users = User::query()
            ->when($announcement->audience === 'admins', fn ($q) => $q->whereHas('roles'))
            ->get()
            ->filter(fn (User $user) => $announcement->audience !== 'admins' || $user->isAdmin())
            ->map(fn (User $user) => (int) $user->getKey())
            ->values()
            ->all();

        return [
            'users' => $users,
            'title' => $announcement->title,
            'body' => (string) $announcement->body,
            'url' => $announcement->button_url,
            'tag' => 'simplify-announcement-'.$announcement->id,
        ];
    }
}
