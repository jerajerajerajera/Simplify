<?php

declare(strict_types=1);

namespace Simplify\Core;

use App\Enums\ConsoleWidgetPosition;
use App\Filament\Server\Pages\Console;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Simplify\Core\Filament\Admin\Pages\People;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Filament\Admin\Pages\Setup;
use Simplify\Core\Filament\App\Pages\Notifications;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Filament\Server\Widgets\AddonSections;
use Simplify\Core\Filament\Server\Widgets\ServerDetails;
use Simplify\Core\Support\Brand;
use Simplify\Core\Support\PanelSettings;

final class SimplifyPlugin implements Plugin
{
    public const ID = 'pelican-simplify';

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook('panels::head.end', fn () => view('pelican-simplify::head'));
        Brand::apply($panel);

        if ($panel->getId() === 'app') {
            $panel->pages([Overview::class, Notifications::class]);
        }
        if (in_array($panel->getId(), ['app', 'server'], true)) {
            $panel->userMenuItems([
                Action::make('simplify-overview')->label(fn () => __('pelican-simplify::settings.overview'))->icon('tabler-layout-grid')
                    ->url(fn () => Overview::getUrl(panel: 'app')),
                Action::make('simplify-notifications')->label(fn () => __('pelican-simplify::push.menu'))->icon('tabler-bell')
                    ->url(fn () => Notifications::getUrl(panel: 'app'))->visible(fn () => Notifications::canAccess()),
                Action::make('simplify-support')->label(fn () => __('pelican-simplify::settings.support'))->icon('tabler-lifebuoy')
                    ->url(fn () => PanelSettings::get('support_url'), shouldOpenInNewTab: true)
                    ->visible(fn () => PanelSettings::get('support_url') !== null),
            ]);
            if (PanelSettings::get('start_page') === 'overview') {
                $panel->homeUrl(fn () => Overview::getUrl(panel: 'app'));
            }
        }
        if ($panel->getId() === 'server') {
            Console::registerCustomWidgets(ConsoleWidgetPosition::Top, [ServerDetails::class]);
            Console::registerCustomWidgets(ConsoleWidgetPosition::BelowConsole, [AddonSections::class]);
        }
        if ($panel->getId() === 'admin') {
            $panel->pages([Settings::class, Setup::class, People::class]);
        }
    }

    public function boot(Panel $panel): void {}
}
