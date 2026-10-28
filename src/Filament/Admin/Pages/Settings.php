<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin\Pages;

use Filament\Forms\Components\ColorPicker;
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
use Illuminate\Support\HtmlString;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Support\Assets;
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
        PanelSettings::save($state);
        Notification::make()->title(__('pelican-simplify::settings.saved'))->success()->send();
    }

    /** @return list<Addon> */
    public function addons(): array
    {
        return app(AddonRegistry::class)->all();
    }
}
