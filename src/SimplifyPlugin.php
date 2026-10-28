<?php

declare(strict_types=1);

namespace Simplify\Core;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Simplify\Core\Filament\Admin\Pages\Settings;

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

        if ($panel->getId() === 'admin') {
            $panel->pages([Settings::class]);
        }
    }

    public function boot(Panel $panel): void {}
}
