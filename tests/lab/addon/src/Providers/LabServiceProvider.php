<?php

declare(strict_types=1);

namespace Simplify\Lab\Providers;

use Illuminate\Support\ServiceProvider;
use Simplify\Lab\LabAddon;

final class LabServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([LabAddon::class], 'simplify.addons');
    }
}
