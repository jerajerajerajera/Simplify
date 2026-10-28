<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin\Pages;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Support\Assets;
use Simplify\Core\Support\Brand;
use Simplify\Core\Support\PanelSettings;

/**
 * Simplify's settings for the whole panel, in a few categories. Add-ons list their own settings page here.
 */
final class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'simplify';

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-layout-dashboard';

    protected string $view = 'pelican-simplify::pages.settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public static function getNavigationLabel(): string
    {
        return __('pelican-simplify::settings.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pelican-simplify::settings.title');
    }

    public function getHeading(): string|Htmlable
    {
        return new HtmlString(sprintf(
            '<span class="s-heading"><img class="s-logo" src="%s" alt="">%s</span>',
            e(Assets::url('logo.svg')),
            e(__('pelican-simplify::settings.title')),
        ));
    }

    public function mount(): void
    {
        $this->form->fill(PanelSettings::all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make()->persistTabInQueryString()->columnSpanFull()->tabs([
                Tab::make(__('pelican-simplify::settings.general.tab'))->icon('tabler-adjustments')->schema([
                    Section::make(__('pelican-simplify::settings.general.refresh'))->schema([
                        TextInput::make('overview_seconds')->label(__('pelican-simplify::settings.general.overview_seconds'))
                            ->helperText(__('pelican-simplify::settings.general.overview_seconds_help'))
                            ->integer()->minValue(5)->maxValue(300)->suffix('s')->required(),
                    ]),
                ]),
                Tab::make(__('pelican-simplify::settings.appearance.tab'))->icon('tabler-palette')->schema([
                    Section::make(__('pelican-simplify::settings.appearance.colours'))->schema([
                        ColorPicker::make('accent')->label(__('pelican-simplify::settings.appearance.accent'))
                            ->helperText(__('pelican-simplify::settings.appearance.accent_help'))
                            ->hex()->regex('/^#[0-9a-fA-F]{6}$/'),
                    ]),
                    Section::make(__('pelican-simplify::settings.appearance.brand'))->columns(2)->schema([
                        TextInput::make('brand_name')->label(__('pelican-simplify::settings.appearance.brand_name'))
                            ->placeholder(fn () => (string) config('app.name'))->maxLength(40)
                            ->helperText(__('pelican-simplify::settings.appearance.brand_name_help')),
                        TextInput::make('logo_height')->label(__('pelican-simplify::settings.appearance.logo_height'))
                            ->integer()->minValue(16)->maxValue(64)->suffix('px')->required(),
                        FileUpload::make('brand_logo')->label(__('pelican-simplify::settings.appearance.logo'))
                            ->helperText(__('pelican-simplify::settings.appearance.logo_help'))
                            ->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->maxSize(1024)
                            ->disk('local')->directory(Brand::DIRECTORY)->visibility('private')->columnSpanFull(),
                    ]),
                    Section::make(__('pelican-simplify::settings.appearance.app'))
                        ->description(__('pelican-simplify::settings.appearance.app_help'))->schema([
                            FileUpload::make('app_icon')->label(__('pelican-simplify::settings.appearance.app_icon'))
                                ->helperText(__('pelican-simplify::settings.appearance.app_icon_help'))
                                ->image()->imageCropAspectRatio('1:1')->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                ->maxSize(1024)->disk('local')->directory(Brand::DIRECTORY)->visibility('private'),
                        ]),
                ]),
                Tab::make(__('pelican-simplify::settings.addons.heading'))->icon('tabler-puzzle')->schema([
                    View::make('pelican-simplify::partials.addon-list'),
                ]),
            ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $state = $this->form->getState();
        $state['accent'] = ($state['accent'] ?? '') === '' ? null : strtolower($state['accent']);
        $state['brand_name'] = trim((string) ($state['brand_name'] ?? '')) ?: null;
        $previous = [];
        foreach (['brand_logo', 'app_icon'] as $file) {
            $state[$file] = ($state[$file] ?? null) ?: null;
            $previous[$file] = PanelSettings::get($file);
        }
        PanelSettings::save($state);
        // A replaced or removed image is not used anywhere else.
        foreach ($previous as $file => $old) {
            if ($old !== null && $old !== $state[$file]) {
                Storage::disk('local')->delete($old);
            }
        }
        Notification::make()->title(__('pelican-simplify::settings.saved'))->success()->send();
    }

    /** @return list<Addon> */
    public function addons(): array
    {
        return app(AddonRegistry::class)->all();
    }
}
