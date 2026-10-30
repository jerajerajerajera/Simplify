<?php

declare(strict_types=1);

namespace Simplify\Core;

use App\Models\Server;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Js;
use Simplify\Core\Announcements\Filament\AnnouncementResource;
use Simplify\Core\Embed\Filament\EmbedSettings;
use Simplify\Core\Embed\Filament\Website;
use Simplify\Core\Filament\Admin\Pages\People;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Filament\Admin\Pages\Setup;
use Simplify\Core\Filament\Admin\Pages\SetupDone;
use Simplify\Core\Filament\App\Pages\Notifications;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Filament\Server\ServerPage;
use Simplify\Core\Http\Middleware\FramePolicy;
use Simplify\Core\Http\Middleware\StartPage;
use Simplify\Core\Subdomains\Filament\AutomaticSubdomains;
use Simplify\Core\Subdomains\Filament\ServerSubdomain;
use Simplify\Core\Support\Brand;
use Simplify\Core\Support\PanelSettings;

final class SimplifyPlugin implements Plugin
{
    public const ID = 'simplify';

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook('panels::head.end', fn () => view('simplify::head'));
        Brand::apply($panel);
        $panel->middleware([FramePolicy::class]);

        if ($panel->getId() === 'app') {
            $panel->pages([Overview::class, Notifications::class]);
            $panel->middleware([StartPage::class]);
        }
        if (in_array($panel->getId(), ['app', 'server'], true)) {
            $panel->userMenuItems([
                Action::make('simplify-overview')->label(fn () => __('simplify::settings.overview'))->icon('tabler-layout-grid')
                    ->url(fn () => Overview::getUrl(panel: 'app')),
                Action::make('simplify-notifications')->label(fn () => __('simplify::push.menu'))->icon('tabler-bell')
                    ->url(fn () => Notifications::getUrl(panel: 'app'))->visible(fn () => Notifications::canAccess()),
                Action::make('simplify-support')->label(fn () => __('simplify::settings.support'))->icon('tabler-lifebuoy')
                    ->url(fn () => PanelSettings::get('support_url'), shouldOpenInNewTab: true)
                    ->visible(fn () => PanelSettings::get('support_url') !== null),
            ]);
            if (PanelSettings::get('start_page') === 'overview') {
                $panel->homeUrl(fn () => Overview::getUrl(panel: 'app'));
            }
        }
        if (in_array($panel->getId(), ['app', 'admin'], true)) {
            $panel->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn () => self::returnButton());
        }
        if ($panel->getId() === 'server') {
            // Simplify's header, server card and menu around every page of a server.
            $panel->renderHook(PanelsRenderHook::CONTENT_BEFORE, fn () => self::serverTop());
            $panel->renderHook('panels::head.end', fn () => self::serverTitle());
        }
        if ($panel->getId() === 'admin') {
            $panel->pages([Settings::class, Setup::class, SetupDone::class, People::class]);
        }
        if ($panel->getId() === 'admin') {
            $panel->resources([AnnouncementResource::class]);
        } elseif ($panel->getId() !== 'server') {
            // The announcement bars sit where Pelican shows its own alert banners; a server's pages have them in the
            // server card (pages/server.blade.php).
            $panel->renderHook(PanelsRenderHook::PAGE_START, fn () => Blade::render("@livewire('simplify-core-announcement-bars')"));
        }
        match ($panel->getId()) {
            'admin' => $panel->pages([EmbedSettings::class, AutomaticSubdomains::class]),
            'server' => $panel->pages([Website::class, ServerSubdomain::class]),
            default => null,
        };
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

    /** Back to Simplify from Pelican's own pages; Simplify's own pages have their own header, a frame has host mode's. */
    private static function returnButton(): string
    {
        if (user() === null || request()->routeIs(Overview::getRouteName(Filament::getPanel('app')), Notifications::getRouteName(Filament::getPanel('app')))) {
            return '';
        }

        return view('simplify::partials.return')->render();
    }

    /** The browser tab of a server's pages: "<server name> - Simplify", also after Livewire changes the page. */
    private static function serverTitle(): string
    {
        $server = Filament::getTenant();
        if (! $server instanceof Server) {
            return '';
        }
        $title = Js::from($server->name.' - Simplify');
        // The path up to the server's key in it (/server/<key>), whichever of its pages the panel's URL names.
        $path = (string) parse_url(Filament::getPanel('server')->getUrl($server), PHP_URL_PATH);
        $key = (string) $server->getRouteKey();
        $at = $key !== '' ? strpos($path, $key) : false;
        $path = Js::from($at !== false ? substr($path, 0, $at + strlen($key)) : rtrim($path, '/'));

        // Only on this server's pages: after Livewire moved on to another page the listener leaves the title alone.
        return "<script>(() => { const title = () => location.pathname.startsWith({$path}) && (document.title = {$title}); title(); "
            ."document.addEventListener('livewire:navigated', title); })();</script>";
    }
}
