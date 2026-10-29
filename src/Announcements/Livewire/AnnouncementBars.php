<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Services\Bell;

/**
 * The live announcements as bars at the top of every page. Each bar is pinned; unpinning it removes the bar for this
 * account and leaves it in the bell, with a button to pin it again. When it is already in the bell for everyone, that
 * item stays and no second one is added.
 */
final class AnnouncementBars extends Component
{
    public const PIN_EVENT = 'simplify-announcements-pin';

    private const LIMIT = 5;

    /** Short rows in the server card instead of bars (pages/server.blade.php). */
    public bool $compact = false;

    public function render(): View
    {
        return view('pelican-simplify::announcements.'.($this->compact ? 'notes' : 'bars'), ['announcements' => $this->announcements()]);
    }

    public function unpin(int $id): void
    {
        $user = user();
        $announcement = $user ? Announcement::barsFor($user)->find($id) : null;
        if (! $user || ! $announcement) {
            return;
        }

        $announcement->dismissedBy()->syncWithoutDetaching([$user->id => ['created_at' => now()]]);
        if (! Bell::has($announcement->id, $user, Bell::EVERYONE)) {
            Bell::forget($announcement->id, $user);
            Bell::send($announcement, $user, Bell::UNPINNED);
        }
        $this->dispatch('databaseNotificationsSent');
    }

    #[On(self::PIN_EVENT)]
    public function pin(int $id): void
    {
        $user = user();
        if (! $user) {
            return;
        }

        Announcement::query()->find($id)?->dismissedBy()->detach($user->id);
        Bell::forget($id, $user, Bell::UNPINNED);
        $this->dispatch('databaseNotificationsSent');
    }

    /** @return Collection<int, Announcement> */
    private function announcements(): Collection
    {
        $user = user();

        return $user ? Announcement::barsFor($user)->limit(self::LIMIT)->get() : collect();
    }
}
