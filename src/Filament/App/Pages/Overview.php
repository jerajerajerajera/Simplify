<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\App\Pages;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Renderless;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Addons\Button;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Health\HostHealth;
use Simplify\Core\Overview\AdminMenu;
use Simplify\Core\Overview\Bell;
use Simplify\Core\Overview\Listing;
use Simplify\Core\Overview\Power;
use Simplify\Core\Overview\ServerGroups;
use Simplify\Core\Overview\ServerQuery;
use Simplify\Core\Support\Assets;
use Simplify\Core\Support\Brand;
use Simplify\Core\Support\PanelSettings;
use Simplify\Core\Support\Preferences;
use Throwable;

/**
 * All servers of the user as cards. The page is drawn in the browser (resources/js/dashboard.js) from what initial()
 * hands over; the numbers refresh through listing() while the page is open.
 */
final class Overview extends Page
{
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
        $settings = PanelSettings::all();
        $admin = $user->canAccessPanel(Filament::getPanel('admin'));
        $logo = Brand::logoFile($settings['brand_logo']);

        return [
            'user' => $user->username,
            'avatar' => $user->getFilamentAvatarUrl(),
            'panel_name' => trim((string) $settings['brand_name']) ?: (string) config('app.name', 'Pelican'),
            'appearance' => [
                'accent_color' => $settings['accent'],
                'brand' => ['mode' => $logo ? 'logo' : 'text', 'logo_url' => $logo ? url('/simplify/brand/'.$logo) : null, 'logo_height' => $settings['logo_height']],
            ],
            'build_version' => self::version(),
            'favicon_url' => Assets::url('favicon.svg'),
            'list_url' => self::getUrl(panel: 'app'),
            'profile_url' => EditProfile::getUrl(panel: 'app'),
            'notifications_url' => Notifications::canAccess() ? Notifications::getUrl(panel: 'app') : null,
            'logout_url' => Filament::getPanel('app')->getLogoutUrl(),
            'admin_url' => $admin ? Filament::getPanel('admin')->getUrl() : null,
            'settings_url' => Settings::canAccess() ? Settings::getUrl(panel: 'admin') : null,
            'support_url' => $settings['support_url'],
            'admin_pages' => AdminMenu::items(),
            'create' => array_map(fn (Button $button) => ['label' => $button->label, 'url' => $button->url, 'primary' => $button->primary],
                app(AddonRegistry::class)->collect(fn (Addon $addon) => $addon->dashboardActions())),
            'features' => ['host_health' => $user->isRootAdmin()],
            'performance' => ['overview_seconds' => $settings['overview_seconds']],
            'overview_groups' => ServerGroups::clean(Preferences::get($user, 'overview.groups')),
            'servers' => Listing::for($user),
            'host_health' => $user->isRootAdmin() ? HostHealth::cached() : [],
            'notifications' => Bell::page($user),
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

    /** Own server groups and the server order, kept with the account so every device shows the same. */
    #[Renderless]
    public function saveOverviewGroups(array $groups, ?string $order = 'name'): array
    {
        $clean = ServerGroups::clean(['groups' => $groups, 'order' => $order]);
        Preferences::set($this->user(), 'overview.groups', $clean);

        return $clean;
    }

    #[Renderless]
    public function notificationPage(int $page = 1): array
    {
        return Bell::page($this->user(), $page);
    }

    /** @param list<string> $ids empty for every notification */
    #[Renderless]
    public function markNotifications(array $ids, bool $read = true): array
    {
        Bell::mark($this->user(), $ids, $read);

        return Bell::page($this->user());
    }

    private function user(): User
    {
        $user = user();
        abort_if($user === null, 403);

        return $user;
    }

    /** The plugin's version, shown in the footer. */
    private static function version(): string
    {
        try {
            return (string) (json_decode((string) file_get_contents(dirname(__DIR__, 4).'/plugin.json'), true)['version'] ?? '');
        } catch (Throwable) {
            return '';
        }
    }
}
