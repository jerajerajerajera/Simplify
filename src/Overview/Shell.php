<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Attributes\Renderless;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Filament\App\Pages\Notifications;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Health\HostHealth;
use Simplify\Core\Support\Assets;
use Simplify\Core\Support\Brand;
use Simplify\Core\Support\PanelSettings;
use Simplify\Core\Support\Preferences;

/**
 * What the overview and the server page share: the header with its menus, the notifications and the account. The
 * browser side is pelicanSimplify() in resources/js/dashboard.js.
 */
trait Shell
{
    /** @return array<string, mixed> */
    protected function shell(User $user): array
    {
        $settings = PanelSettings::all();
        $logo = Brand::logoFile($settings['brand_logo']);

        return [
            'user' => $user->username,
            'avatar' => $user->getFilamentAvatarUrl(),
            'panel_name' => trim((string) $settings['brand_name']) ?: (string) config('app.name', 'Pelican'),
            'appearance' => [
                'accent_color' => $settings['accent'],
                'server_colours' => (bool) $settings['server_colours'],
                'brand' => ['mode' => $logo ? 'logo' : 'text', 'logo_url' => $logo ? url('/simplify/brand/'.$logo) : null, 'logo_height' => $settings['logo_height']],
            ],
            'build_version' => Assets::version(),
            'favicon_url' => Assets::url('favicon.svg'),
            'list_url' => Overview::getUrl(panel: 'app'),
            'profile_url' => EditProfile::getUrl(panel: 'app'),
            'notifications_url' => Notifications::canAccess() ? Notifications::getUrl(panel: 'app') : null,
            'logout_url' => Filament::getPanel('app')->getLogoutUrl(),
            'admin_url' => $user->canAccessPanel(Filament::getPanel('admin')) ? Filament::getPanel('admin')->getUrl() : null,
            'settings_url' => Settings::canAccess() ? Settings::getUrl(panel: 'admin') : null,
            'settings_menu' => Settings::canAccess() ? Settings::menu() : [],
            'support_url' => $settings['support_url'],
            'admin_pages' => AdminMenu::items(),
            'health_checks' => $user->isAdmin() ? HostHealth::checks() : [],
            'features' => ['host_health' => $user->isRootAdmin()],
            'performance' => ['overview_seconds' => $settings['overview_seconds']],
            'overview_groups' => ServerGroups::clean(Preferences::get($user, 'overview.groups')),
            'notifications' => Bell::page($user),
        ];
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
}
