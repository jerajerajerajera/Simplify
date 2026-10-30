<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Simplify\Core\Support\Assets;
use Simplify\Core\Support\Brand;
use Simplify\Core\Support\PanelSettings;
use Simplify\Core\WebApp\WebApp;
use Throwable;

/** The settings sections, shared by the settings page and the setup wizard so both ask the same thing the same way. */
final class SettingsFields
{
    private const T = 'simplify::settings.';

    /** Ready-made accent colours, soft enough for buttons with dark text in light and dark mode. */
    public const PASTELS = [
        'rose' => '#f4a6b8', 'coral' => '#f39c8f', 'peach' => '#f7b98b', 'butter' => '#f2d67e', 'sage' => '#b5cf9a', 'mint' => '#9ed9b5',
        'aqua' => '#8fd3d1', 'sky' => '#94c9ef', 'periwinkle' => '#a9b4f2', 'lavender' => '#c3a8ee', 'lilac' => '#e0a8e0',
    ];

    /** Help for everyone on the panel. Hosting details (billing, client area) come from the Simplify Hosting add-on. */
    public static function support(): Section
    {
        return Section::make(__(self::T.'profile.support'))->schema([
            TextInput::make('support_url')->hiddenLabel()->maxLength(255)
                ->regex(PanelSettings::SUPPORT_URL)->placeholder('https://example.com/support')
                ->helperText(__(self::T.'profile.support_help')),
        ]);
    }

    public static function startPage(): Section
    {
        return Section::make(__(self::T.'start.heading'))->schema([
            Radio::make('start_page')->hiddenLabel()->required()
                ->options(['overview' => __(self::T.'start.overview'), 'pelican' => __(self::T.'start.pelican')])
                ->descriptions(['overview' => __(self::T.'start.overview_help'), 'pelican' => __(self::T.'start.pelican_help')]),
        ]);
    }

    /** Friends mode: everyone signed in sees every server's card; opening one still needs Pelican's access. */
    public static function visibility(): Section
    {
        return Section::make(__(self::T.'general.visibility'))->schema([
            Toggle::make('friends_mode')->label(__(self::T.'general.friends_mode'))
                ->helperText(__(self::T.'general.friends_mode_help')),
        ]);
    }

    /** Setup only: picks friends mode for the panel's audience. Not stored; friends mode itself is the setting. */
    public static function panelType(): Section
    {
        return Section::make(__(self::T.'general.panel_type'))->description(__(self::T.'general.panel_type_help'))->schema([
            Radio::make('panel_type')->hiddenLabel()->dehydrated(false)->live()
                ->options(['friends' => __(self::T.'general.panel_friends'), 'hosting' => __(self::T.'general.panel_hosting')])
                ->descriptions(['friends' => __(self::T.'general.panel_friends_help'), 'hosting' => __(self::T.'general.panel_hosting_help')])
                ->afterStateUpdated(fn (?string $state, Set $set) => $set('friends_mode', $state === 'friends')),
        ]);
    }

    public static function refresh(): Section
    {
        return Section::make(__(self::T.'general.refresh'))->schema([
            TextInput::make('overview_seconds')->label(__(self::T.'general.overview_seconds'))
                ->helperText(__(self::T.'general.overview_seconds_help'))
                ->integer()->minValue(5)->maxValue(300)->suffix('s')->required(),
        ]);
    }

    /**
     * The Look: the accent colour, then the name and logo, then the name's style, with one preview beside them that
     * stays in view while they scroll. Without a logo the top bar shows the name, so "what shows" only asks with one.
     */
    public static function look(): Section
    {
        $hasLogo = fn (Get $get) => self::previewUrl($get('brand_logo')) !== null;

        return Section::make(__(self::T.'appearance.look'))->columns(['lg' => 3])->extraAttributes(['class' => 's-look'])->schema([
            Group::make([
                self::heading('appearance.colours'),
                ViewField::make('accent')->view('simplify::fields.accent')->label(__(self::T.'appearance.accent'))
                    ->helperText(__(self::T.'appearance.accent_help'))->rules(['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'])
                    ->viewData(['pastels' => self::PASTELS]),
                Toggle::make('server_colours')->label(__(self::T.'appearance.server_colours'))
                    ->helperText(__(self::T.'appearance.server_colours_help')),
                Toggle::make('seasonal')->label(__(self::T.'appearance.seasonal'))
                    ->helperText(__(self::T.'appearance.seasonal_help')),
                self::heading('appearance.brand'),
                Grid::make(['sm' => 2])->schema([
                    Group::make([
                        TextInput::make('brand_name')->label(__(self::T.'appearance.brand_name'))
                            ->placeholder(fn () => (string) config('app.name'))->maxLength(40)
                            ->helperText(__(self::T.'appearance.brand_name_help')),
                        Toggle::make('pelican_suffix')->label(__(self::T.'appearance.pelican_suffix')),
                    ]),
                    Group::make([
                        self::image('brand_logo', 'appearance.logo', 'appearance.logo_help'),
                        ViewField::make('logo_height')->view('simplify::fields.logo-size')->label(__(self::T.'appearance.logo_size'))
                            ->rules(['required', 'integer', 'min:16', 'max:64'])->visible($hasLogo)
                            ->viewData(fn (Get $get) => ['image' => self::previewUrl($get('brand_logo'))]),
                    ])->extraAttributes(['class' => 's-look-logo']),
                ]),
                self::chips('brand_show', 'appearance.show', 'logo,text,both', 'appearance.shows.', live: true)->visible($hasLogo),
                ViewField::make('brand_font')->view('simplify::fields.brand-style')->label(__(self::T.'appearance.text_style'))
                    ->rules(['required', 'in:'.implode(',', array_keys(Brand::FONTS))])
                    ->viewData(['fallback' => (string) config('app.name')])
                    ->visible(fn (Get $get) => ! $hasLogo($get) || $get('brand_show') !== 'logo'),
                // The name's style itself, set by the field above.
                Hidden::make('brand_weight')->rules(['required', 'integer', 'in:400,500,600,700,800,900']),
                Hidden::make('brand_colour')->rules(['required', 'in:solid,accent,gradient']),
                Hidden::make('brand_colour1')->rules(['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']),
                Hidden::make('brand_colour2')->rules(['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']),
                Hidden::make('brand_effect')->rules(['required', 'in:plain,glow,shadow']),
                Hidden::make('brand_spacing')->rules(['required', 'integer', 'in:'.implode(',', Brand::SPACINGS)]),
                Hidden::make('brand_uppercase')->rules(['boolean']),
            ])->columnSpan(['lg' => 2])->extraAttributes(['class' => 's-look-fields']),
            View::make('simplify::partials.look-preview')->viewData(fn (Get $get) => [
                'fallback' => (string) config('app.name'),
                'image' => self::previewUrl($get('brand_logo')),
            ]),
        ]);
    }

