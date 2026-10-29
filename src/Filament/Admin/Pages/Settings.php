<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin\Pages;

use App\Filament\Admin\Pages\Settings as PelicanSettings;
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

    protected static ?int $navigationSort = 1;

    protected string $view = 'pelican-simplify::pages.settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    /** Simplify's own group in Pelican's menu, shared with the add-ons (ADDONS.md, "Admin menu"). */
    public static function getNavigationGroup(): ?string
    {
        return 'Simplify';
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
        return self::logoHeading(__('pelican-simplify::settings.page_title'));
    }

    /** A page title beside Simplify's logo, as on the settings page and the setup. */
    public static function logoHeading(string $title): HtmlString
    {
        return new HtmlString(sprintf(
            '<span class="s-heading"><span class="s-logo-tile"><img class="s-logo" src="%s" alt=""></span>%s</span>',
            e(Assets::url('logo.svg')),
            e($title),
        ));
    }

    public function getSubheading(): string
    {
        return __('pelican-simplify::settings.version_line', ['version' => Assets::version()]);
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
            Action::make('setup')->label(__('pelican-simplify::setup.again'))->icon('tabler-wand')->color('gray')->button()
                ->url(fn () => Setup::getUrl(panel: 'admin')),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $pelican = array_map(fn (array $tab) => Tab::make($tab['label'])->key($tab['key'])->icon($tab['icon'])->schema([
            View::make('pelican-simplify::partials.pelican-settings')->viewData($tab),
        ]), self::pelicanTabs());

        return $schema->statePath('data')->components([
            Tabs::make()->vertical()->persistTabInQueryString()->columnSpanFull()->extraAttributes(['class' => 's-settings-tabs'])->tabs([
                Tab::make(__('pelican-simplify::settings.general.tab'))->key('general')->icon('tabler-adjustments')->schema([
                    SettingsFields::support(),
                    SettingsFields::startPage(),
                    SettingsFields::refresh(),
                ]),
                Tab::make(__('pelican-simplify::settings.appearance.tab'))->key('appearance')->icon('tabler-palette')->schema([
                    SettingsFields::colours(),
                    SettingsFields::brand(),
                    SettingsFields::app(),
                ]),
                Tab::make(__('pelican-simplify::settings.addons.heading'))->key('addons')->icon('tabler-puzzle')->schema([
                    View::make('pelican-simplify::partials.addon-list'),
                ]),
                ...$pelican,
            ]),
        ]);
    }

    /**
     * The settings as leaves of host mode's menu tree: Simplify's tabs, each add-on's own page and Pelican's tabs.
     *
     * @return list<array{key: string, group: string, label: string, icon: string, url: string}>
     */
    public static function menu(): array
    {
        $tab = fn (string $key, string $group, string $label, string $icon) => ['key' => 'settings:'.$key, 'group' => $group, 'label' => $label, 'icon' => $icon, 'url' => self::getUrl(['tab' => $key], panel: 'admin')];
        $items = [
            $tab('general', 'panel', __('pelican-simplify::settings.general.tab'), 'settings'),
            $tab('appearance', 'panel', __('pelican-simplify::settings.appearance.tab'), 'palette'),
            $tab('addons', 'addons', __('pelican-simplify::settings.addons.heading'), 'puzzle'),
        ];
        foreach (self::addonRows() as $addon) {
            if ($addon['url']) {
                $items[] = ['key' => 'addon:'.$addon['id'], 'group' => 'addons', 'label' => $addon['name'], 'icon' => 'plugins', 'url' => $addon['url']];
            }
        }
        foreach (self::pelicanTabs() as $pelican) {
            $items[] = [...$tab($pelican['key'], 'pelican', $pelican['label'], 'gear'), 'url' => $pelican['url']];
        }

        return $items;
    }

    /**
     * Pelican's own settings close the menu: each tab shows Pelican's page on that tab, saved with Pelican's button.
     *
     * @return list<array{key: string, label: string, icon: string, url: string}>
     */
    public static function pelicanTabs(): array
    {
        if (! PelicanSettings::canAccess()) {
            return [];
        }
        $icons = ['general' => 'home', 'captcha' => 'shield', 'mail' => 'mail', 'backup' => 'box', 'oauth' => 'brand-oauth', 'misc' => 'tool'];

        return array_map(fn (string $key, string $icon) => [
            'key' => 'pelican-'.$key,
            'label' => (string) __('admin/setting.navigation.'.$key),
            'icon' => 'tabler-'.$icon,
            'url' => PelicanSettings::getUrl(['tab' => $key.'::data::tab', 'simplify_embed' => 1], panel: 'admin'),
        ], array_keys($icons), $icons);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        PanelSettings::saveForm($this->form->getState());
        Notification::make()->title(__('pelican-simplify::settings.saved'))->success()->send();
    }

    /**
     * Every add-on as a row of the list; one that throws is reported and left out, so the page still opens.
     *
     * @return list<array{id: string, name: string, description: string, icon: string, url: ?string}>
     */
    public static function addonRows(): array
    {
        return app(AddonRegistry::class)->collect(fn (Addon $addon) => [[
            'id' => $addon->id(), 'name' => $addon->name(), 'description' => $addon->description(), 'icon' => $addon->icon(), 'url' => $addon->settingsUrl(),
        ]]);
    }
}
