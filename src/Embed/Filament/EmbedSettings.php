<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Filament;

use App\Models\Server;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Simplify\Core\Embed\Services\EmbedOptions;
use Simplify\Core\Embed\Services\EmbedServers;
use Simplify\Core\Embed\Services\ServerList;
use Simplify\Core\Filament\FormPage;
use Simplify\Core\Overview\ServerGroups;
use Simplify\Core\Support\Preferences;

/**
 * Where a root admin picks the servers on the website, how the list looks, and copies the code for a website.
 */
final class EmbedSettings extends FormPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-code';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'simplify-embed';

    public static function getNavigationLabel(): string
    {
        return __('simplify::embed.name');
    }

    /** In the Simplify group of Pelican's menu, with the other Simplify add-ons (Simplify's ADDONS.md, "Admin menu"). */
    public static function getNavigationGroup(): ?string
    {
        return 'Simplify';
    }

    public function mount(): void
    {
        $this->form->fill([
            'options' => EmbedOptions::all(),
            'servers' => EmbedServers::ids(),
            'code' => ['show' => 'all'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $t = fn (string $key) => __('simplify::embed.settings.'.$key);

        return $schema->statePath('data')->components([
            Section::make($t('servers'))->description($t('servers_help'))->schema([
                CheckboxList::make('servers')
                    ->hiddenLabel()
                    ->options(fn () => Server::query()->orderBy('name')->limit(500)->pluck('name', 'id')->all())
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2),
            ]),
            Section::make($t('look'))->description($t('look_help'))->columns(2)->schema([
                Select::make('options.style')->label($t('style'))->selectablePlaceholder(false)->required()->options([
                    'auto' => $t('style_auto'),
                    'light' => $t('style_light'),
                    'dark' => $t('style_dark'),
                ]),
                Select::make('options.corners')->label($t('corners'))->selectablePlaceholder(false)->required()->options([
                    'round' => $t('corners_round'),
                    'square' => $t('corners_square'),
                ]),
                ColorPicker::make('options.accent')
                    ->label($t('accent'))
                    ->regex('/^#[0-9a-fA-F]{6}$/')
                    ->placeholder(EmbedOptions::ACCENT)
                    ->helperText($t('accent_help')),
                Grid::make(1)->schema([
                    Toggle::make('options.players')->label($t('players')),
                    Toggle::make('options.modpack')->label($t('modpack')),
                    Toggle::make('options.join')->label($t('join'))->helperText($t('join_help')),
                ]),
            ]),
            Section::make($t('code'))->description($t('code_help'))->columns(2)->schema([
                Select::make('code.show')->label($t('show'))->live()->selectablePlaceholder(false)->afterStateUpdated(fn (Set $set, Get $get) => $this->pickFirst($set, $get))->options([
                    'all' => $t('show_all'),
                    'server' => $t('show_server'),
                    'game' => $t('show_game'),
                    ...self::groups() === [] ? [] : ['group' => $t('show_group')],
                ]),
                Select::make('code.server')
                    ->label($t('server'))
                    ->live()
                    ->placeholder($t('pick_server'))
                    ->helperText($t('server_help'))
                    ->options(fn () => self::servers())
                    ->visible(fn (Get $get) => $get('code.show') === 'server'),
                Select::make('code.game')
                    ->label($t('game'))
                    ->live()
                    ->placeholder($t('pick_game'))
                    ->options(fn () => array_combine(ServerList::games(), ServerList::games()))
                    ->visible(fn (Get $get) => $get('code.show') === 'game'),
                Select::make('code.group')
                    ->label($t('group'))
                    ->live()
                    ->placeholder($t('pick_group'))
                    ->helperText($t('group_help'))
                    ->options(fn () => array_map(fn (array $group) => $group['name'], self::groups()))
                    ->visible(fn (Get $get) => $get('code.show') === 'group'),
                View::make('simplify::embed.code')->columnSpanFull()->viewData(fn (Get $get) => $this->codeFor($get)),
            ]),
        ]);
    }

    /** @return array<string, string> the servers on the website by short id, as saved */
    private static function servers(): array
    {
        return Server::query()->whereIn('id', EmbedServers::ids())->orderBy('name')->pluck('name', 'uuid_short')->all();
    }

    /**
     * The admin's own server groups from the overview, by id, with the short ids of their servers on the website.
     *
     * @return array<string, array{name: string, servers: list<string>}>
     */
    private static function groups(): array
    {
        $shown = Server::query()->whereIn('id', EmbedServers::ids())->pluck('uuid_short', 'uuid')->all();
        $groups = [];
        foreach (ServerGroups::clean(Preferences::get(user(), 'overview.groups'))['groups'] as $group) {
            $groups[$group['id']] = ['name' => $group['name'], 'servers' => array_values(array_intersect_key($shown, array_flip($group['servers'])))];
        }

        return $groups;
    }

    /** One server or one game shows the code at once: the first on the list is picked until another is chosen. */
    private function pickFirst(callable $set, callable $get): void
    {
        if ($get('code.show') === 'server' && ! array_key_exists((string) $get('code.server'), self::servers())) {
            $set('code.server', array_key_first(self::servers()));
        }
        if ($get('code.show') === 'game' && ! in_array($get('code.game'), ServerList::games(), true)) {
            $set('code.game', ServerList::games()[0] ?? null);
        }
        if ($get('code.show') === 'group' && ! array_key_exists((string) $get('code.group'), self::groups())) {
            $set('code.group', array_key_first(self::groups()));
        }
    }

    /** @return array{show: array<string, string>|null} */
    private function codeFor(Get $get): array
    {
        return ['show' => match ($get('code.show')) {
            'server' => filled($get('code.server')) ? ['server' => (string) $get('code.server')] : null,
            'game' => filled($get('code.game')) ? ['game' => (string) $get('code.game')] : null,
            'group' => (self::groups()[(string) $get('code.group')]['servers'] ?? []) !== [] ? ['servers' => self::groups()[(string) $get('code.group')]['servers']] : null,
            default => [],
        }];
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $state = $this->form->getState();
        EmbedOptions::save((array) ($state['options'] ?? []));
        EmbedServers::replace(array_map('intval', (array) ($state['servers'] ?? [])));
        // The code below follows the new list straight away.
        $this->pickFirst(fn (string $key, mixed $value) => data_set($this->data, $key, $value), fn (string $key) => data_get($this->data, $key));
        Notification::make()->title(__('simplify::embed.saved'))->success()->send();
    }
}
