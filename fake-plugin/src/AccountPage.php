<?php

namespace Audit\Directory;

use Filament\Pages\Page;

final class AccountPage extends Page
{
    protected string $view = 'audit-directory::account';

    protected static ?string $title = 'Directory account';

    protected static ?string $slug = 'directory-account';

    protected static bool $shouldRegisterNavigation = false;
}
