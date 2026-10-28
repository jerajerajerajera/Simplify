<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Admin\Pages;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Addons\Catalog;
use Simplify\Core\Filament\Admin\SettingsFields;
use Simplify\Core\Push\WebPush;
use Simplify\Core\Support\PanelSettings;
use Throwable;

/**
 * The first setup: who the panel is for, where people start, how it looks, the app, and which add-ons exist. Every
 * answer can be changed later on the settings page; nothing outside these settings is touched.
 */
final class Setup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'simplify-setup';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'pelican-simplify::pages.setup';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) user()?->isRootAdmin();
    }

    public function getTitle(): string|Htmlable
    {
        return __('pelican-simplify::setup.title');
    }

    public function mount(): void
    {
        $this->form->fill(PanelSettings::all());
    }

    public function form(Schema $schema): Schema
    {
        $step = fn (string $key, string $icon, array $schema) => Step::make(__('pelican-simplify::setup.steps.'.$key))
            ->icon($icon)->schema($schema);

        return $schema->statePath('data')->components([
            Wizard::make([
                $step('panel', 'tabler-users-group', [SettingsFields::startPage(), SettingsFields::support()]),
                $step('look', 'tabler-palette', [SettingsFields::colours(), SettingsFields::brand()]),
                $step('app', 'tabler-device-mobile', [View::make('pelican-simplify::partials.setup-checks'), SettingsFields::app()]),
                $step('addons', 'tabler-puzzle', [View::make('pelican-simplify::partials.setup-addons')]),
            ])->columnSpanFull()->submitAction(new HtmlString(view('pelican-simplify::partials.setup-submit')->render())),
        ]);
    }

    public function finish(): void
    {
        abort_unless(self::canAccess(), 403);
        PanelSettings::saveForm([...$this->form->getState(), 'setup_done' => true]);
        Notification::make()->title(__('pelican-simplify::setup.done'))->success()->send();
        $this->redirect(Settings::getUrl(panel: 'admin'));
    }

    /** @return list<array{id: string, icon: string, name: string, description: string, url: string, installed: bool}> */
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
            ['ok' => str_starts_with(url('/'), 'https://'), 'text' => __('pelican-simplify::setup.checks.https')],
            ['ok' => $keys, 'text' => __('pelican-simplify::setup.checks.openssl')],
        ];
    }
}
