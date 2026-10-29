<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Filament;

use App\Models\Server;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Services\Bell;
use Simplify\Core\Announcements\Services\Push;
use Simplify\Core\Announcements\Support\GameChat;
use Simplify\Core\Announcements\Support\Rules;

/**
 * Where root admins write, end and delete announcements.
 */
final class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static ?string $slug = 'simplify-announcements';

    protected static string|BackedEnum|null $navigationIcon = 'tabler-speakerphone';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    /** In the Simplify group of Pelican's menu, with the other Simplify add-ons (Simplify's ADDONS.md, "Admin menu"). */
    public static function getNavigationGroup(): ?string
    {
        return 'Simplify';
    }

    public static function getNavigationLabel(): string
    {
        return self::text('title');
    }

    public static function getModelLabel(): string
    {
        return self::text('model');
    }

    public static function getPluralModelLabel(): string
    {
        return self::text('title');
    }

    public static function form(Schema $schema): Schema
    {
        $zone = self::timezone();

        return $schema->columns(1)->components([
            ToggleButtons::make('category')
                ->label(self::text('form.category'))
                ->options(self::options('category', Rules::CATEGORIES))
                ->colors(array_combine(Rules::CATEGORIES, array_map(Rules::color(...), Rules::CATEGORIES)))
                ->icons(array_combine(Rules::CATEGORIES, array_map(Rules::icon(...), Rules::CATEGORIES)))
                ->inline()
                ->required()
                ->default('info'),
            TextInput::make('title')
                ->label(self::text('form.title'))
                ->required()
                ->maxLength(Rules::TITLE_LENGTH),
            Textarea::make('body')
                ->label(self::text('form.body'))
                ->rows(3)
                ->maxLength(Rules::BODY_LENGTH),
            Grid::make(2)->schema([
                DateTimePicker::make('starts_at')
                    ->label(self::text('form.starts_at'))
                    ->seconds(false)
                    ->timezone($zone)
                    ->helperText(self::text('form.starts_at_help', ['zone' => $zone])),
                DateTimePicker::make('ends_at')
                    ->label(self::text('form.ends_at'))
                    ->seconds(false)
                    ->timezone($zone)
                    ->after('starts_at')
                    ->helperText(self::text('form.ends_at_help')),
            ]),
            ToggleButtons::make('audience')
                ->label(self::text('form.audience'))
                ->options(self::options('audience', Rules::AUDIENCES))
                ->inline()
                ->required()
                ->default('everyone'),
            Grid::make(2)->schema([
                TextInput::make('button_url')
                    ->label(self::text('form.button_url'))
                    ->placeholder(self::text('form.button_url_placeholder'))
                    ->maxLength(Rules::URL_LENGTH)
                    ->regex(Rules::URL_PATTERN)
                    ->validationMessages(['regex' => self::text('form.button_url_invalid')]),
                TextInput::make('button_label')
                    ->label(self::text('form.button_label'))
                    ->placeholder(self::text('bars.open'))
                    ->maxLength(Rules::LABEL_LENGTH),
            ]),
            Toggle::make('in_bell')
                ->label(self::text('form.in_bell'))
                ->helperText(self::text('form.in_bell_help')),
            Toggle::make('push')
                ->label(self::text('form.push'))
                ->helperText(self::text('form.push_help'))
                ->default(true)
                ->visible(Push::available()),
            Toggle::make('in_game')
                ->label(self::text('form.in_game'))
                ->helperText(self::text('form.in_game_help'))
                ->live(),
            Grid::make(2)->visible(fn ($get): bool => (bool) $get('in_game'))->schema([
                Select::make('in_game_servers')
                    ->label(self::text('form.in_game_servers'))
                    ->placeholder(self::text('form.in_game_servers_all'))
                    ->helperText(self::text('form.in_game_servers_help'))
                    ->multiple()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Server::query()->where('name', 'like', '%'.$search.'%')->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                    ->getOptionLabelsUsing(fn (array $values): array => Server::query()->whereKey($values)->pluck('name', 'id')->all()),
                Select::make('in_game_repeat')
                    ->label(self::text('form.in_game_repeat'))
                    ->options(self::repeats())
                    ->default(0)
                    ->selectablePlaceholder(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $zone = self::timezone();

        return $table
            ->defaultSort('created_at', 'desc')
            // Two rows, as the panel is too narrow for nine columns: the announcement itself, then what it does.
            ->columns([
                Stack::make([
                    TextColumn::make('title')
                        ->label(self::text('form.title'))
                        ->weight(FontWeight::SemiBold)
                        ->icon(fn (Announcement $record): string => Rules::icon($record->category))
                        ->iconColor(fn (Announcement $record): string => Rules::color($record->category))
                        ->description(fn (Announcement $record): ?string => $record->body ? str($record->body)->limit(200)->toString() : null)
                        ->searchable()
                        ->wrap(),
                    Split::make([
                        TextColumn::make('state')
                            ->state(fn (Announcement $record): string => $record->state())
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'live' => 'success',
                                'scheduled' => 'info',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => self::text('state.'.$state))
                            ->grow(false),
                        TextColumn::make('starts_at')
                            ->prefix(self::text('form.starts_at').': ')
                            ->dateTime(timezone: $zone)
                            ->placeholder(self::text('table.right_away'))
                            ->color('gray')
                            ->grow(false),
                        TextColumn::make('ends_at')
                            ->prefix(self::text('form.ends_at').': ')
                            ->dateTime(timezone: $zone)
                            ->placeholder(self::text('table.until_ended'))
                            ->color('gray')
                            ->grow(false),
                        TextColumn::make('audience')
                            ->badge()
                            ->color('gray')
                            ->tooltip(self::text('form.audience'))
                            ->formatStateUsing(fn (string $state): string => self::text('audience.'.$state))
                            ->grow(false),
                        TextColumn::make('in_bell_at')
                            ->icon('tabler-bell')
                            ->tooltip(self::text('table.in_bell'))
                            ->state(fn (Announcement $record): ?string => match (true) {
                                ! $record->in_bell => null,
                                $record->in_bell_at !== null => self::text('table.in_bell_sent'),
                                default => self::text('table.in_bell_waiting'),
                            })
                            ->color('gray')
                            ->grow(false),
                        TextColumn::make('pushed_to')
                            ->icon('tabler-device-mobile')
                            ->tooltip(self::text('table.push'))
                            ->state(fn (Announcement $record): ?string => $record->push ? self::text('table.pushed_to', ['count' => $record->pushed_to]) : null)
                            ->color('gray')
                            ->visible(Push::available())
                            ->grow(false),
                        TextColumn::make('in_game_result')
                            ->icon('tabler-device-gamepad-2')
                            ->tooltip(self::text('table.in_game'))
                            ->state(fn (Announcement $record): ?string => self::inGameSummary($record))
                            ->color('gray')
                            ->grow(false),
                    ])->from('md'),
                ])->space(2),
            ])
            ->recordActions([
                EditAction::make()->after(fn () => self::deliverNow()),
                Action::make('end')
                    ->label(self::text('table.end'))
                    ->icon('tabler-player-stop')
                    ->color('warning')
                    ->visible(fn (Announcement $record): bool => $record->state() !== 'ended')
                    ->requiresConfirmation()
                    ->modalHeading(self::text('table.end_heading'))
                    ->modalDescription(self::text('table.end_help'))
                    ->action(fn (Announcement $record) => $record->update(['ends_at' => now()])),
                DeleteAction::make(),
            ])
            ->emptyStateIcon('tabler-speakerphone')
            ->emptyStateHeading(self::text('table.empty'))
            ->emptyStateDescription(self::text('table.empty_help'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAnnouncements::route('/'),
        ];
    }

    /** A live announcement reaches the bell and devices right away instead of on the next scheduler run. */
    public static function deliverNow(): void
    {
        Bell::deliverDue();
        Push::sendDue();
    }

    /** @return array<int, string> */
    public static function repeats(): array
    {
        $labels = [];
        foreach (GameChat::REPEATS as $minutes) {
            $labels[$minutes] = match (true) {
                $minutes === 0 => self::text('repeat.once'),
                $minutes === 60 => self::text('repeat.hourly'),
                $minutes > 60 => self::text('repeat.hours', ['count' => intdiv($minutes, 60)]),
                default => self::text('repeat.minutes', ['count' => $minutes]),
            };
        }

        return $labels;
    }

    private static function inGameSummary(Announcement $record): ?string
    {
        if (! $record->in_game) {
            return null;
        }
        $result = $record->in_game_result;
        if (! is_array($result)) {
            return self::text('table.in_bell_waiting');
        }
        $total = array_sum(array_map('intval', $result));

        return self::text('table.in_game_sent', ['sent' => (int) ($result['sent'] ?? 0), 'total' => $total]);
    }

    /** Times are stored in UTC and shown in the admin's own time zone from their Pelican profile. */
    public static function timezone(): string
    {
        return user()?->timezone ?: (string) config('app.timezone', 'UTC');
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private static function options(string $group, array $keys): array
    {
        return array_combine($keys, array_map(fn (string $key): string => self::text($group.'.'.$key), $keys));
    }

    /** @param  array<string, string>  $replace */
    private static function text(string $key, array $replace = []): string
    {
        return __('simplify::announcements.'.$key, $replace);
    }
}
