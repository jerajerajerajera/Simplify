<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Services;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Simplify\Core\Announcements\Livewire\AnnouncementBars;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Support\Rules;

/**
 * Announcements in Pelican's own bell. An item is either put there for everyone when the announcement starts
 * ("everyone"), or for one account when it unpins the bar ("unpinned", with a button to pin it again).
 */
final class Bell
{
    public const EVERYONE = 'everyone';

    public const UNPINNED = 'unpinned';

    private const KEY = 'data->viewData->simplify_announcement';

    private const KIND = 'data->viewData->simplify_kind';

    /** Puts the live announcements marked "in everyone's notifications" in the bell, once. Returns the accounts reached. */
    public static function deliverDue(): int
    {
        $reached = 0;
        $due = Announcement::query()->live()->where('in_bell', true)->whereNull('in_bell_at')->get();
        foreach ($due as $announcement) {
            // Marked first: an error halfway leaves some accounts out, but never sends twice.
            $announcement->forceFill(['in_bell_at' => now()])->save();
            self::audience($announcement)->chunkById(200, function (Collection $users) use ($announcement, &$reached): void {
                foreach ($users as $user) {
                    if ($announcement->audience === 'admins' && ! $user->isAdmin()) {
                        continue;
                    }
                    if (self::has($announcement->id, $user)) {
                        continue;
                    }
                    self::send($announcement, $user, self::EVERYONE);
                    $reached++;
                }
            });
        }

        return $reached;
    }

    public static function send(Announcement $announcement, User $user, string $kind): void
    {
        $actions = $kind === self::UNPINNED ? [
            Action::make('pin')
                ->label(__('pelican-simplify::announcements.bars.pin_again'))
                ->icon('tabler-pin')
                ->button()
                ->dispatch(AnnouncementBars::PIN_EVENT, ['id' => $announcement->id])
                ->close(),
        ] : [];

        $user->notifyNow(Notification::make()
            ->title($announcement->title)
            ->body($announcement->body)
            ->icon(Rules::icon($announcement->category))
            ->iconColor(Rules::color($announcement->category))
            ->viewData(['simplify_announcement' => $announcement->id, 'simplify_kind' => $kind])
            ->actions($actions)
            ->toDatabase());
    }

    public static function has(int $id, User $user, ?string $kind = null): bool
    {
        return $user->notifications()->where(self::KEY, $id)->when($kind, fn ($q) => $q->where(self::KIND, $kind))->exists();
    }

    /** Removes the bell items of an announcement: for one account (and kind), or for everyone. */
    public static function forget(int $id, ?User $user = null, ?string $kind = null): void
    {
        $query = $user ? $user->notifications() : DB::table('notifications');
        $query->where(self::KEY, $id)->when($kind, fn ($q) => $q->where(self::KIND, $kind))->delete();
    }

    /** @return Builder<User> */
    private static function audience(Announcement $announcement): Builder
    {
        return User::query()->when($announcement->audience === 'admins', fn (Builder $q) => $q->whereHas('roles'));
    }
}
