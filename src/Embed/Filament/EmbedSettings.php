<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Filament;

use App\Models\Server;
use Closure;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Simplify\Core\Embed\Services\EggLogo;
use Simplify\Core\Embed\Services\EmbedDetails;
use Simplify\Core\Embed\Services\EmbedOptions;
use Simplify\Core\Embed\Services\EmbedServers;
use Simplify\Core\Embed\Services\ServerList;
use Simplify\Core\Embed\Services\StatusAddress;
use Simplify\Core\Filament\FormPage;
use Simplify\Core\Overview\ServerGroups;
use Simplify\Core\Support\Assets;
use Simplify\Core\Support\Preferences;
use Throwable;

/**
 * Where a root admin picks the servers on the website and their order, how the list looks, the logo per egg, the
 * status page's own address, and copies the code for a website.
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
        $options = EmbedOptions::all();
        $this->form->fill([
            'options' => $options,
            'servers' => self::ordered(),
            'logos' => $options['logos'],
            'code' => ['show' => 'all'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $t = fn (string $key) => __('simplify::embed.settings.'.$key);

        return $schema->statePath('data')->components([
            Section::make($t('servers'))->description($t('servers_help'))->schema([
                ViewField::make('servers')->view('simplify::embed.order')->hiddenLabel()->helperText($t('order_help'))
                    ->viewData(fn () => ['servers' => self::choices()]),
                Toggle::make('options.online_first')->label($t('online_first'))->helperText($t('online_first_help')),
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
                    Toggle::make('options.uptime')->label($t('uptime'))->helperText($t('uptime_help')),
                ]),
            ]),
            Section::make($t('logos'))->description($t('logos_help'))
                ->visible(fn () => self::eggs() !== [])
                ->columns(['default' => 2, 'sm' => 3, 'lg' => 4])
                ->schema(fn () => array_map(fn (array $egg) => self::logoField($egg), self::eggs())),
            Section::make($t('status'))->description($t('status_help'))->schema([
                TextInput::make('options.status_host')->label($t('status_host'))
                    ->placeholder('status.example.com')
                    ->helperText($t('status_host_help'))
                    ->maxLength(190)->extraInputAttributes(['class' => 'font-mono'])
                    ->dehydrateStateUsing(fn (?string $state) => strtolower(trim((string) $state)))
                    ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) use ($t) {
                        $host = strtolower(trim((string) $value));
                        if ($host !== '' && ! StatusAddress::allowed($host)) {
                            $fail($t('status_invalid'));
                        }
                    }),
                View::make('simplify::embed.status')->viewData(fn () => self::statusData()),
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

    /**
     * The servers on the website in their order on the list (position, then name).
     *
     * @return list<int>
     */
    private static function ordered(): array
    {
        $ids = EmbedServers::ids();
        $positions = array_map(fn (array $details) => $details['position'], EmbedDetails::many($ids));
        $names = Server::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
        $ids = array_values(array_filter($ids, fn (int $id) => isset($names[$id])));
        usort($ids, fn (int $a, int $b) => [$positions[$a] ?? PHP_INT_MAX, mb_strtolower((string) $names[$a])] <=> [$positions[$b] ?? PHP_INT_MAX, mb_strtolower((string) $names[$b])]);

        return $ids;
    }

    /**
     * Every server to pick from, by name, with the logo it gets on the website.
     *
     * @return list<array{id: int, name: string, logo: ?string}>
     */
    private static function choices(): array
    {
        $logos = EmbedOptions::all()['logos'];

        return Server::query()->with('egg')->orderBy('name')->limit(500)->get()
            ->map(fn (Server $server) => ['id' => (int) $server->id, 'name' => (string) $server->name, 'logo' => EggLogo::for($server, $logos)['url'] ?? null])
            ->values()->all();
    }

    /**
     * The eggs of the servers on the website, with the logo each gets without an upload.
     *
     * @return list<array{id: int, name: string, automatic: ?string, from: string}>
     */
    private static function eggs(): array
    {
        $eggs = [];
        foreach (Server::query()->with('egg')->whereIn('id', EmbedServers::ids())->get() as $server) {
            if (isset($eggs[(int) $server->egg_id]) || $server->getRelationValue('egg') === null) {
                continue;
            }
            $egg = $server->egg;
            $icon = (string) $egg->icon;
            $own = $icon !== '' ? $icon : null;
            $software = EggLogo::automatic($server);
            $eggs[(int) $egg->id] = [
                'id' => (int) $egg->id,
                'name' => (string) $egg->name,
                'automatic' => $own ?? ($software === null ? null : Assets::url($software)),
                'from' => $own !== null ? 'pelican' : ($software === null ? 'none' : 'software'),
            ];
        }
        usort($eggs, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $eggs;
    }

    /** @param  array{id: int, name: string, automatic: ?string, from: string}  $egg */
    private static function logoField(array $egg): FileUpload
    {
        $t = fn (string $key) => __('simplify::embed.settings.'.$key);

        return FileUpload::make('logos.'.$egg['id'])->label($egg['name'])->helperText($t('logo_'.$egg['from']))
            ->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->maxSize(512)
            ->disk('local')->directory(EggLogo::DIRECTORY)->visibility('private')
            ->panelLayout('integrated')->panelAspectRatio('1:1')->imagePreviewHeight('64')
            ->removeUploadedFileButtonPosition('right top')->uploadProgressIndicatorPosition('center bottom')
            ->placeholder(svg('tabler-plus', 's-tile-plus')->toHtml().'<span class="fi-sr-only">'.e($t('logo_upload')).'</span>')
            ->extraFieldWrapperAttributes(['class' => 's-tile-field'])
            ->beforeContent(View::make('simplify::embed.logo-tile')->viewData(['url' => $egg['automatic'], 'label' => $t('logo_'.$egg['from'])]));
    }

    /** @return array{host: string, works: bool, cloudflare: bool, record: string, panel: string, wildcard: string} */
    private static function statusData(): array
    {
        $host = EmbedOptions::all()['status_host'];

        return [
            'host' => $host,
            'works' => $host !== '' && StatusAddress::checked($host),
            'cloudflare' => self::safely(fn () => StatusAddress::cloudflare(), false),
            'record' => $host === '' ? '' : StatusAddress::record($host),
            'panel' => StatusAddress::panelHost(),
            'wildcard' => StatusAddress::wildcard($host),
        ];
    }

    private static function safely(Closure $value, mixed $fallback): mixed
    {
        try {
            return $value();
        } catch (Throwable) {
            return $fallback;
        }
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
        $before = EmbedOptions::all();
        $logos = $this->logos($before['logos'], (array) ($state['logos'] ?? []));
        $options = [...(array) ($state['options'] ?? []), 'logos' => $logos];
        EmbedOptions::save($options);
        $ids = array_values(array_unique(array_map('intval', array_values((array) ($state['servers'] ?? [])))));
        EmbedServers::replace($ids);
        EmbedDetails::order($ids);
        $this->status($before['status_host'], EmbedOptions::all()['status_host']);
        // The code below follows the new list straight away.
        $this->pickFirst(fn (string $key, mixed $value) => data_set($this->data, $key, $value), fn (string $key) => data_get($this->data, $key));
        Notification::make()->title(__('simplify::embed.saved'))->success()->send();
    }

    /**
     * The logos per egg after this save: the upload fields of the eggs on the page, and the saved logos of other eggs
     * as they were. A replaced or removed upload is deleted.
     *
     * @param  array<int, string>  $saved
     * @param  array<int|string, mixed>  $fields
     * @return array<int, string>
     */
    private function logos(array $saved, array $fields): array
    {
        $logos = $saved;
        foreach (self::eggs() as $egg) {
            $file = $fields[$egg['id']] ?? null;
            $file = is_array($file) ? (array_values($file)[0] ?? null) : $file;
            if (is_string($file) && EggLogo::validUpload($file)) {
                $logos[$egg['id']] = $file;
            } else {
                unset($logos[$egg['id']]);
            }
        }
        foreach (array_diff($saved, $logos) as $old) {
            if (EggLogo::validUpload($old)) {
                Storage::disk('local')->delete($old);
            }
        }

        return $logos;
    }

    /** A new or changed status address: Simplify makes its Cloudflare record when it can, and says how that went. */
    private function status(string $old, string $new): void
    {
        StatusAddress::forget($old);
        StatusAddress::forget($new);
        if ($new === '' || ($new === $old && StatusAddress::works($new)) || ! self::safely(fn () => StatusAddress::cloudflare(), false)) {
            return;
        }
        $problem = StatusAddress::connect($new);
        $notification = Notification::make()->title(__('simplify::embed.settings.status'));
        if ($problem === null) {
            $notification->body(__('simplify::embed.settings.status_connected', ['host' => $new]))->success()->send();
        } else {
            $notification->body($problem)->warning()->send();
        }
    }
}
