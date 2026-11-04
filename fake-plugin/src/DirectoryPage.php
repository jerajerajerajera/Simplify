<?php

namespace Audit\Directory;

use Filament\Pages\Page;

final class DirectoryPage extends Page
{
    protected string $view = 'audit-directory::directory';

    protected static ?string $navigationLabel = 'Site directory';

    protected static ?string $title = 'Site directory';

    protected static ?string $slug = 'site-directory';
}
