<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\Server;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Services\Bell;
use Simplify\Core\Announcements\Services\InGame;
use Simplify\Core\Announcements\Services\Push;
use Simplify\Core\Announcements\Services\WeeklyAnnouncements;
use Simplify\Core\Announcements\Support\FormData;
use Simplify\Core\Announcements\Support\GameChat;
use Simplify\Core\Announcements\Support\Rules;
use Simplify\Core\Announcements\Support\StartTrigger;
use Simplify\Core\Announcements\Support\Summary;
use Simplify\Core\Announcements\Support\Weekly;
use Throwable;

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

    /** The fields the sentence "What this does" reads. */
    private const SUMMARY_FIELDS = ['when', 'often', 'more', 'stop_after', 'stay_n', 'stay_unit', 'starts_at', 'ends_at', 'weekly_days', 'weekly_times', 'start_event', 'start_servers', 'start_from', 'start_until', 'bar', 'stay', 'in_bell', 'bell_pinned', 'push', 'in_game', 'audience'];

    public static function form(Schema $schema): Schema
    {
        $zone = self::timezone();
        $when = fn (string ...$whens) => fn (Get $get): bool => in_array($get('when'), $whens, true);
        $server = $when(FormData::SERVER);
        $weekly = $when(FormData::WEEKLY);
        $repeats = $when(FormData::WEEKLY, FormData::SERVER);
        $more = fn (Get $get): bool => $repeats($get) && (bool) $get('more');
        $bar = fn (Get $get): bool => (bool) $get('bar');
        $heading = fn (string $key) => View::make('simplify::announcements.heading')->viewData(['label' => self::text('form.'.$key)]);
        $shown = fn (?Announcement $record): array => $record ? FormData::fill($record->attributesToArray()) : [];
        // A click on the choice that is already on leaves it on: it emptied the field, and Save then refused.
        $keep = fn (string $field) => function (Set $set, mixed $state, mixed $old) use ($field): void {
            if ($state === null && $old !== null) {
                $set($field, $old);
            }
        };

        // Four questions (V65): what, when, where and who. Every place works with every moment; an example fills in
        // the answers, and the sentence under the examples says what the announcement will do.
        return $schema->columns(1)->components([
            ToggleButtons::make('example')
                ->label(self::text('form.example'))
                ->options(array_map(fn (string $key): string => self::text('form.examples.'.$key), array_combine(array_keys(FormData::examples()), array_keys(FormData::examples()))))
                ->inline()
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function (Set $set, ?string $state) use ($zone): void {
                    foreach (FormData::examples()[$state] ?? [] as $field => $value) {
                        $set($field, $value);
                    }
                    if ($state === 'maintenance') {
                        // Tonight at ten, or tomorrow when that has passed.
                        $tonight = Carbon::now($zone)->setTime(22, 0);
                        $set('starts_at', ($tonight->isPast() ? $tonight->addDay() : $tonight)->format('Y-m-d H:i:s'));
                    }
                })
                ->helperText(fn (Get $get): string => self::text('summary.prefix').' '.self::summary(array_combine(self::SUMMARY_FIELDS, array_map(fn (string $field): mixed => $get($field), self::SUMMARY_FIELDS)), $zone, stored: false)),

            $heading('what'),
            ToggleButtons::make('category')
                ->label(self::text('form.category'))
                ->options(self::options('category', Rules::CATEGORIES))
                ->colors(array_combine(Rules::CATEGORIES, array_map(Rules::color(...), Rules::CATEGORIES)))
                ->icons(array_combine(Rules::CATEGORIES, array_map(Rules::icon(...), Rules::CATEGORIES)))
                ->inline()
                ->required()
                ->default('info')
                ->live()
                ->afterStateUpdated($keep('category')),
            TextInput::make('title')
                ->label(self::text('form.title'))
                ->required()
                ->maxLength(Rules::TITLE_LENGTH),
            Textarea::make('body')
                ->label(self::text('form.body'))
                ->rows(3)
                ->maxLength(Rules::BODY_LENGTH),
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

            $heading('when'),
            // Now, at a date, every week on chosen days (Khion 15-11), or when a server starts (Khion 6-11), stops or
            // crashes. Stored in send_on by FormData::clean().
            ToggleButtons::make('when')
                ->hiddenLabel()
                ->options([
                    FormData::NOW => self::text('form.when_now'),
                    FormData::DATE => self::text('form.when_date'),
                    FormData::WEEKLY => self::text('form.when_weekly'),
                    FormData::SERVER => self::text('form.when_server'),
                ])
                ->inline()
                ->required()
                ->default(FormData::NOW)
                ->afterStateHydrated(fn (ToggleButtons $component, ?Announcement $record) => $record ? $component->state($shown($record)['when']) : null)
                ->live()
                ->afterStateUpdated($keep('when')),
            DateTimePicker::make('starts_at')
                ->label(self::text('form.when_date'))
                ->visible($when(FormData::DATE))
                ->required($when(FormData::DATE))
                ->seconds(false)
                ->timezone($zone),
            ToggleButtons::make('start_event')
                ->hiddenLabel()
                ->visible($server)
                ->options(array_combine(StartTrigger::EVENTS, array_map(fn (string $event): string => self::text('form.event.'.$event), StartTrigger::EVENTS)))
                ->helperText(fn (Get $get): ?string => $get('start_event') === 'crash' ? self::text('form.crash_help') : null)
                ->inline()
                ->default(StartTrigger::START)
                ->live()
                ->afterStateUpdated($keep('start_event')),
            Select::make('start_servers')
                ->label(self::text('form.start_servers'))
                ->visible($server)
                ->multiple()
                ->required($server)
                ->maxItems(StartTrigger::SERVERS)
                ->searchable()
                ->live()
                ->getSearchResultsUsing(fn (string $search): array => Server::query()->where('name', 'like', '%'.$search.'%')->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                ->getOptionLabelsUsing(fn (array $values): array => Server::query()->whereKey($values)->pluck('name', 'id')->all()),
            // Every week: the days it goes out on. A server moment: only moments on these days count, none is every
            // day (More options).
            ToggleButtons::make('weekly_days')
                ->label(fn (Get $get): string => self::text($server($get) ? 'form.only_on' : 'form.weekly_days'))
                ->helperText(fn (Get $get): ?string => $server($get) ? self::text('form.only_on_help') : null)
                ->visible(fn (Get $get): bool => $weekly($get) || ($server($get) && $more($get)))
                ->options(self::weekdays())
                ->multiple()
                ->inline()
                ->live()
                ->required($weekly),
            // The author's own clock: 19:00 stays 19:00 in winter and in summer. One time or several.
            Select::make('weekly_times')
                ->label(self::text('form.weekly_at'))
                ->helperText(fn (Get $get): string => self::text('form.weekly_at_help', ['zone' => Weekly::zone($get('weekly_zone') ?: $zone)]))
                ->visible($weekly)
                ->required($weekly)
                ->multiple()
                ->maxItems(Weekly::TIMES)
                ->searchable()
                ->optionsLimit(100)
                ->live()
                ->options(fn (?Announcement $record): array => self::times($record))
                ->afterStateHydrated(fn (Select $component, ?Announcement $record) => $record ? $component->state(Weekly::times($record->weekly_times, $record->weekly_at)) : null),
            // How often one that comes back goes out: every time, the first time of each day per server (Khion 22-11),
            // or only the next time.
            ToggleButtons::make('often')
                ->label(self::text('form.in_game_repeat'))
                ->visible($repeats)
                ->options(fn (Get $get): array => $server($get)
                    ? array_combine([FormData::EVERY, FormData::DAY, FormData::ONCE], array_map(fn (string $often): string => self::text('form.often.'.StartTrigger::event($get('start_event')).'_'.$often), [FormData::EVERY, FormData::DAY, FormData::ONCE]))
                    : [FormData::EVERY => self::text('form.often.weekly_every'), FormData::ONCE => self::text('form.often.weekly_once')])
                ->inline()
                ->default(FormData::EVERY)
                ->afterStateHydrated(fn (ToggleButtons $component, ?Announcement $record) => $record ? $component->state($shown($record)['often']) : null)
                ->live()
                ->afterStateUpdated($keep('often')),
            Toggle::make('more')
                ->label(self::text('form.more'))
                ->helperText(self::text('form.more_help'))
                ->visible($repeats)
                ->afterStateHydrated(fn (Toggle $component, ?Announcement $record) => $record ? $component->state($shown($record)['more']) : null)
                ->live(),
            // Only moments in this part of the day count; either end may stay empty (Khion 22-11).
            Grid::make(2)->visible(fn (Get $get): bool => $server($get) && $more($get))->schema([
                TimePicker::make('start_from')
                    ->label(self::text('form.start_from'))
                    ->seconds(false)
                    ->live()
                    ->helperText(self::text('form.only_hours_help')),
                TimePicker::make('start_until')
                    ->label(self::text('form.start_until'))
                    ->live()
                    ->seconds(false),
            ]),
            DateTimePicker::make('stop_after')
                ->label(self::text('form.stop_after'))
                ->helperText(self::text('form.ends_at_help'))
                ->visible($more)
                ->seconds(false)
                ->timezone($zone)
                ->live()
                ->afterStateHydrated(fn (DateTimePicker $component, ?Announcement $record) => $record ? $component->state($shown($record)['stop_after']) : null),
            // Kept when another admin edits it, so a time and the days keep meaning the same moment.
            Hidden::make('weekly_zone')
                ->default($zone)
                ->dehydrateStateUsing(fn ($state): string => Weekly::zone($state ?: $zone)),
            View::make('simplify::announcements.zone-hint')->viewData(['zone' => $zone, 'profile' => EditProfile::getUrl(panel: 'app')]),

            $heading('where'),
            Toggle::make('bar')
                ->label(self::text('form.bar'))
                ->helperText(self::text('form.bar_help'))
                ->default(true)
                ->live(),
            // How long the bar stays, each time it goes out. "Until a date" is for one at a time.
            ToggleButtons::make('stay')
                ->label(self::text('form.stay'))
                ->visible($bar)
                ->options(fn (Get $get): array => array_filter([
                    'for' => self::text('form.stays.for'),
                    'until' => $repeats($get) ? null : self::text('form.stays.until'),
                    'ended' => self::text('form.stays.ended'),
                ]))
                ->inline()
                ->default('ended')
                ->afterStateHydrated(fn (ToggleButtons $component, ?Announcement $record) => $record ? $component->state($record->stay === 'until' && $record->send_on !== StartTrigger::TIME ? 'ended' : Rules::stay($record->stay)) : null)
                ->live()
                ->afterStateUpdated($keep('stay')),
            Grid::make(2)->visible(fn (Get $get): bool => $bar($get) && $get('stay') === 'for')->schema([
                TextInput::make('stay_n')
                    ->label(self::text('form.stay_n'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(999)
                    ->required()
                    ->default(3)
                    ->afterStateHydrated(fn (TextInput $component, ?Announcement $record) => $record ? $component->state($shown($record)['stay_n']) : null)
                    ->live(onBlur: true),
                Select::make('stay_unit')
                    ->label(self::text('form.stay_unit'))
                    ->options(array_combine(array_keys(Rules::UNITS), array_map(fn (string $unit): string => self::text('form.units.'.$unit), array_keys(Rules::UNITS))))
                    ->default('hours')
                    ->selectablePlaceholder(false)
                    ->afterStateHydrated(fn (Select $component, ?Announcement $record) => $record ? $component->state($shown($record)['stay_unit']) : null)
                    ->live(),
            ]),
            DateTimePicker::make('ends_at')
                ->label(self::text('form.ends_at'))
                ->visible(fn (Get $get): bool => $bar($get) && $get('stay') === 'until' && ! $repeats($get))
                ->required(fn (Get $get): bool => $bar($get) && $get('stay') === 'until' && ! $repeats($get))
                ->seconds(false)
                ->timezone($zone)
                ->live()
                // After its start, when it has one: "Now" has no start to compare with.
                ->rule(fn (Get $get) => function (string $attribute, mixed $value, $fail) use ($get): void {
                    $start = $get('when') === FormData::DATE ? strtotime((string) $get('starts_at')) : false;
                    if ($start !== false && strtotime((string) $value) <= $start) {
                        $fail(__('validation.after', ['attribute' => self::text('form.ends_at'), 'date' => self::text('form.when_date')]));
                    }
                }),
            Toggle::make('in_bell')
                ->label(self::text('table.in_bell'))
                ->helperText(self::text('form.in_bell_help'))
                ->live(),
            Toggle::make('bell_pinned')
                ->label(self::text('form.bell_pinned'))
                ->live()
                ->visible(fn (Get $get): bool => (bool) $get('in_bell') && $bar($get)),
            Toggle::make('push')
                ->label(self::text('table.push'))
                ->helperText(self::text('form.push_help'))
                ->default(true)
                ->live()
                ->visible(Push::available()),
            Toggle::make('in_game')
                ->label(self::text('table.in_game'))
                ->helperText(self::text('form.in_game_help'))
                ->live(),
            // A server moment: the chat of that server, or of chosen servers as one at a time.
            ToggleButtons::make('in_game_target')
                ->label(self::text('form.in_game_target'))
                ->visible(fn (Get $get): bool => (bool) $get('in_game') && $server($get))
                ->options(array_combine(FormData::TARGETS, array_map(fn (string $target): string => self::text('form.targets.'.$target), FormData::TARGETS)))
                ->inline()
                ->default('started')
                ->afterStateHydrated(fn (ToggleButtons $component, ?Announcement $record) => $record ? $component->state($record->in_game_target === 'servers' ? 'servers' : 'started') : null)
                ->live()
                ->afterStateUpdated($keep('in_game_target')),
            Grid::make(2)->visible(fn (Get $get): bool => (bool) $get('in_game') && (! $server($get) || $get('in_game_target') === 'servers'))->schema([
                Select::make('in_game_servers')
                    ->label(self::text('form.in_game_servers'))
                    ->placeholder(self::text('form.in_game_servers_all'))
                    ->helperText(self::text('form.in_game_servers_help'))
                    ->multiple()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Server::query()->where('name', 'like', '%'.$search.'%')->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                    ->getOptionLabelsUsing(fn (array $values): array => Server::query()->whereKey($values)->pluck('name', 'id')->all()),
                Select::make('in_game_groups')
                    ->label(self::text('form.in_game_groups'))
                    ->helperText(self::text('form.in_game_groups_help'))
                    ->multiple()
                    ->options(fn (?Announcement $record): array => InGame::groups($record?->author ?? user()))
                    ->visible(fn (?Announcement $record): bool => InGame::groups($record?->author ?? user()) !== []),
            ]),
            // Again in the chat while the bar stays.
            Select::make('in_game_repeat')
                ->label(self::text('form.in_game_repeat'))
                ->visible(fn (Get $get): bool => (bool) $get('in_game') && $bar($get))
                ->options(self::repeats())
                ->default(0)
                ->selectablePlaceholder(false),

            $heading('who'),
            ToggleButtons::make('audience')
                ->hiddenLabel()
                ->options(fn (Get $get): array => array_filter(self::options('audience', Rules::AUDIENCES), fn (string $audience): bool => $audience !== Rules::SERVER_AUDIENCE || $server($get), ARRAY_FILTER_USE_KEY))
                ->helperText(fn (Get $get): ?string => $get('audience') === Rules::SERVER_AUDIENCE ? self::text('form.audience_server_help') : null)
                ->inline()
                ->required()
                ->default('everyone')
                ->live()
                ->afterStateUpdated($keep('audience')),
        ]);
    }

    /**
     * What the form's Save stores (create and edit): the form's own fields become the columns the scheduler reads.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function store(array $data): array
    {
        return FormData::clean($data);
    }

    /**
     * "What this does", in the admin's language.
     *
     * @param  array<string, mixed>  $state  the form's state, or FormData::fill() merged into a stored announcement
     * @param  bool  $stored  times of a stored announcement are UTC; the form holds them in the admin's time zone
     */
    public static function summary(array $state, string $zone, bool $stored = true): string
    {
        $date = function (mixed $value) use ($zone, $stored): string {
            try {
                $at = $stored ? Carbon::parse($value)->setTimezone($zone) : Carbon::parse((string) $value, $zone);

                return $at->locale(app()->getLocale())->isoFormat('lll');
            } catch (Throwable) {
                return '…';
            }
        };

        return Summary::text($state, fn (string $key, array $replace): string => self::text('summary.'.$key, $replace), [
            'days' => self::weekdays(),
            'servers' => Server::query()->whereKey(StartTrigger::servers($state['start_servers'] ?? null))->pluck('name', 'id')->all(),
            'date' => $date,
        ]);
    }

    /**
     * Every quarter of an hour, and the times this announcement already has.
     *
     * @return array<string, string>
     */
    private static function times(?Announcement $record): array
    {
        $times = [];
        foreach (range(0, 95) as $quarter) {
            $times[] = sprintf('%02d:%02d', intdiv($quarter, 4), $quarter % 4 * 15);
        }
        $times = [...$times, ...Weekly::times($record?->weekly_times, $record?->weekly_at)];
        sort($times);

        return array_combine($times, $times);
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
                    // What it does, in the same sentence as under the form's examples.
                    TextColumn::make('send_on')
                        ->state(fn (Announcement $record): string => self::summary([...$record->attributesToArray(), ...FormData::fill($record->attributesToArray()), 'weekly_times' => Weekly::times($record->weekly_times, $record->weekly_at)], $zone))
                        ->color('gray')
                        ->wrap(),
                    Split::make([
                        TextColumn::make('state')
                            // One without a bar that went out has nothing left to show: "Sent", not "Live".
                            ->state(fn (Announcement $record): string => $record->state() === 'live' && $record->send_on === StartTrigger::TIME && ! $record->bar ? 'sent' : $record->state())
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'live' => 'success',
                                'scheduled', 'sent' => 'info',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => self::text('state.'.$state))
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
                EditAction::make()->mutateDataUsing(fn (array $data): array => self::store($data))->after(fn () => self::deliverNow()),
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
        WeeklyAnnouncements::runDue();
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

    /**
     * Monday to Sunday, short, in the panel's language.
     *
     * @return array<int, string>
     */
    public static function weekdays(): array
    {
        $names = [];
        foreach (range(1, 7) as $day) {
            $names[$day] = Carbon::parse('2026-11-'.(8 + $day))->locale(app()->getLocale())->isoFormat('ddd');
        }

        return $names;
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
