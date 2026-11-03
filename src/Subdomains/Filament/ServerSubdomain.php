<?php

declare(strict_types=1);

namespace Simplify\Core\Subdomains\Filament;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Simplify\Core\Filament\FormPage;
use Simplify\Core\Subdomains\Models\Subdomain;
use Simplify\Core\Subdomains\Services\Settings;
use Simplify\Core\Subdomains\Subdomains;

/**
 * The server's own subdomain. Its owner (or a root admin) picks the name; the records follow after the response.
 * Until Cloudflare is set up only root admins see the page, with what is missing and a button to the settings.
 */
final class ServerSubdomain extends FormPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-world';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'subdomain';

    public static function canAccess(): bool
    {
        $server = Filament::getTenant();
        $user = user();

        return $server instanceof Server
            && $user !== null
            && ($user->isRootAdmin() || ((int) $server->owner_id === (int) $user->id && Subdomains::ready()));
    }

    public static function getNavigationLabel(): string
    {
        return __('simplify::subdomains.subdomain');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        if (! Subdomains::ready()) {
            return;
        }
        $server = $this->server();
        $name = Subdomains::state($server)['name'];
        $this->form->fill(['name' => $name !== '' ? $name : Subdomains::suggest((string) $server->name, $server->uuid)]);
    }

    /** True while Cloudflare is still being called, so the page keeps checking. */
    public function waiting(): bool
    {
        return in_array(Subdomains::state($this->server())['status'], [Subdomain::PENDING, Subdomain::REMOVING], true);
    }

    public function form(Schema $schema): Schema
    {
        if (! Subdomains::ready()) {
            return $schema->components([
                Section::make(__('simplify::subdomains.errors.not_set_up'))
                    ->icon('tabler-alert-circle')
                    ->description(fn () => $this->notSetUp())
                    ->schema([Text::make(fn () => new HtmlString(sprintf(
                        '<a class="s-btn" href="%s">%s</a>',
                        e(AutomaticSubdomains::getUrl(panel: 'admin')),
                        e(__('simplify::subdomains.setup.button')),
                    )))]),
            ]);
        }

        return $schema->statePath('data')->components([
            Section::make(__('simplify::subdomains.subdomain'))
                ->description(__('simplify::subdomains.server.help'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('simplify::subdomains.server.name'))
                        ->maxLength(63)
                        ->placeholder('oak')
                        ->dehydrateStateUsing(fn ($state) => strtolower(trim((string) $state)))
                        ->suffix('.'.Subdomains::domain()),
                    Text::make(fn () => $this->status())->visible(fn () => $this->status() !== ''),
                ]),
        ]);
    }

    /** "Connected: oak.example.com", "Not connected: <reason>", or empty when there is no subdomain. */
    public function status(): string
    {
        $state = Subdomains::state($this->server());
        if ($state['name'] === '') {
            return '';
        }
        $label = __('simplify::subdomains.status.'.($state['status'] ?: Subdomain::PENDING));
        $detail = $state['status'] === Subdomain::ACTIVE ? (string) Subdomains::address($this->server()) : $state['message'];

        return $detail !== '' ? $label.': '.$detail : $label;
    }

    /** Until Cloudflare is set up there is nothing to save: the button to the settings is in the page itself. */
    protected function getHeaderActions(): array
    {
        return Subdomains::ready() ? parent::getHeaderActions() : [];
    }

    /** "Still missing: Cloudflare token. Once they are, players can join this server with oak.example.com." */
    private function notSetUp(): string
    {
        $items = array_map(fn (string $item) => __('simplify::subdomains.missing.'.$item), Settings::missing());
        $server = $this->server();
        $name = Subdomains::state($server)['name'] ?: Subdomains::suggest((string) $server->name, $server->uuid) ?: 'oak';
        $address = $name.'.'.(Subdomains::domain() ?: 'example.com');

        return __('simplify::subdomains.settings.still_missing', ['items' => implode(', ', $items)]).' '
            .__('simplify::subdomains.setup.server_help', ['address' => $address]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess() && Subdomains::ready(), 403);
        $name = (string) ($this->form->getState()['name'] ?? '');
        try {
            Subdomains::request($this->server(), $name);
        } catch (ValidationException $error) {
            throw ValidationException::withMessages(['data.name' => collect($error->errors())->flatten()->first()]);
        }
        Notification::make()
            ->title(__('simplify::subdomains.server.'.($name !== '' ? 'saved' : 'removed')))
            ->success()
            ->send();
    }
}
