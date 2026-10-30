<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

    public static function colours(): Section
    {
        return Section::make(__(self::T.'appearance.colours'))->schema([
            ColorPicker::make('accent')->label(__(self::T.'appearance.accent'))
                ->helperText(__(self::T.'appearance.accent_help'))
                ->hex()->regex('/^#[0-9a-fA-F]{6}$/'),
            Toggle::make('server_colours')->label(__(self::T.'appearance.server_colours'))
                ->helperText(__(self::T.'appearance.server_colours_help')),
            Toggle::make('seasonal')->label(__(self::T.'appearance.seasonal'))
                ->helperText(__(self::T.'appearance.seasonal_help')),
        ]);
    }

    public static function brand(): Section
    {
        return Section::make(__(self::T.'appearance.brand'))->columns(2)->schema([
            TextInput::make('brand_name')->label(__(self::T.'appearance.brand_name'))->live(onBlur: true)
                ->placeholder(fn () => (string) config('app.name'))->maxLength(40)
                ->helperText(__(self::T.'appearance.brand_name_help')),
            TextInput::make('logo_height')->label(__(self::T.'appearance.logo_height'))->live(onBlur: true)
                ->integer()->minValue(16)->maxValue(64)->suffix('px')->required(),
            Toggle::make('pelican_suffix')->label(__(self::T.'appearance.pelican_suffix'))->columnSpanFull(),
            self::image('brand_logo', 'appearance.logo', 'appearance.logo_help')->columnSpanFull(),
            self::preview('logo', fn (Get $get) => [
                'image' => self::previewUrl($get('brand_logo')),
                'name' => trim((string) $get('brand_name')) ?: (string) config('app.name'),
                'height' => max(16, min(64, (int) $get('logo_height') ?: 32)),
            ]),
        ]);
    }

    /** The installed app: its icon and whether it may send notifications. */
    public static function app(): Section
    {
        return Section::make(__(self::T.'appearance.app'))->description(__(self::T.'appearance.app_help'))->schema([
            self::image('app_icon', 'appearance.app_icon', 'appearance.app_icon_help')->imageCropAspectRatio('1:1'),
            self::preview('icon', fn (Get $get) => ['icon' => self::appIcon($get)]),
            self::image('app_badge', 'appearance.app_badge', 'appearance.app_badge_help')->acceptedFileTypes(['image/png']),
            self::preview('badge', fn (Get $get) => [
                'icon' => self::appIcon($get),
                'image' => self::previewUrl($get('app_badge')) ?? WebApp::badge(),
            ]),
            Toggle::make('push')->label(__(self::T.'general.push_on'))->helperText(__(self::T.'general.push_help')),
        ]);
    }

    /** The banners people uploaded for their servers, to remove one that should not be there. Settings page only. */
    public static function banners(): Section
    {
        return Section::make(__(self::T.'appearance.banners'))->schema([
            View::make('simplify::partials.server-banners'),
        ]);
    }

    private static function image(string $name, string $label, string $help): FileUpload
    {
        return FileUpload::make($name)->live()->label(__(self::T.$label))->helperText(__(self::T.$help))
            ->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->maxSize(1024)
            ->disk('local')->directory(Brand::DIRECTORY)->visibility('private');
    }

    /** A small picture of where an image ends up: the top bar, a phone's home screen or Android's status bar. */
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
