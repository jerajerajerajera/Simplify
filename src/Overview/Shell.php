<?php

declare(strict_types=1);

namespace Simplify\Core\Overview;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use InvalidArgumentException;
use Livewire\Attributes\Renderless;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Filament\App\Pages\Notifications;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Health\HostHealth;
use Simplify\Core\Push\Push;
use Simplify\Core\Support\Assets;
use Simplify\Core\Support\Brand;
use Simplify\Core\Support\PanelSettings;
use Simplify\Core\Support\Preferences;
use Simplify\Core\Support\SafeCache;

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
                'pelican_suffix' => (bool) $settings['pelican_suffix'],
                'seasonal' => (bool) $settings['seasonal'],
                'brand' => ['mode' => $logo ? 'logo' : 'text', 'logo_url' => $logo ? url('/simplify/brand/'.$logo) : null, 'logo_height' => $settings['logo_height']],
            ],
            'panel_born' => self::panelBorn(),
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

    /** When the panel was set up (its first account), for its birthday among the seasonal touches; null when unknown. */
    private static function panelBorn(): ?int
    {
        $first = (string) SafeCache::rememberForever('simplify.panel_born', fn () => (string) User::query()->min('created_at'));

        return $first !== '' ? (strtotime($first) ?: null) : null;
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
    public function notificationPage(int $page = 1, bool $archived = false): array
    {
        return Bell::page($this->user(), $page, $archived);
    }

    /** @param list<string> $ids empty for every notification */
    #[Renderless]
    public function markNotifications(array $ids, bool $read = true): array
    {
        Bell::mark($this->user(), $ids, $read);

        return Bell::page($this->user());
    }

    /** @param list<string> $ids empty for "Clear read": every read notification moves to Archived */
    #[Renderless]
    public function archiveNotifications(array $ids = []): array
    {
        Bell::archiveNotifications($this->user(), $ids);

        return Bell::page($this->user());
    }

    /**
     * What this account gets on its devices, for the window with every notification; null while app notifications
     * are off for the panel.
     *
     * @return array{key: string, current: bool, messages: bool, updates: ?bool}|null
     */
    #[Renderless]
    public function pushSettings(?string $endpoint): ?array
    {
        if (! Push::enabled()) {
            return null;
        }
        $user = $this->user();

        return [
            'key' => Push::publicKey(),
            'current' => Push::current($user, $endpoint) !== null,
            'messages' => Push::wants($user, 'messages'),
            'updates' => $user->isRootAdmin() ? Push::wants($user, 'updates') : null,
        ];
    }

    #[Renderless]
    public function pushChoose(string $kind, bool $on): void
    {
        $user = $this->user();
        abort_unless(Push::enabled() && ($kind === 'messages' || ($kind === 'updates' && $user->isRootAdmin())), 403);
        Push::choose($user, $kind, $on);
    }

    /**
     * @param  array<string, mixed>  $subscription
     * @return array{ok: bool, message: string}
     */
    #[Renderless]
    public function pushOn(array $subscription, string $label): array
    {
        abort_unless(Push::enabled(), 403);
        try {
            Push::subscribe($this->user(), $subscription, $label);
        } catch (InvalidArgumentException) {
            return ['ok' => false, 'message' => __('simplify::push.unsupported')];
        }

        return ['ok' => true, 'message' => __('simplify::push.on')];
    }

    #[Renderless]
    public function pushOff(string $endpoint): void
    {
        Push::remove($this->user(), $endpoint);
    }

    /** @return array{ok: bool, gone: bool, message: string} */
    #[Renderless]
    public function pushTest(?string $endpoint): array
    {
        $device = Push::current($this->user(), $endpoint);
        $result = $device ? Push::test($device, __('simplify::push.test_title'), __('simplify::push.test_body')) : 'gone';

        return ['ok' => $result === 'sent', 'gone' => $result === 'gone', 'message' => __('simplify::push.test_'.$result)];
    }

    private function user(): User
    {
        $user = user();
        abort_if($user === null, 403);

        return $user;
    }
}
