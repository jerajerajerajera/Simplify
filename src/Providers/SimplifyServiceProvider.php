<?php

declare(strict_types=1);

namespace Simplify\Core\Providers;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Http\Controllers\AssetController;
use Simplify\Core\Http\Controllers\BrandController;
use Simplify\Core\Support\Brand;
use Throwable;

final class SimplifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AddonRegistry::class, fn (Container $app) => new AddonRegistry(
            // The container has no public list of a tag's class names. Reading them lets one add-on fail to build
            // without hiding the add-ons after it, which iterating $app->tagged() would do.
            fn () => (fn () => $this->tags[AddonRegistry::TAG] ?? [])->call($app),
            fn (string $class) => $app->make($class),
            fn (Throwable $error) => report($error),
        ));
    }

    public function boot(): void
    {
        Route::get('/simplify/assets/{name}', AssetController::class)
            ->where('name', '[a-z.]+')
            ->name('simplify.asset');
        Route::get('/simplify/brand/{file}', BrandController::class)
            ->where('file', Brand::FILE_PATTERN)
            ->name('simplify.brand');
    }
}
