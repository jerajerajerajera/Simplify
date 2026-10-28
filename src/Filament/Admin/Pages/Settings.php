<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;

/**
 * Simplify's settings. For now it lists the installed add-ons; the settings themselves follow in later versions.
 */
final class Settings extends Page
{
    protected static ?string $slug = 'simplify';

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-layout-dashboard';

    protected string $view = 'pelican-simplify::pages.settings';

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public static function getNavigationLabel(): string
    {
        return __('pelican-simplify::settings.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pelican-simplify::settings.title');
    }

    /** @return list<Addon> */
    public function addons(): array
    {
        return app(AddonRegistry::class)->all();
    }
}
