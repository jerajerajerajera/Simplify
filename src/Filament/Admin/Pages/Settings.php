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
use Livewire\Component;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Announcements\Filament\AnnouncementResource;
use Simplify\Core\Embed\Filament\EmbedSettings;
use Simplify\Core\Filament\Admin\SettingsFields;
use Simplify\Core\Server\Looks;
use Simplify\Core\Subdomains\Filament\AutomaticSubdomains;
use Simplify\Core\Support\Assets;
use Simplify\Core\Support\PanelSettings;

/**
 * Simplify's settings for the whole panel, in a few categories. Every add-on has a section under Add-ons with its
 * explanation and its settings.
 */
final class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    /**
     * Browse Hub (the 'hub' tab) is hidden for now: not in the tabs, the settings row or the Admin menu (Jeroen 3-11).
     * Its code stays, so turning this on brings it back.
     */
    public const HUB = false;

    /** The tabs of this page, in the order of the form (form()). */
    public const TABS = self::HUB ? ['appearance', 'general', 'app', 'addons', 'hub'] : ['appearance', 'general', 'app', 'addons'];

    protected static ?string $slug = 'simplify';

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-layout-dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'simplify::pages.settings';

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
        return __('simplify::settings.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('simplify::settings.title');
    }

    public function getHeading(): string|Htmlable
    {
        return self::logoHeading(__('simplify::settings.page_title'));
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

    public function mount(): void
    {
        // The first visit on a new panel walks through the setup; the settings page is for changes afterwards.
        if (! PanelSettings::get('setup_done') && ! PanelSettings::configured()) {
            $this->redirect(Setup::getUrl(panel: 'admin'));

            return;
        }
        $this->form->fill(PanelSettings::all());
    }

    protected function getHeaderActions(): array
    {
        return [
            // Save in the head of the page; it presses the form's own button, which also saves a Pelican tab (settings.blade.php).
            // With changes in an add-on's settings it saves every form with changes, this page's own among them (unsaved.js).
            Action::make('save')->label(__('simplify::settings.save'))->icon('tabler-device-floppy')->extraAttributes(['class' => 's-save-button'])
                ->alpineClickHandler("window.SimplifyUnsaved?.dirty() ? window.SimplifyUnsaved.save() : document.querySelector('.s-settings > .s-bar button[type=submit]')?.click()"),
        ];
    }

    public function form(Schema $schema): Schema
    {
        // One row of tabs over Simplify's settings (V51/V53): the settings nav (partials/settings-nav) draws it and
        // switches these tabs, whose own row stays hidden. Settings that left the screen keep their saved values.
        return $schema->statePath('data')->components([
            Tabs::make()->persistTabInQueryString()->columnSpanFull()->extraAttributes(['class' => 's-settings-tabs'])->tabs([
                Tab::make(__('simplify::settings.appearance.tab'))->key('appearance')->icon('tabler-palette')->schema([
                    SettingsFields::look(full: false),
                ]),
                Tab::make(__('simplify::settings.general.tab'))->key('general')->icon('tabler-adjustments')->schema([
                    SettingsFields::support(),
                    SettingsFields::visibility(),
                    SettingsFields::banners(),
                    // Only here, not in the head of every tab (Jeroen 3-11), as a section of its own (Jeroen 5-11).
                    SettingsFields::setupAgain(),
                ]),
                Tab::make(__('simplify::settings.appearance.app'))->key('app')->icon('tabler-device-mobile')->schema([
                    SettingsFields::app(),
                ]),
                Tab::make(__('simplify::settings.addons.heading'))->key('addons')->icon('tabler-puzzle')->schema([
                    View::make('simplify::partials.addon-list'),
                ]),
                Tab::make(__('simplify::settings.hub.tab'))->key('hub')->icon('tabler-building-store')->visible(self::HUB)->schema([
                    View::make('simplify::hub.tab'),
                ]),
            ]),
        ]);
    }

    /**
     * The one row of tabs over the settings: this page's tabs, Simplify's other settings pages, each add-on's own
     * settings page and Pelican's settings. A tab that is a page of its own shows the same row over it (SimplifyPlugin).
     *
     * @return list<array{key: string, label: string, icon: string, url: string, tab: bool}>
     */
    public static function nav(): array
    {
        if (! self::canAccess()) {
            return [];
        }
        $tab = fn (string $key, string $label, string $icon) => ['key' => $key, 'label' => $label, 'icon' => $icon, 'url' => self::getUrl(['tab' => $key], panel: 'admin'), 'tab' => true];
        $page = fn (string $key, string $label, string $icon, string $url) => ['key' => $key, 'label' => $label, 'icon' => $icon, 'url' => $url, 'tab' => false];
        $items = [
            $tab('appearance', __('simplify::settings.appearance.tab'), 'tabler-palette'),
            $tab('general', __('simplify::settings.general.tab'), 'tabler-adjustments'),
            $tab('app', __('simplify::settings.appearance.app'), 'tabler-device-mobile'),
            $page('website', EmbedSettings::getNavigationLabel(), 'tabler-code', EmbedSettings::getUrl(panel: 'admin')),
            $page('subdomains', AutomaticSubdomains::getNavigationLabel(), 'tabler-world', AutomaticSubdomains::getUrl(panel: 'admin')),
            $page('announcements', AnnouncementResource::getNavigationLabel(), 'tabler-speakerphone', AnnouncementResource::getUrl(panel: 'admin')),
        ];
        foreach (self::addonRows() as $addon) {
            if ($addon['url'] && ! $addon['form']) {
                $items[] = $page('addon:'.$addon['id'], $addon['name'], $addon['icon'], $addon['url']);
            }
        }
        $items[] = $tab('addons', __('simplify::settings.addons.heading'), 'tabler-puzzle');
        if (self::HUB) {
            $items[] = $tab('hub', __('simplify::settings.hub.tab'), 'tabler-building-store');
        }
        if (PelicanSettings::canAccess()) {
            $items[] = $page('pelican', __('simplify::js.dashboard.admin_groups.pelican_settings').' ↗', 'tabler-settings', PelicanSettings::getUrl(panel: 'admin'));
        }

        return $items;
    }

    /**
     * The settings as leaves of the Admin menu's tree: Simplify's tabs, each add-on's own page and Pelican's tabs.
     *
     * @return list<array{key: string, group: string, label: string, icon: string, url: string}>
     */
    public static function menu(): array
    {
        $tab = fn (string $key, string $group, string $label, string $icon) => ['key' => 'settings:'.$key, 'group' => $group, 'label' => $label, 'icon' => $icon, 'url' => self::getUrl(['tab' => $key], panel: 'admin')];
        $items = [
            $tab('general', 'panel', __('simplify::settings.general.tab'), 'settings'),
            $tab('appearance', 'panel', __('simplify::settings.appearance.tab'), 'palette'),
            $tab('app', 'panel', __('simplify::settings.appearance.app'), 'image'),
            $tab('addons', 'addons', __('simplify::settings.addons.heading'), 'puzzle'),
        ];
        if (self::HUB) {
            $items[] = $tab('hub', 'addons', __('simplify::settings.hub.tab'), 'store');
        }
        // An add-on whose settings are a form in the add-on list has no leaf of its own: Simplify add-ons holds pages.
        foreach (self::addonRows() as $addon) {
            if ($addon['url'] && ! $addon['form']) {
                $items[] = ['key' => 'addon:'.$addon['id'], 'group' => 'addons', 'label' => $addon['name'], 'icon' => 'plugins', 'url' => $addon['url']];
            }
        }
        // The menu draws from Simplify's own icon sprite, not Filament's Tabler names. Pelican's tabs open Pelican's own
        // settings page, on that tab, in the Admin page's card.
        $sprite = ['pelican-general' => 'home', 'pelican-captcha' => 'shield', 'pelican-mail' => 'mail', 'pelican-backup' => 'backups', 'pelican-oauth' => 'key', 'pelican-misc' => 'tool'];
        foreach (self::pelicanTabs() as $pelican) {
            $items[] = ['key' => 'settings:'.$pelican['key'], 'group' => 'pelican', 'label' => $pelican['label'], 'icon' => $sprite[$pelican['key']] ?? 'gear', 'url' => $pelican['url']];
        }

        return $items;
    }

    /**
     * Pelican's own settings, one leaf per tab of its page.
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
            'url' => PelicanSettings::getUrl(['tab' => $key.'::data::tab'], panel: 'admin'),
        ], array_keys($icons), $icons);
    }

    /** Removes an uploaded server banner; its servers go back to their automatic scene. */
    public function deleteBanner(string $file): void
    {
        abort_unless(self::canAccess(), 403);
        Looks::forget($file);
        Notification::make()->title(__('simplify::settings.appearance.banner_removed'))->success()->send();
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        PanelSettings::saveForm($this->form->getState());
        Notification::make()->title(__('simplify::settings.saved'))->success()->send();
    }

    /**
     * Every add-on as a section of the list: its explanation, and its settings form or a link to its settings page. One
     * that throws is reported and left out, so the page still opens.
     *
     * @return list<array{id: string, name: string, description: string, icon: string, url: ?string, form: ?string}>
     */
    public static function addonRows(): array
    {
        return app(AddonRegistry::class)->collect(function (Addon $addon) {
            $form = method_exists($addon, 'settingsForm') ? $addon->settingsForm() : null;

            return [[
                'id' => $addon->id(), 'name' => $addon->name(), 'description' => $addon->description(), 'icon' => $addon->icon(),
                'url' => $addon->settingsUrl(),
                'form' => is_string($form) && is_subclass_of($form, Component::class) ? $form : null,
            ]];
        });
    }

    /** Simplify settings › Add-ons with one add-on's section open, for links to an add-on's settings. */
    public static function addonUrl(string $id): string
    {
        return self::getUrl(['tab' => 'addons', 'addon' => $id], panel: 'admin');
    }
}
