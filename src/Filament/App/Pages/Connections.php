<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\App\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Simplify\Core\Account\Connections as Rows;

/**
 * Connections (account menu): one card with a row per outside account an add-on links, such as Server Maker's Steam
 * account. Each row is the add-on's own Livewire component and saves on its own; the page has no Save. Without a row
 * the page does not exist (404).
 */
final class Connections extends Page
{
    protected static ?string $slug = 'connections';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'simplify::pages.connections';

    /** @var list<class-string> */
    public array $rows = [];

    public function mount(): void
    {
        $this->rows = Rows::rows(user());
        abort_if($this->rows === [], 404);
    }

    public function getTitle(): string|Htmlable
    {
        return __('simplify::dashboard.ui.connections');
    }

    public function getSubheading(): string
    {
        return __('simplify::dashboard.ui.connections_intro');
    }
}
