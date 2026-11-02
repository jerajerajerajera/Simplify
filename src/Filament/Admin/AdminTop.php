<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Overview\Shell;

/**
 * Simplify's header over Pelican's admin pages while an add-on's own host mode is on (Addon::startUrl(), API 5), so
 * the panel name, the bell and the account menu stay where they are on every Simplify page.
 */
final class AdminTop extends Component
{
    use Shell;

    public function render(): View
    {
        return view('simplify::pages.admin-top');
    }

    /** @return array<string, mixed> */
    public function initial(): array
    {
        return $this->shell($this->user());
    }

    /** Whether the header belongs on this request: an admin with an add-on start page. */
    public static function wanted(?User $user): bool
    {
        return $user !== null && $user->isAdmin() && self::firstUrl(fn (Addon $addon) => $addon->startUrl($user)) !== null;
    }
}
