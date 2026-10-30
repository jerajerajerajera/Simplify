<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin\Pages;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Addons\Catalog;
use Simplify\Core\Filament\Admin\SettingsFields;
use Simplify\Core\Push\WebPush;
use Simplify\Core\Support\PanelSettings;
use Throwable;

/**
 * The first setup: which add-ons to install first, where people start, how it looks, the app, and the installed
 * add-ons. Every answer can be changed later on the settings page; nothing outside these settings and the add-ons'
 * own setup answers is touched.
 */
final class Setup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'simplify-setup';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'simplify::pages.setup';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public function getTitle(): string|Htmlable
    {
        return __('simplify::setup.title');
    }

    public function getHeading(): string|Htmlable
    {
        return Settings::logoHeading(__('simplify::setup.title'));
    }

    public function getSubheading(): string
    {
        return __('simplify::setup.intro');
    }

    public function mount(): void
    {
        $addons = [];
        foreach (app(AddonRegistry::class)->collect(fn (Addon $addon) => [[$addon->id(), $addon->setupState()]]) as [$id, $state]) {
            $addons[$id] = $state;
        }
        $this->form->fill([...PanelSettings::all(), 'addons' => $addons]);
    }

    public function form(Schema $schema): Schema
    {
        $step = fn (string $key, string $icon, array $schema) => Step::make(__('simplify::setup.steps.'.$key))
            ->icon($icon)->schema($schema);

        return $schema->statePath('data')->components([
            Wizard::make([
                $step('addons', 'tabler-puzzle', [View::make('simplify::partials.setup-addons')]),
                $step('panel', 'tabler-users-group', [SettingsFields::startPage(), SettingsFields::visibility(), SettingsFields::support()]),
                $step('look', 'tabler-palette', [SettingsFields::colours(), SettingsFields::brand()]),
                $step('app', 'tabler-device-mobile', [View::make('simplify::partials.setup-checks'), SettingsFields::app()]),
                $step('finish', 'tabler-checklist', [
                    ...$this->addonSections(),
                    Section::make(__('simplify::setup.addon_pages'))->description(__('simplify::setup.addon_pages_help'))
                        ->schema([View::make('simplify::partials.addon-list')]),
                ]),
            ])->skippable()->columnSpanFull()->extraAttributes(['class' => 's-setup'])
                ->submitAction(new HtmlString(view('simplify::partials.setup-submit')->render())),
        ]);
    }

    public function finish(): void
    {
        abort_unless(self::canAccess(), 403);
        $state = $this->form->getState();
        $addons = $state['addons'] ?? [];
        unset($state['addons']);
        PanelSettings::saveForm([...$state, 'setup_done' => true]);
        // Each add-on saves its own answers; one that throws is reported by the registry and the rest still save.
        app(AddonRegistry::class)->collect(function (Addon $addon) use ($addons): array {
            if (isset($addons[$addon->id()])) {
                $addon->saveSetup($addons[$addon->id()]);
            }

            return [];
        });
        Notification::make()->title(__('simplify::setup.done'))->success()->send();
        $this->redirect(Settings::getUrl(panel: 'admin'));
    }

    /**
     * A section per installed add-on that asks something in the setup, its answers kept under addons.<id>.
     *
     * @return list<Section>
     */
    private function addonSections(): array
    {
        return app(AddonRegistry::class)->collect(fn (Addon $addon) => ($fields = $addon->setupFields()) === [] ? [] : [
            Section::make($addon->name())->icon($addon->icon())->statePath('addons.'.$addon->id())->schema($fields),
        ]);
    }

    /** @return list<array{id: string, icon: string, tone: string, name: string, description: string, url: string, installed: bool}> */
    public function catalog(): array
    {
        return Catalog::all(app(AddonRegistry::class));
    }

    /**
     * What the app and its notifications need from this panel. None of it blocks the setup.
     *
     * @return list<array{ok: bool, text: string}>
     */
    public function checks(): array
    {
        try {
            WebPush::createKeys();
            $keys = true;
        } catch (Throwable) {
            $keys = false;
        }

        return [
            ['ok' => str_starts_with(url('/'), 'https://'), 'text' => __('simplify::setup.checks.https')],
            ['ok' => $keys, 'text' => __('simplify::setup.checks.openssl')],
        ];
    }
}
