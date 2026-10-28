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

        return [
            ...$this->shell($user),
            'create' => [
                ...array_map(fn (Button $button) => ['label' => $button->label, 'url' => $button->url, 'primary' => $button->primary],
                    app(AddonRegistry::class)->collect(fn (Addon $addon) => $addon->dashboardActions())),
                ...AdminMenu::createServer(),
            ],
            'servers' => Listing::for($user),
            'host_health' => $user->isRootAdmin() ? HostHealth::cached() : [],
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
