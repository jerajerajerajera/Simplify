<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin\Pages;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
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
use Simplify\Core\Support\PanelSettings;
use Simplify\Core\Support\SystemCheck;

/**
 * The first setup: which add-ons to install first, where people start, how it looks, the phone app, and what the
 * installed add-ons ask; then SetupDone points to each add-on's own settings page. Every answer can be changed later
 * on the settings page; nothing outside these settings and the add-ons' own setup answers is touched.
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
        $settings = PanelSettings::all();
        // A first setup starts as a panel for friends, like most panels are; a repeated one shows what is set now.
        $friends = $settings['setup_done'] ? (bool) $settings['friends_mode'] : true;
        $this->form->fill([...$settings, 'friends_mode' => $friends, 'panel_type' => $friends ? 'friends' : 'hosting', 'addons' => $addons]);
    }

    public function form(Schema $schema): Schema
    {
        $step = fn (string $key, string $icon, array $schema) => Step::make(__('simplify::setup.steps.'.$key))
            ->icon($icon)->schema($schema);

        return $schema->statePath('data')->components([
            Wizard::make([
                $step('addons', 'tabler-puzzle', [
                    Section::make(__('simplify::setup.checks.title'))->schema([View::make('simplify::partials.setup-checks')]),
                    View::make('simplify::partials.setup-addons'),
                ]),
                $step('panel', 'tabler-users-group', [
                    SettingsFields::panelType(), SettingsFields::startPage(), SettingsFields::visibility(), SettingsFields::support(),
                ]),
                $step('look', 'tabler-palette', [SettingsFields::look()]),
                $step('app', 'tabler-device-mobile', [SettingsFields::app()]),
                // Only what the add-ons ask; their own settings pages follow on the page after the setup.
                $step('finish', 'tabler-checklist', $this->addonSections() ?: [View::make('simplify::partials.setup-no-questions')]),
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
        $this->redirect(SetupDone::getUrl(panel: 'admin'));
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

    /** @return list<array{ok: bool, text: string, fix: string, code?: string}> */
    public function checks(): array
    {
        return SystemCheck::all();
    }
}
