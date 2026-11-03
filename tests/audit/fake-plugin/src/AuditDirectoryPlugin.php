<?php

namespace Audit\Directory;

use App\Enums\HeaderActionPosition;
use App\Filament\Server\Pages\Console;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Simplify\Core\Addons\Addon;

// A plugin that is not Simplify: one admin page, so Simplify puts it under Other plugins, one account page, and a
// header action on Pelican's Console page, as an add-on such as Explain has.
final class AuditDirectoryPlugin implements Plugin
{
    public function getId(): string
    {
        return 'audit-directory';
    }

    public function register(Panel $panel): void
    {
        view()->addNamespace('audit-directory', dirname(__DIR__).'/resources/views');
        // Console's header actions are static, so registering once from whichever panel registers first covers the
        // server panel too (this plugin is not on the server panel).
        static $consoleAction = false;
        if (! $consoleAction && enum_exists(HeaderActionPosition::class) && method_exists(Console::class, 'registerCustomHeaderActions')) {
            $consoleAction = true;
            Console::registerCustomHeaderActions(HeaderActionPosition::Before, Action::make('audit-action')->label('Audit action')->icon('tabler-bulb')->action(fn () => null));
        }
        if ($panel->getId() === 'app') {
            $panel->pages([AccountPage::class]);
            if (class_exists(Addon::class)) {
                app()->tag([AuditAddon::class], 'simplify.addons');
            }

            return;
        }
        $panel->pages([DirectoryPage::class]);
    }

    public function boot(Panel $panel): void {}
}
