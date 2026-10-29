<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Filament;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Simplify\Core\Embed\Services\EmbedServers;
use Simplify\Core\Filament\FormPage;

/**
 * The server's owner (or a root admin) puts the server on the website and copies the code for this one server.
 */
final class Website extends FormPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-code';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'website';

    public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        return $server instanceof Server && EmbedServers::canManage($server);
    }

    public static function getNavigationLabel(): string
    {
        return __('simplify::embed.server.title');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->form->fill(['visible' => EmbedServers::visible($this->server())]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make(__('simplify::embed.server.title'))
                ->description(__('simplify::embed.server.help'))
                ->schema([
                    Toggle::make('visible')
                        ->label(__('simplify::embed.server.show'))
                        ->live()
                        ->afterStateUpdated(fn (bool $state) => $this->toggle($state)),
                    View::make('simplify::embed.code')
                        ->viewData(fn () => ['show' => ['server' => (string) $this->server()->uuid_short]])
                        ->visible(fn (Get $get) => (bool) $get('visible')),
                ]),
        ]);
    }

    public function toggle(bool $visible): void
    {
        abort_unless(self::canAccess(), 403);
        EmbedServers::setVisible($this->server(), $visible);
        Notification::make()
            ->title(__('simplify::embed.server.'.($visible ? 'shown' : 'hidden')))
            ->success()
            ->send();
    }

    public function save(): void
    {
        $this->toggle((bool) ($this->form->getState()['visible'] ?? false));
    }
}
