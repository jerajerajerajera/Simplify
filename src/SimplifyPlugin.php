<?php

declare(strict_types=1);

namespace Simplify\Core;

use App\Enums\ConsoleWidgetPosition;
use App\Filament\Server\Pages\Console;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Simplify\Core\Filament\Admin\Pages\People;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Filament\Admin\Pages\Setup;
use Simplify\Core\Filament\App\Pages\Notifications;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Filament\Server\ServerPage;
use Simplify\Core\Filament\Server\Widgets\AddonSections;
use Simplify\Core\Http\Middleware\FramePolicy;
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
        $panel->middleware([FramePolicy::class]);

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
            // Simplify's header, server card and menu around every page of a server.
            $panel->renderHook(PanelsRenderHook::CONTENT_BEFORE, fn () => self::serverTop());
            Console::registerCustomWidgets(ConsoleWidgetPosition::BelowConsole, [AddonSections::class]);
        }
        if ($panel->getId() === 'admin') {
            $panel->pages([Settings::class, Setup::class, People::class]);
        }
    }

    public function boot(Panel $panel): void {}

    private static function serverTop(): string
    {
        $server = Filament::getTenant();
        if (! $server instanceof Server || user() === null) {
            return '';
        }

        return Blade::render('@livewire($component, $data)', [
            'component' => ServerPage::class,
            'data' => ['server' => $server, 'console' => request()->routeIs('filament.server.pages.console')],
        ]);
    }
}
