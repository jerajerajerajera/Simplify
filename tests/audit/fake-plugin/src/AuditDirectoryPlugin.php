<?php

namespace Audit\Directory;

use Filament\Contracts\Plugin;
use Filament\Panel;

// A plugin that is not Simplify: one admin page, so Simplify puts it under Other plugins.
final class AuditDirectoryPlugin implements Plugin
{
    public function getId(): string
    {
        return 'audit-directory';
    }

    public function register(Panel $panel): void
    {
        view()->addNamespace('audit-directory', dirname(__DIR__).'/resources/views');
        $panel->pages([DirectoryPage::class]);
    }

    public function boot(Panel $panel): void {}
}
