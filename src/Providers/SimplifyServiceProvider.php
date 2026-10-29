<?php

declare(strict_types=1);

namespace Simplify\Core\Providers;

use App\Models\Server;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Announcements\Console\DeliverAnnouncements;
use Simplify\Core\Announcements\Livewire\AnnouncementBars;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Policies\AnnouncementPolicy;
use Simplify\Core\Console\UpdateNoticesCommand;
use Simplify\Core\Embed\EmbedController;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Http\Controllers\AssetController;
use Simplify\Core\Http\Controllers\BannerController;
use Simplify\Core\Http\Controllers\BrandController;
use Simplify\Core\Http\Controllers\WebAppController;
use Simplify\Core\Parts\PartAddon;
use Simplify\Core\Parts\Parts;
use Simplify\Core\Push\Push;
use Simplify\Core\Server\Looks;
use Simplify\Core\Subdomains\Console\SyncSubdomains;
use Simplify\Core\Subdomains\Subdomains;
use Simplify\Core\Support\Brand;
use Simplify\Core\Support\PanelSettings;
use Throwable;

final class SimplifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Simplify's own parts show up like add-ons, unless they are off.
        foreach (array_keys(Parts::PARTS) as $part) {
            $this->app->bind(Parts::addon($part), fn () => new PartAddon($part));
            $this->app->tag([Parts::addon($part)], AddonRegistry::TAG);
        }
        $this->app->singleton(AddonRegistry::class, fn (Container $app) => new AddonRegistry(
            // The container has no public list of a tag's class names. Reading them lets one add-on fail to build
            // without hiding the add-ons after it, which iterating $app->tagged() would do.
            fn () => array_filter((fn () => $this->tags[AddonRegistry::TAG] ?? [])->call($app), Parts::allows(...)),
            fn (string $class) => $app->make($class),
            fn (Throwable $error) => report($error),
        ));
    }

    public function boot(): void
    {
        Route::get('/simplify/assets/{name}', AssetController::class)
            ->where('name', '[a-z0-9.-]+')
            ->name('simplify.asset');
        Route::get('/simplify/brand/{file}', BrandController::class)
            ->where('file', Brand::FILE_PATTERN)
            ->name('simplify.brand');
        Route::middleware(['web', 'auth'])->get('/simplify/banners/{file}', BannerController::class)
            ->where('file', Looks::FILE_PATTERN)
            ->name('simplify.banner');
        Route::get('/simplify/manifest.webmanifest', [WebAppController::class, 'manifest'])->name('simplify.manifest');
        // At the root, so the worker's scope can be the whole panel.
        Route::get('/simplify-worker.js', [WebAppController::class, 'worker'])->name('simplify.worker');

        // Embed: public and read-only, without a session, so any website can show the list. These are the addresses
        // PelicanSimplify and the separate Embed plugin used, so websites that show the list keep working. Left to
        // that plugin while it is installed; the controller answers 404 while the part is off.
        if (! Parts::standalone('embed')) {
            Route::get('/simplify/embed/servers.json', [EmbedController::class, 'servers'])->middleware('throttle:120,1')->name('simplify.embed.servers');
            Route::get('/simplify/embed.js', [EmbedController::class, 'script'])->name('simplify.embed.script');
            Route::get('/simplify/embed', [EmbedController::class, 'frame'])->middleware('throttle:120,1')->name('simplify.embed.frame');
        }

        // Add-ons check Event::hasListeners('simplify.push'), so there is no listener while an admin turned push off.
        if (Push::enabled()) {
            Event::listen('simplify.push', fn (array $message) => Push::fromEvent($message));
        }
        // The overview as start page: only where nothing else asked for a page, so a link someone followed still wins.
        Event::listen(Login::class, function () {
            if (PanelSettings::get('start_page') === 'overview' && app()->bound('session') && ! session()->has('url.intended')) {
                session()->put('url.intended', Overview::getUrl(panel: 'app'));
            }
        });
        Gate::policy(Announcement::class, AnnouncementPolicy::class);
        Livewire::component('simplify-core-announcement-bars', AnnouncementBars::class);

        // A deleted server's records leave Cloudflare after the response, so deleting never waits for it; the sync retries.
        Server::deleted(function (Server $server) {
            if (Parts::on('subdomains')) {
                $uuid = (string) $server->uuid;
                $this->app->terminating(function () use ($uuid) {
                    try {
                        Subdomains::forget($uuid);
                    } catch (Throwable $error) {
                        report($error);
                    }
                });
            }
        });

        $this->commands([UpdateNoticesCommand::class, DeliverAnnouncements::class, SyncSubdomains::class]);
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command(UpdateNoticesCommand::class)->hourly()->withoutOverlapping();
            // Every minute, so an announcement with a later start reaches the bell, devices and game chats on time.
            if (Parts::on('announcements')) {
                $schedule->command(DeliverAnnouncements::class)->everyMinute()->withoutOverlapping(10);
            }
            // Retries subdomains that are not connected, follows port changes and removes records of deleted servers.
            if (Parts::on('subdomains')) {
                $schedule->command(SyncSubdomains::class)->everyFiveMinutes()->withoutOverlapping(10);
            }
        });
    }
}
