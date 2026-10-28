<?php

declare(strict_types=1);

namespace Simplify\Core\Providers;

use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use Simplify\Core\Addons\AddonRegistry;
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
}
