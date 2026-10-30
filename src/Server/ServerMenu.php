<?php

declare(strict_types=1);

namespace Simplify\Core\Server;

use App\Filament\Server\Pages\Mounts;
use App\Filament\Server\Pages\Settings;
use App\Filament\Server\Pages\Startup;
use App\Filament\Server\Resources\Activities\ActivityResource;
use App\Filament\Server\Resources\Allocations\AllocationResource;
use App\Filament\Server\Resources\Backups\BackupResource;
use App\Filament\Server\Resources\Databases\DatabaseResource;
use App\Filament\Server\Resources\Schedules\ScheduleResource;
use App\Filament\Server\Resources\Subusers\SubuserResource;
use App\Filament\Server\Resources\Webhooks\WebhookResource;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Throwable;

/**
 * The menu next to a server's page: the everyday pages on top, Backups and Schedules as one item with two tabs, and
 * Pelican's rarely used pages under Advanced. Pages that add-ons and other plugins add stay on top.
 */
final class ServerMenu
{
    /** Pelican's pages that go under Advanced. */
    private const ADVANCED = [
        SubuserResource::class, DatabaseResource::class, AllocationResource::class, Startup::class, Settings::class,
        Mounts::class, WebhookResource::class, ActivityResource::class,
    ];

    /** @return array{main: list<array<string, mixed>>, advanced: list<array<string, mixed>>} */
    public static function build(): array
    {
        $advancedUrls = array_flip(array_filter(array_map(self::url(...), self::ADVANCED)));
        $tabs = self::tabs();
        $admin = trans('server/console.open_in_admin');
        $main = $advanced = [];
        $combined = false;
        foreach (Filament::getCurrentPanel()?->getNavigation() ?? [] as $group) {
            foreach ($group->getItems() as $item) {
                // Pelican's "Open in Admin" leads to the legacy admin page; the server page has its own settings.
                if (! $item->isVisible() || (string) $item->getLabel() === $admin) {
                    continue;
                }
                $url = (string) $item->getUrl();
                $entry = ['label' => (string) $item->getLabel(), 'url' => $url, 'icon' => $item->getIcon(), 'active' => $item->isActive(), 'new_tab' => $item->shouldOpenUrlInNewTab()];
                if (count($tabs) > 1 && in_array($url, array_column($tabs, 'url'), true)) {
                    if (! $combined) {
                        $main[] = [...$entry, 'label' => __('simplify::dashboard.ui.backups_and_tasks'), 'url' => $tabs[0]['url'], 'active' => self::onTabs($tabs)];
                        $combined = true;
                    }

                    continue;
                }
                if (isset($advancedUrls[$url])) {
                    $advanced[] = $entry;
                } else {
                    $main[] = $entry;
                }
            }
        }
        // Pelican sorts Advanced's pages its own way; keep the order of the list above.
        usort($advanced, fn (array $a, array $b) => array_search($a['url'], array_keys($advancedUrls), true) <=> array_search($b['url'], array_keys($advancedUrls), true));

        return ['main' => $main, 'advanced' => $advanced];
    }

    /**
     * Backups and Schedules, for the tabs on their pages; one or none when the person may see only one. The open one
     * comes from the page classes Filament's render hook passes, or else from the address.
     *
     * @param  list<string>  $scopes
     * @return list<array{label: string, url: string, active: bool}>
     */
    public static function tabs(array $scopes = []): array
    {
        $resources = array_map(fn (string $page) => method_exists($page, 'getResource') ? $page::getResource() : null, $scopes);
        $tabs = [];
        foreach ([BackupResource::class, ScheduleResource::class] as $class) {
            try {
                if (class_exists($class) && $class::canAccess()) {
                    $url = $class::getUrl('index');
                    $active = $scopes !== [] ? in_array($class, $resources, true) : str_starts_with(request()->url(), $url);
                    $tabs[] = ['label' => (string) $class::getNavigationLabel(), 'url' => $url, 'active' => $active];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $tabs;
    }

    /** @param  list<array{active: bool}>  $tabs */
    public static function onTabs(array $tabs): bool
    {
        return in_array(true, array_column($tabs, 'active'), true);
    }

    /** @param  class-string  $class */
    private static function url(string $class): ?string
    {
        try {
            if (! class_exists($class)) {
                return null;
            }

            return is_subclass_of($class, Resource::class) ? $class::getUrl('index') : $class::getUrl();
        } catch (Throwable) {
            return null;
        }
    }
}
