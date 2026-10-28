<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin\Pages;

use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Filament\Admin\SettingsFields;
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
        // The first visit walks through the setup; the settings page is for changes afterwards.
        if (! PanelSettings::get('setup_done')) {
            $this->redirect(Setup::getUrl(panel: 'admin'));

            return;
        }
        $this->form->fill(PanelSettings::all());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setup')->label(__('pelican-simplify::setup.again'))->icon('tabler-wand')->color('gray')
                ->url(fn () => Setup::getUrl(panel: 'admin')),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make()->persistTabInQueryString()->columnSpanFull()->tabs([
                Tab::make(__('pelican-simplify::settings.general.tab'))->icon('tabler-adjustments')->schema([
                    SettingsFields::profile(),
                    SettingsFields::startPage(),
                    SettingsFields::refresh(),
                ]),
                Tab::make(__('pelican-simplify::settings.appearance.tab'))->icon('tabler-palette')->schema([
                    SettingsFields::colours(),
                    SettingsFields::brand(),
                    SettingsFields::app(),
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
        PanelSettings::saveForm($this->form->getState());
        Notification::make()->title(__('pelican-simplify::settings.saved'))->success()->send();
    }

    /** @return list<Addon> */
    public function addons(): array
    {
        return app(AddonRegistry::class)->all();
    }
}
