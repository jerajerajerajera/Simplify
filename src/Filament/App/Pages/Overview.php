<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\App\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Renderless;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Announcements\Support\Diagnose;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Addons\Button;
use Simplify\Core\Health\HostHealth;
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

    protected string $view = 'simplify::pages.overview';

    public static function getNavigationLabel(): string
    {
        return __('simplify::dashboard.page.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('simplify::dashboard.page.title');
    }

    /** @return array<string, mixed> everything the page needs on first paint */
    public function initial(): array
    {
        $user = $this->user();
        $addons = app(AddonRegistry::class);

        return [
            ...$this->shell($user),
            'servers' => Listing::for($user),
            'host_health' => $user->isRootAdmin() ? HostHealth::cached() : [],
            'customer' => $user->isAdmin() ? [] : $this->customerLinks($addons->collect(fn (Addon $addon) => $addon->customerLinks($user))),
        ];
    }

    /**
     * Debug build only: what the Test announcements sheet shows, plus the servers a test can use.
     *
     * @return array{checks: list<array{level: string, title: string, detail: string}>, log: string, servers: list<array{id: int, name: string}>, done?: string}
     */
    #[Renderless]
    public function testAnnouncements(?string $zone = null, ?string $action = null, ?int $server = null): array
    {
        $user = $this->user();
        abort_unless(Diagnose::BUTTON && $user->isRootAdmin(), 403);
        $done = match ($action) {
            'me' => Diagnose::sendToMe($user),
            'timed' => Diagnose::scheduleTest($user, $server ?: null),
            'start' => $server ? Diagnose::startTest($user, $server) : 'Pick a server first.',
            default => null,
        };

        return [
            ...Diagnose::run($user, $zone),
            'servers' => \App\Models\Server::query()->orderBy('name')->limit(200)->get(['id', 'name'])->map(fn ($s) => ['id' => (int) $s->id, 'name' => (string) $s->name])->all(),
            ...($done !== null ? ['done' => $done] : []),
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
            ...array_map(fn (Button $link) => $link->toArray(), $links),
            ...$support === null ? [] : [['label' => __('simplify::settings.support'), 'url' => $support]],
        ];
    }

    #[Renderless]
    public function listing(): array
    {
        return Listing::for($this->user(), badges: false);
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
