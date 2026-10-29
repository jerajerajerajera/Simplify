<?php

declare(strict_types=1);

namespace Simplify\Core\Providers;

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
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Http\Controllers\AssetController;
use Simplify\Core\Http\Controllers\BannerController;
use Simplify\Core\Http\Controllers\BrandController;
use Simplify\Core\Http\Controllers\WebAppController;
use Simplify\Core\Parts\Parts;
use Simplify\Core\Push\Push;
use Simplify\Core\Server\Looks;
use Simplify\Core\Support\Brand;
use Simplify\Core\Support\PanelSettings;
use Throwable;

final class SimplifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Simplify's own parts show up like add-ons, unless they are off.
        $this->app->tag(Parts::addons(), AddonRegistry::TAG);
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

        $this->commands([UpdateNoticesCommand::class, DeliverAnnouncements::class]);
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command(UpdateNoticesCommand::class)->hourly()->withoutOverlapping();
            // Every minute, so an announcement with a later start reaches the bell, devices and game chats on time.
            if (Parts::on('announcements')) {
                $schedule->command(DeliverAnnouncements::class)->everyMinute()->withoutOverlapping(10);
            }
        });
    }
}
