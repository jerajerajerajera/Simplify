<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Health\HostHealth;
use Simplify\Core\Overview\Shell;
use Simplify\Core\Support\Legacy;

/**
 * Simplify's header over Pelican's admin pages (the Admin page, V52) and over the account pages, so the panel name, the
 * bell and the account menu stay where they are on every page. The legacy panel (Legacy) shows Pelican's own pages.
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
        // The same header as on the overview (Jeroen 3-11): Healthy and Create server stay, and Healthy opens the Health
        // page. On the Admin page the shield shows where you are and brings you back to the overview.
        $user = $this->user();
        $shell = $this->shell($user);

        return [
            ...$shell,
            'admin_here' => Filament::getCurrentPanel()?->getId() === 'admin',
            'admin_page' => true,
            'host_health' => $user->isRootAdmin() ? HostHealth::cached() : [],
            'health_url' => $user->isAdmin() ? AdminSide::page($shell['admin_tree'], 'health') : null,
        ];
    }

    /**
     * Whether this page is drawn inside Simplify: an admin page for an admin, or a page of the account panel other than
     * the overview (which is Simplify itself); never in the legacy panel or in a Simplify popup's frame.
     */
    public static function here(): bool
    {
        $user = user();
        if ($user === null || Legacy::on() || request()->query('simplify_embed') !== null) {
            return false;
        }

        return match (Filament::getCurrentPanel()?->getId()) {
            'admin' => $user->isAdmin(),
            'app' => ! request()->routeIs(Overview::getRouteName(Filament::getPanel('app'))),
            default => false,
        };
    }

    /** The menu beside the page: on the admin pages only. */
    public static function menu(): bool
    {
        return self::here() && Filament::getCurrentPanel()?->getId() === 'admin';
    }
}