    /** The installed app on a phone: its icons and whether it may send notifications. */
    public static function app(): Section
    {
        return Section::make(__(self::T.'appearance.app'))->description(__(self::T.'appearance.app_help'))->schema([
            self::image('app_icon', 'appearance.app_icon', 'appearance.app_icon_help', 'icon')->imageCropAspectRatio('1:1'),
            self::image('app_badge', 'appearance.app_badge', 'appearance.app_badge_help', 'badge')->acceptedFileTypes(['image/png']),
            Toggle::make('push')->label(__(self::T.'general.push_on'))->helperText(__(self::T.'general.push_help')),
            Group::make([
                self::preview('icon', fn (Get $get) => ['icon' => self::appIcon($get)]),
                self::preview('badge', fn (Get $get) => [
                    'icon' => self::appIcon($get),
                    'image' => self::previewUrl($get('app_badge')) ?? WebApp::badge(),
                ]),
            ])->extraAttributes(['class' => 's-preview-row']),
        ]);
    }

    /** The banners people uploaded for their servers, to remove one that should not be there. Settings page only. */
    public static function banners(): Section
    {
        return Section::make(__(self::T.'appearance.banners'))->schema([
            View::make('simplify::partials.server-banners'),
        ]);
    }

    /**
     * A row of small buttons to pick one of a few values, such as a font or a logo height.
     *
     * @param  ?string  $labels  translation prefix for each value, or null to show the value in pixels
     */
    private static function chips(string $name, string $label, string $values, ?string $labels, bool $live = false): ViewField
    {
        $options = [];
        foreach (explode(',', $values) as $value) {
            $options[$value] = $labels === null ? $value.' px' : __(self::T.$labels.$value);
        }

        return ViewField::make($name)->view('simplify::fields.chips')->label(__(self::T.$label))
            ->rules(['required', 'in:'.$values])->viewData(['options' => $options, 'live' => $live]);
    }

    private static function heading(string $label): View
    {
        return View::make('simplify::partials.look-heading')->viewData(['label' => __(self::T.$label)]);
    }

    /**
     * A small square upload with a "+", after a tile for what is used without an upload (the Simplify icon), if
     * there is one. An upload is the choice; the tile removes it again (upload-tile.blade.php).
     */
    private static function image(string $name, string $label, string $help, ?string $default = null): FileUpload
    {
        $upload = FileUpload::make($name)->live()->label(__(self::T.$label))->helperText(__(self::T.$help))
            ->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->maxSize(1024)
            ->disk('local')->directory(Brand::DIRECTORY)->visibility('private')
            ->panelLayout('integrated')->panelAspectRatio('1:1')->imagePreviewHeight('64')
            ->removeUploadedFileButtonPosition('right top')->uploadProgressIndicatorPosition('center bottom')
            ->placeholder(svg('tabler-plus', 's-tile-plus')->toHtml().'<span class="fi-sr-only">'.e(__(self::T.'appearance.upload')).'</span>')
            ->extraFieldWrapperAttributes(['class' => 's-tile-field']);

        return $default === null ? $upload : $upload->beforeContent(View::make('simplify::partials.upload-tile')->viewData(['kind' => $default]));
    }

    /** A small picture of where an app image ends up: a phone's home screen or Android's status bar. */
    private static function preview(string $kind, callable $data): View
    {
        return View::make('simplify::partials.image-preview')->columnSpanFull()
            ->viewData(fn (Get $get) => ['kind' => $kind, 'name' => WebApp::name()] + $data($get));
    }

    private static function appIcon(Get $get): string
    {
        return self::previewUrl($get('app_icon')) ?? Assets::url('app-icon-192.png');
    }

    /** The image in an upload field: a fresh upload before saving, or the saved file. */
    private static function previewUrl(mixed $state): ?string
    {
        foreach ((array) $state as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                try {
                    return $file->temporaryUrl();
                } catch (Throwable) {
                    return null;
                }
            }
            if (is_string($file) && ($name = Brand::logoFile($file)) !== null) {
                return url('/simplify/brand/'.$name);
            }
        }

        return null;
    }
}
