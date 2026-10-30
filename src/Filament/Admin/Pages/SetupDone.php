<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin\Pages;

use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\IconPosition;
use Illuminate\Contracts\Support\Htmlable;
use Simplify\Core\Filament\App\Pages\Overview;

/**
 * After the setup: everything is saved, and each add-on's own settings page is one click away. The setup links there
 * only from here, so nobody leaves the setup halfway.
 */
final class SetupDone extends Page
{
    protected static ?string $slug = 'simplify-setup-done';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'simplify::pages.setup-done';

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public function getTitle(): string|Htmlable
    {
        return __('simplify::setup.done');
    }

    public function getHeading(): string|Htmlable
    {
        return Settings::logoHeading(__('simplify::setup.done'));
    }

    public function getSubheading(): string
    {
        return __('simplify::setup.done_intro');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('again')->label(__('simplify::setup.again'))->icon('tabler-wand')->color('gray')->button()
                ->url(fn () => Setup::getUrl(panel: 'admin')),
            Action::make('panel')->label(__('simplify::setup.open_panel'))->icon('tabler-arrow-right')->iconPosition(IconPosition::After)
                ->url(fn () => Overview::getUrl(panel: 'app')),
        ];
    }
}
