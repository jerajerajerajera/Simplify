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
use Simplify\Core\Overview\Listing;

/**
 * The Admin page's menu (V52): beside every admin page, the admin pages as a tree, or the servers per node, with host
 * health at the bottom. It stays in place while its links load the page next to it (wire:navigate); on a phone it is a
 * page of its own that the bottom bar's Admin opens. Every admin has it; add-ons put their pages in it through Pelican's admin menu (AdminTree).
 */
final class AdminSide extends Component
{
    public string $tab = 'admin';

    public function render(): View
    {
        $user = user();
        abort_unless($user !== null && $user->isAdmin(), 403);
        $sections = AdminTree::build();
        $nodes = [];
        if ($this->tab === 'servers') {
            foreach (Listing::for($user, false) as $server) {
                $nodes[$server['node_id']] ??= ['name' => $server['node_name'], 'servers' => []];
                $nodes[$server['node_id']]['servers'][] = [
                    'uuid' => $server['uuid'],
                    'name' => $server['name'],
                    'status' => $server['status'],
                    'url' => $server['url'],
                    'line' => self::line((string) $server['software'], (string) $server['reported_version']),
                    'players' => $server['players'] === null ? '—' : (int) $server['players'].' / '.(int) $server['max_players'],
                ];
            }
        }

        return view('simplify::pages.admin-side', [
            'sections' => array_values(array_filter($sections, fn (array $section) => $section['key'] !== 'health')),
            'healthUrl' => self::page($sections, 'health'),
            'nodes' => array_values($nodes),
            'serverCount' => $user->accessibleServers()->count(),
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

    /** The game and its version under a server's name, as on the server cards. */
    private static function line(string $software, string $version): string
    {
        $software = trim($software);
        $version = trim($version);
        if ($version !== '' && ! str_contains(strtolower($version), strtolower($software))) {
            return $software !== '' ? $software.' · '.$version : $version;
        }

        return $version ?: ($software ?: '—');
    }
}
