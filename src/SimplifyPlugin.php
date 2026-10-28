<?php

declare(strict_types=1);

namespace Simplify\Core;

use App\Enums\ConsoleWidgetPosition;
use App\Filament\Server\Pages\Console;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Filament\Server\Widgets\AddonSections;
use Simplify\Core\Filament\Server\Widgets\ServerDetails;
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
        $accent = PanelSettings::get('accent');
        if ($accent !== null) {
            $panel->colors(['primary' => Color::hex($accent)]);
        }

        if ($panel->getId() === 'app') {
            $panel->pages([Overview::class]);
        }
        if ($panel->getId() === 'server') {
            Console::registerCustomWidgets(ConsoleWidgetPosition::Top, [ServerDetails::class]);
            Console::registerCustomWidgets(ConsoleWidgetPosition::BelowConsole, [AddonSections::class]);
        }
        if ($panel->getId() === 'admin') {
            $panel->pages([Settings::class]);
        }
    }

    public function boot(Panel $panel): void {}
}
