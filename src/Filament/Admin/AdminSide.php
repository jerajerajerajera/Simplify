<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Health\HostHealth;
use Simplify\Core\Overview\AdminMenu;
use Simplify\Core\Overview\AdminTree;

/**
 * The Admin page's menu (V52): beside every admin page, the admin pages as a tree. It stays in place while its links
 * load the page next to it (wire:navigate); its breadcrumb is the page's title. On a phone it is a page of its own that
 * the bottom bar's Admin opens, with host health at the bottom (on a computer that is the header's Healthy button).
 * Every admin has it; add-ons put their pages in it through Pelican's admin menu (AdminTree).
 */
final class AdminSide extends Component
{
    public function render(): View
    {
        $user = user();
        abort_unless($user !== null && $user->isAdmin(), 403);
        $sections = AdminTree::build();

        return view('simplify::pages.admin-side', [
            'sections' => array_values(array_filter($sections, fn (array $section) => $section['key'] !== 'health')),
            'healthUrl' => self::page($sections, 'health'),
            'health' => $user->isRootAdmin() ? HostHealth::cached() : [],
            'checks' => HostHealth::checks(),
            'overviewUrl' => Overview::getUrl(panel: 'app'),
            // New opens Create server in Simplify's popup (AdminTop listens for simplify:create).
            'create' => AdminMenu::createServer() !== [] || app(AddonRegistry::class)->collect(fn (Addon $addon) => $addon->dashboardActions()) !== [],
        ]);
    }

    /**
     * The first page of a section, or a page itself, with this key in the admin tree.
     *
     * @param  list<array{key: string, pages: list<array<string, mixed>>}>  $sections
     */
    public static function page(array $sections, string $key): ?string
    {
        foreach ($sections as $section) {
            foreach ($section['pages'] as $page) {
                if ($section['key'] === $key || ($page['key'] ?? null) === $key) {
                    return $page['url'] ?? null;
                }
            }
        }

        return null;
    }
}
