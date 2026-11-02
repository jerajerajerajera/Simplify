<?php

namespace Audit\Directory;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Simplify\Core\Addons\Addon;

// A plugin that is not Simplify: one admin page, so Simplify puts it under Other plugins, and one account page.
final class AuditDirectoryPlugin implements Plugin
{
    public function getId(): string
    {
        return 'audit-directory';
    }

    public function register(Panel $panel): void
    {
        view()->addNamespace('audit-directory', dirname(__DIR__).'/resources/views');
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
