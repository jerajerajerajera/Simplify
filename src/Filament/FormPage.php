<?php

declare(strict_types=1);

namespace Simplify\Core\Filament;

use App\Models\Server;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Pages\Page;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A page with one form and a save button in its header (also mod+s). Root admins only, unless the page says
 * otherwise. A page of a server gets the server from the tenant.
 *
 * @property Schema $form
 */
abstract class FormPage extends Page implements HasSchemas
{
    use InteractsWithForms;

    protected string $view = 'pelican-simplify::form-page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public function getTitle(): string|Htmlable
    {
        return static::getNavigationLabel();
    }

    /** True while the page waits for something outside the panel, so it keeps checking. */
    public function waiting(): bool
    {
        return false;
    }

    abstract public function save(): void;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->hiddenLabel()
                ->icon('tabler-device-floppy')
                ->tooltip(__('pelican-simplify::settings.save'))
                ->action('save')
                ->keyBindings(['mod+s']),
        ];
    }

    protected function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }
}
