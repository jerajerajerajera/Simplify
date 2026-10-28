<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\App\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Renderless;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Addons\Button;
use Simplify\Core\Health\HostHealth;
use Simplify\Core\Overview\AdminMenu;
use Simplify\Core\Overview\Listing;
use Simplify\Core\Overview\Power;
use Simplify\Core\Overview\ServerQuery;
use Simplify\Core\Overview\Shell;
use Simplify\Core\Support\PanelSettings;

/**
 * All servers of the user as cards. The page is drawn in the browser (resources/js/dashboard.js) from what initial()
 * hands over; the numbers refresh through listing() while the page is open.
 */
final class Overview extends Page
{
    use Shell;

    protected static ?string $slug = 'simplify';

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-layout-grid';

    protected static ?int $navigationSort = -1;

    protected string $view = 'pelican-simplify::pages.overview';

    public static function getNavigationLabel(): string
    {
        return __('pelican-simplify::dashboard.page.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pelican-simplify::dashboard.page.title');
    }

    /** @return array<string, mixed> everything the page needs on first paint */
    public function initial(): array
    {
        $user = $this->user();
        $addons = app(AddonRegistry::class);

        return [
            ...$this->shell($user),
            'create' => [
                ...array_map(fn (Button $button) => ['label' => $button->label, 'url' => $button->url, 'primary' => $button->primary],
                    $addons->collect(fn (Addon $addon) => $addon->dashboardActions())),
                ...AdminMenu::createServer(),
            ],
            'servers' => Listing::for($user),
            'host_health' => $user->isRootAdmin() ? HostHealth::cached() : [],
            // An add-on (Simplify Hosting) can open host mode for admins of panels with more than one node, until they pick a mode.
            'host_mode_auto' => $addons->collect(fn (Addon $addon) => $addon->hostModeDefault() ? [true] : []) !== [],
            'customer' => $user->isAdmin() ? [] : $this->customerLinks($addons->collect(fn (Addon $addon) => $addon->customerLinks($user))),
        ];
    }

    /**
     * The add-ons' customer links, then the panel's support link.
     *
     * @param  list<Button>  $links
     * @return list<array{label: string, url: string}>
     */
    private function customerLinks(array $links): array
    {
        if ($links === []) {
            return [];
        }
        $support = PanelSettings::get('support_url');

        return [
            ...array_map(fn (Button $link) => ['label' => $link->label, 'url' => $link->url], $links),
            ...$support === null ? [] : [['label' => __('pelican-simplify::settings.support'), 'url' => $support]],
        ];
    }

    #[Renderless]
    public function listing(): array
    {
        return Listing::for($this->user(), badges: false);
    }

    #[Renderless]
    public function hostHealth(): array
    {
        return $this->user()->isRootAdmin() ? HostHealth::snapshot() : [];
    }

    #[Renderless]
    public function refreshHostHealth(): array
    {
        return $this->user()->isRootAdmin() ? HostHealth::refresh() : [];
    }

    #[Renderless]
    public function overviewPower(string $uuid, string $action): void
    {
        $user = $this->user();
        $server = ServerQuery::for($user)->where('servers.uuid', $uuid)->first();
        abort_unless($server !== null && in_array($action, Power::ACTIONS, true) && Power::allowed($user, $server, $action), 403);
        Power::send($server, $action);
    }
}
