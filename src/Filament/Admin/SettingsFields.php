<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Simplify\Core\Support\Brand;
use Simplify\Core\Support\PanelSettings;

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
        ]);
    }

    public static function brand(): Section
    {
        return Section::make(__(self::T.'appearance.brand'))->columns(2)->schema([
            TextInput::make('brand_name')->label(__(self::T.'appearance.brand_name'))
                ->placeholder(fn () => (string) config('app.name'))->maxLength(40)
                ->helperText(__(self::T.'appearance.brand_name_help')),
            TextInput::make('logo_height')->label(__(self::T.'appearance.logo_height'))
                ->integer()->minValue(16)->maxValue(64)->suffix('px')->required(),
            self::image('brand_logo', 'appearance.logo', 'appearance.logo_help')->columnSpanFull(),
        ]);
    }

    /** The installed app: its icon and whether it may send notifications. */
    public static function app(): Section
    {
        return Section::make(__(self::T.'appearance.app'))->description(__(self::T.'appearance.app_help'))->schema([
            self::image('app_icon', 'appearance.app_icon', 'appearance.app_icon_help')->imageCropAspectRatio('1:1'),
            self::image('app_badge', 'appearance.app_badge', 'appearance.app_badge_help')->acceptedFileTypes(['image/png']),
            Toggle::make('push')->label(__(self::T.'general.push_on'))->helperText(__(self::T.'general.push_help')),
        ]);
    }

    private static function image(string $name, string $label, string $help): FileUpload
    {
        return FileUpload::make($name)->label(__(self::T.$label))->helperText(__(self::T.$help))
            ->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->maxSize(1024)
            ->disk('local')->directory(Brand::DIRECTORY)->visibility('private');
    }
}
