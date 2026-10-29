<?php

declare(strict_types=1);

namespace Simplify\Lab;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class LabPlugin implements Plugin
{
    public function getId(): string
    {
        return 'simplify-lab';
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}
}
