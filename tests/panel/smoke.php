<?php

// Loads Simplify inside a real Pelican panel and opens its pages. Run from the panel folder after
// `php artisan p:plugin:install simplify`: php plugins/simplify/tests/panel/smoke.php
use App\Enums\ContainerStatus;
use App\Filament\Admin\Pages\Health;
use App\Filament\Server\Resources\Files\Pages\ListFiles;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Server;
use App\Models\Task;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\ViewException;
use Livewire\Component;
use Livewire\Exceptions\ComponentNotFoundException;
use Livewire\Features\SupportTesting\Testable;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Addons\Button;
use Simplify\Core\Announcements\Filament\AnnouncementResource;
use Simplify\Core\Announcements\Filament\ManageAnnouncements;
use Simplify\Core\Announcements\Livewire\AnnouncementBars;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Services\Bell as AnnouncementBell;
use Simplify\Core\Announcements\Services\StartAnnouncements;
use Simplify\Core\Announcements\Services\TaskWaits;
use Simplify\Core\Announcements\Services\WeeklyAnnouncements;
use Simplify\Core\Announcements\Support\Diagnose;
use Simplify\Core\Announcements\Support\FormData;
use Simplify\Core\Announcements\Support\Rules;
use Simplify\Core\Announcements\Support\StartTrigger;
use Simplify\Core\Announcements\Support\TaskAnnouncement;
use Simplify\Core\Announcements\Support\Weekly;
use Simplify\Core\Announcements\Tasks\AnnouncementTask;
use Simplify\Core\Embed\Filament\EmbedSettings;
use Simplify\Core\Embed\Filament\Website as EmbedWebsite;
use Simplify\Core\Embed\Services\EmbedDetails;
use Simplify\Core\Embed\Services\EmbedOptions;
use Simplify\Core\Embed\Services\EmbedServers;
use Simplify\Core\Embed\Services\StatusAddress;
use Simplify\Core\Embed\Services\Uptime;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Filament\Admin\Pages\Setup;
use Simplify\Core\Filament\Admin\Pages\SetupDone;
use Simplify\Core\Filament\App\Pages\Connections;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Filament\Server\ServerPage;
use Simplify\Core\Health\HostHealth;
use Simplify\Core\Models\Setting;
use Simplify\Core\Overview\Bell as OverviewBell;
use Simplify\Core\Overview\Listing;
use Simplify\Core\Overview\Power;
use Simplify\Core\Push\PluginUpdates;
use Simplify\Core\Push\Push;
use Simplify\Core\Push\UpdateNotices;
use Simplify\Core\Push\WebPush;
use Simplify\Core\Server\Looks;
use Simplify\Core\Server\ServerMenu;
use Simplify\Core\Subdomains\Filament\AutomaticSubdomains;
use Simplify\Core\Subdomains\Filament\ServerSubdomain;
use Simplify\Core\Subdomains\Models\Subdomain;
use Simplify\Core\Subdomains\Services\Settings as SubdomainSettings;
use Simplify\Core\Subdomains\Subdomains;
use Simplify\Core\Support\Assets;
use Simplify\Core\Support\Brand;
use Simplify\Core\Support\FreshViews;
use Simplify\Core\Support\PanelSettings;
use Simplify\Core\Support\Preferences;
use Simplify\Core\Support\StaleCode;
use Spatie\Health\ResultStores\ResultStore;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResult;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResults;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
// Each step gets a fresh application, like a real request: Filament keeps panel state (render hooks, plugins) for
// the whole process, so opening an admin page and then an app page in one application fails where a browser would not.
function boot(): Application
{
    $app = require getcwd().'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    // Laravel's own handler prints an error outside a step and still exits with 0, which would leave CI green
    // without testing anything.
    set_exception_handler(function (Throwable $e): void {
        echo 'FAIL setup: '.get_class($e).': '.substr($e->getMessage(), 0, 400).' ('.basename($e->getFile()).':'.$e->getLine().")\n";
        exit(1);
    });

    return $app;
}
boot();

$failed = false;
$passed = 0;
function step(string $what, callable $run): void
{
    global $failed, $passed;
    try {
        $run(boot());
        $passed++;
        echo "PASS $what\n";
    } catch (Throwable $e) {
        $failed = true;
        echo "FAIL $what: ".get_class($e).': '.substr($e->getMessage(), 0, 400).' ('.basename($e->getFile()).':'.$e->getLine().")\n";
    }
}
function visit(User $user, string $url, array $headers = []): TestResponse
{
    $client = new class(app())
    {
        use InteractsWithAuthentication, InteractsWithSession, MakesHttpRequests;

        public function __construct(public $app) {}
    };

    return $client->actingAs($user)->get($url, $headers);
}
// Livewire's own assertions need PHPUnit's configuration, which a plain script does not have.
function noErrors(Testable $component): void
{
    if ($component->errors()->isNotEmpty()) {
        throw new RuntimeException('validation failed: '.json_encode($component->errors()->toArray()));
    }
}
function expectOk(TestResponse $response, string $url): void
{
    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException('HTTP '.$response->getStatusCode().' at '.$url.($response->exception ? ': '.$response->exception->getMessage() : ''));
    }
}

final class SmokeAddon extends Addon
{
    public function id(): string
    {
        return 'smoke-addon';
    }

    public function name(): string
    {
        return 'Smoke add-on';
    }
}

final class HostingAddon extends Addon
{
    public function id(): string
    {
        return 'hosting-addon';
    }

    public function name(): string
    {
        return 'Hosting add-on';
    }

    public function serverLinks(Server $server): array
    {
        // As Simplify-Hosting: a suspended server's link is the open invoice, the card's main button.
        return $server->status?->value === 'suspended'
            ? [new Button('Pay invoice', 'https://billing.example.com/pay/'.$server->uuid, 'tabler-receipt', primary: true)]
            : [new Button('Upgrade this server', 'https://billing.example.com/s/'.$server->uuid)];
    }

    public function customerLinks(User $user): array
    {
        return [new Button('Client area', 'https://billing.example.com/clientarea', 'tabler-receipt'), new Button('Order a server', 'https://billing.example.com/order')];
    }

    public function hostModeDefault(): bool
    {
        return true;
    }
}

final class BadgeAddon extends Addon
{
    public static int $asked = 0;

    public function id(): string
    {
        return 'badge-addon';
    }

    public function name(): string
    {
        return 'Badge add-on';
    }

    public function tileBadges(Server $server): array
    {
        self::$asked++;

        return ['smoke-badge'];
    }
}

final class AccountAddon extends Addon
{
    public function id(): string
    {
        return 'account-addon';
    }

    public function name(): string
    {
        return 'Account add-on';
    }

    public function accountPages(User $user): array
    {
        return [new Button('Steam', url('/steam-key'), 'tabler-brand-steam'), new Button('Elsewhere', 'https://example.com/account', 'tabler-world')];
    }
}

final class SteamConnectionRow extends Component
{
    public function render(): string
    {
        return '<div class="smoke-connection-row">Steam account row</div>';
    }
}

final class ConnectionsAddon extends Addon
{
    public function id(): string
    {
        return 'connections-addon';
    }

    public function name(): string
    {
        return 'Connections add-on';
    }

    public function connections(User $user): array
    {
        // A class that is not a Livewire component is left out.
        return [SteamConnectionRow::class, stdClass::class];
    }
}

final class SettingsFormRow extends Component
{
    public function render(): string
    {
        return '<div class="smoke-settings-form" data-simplify-unsaved data-simplify-save="save">Settings form row</div>';
    }
}

final class LockActions extends Component
{
    public string $server = '';

    public function render(): string
    {
        return '<div class="smoke-lock-actions">Force kill · Retry</div>';
    }
}

/** Locks a server the way Egg Updates locks its test server (Addon::lockedServer, API 10). */
final class LockAddon extends Addon
{
    public function id(): string
    {
        return 'lock-addon';
    }

    public function name(): string
    {
        return 'Lock add-on';
    }

    public function lockedServer(Server $server): ?array
    {
        return str_starts_with($server->name, 'Egg update test: ') ? ['notice' => 'This test server is removed when the egg update is done.', 'actions' => LockActions::class] : null;
    }
}

final class FormAddon extends Addon
{
    public function id(): string
    {
        return 'form-addon';
    }

    public function name(): string
    {
        return 'Form add-on';
    }

    public function description(): string
    {
        return 'What the form add-on does.';
    }

    public function settingsUrl(): ?string
    {
        return url('/form-addon-settings');
    }

    public function settingsForm(): ?string
    {
        return SettingsFormRow::class;
    }
}

final class LinkAddon extends Addon
{
    public function id(): string
    {
        return 'link-addon';
    }

    public function name(): string
    {
        return 'Link add-on';
    }

    public function settingsUrl(): ?string
    {
        return url('/link-addon-settings');
    }
}

// Pelican 1.0 has no users.root_admin column: root admins have the root admin role.
$admin = User::where('email', 'smoke-simplify@example.com')->first()
    ?? User::withoutEvents(fn () => User::factory()->create(['email' => 'smoke-simplify@example.com']));
$admin->syncRoles(Role::getRootAdmin());

step('a panel with settings opens them, a new panel opens the setup, and finishing it saves the answers', function () use ($admin) {
    // CI took this panel over from PelicanSimplify: it has settings but never finished the setup.
    $response = visit($admin, Settings::getUrl(panel: 'admin'));
    if ($response->isRedirect()) {
        throw new RuntimeException('a panel with settings was sent to the setup: '.$response->headers->get('Location'));
    }
    // A new panel: nothing saved yet.
    Setting::query()->delete();
    PanelSettings::save([]);
    $response = visit($admin, Settings::getUrl(panel: 'admin'));
    if (! $response->isRedirect() || ! str_contains((string) $response->headers->get('Location'), 'simplify/setup')) {
        throw new RuntimeException('the settings did not open the setup first (HTTP '.$response->getStatusCode().')');
    }
    $url = Setup::getUrl(panel: 'admin');
    $page = visit($admin, $url);
    expectOk($page, $url);
    if (! str_contains($page->getContent(), 'hub.pelican.dev/plugins/simplify-servermaker') || ! str_contains($page->getContent(), 's-addon-card')) {
        throw new RuntimeException('the setup does not recommend the add-ons');
    }
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    $setup = Livewire\Livewire::actingAs($admin)->test(Setup::class)
        ->set('data.support_url', 'mailto:help@example.com')->set('data.accent', '#a9b4f2')
        ->set('data.brand_font', 'monocraft')->set('data.brand_effect', 'glow')->set('data.brand_colour', 'accent')->call('finish');
    noErrors($setup);
    if (! PanelSettings::get('setup_done') || PanelSettings::get('support_url') !== 'mailto:help@example.com'
        || PanelSettings::get('brand_font') !== 'monocraft' || PanelSettings::get('brand_effect') !== 'glow' || PanelSettings::get('brand_colour') !== 'accent' || PanelSettings::get('accent') !== '#a9b4f2') {
        throw new RuntimeException('the setup was not saved: '.json_encode(PanelSettings::all()));
    }
    // The page after the setup, with each add-on's own settings. The top bar shows the new font and style from the
    // next request on, and this script is a single request.
    $url = SetupDone::getUrl(panel: 'admin');
    $page = visit($admin, $url);
    expectOk($page, $url);
    if (! str_contains($page->getContent(), 's-done')) {
        throw new RuntimeException('the page after the setup misses its add-on list');
    }
    PanelSettings::save(['brand_font' => 'inter', 'brand_effect' => 'plain', 'brand_colour' => 'solid']);
});
step('opening the panel shows the overview, unless Pelican is the start page', function () use ($admin) {
    $home = visit($admin, url('/'));
    if (! $home->isRedirect(Overview::getUrl(panel: 'app'))) {
        throw new RuntimeException('the panel opened without the overview (HTTP '.$home->getStatusCode().')');
    }
    PanelSettings::save(['start_page' => 'pelican']);
    try {
        expectOk(visit($admin, url('/')), url('/'));
    } finally {
        PanelSettings::save(['start_page' => 'overview']);
    }
});
step('settings page renders with its one row of tabs, Pelican as a link without frames', function () use ($admin) {
    $url = Settings::getUrl(panel: 'admin');
    $response = visit($admin, $url);
    expectOk($response, $url);
    $html = $response->getContent();
    auth()->setUser($admin);
    $menu = collect(Settings::menu())->keyBy('key');
    $nav = collect(Settings::nav())->pluck('key');
    if (! str_contains($html, 's-settings-tabs') || ! str_contains($html, '<nav class="s-settings-nav"') || ($menu['settings:appearance']['group'] ?? null) !== 'panel' || ($menu['settings:pelican-mail']['group'] ?? null) !== 'pelican') {
        throw new RuntimeException('the settings menu is incomplete: '.json_encode($menu->pluck('group', 'key')));
    }
    // The header's create popup has a frame of its own; nothing else on the page may be one.
    if (preg_match('/<iframe(?![^>]*ps-create-frame)/', $html) || str_contains((string) ($menu['settings:pelican-mail']['url'] ?? ''), 'simplify_embed')) {
        throw new RuntimeException('Pelican\'s settings are still in a frame');
    }
    foreach (['appearance', 'general', 'app', 'website', 'subdomains', 'announcements', 'addons', 'pelican'] as $key) {
        if (! $nav->contains($key)) {
            throw new RuntimeException("the settings' row of tabs has no $key: ".$nav->toJson());
        }
    }
    // No version line in the head (it is in the panel's footer) and Run setup again only on General, as a button in
    // its list (Jeroen 3-11); what Save does stays, small, under the settings.
    if (str_contains($html, 'fi-header-subheading') || ! str_contains($html, 's-settings-setup') || ! str_contains($html, 's-settings-note')) {
        throw new RuntimeException('the settings head still has the version line or Run setup again');
    }
    // Run setup again is a section of its own: a title, what it does and a button with its name, not an icon only (Jeroen 5-11).
    if (! preg_match('~class="s-action-row s-settings-setup">\s*<p[^>]*>[^<]+</p>\s*<a class="s-btn"[^>]*>.*?<span>'.preg_quote(e(__('simplify::setup.again')), '~').'</span>~s', $html)) {
        throw new RuntimeException('Run setup again is not a labelled button with its explanation');
    }
    // Appearance keeps colour, name and logo; the name's style designer is the setup's only.
    if (str_contains($html, 'class="s-presets"') || ! str_contains($html, 'brand_name')) {
        throw new RuntimeException('Appearance on the settings page is not the short one');
    }
    // A settings page of its own (Website) has the same row of tabs over it.
    $embed = EmbedSettings::getUrl(panel: 'admin');
    $page = visit($admin, $embed);
    expectOk($page, $embed);
    if (! str_contains($page->getContent(), '<nav class="s-settings-nav"')) {
        throw new RuntimeException('Website has no settings row of tabs');
    }
});
step('a tagged add-on shows on the settings page', function ($app) use ($admin) {
    $app->tag([SmokeAddon::class], AddonRegistry::TAG);
    $url = Settings::getUrl(panel: 'admin');
    $response = visit($admin, $url);
    expectOk($response, $url);
    if (! str_contains($response->getContent(), 'Smoke add-on')) {
        throw new RuntimeException('the add-on is not listed');
    }
});
step('Settings › Add-ons: a section per add-on with what it does and its settings form; only pages in the menu', function ($app) use ($admin) {
    $app->tag([FormAddon::class, LinkAddon::class], AddonRegistry::TAG);
    $url = Settings::addonUrl('form-addon');
    $response = visit($admin, $url);
    expectOk($response, $url);
    $html = $response->getContent();
    // The asked add-on's section is open, with its explanation first and its own form under it.
    if (! preg_match('/<details class="s-addon"[^>]*open[^>]*>.*?Form add-on.*?What the form add-on does\..*?smoke-settings-form/s', $html)) {
        throw new RuntimeException('the add-on\'s section does not show its explanation and settings form');
    }
    // An add-on without a form keeps its Settings link; one with a form has no leaf in the menu or the row of tabs.
    if (! str_contains($html, '/link-addon-settings') || str_contains($html, '/form-addon-settings')) {
        throw new RuntimeException('the settings links of the add-ons are wrong');
    }
    auth()->setUser($admin);
    $menu = collect(Settings::menu())->pluck('key');
    $nav = collect(Settings::nav())->pluck('key');
    if ($menu->contains('addon:form-addon') || $nav->contains('addon:form-addon') || ! $menu->contains('addon:link-addon')) {
        throw new RuntimeException('an add-on with a settings form is still a leaf of its own: '.$menu->toJson());
    }
});
step('the stylesheet and logo are served with a long cache, styles and scripts gzipped', function () use ($admin) {
    foreach (['simplify.css' => 'text/css', 'logo.svg' => 'image/svg+xml'] as $name => $type) {
        $url = Assets::url($name);
        $response = visit($admin, $url);
        expectOk($response, $url);
        if (! str_starts_with((string) $response->headers->get('Content-Type'), $type)) {
            throw new RuntimeException("$name has type ".$response->headers->get('Content-Type'));
        }
    }
    // Pelican's web server compresses only HTML: Simplify gzips its own styles and scripts.
    foreach (['dashboard.css', 'dashboard.js'] as $name) {
        $response = visit($admin, Assets::url($name), ['Accept-Encoding' => 'gzip, deflate, br']);
        expectOk($response, $name);
        if ($response->headers->get('Content-Encoding') !== 'gzip' || gzdecode((string) $response->getContent()) !== file_get_contents((string) Assets::path($name))) {
            throw new RuntimeException("$name is not sent gzipped (Content-Encoding: ".$response->headers->get('Content-Encoding').')');
        }
    }
    $response = visit($admin, '/simplify/assets/plugin.json');
    if ($response->getStatusCode() !== 404) {
        throw new RuntimeException('a file outside the list is served');
    }
});
step('every panel page loads the stylesheet', function () use ($admin) {
    $url = Settings::getUrl(panel: 'admin');
    if (! str_contains(visit($admin, $url)->getContent(), '/simplify/assets/simplify.css?v=')) {
        throw new RuntimeException('the stylesheet link is missing');
    }
});
step('the settings have no Browse Hub: no tab, nothing in the row or the Admin menu, and the Hub is never read', function () use ($admin) {
    $asked = [];
    Http::fake(['hub.pelican.dev/*' => function ($request) use (&$asked) {
        $asked[] = $request->url();

        return Http::response(['last_page' => 1, 'data' => []]);
    }]);
    // The address of the tab it once had opens the settings on their first tab.
    $url = Settings::getUrl(['tab' => 'hub'], panel: 'admin');
    $response = visit($admin, $url);
    expectOk($response, $url);
    auth()->setUser($admin);
    if (collect(Settings::menu())->pluck('key')->contains('settings:hub') || collect(Settings::nav())->pluck('key')->contains('hub') || in_array('hub', Settings::TABS, true)) {
        throw new RuntimeException('Browse Hub is still a tab, in the settings row or in the Admin menu');
    }
    foreach (['Browse Hub', 'settings.hub.', 'simplify-core-browse-hub', 's-hub-'] as $left) {
        if (str_contains($response->getContent(), $left)) {
            throw new RuntimeException("the settings page still holds $left");
        }
    }
    if ($asked !== []) {
        throw new RuntimeException('the settings page read the Hub: '.json_encode($asked));
    }
});
$server = Server::query()->first() ?? Server::factory()->create(['owner_id' => $admin->id, 'name' => 'Smoke server']);
step('the overview renders with the server card', function () use ($admin, $server) {
    $url = Overview::getUrl(panel: 'app');
    $response = visit($admin, $url);
    expectOk($response, $url);
    if (! str_contains($response->getContent(), 'pelicanSimplify(') || ! str_contains($response->getContent(), '/simplify/assets/dashboard.js?v=')) {
        throw new RuntimeException('the overview script is missing');
    }
    // P-10: placeholders while Simplify starts, never a white page (dashboard/boot.blade.php, tests/boot.cjs).
    if (! str_contains($response->getContent(), 'class="ps-boot is-overview"')) {
        throw new RuntimeException('the overview has no placeholders while it starts');
    }
    $card = collect(Listing::for($admin))->firstWhere('uuid', $server->uuid);
    if ($card === null || $card['name'] !== $server->name || ! isset($card['status'], $card['usage'])) {
        throw new RuntimeException('the server has no card: '.json_encode($card));
    }
});
step('the SFTP link on a server page names the account once (K-B71)', function () use ($admin, $server) {
    $sftp = Listing::one($admin, $server)['sftp'] ?? null;
    $username = $admin->username.'.'.$server->uuid_short;
    $url = (string) ($sftp['url'] ?? '');
    if (($sftp['username'] ?? null) !== $username || ! str_starts_with($url, 'sftp://'.rawurlencode($username).'@') || substr_count($url, '@') !== 1) {
        throw new RuntimeException('unexpected SFTP details: '.json_encode($sftp));
    }
});
step('live numbers load without Wings and leave unknown values empty', function () use ($admin, $server) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    $page = Livewire\Livewire::actingAs($admin)->test(Overview::class)->call('listing')->assertOk();
    $card = collect($page->instance()->listing())->firstWhere('uuid', $server->uuid);
    if (! is_string($card['status'] ?? null) || $card['usage']['cpu_percent'] !== null) {
        throw new RuntimeException('unexpected numbers without Wings: '.json_encode($card));
    }
});
step('add-on badges show on the cards and are not asked again on every refresh', function ($app) use ($admin) {
    $app->tag([BadgeAddon::class], AddonRegistry::TAG);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    BadgeAddon::$asked = 0;
    $page = Livewire\Livewire::actingAs($admin)->test(Overview::class)->assertSee('smoke-badge');
    $first = BadgeAddon::$asked;
    $page->call('listing')->call('listing');
    if ($first < 1 || BadgeAddon::$asked !== $first) {
        throw new RuntimeException("asked $first times on the first view, ".BadgeAddon::$asked.' after two refreshes');
    }
});
step('admins get the create form of Pelican as a create button', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    $create = Livewire\Livewire::actingAs($admin)->test(Overview::class)->instance()->initial()['create'];
    if (! collect($create)->contains(fn (array $button) => ($button['native'] ?? false) && str_contains($button['url'], '/create'))) {
        throw new RuntimeException('no create button: '.json_encode($create));
    }
});
step('the Admin page: an admin page has Simplify\'s header and the Admin menu, the legacy panel has neither', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    $initial = Livewire\Livewire::actingAs($admin)->test(Overview::class)->instance()->initial();
    $pages = collect($initial['admin_tree'])->flatMap(fn (array $section) => array_column($section['pages'], 'key'));
    if (! $pages->contains('health') || ! is_array($initial['health_checks']) || ! is_string($initial['admin_url']) || ! str_contains((string) $initial['legacy_url'], 'simplify_legacy=1')) {
        throw new RuntimeException('the Admin page is missing parts: '.json_encode([$pages, $initial['admin_url'], $initial['legacy_url']]));
    }
    $client = new class(app())
    {
        use InteractsWithAuthentication, InteractsWithSession, MakesHttpRequests;

        public function __construct(public $app) {}
    };
    $client->actingAs($admin);
    $url = Health::getUrl(panel: 'admin');
    $page = $client->get($url);
    expectOk($page, $url);
    // The header and the menu as @persist draws them, and the class head.blade.php sets (the head's own script names
    // them too, so the markup itself is what counts).
    foreach (['<div x-persist="simplify-admin-top"', '<div x-persist="simplify-admin-side"', "classList.add('ps-shell'", 'class="ps-boot is-admin"'] as $part) {
        if (! str_contains($page->getContent(), $part)) {
            throw new RuntimeException('the Admin page has no '.$part);
        }
    }
    // Only Create server's popup has a frame (filled when it opens); the page itself is not in one.
    if (preg_match_all('/<iframe\b(?![^>]*\bps-create-frame\b)/', $page->getContent()) > 0) {
        throw new RuntimeException('the Admin page uses a frame');
    }
    $legacy = $client->get($url.'?simplify_legacy=1');
    if (! $legacy->isRedirect() || str_contains((string) $legacy->headers->get('Location'), 'simplify_legacy')) {
        throw new RuntimeException('?simplify_legacy=1 does not lead to the page itself: HTTP '.$legacy->getStatusCode());
    }
    $plain = $client->get($url);
    expectOk($plain, $url);
    if (str_contains($plain->getContent(), '<div x-persist="simplify-admin-side"') || str_contains($plain->getContent(), '<div x-persist="simplify-admin-top"')) {
        throw new RuntimeException('the legacy panel still has Simplify\'s header or menu');
    }
    if (! str_contains($plain->getContent(), 's-return') || ! str_contains($plain->getContent(), 'simplify_legacy=0')) {
        throw new RuntimeException('the legacy panel has no Back to Simplify button');
    }
    $client->get($url.'?simplify_legacy=0');
    if (! str_contains($client->get($url)->getContent(), '<div x-persist="simplify-admin-side"')) {
        throw new RuntimeException('Back to Simplify does not bring the Admin page back');
    }
});
step('Pelican\'s own admin pages stand in their groups, never under Pelican plugins (K-B72)', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    $tree = Livewire\Livewire::actingAs($admin)->test(Overview::class)->instance()->initial()['admin_tree'];
    $plugins = array_column(collect($tree)->firstWhere('key', 'extensions')['pages'] ?? [], 'key');
    $panel = Filament\Facades\Filament::getPanel('admin');
    $own = array_map(fn (string $class) => strtolower(class_basename($class)), array_filter([...$panel->getResources(), ...$panel->getPages()], fn (string $class) => str_starts_with($class, 'App\\')));
    if (array_intersect($plugins, $own) !== []) {
        throw new RuntimeException('pages of Pelican under Pelican plugins: '.implode(', ', array_intersect($plugins, $own)));
    }
});
step('the link to Simplify settings opens an item of the Admin menu (K-B77)', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    $initial = Livewire\Livewire::actingAs($admin)->test(Overview::class)->instance()->initial();
    $leaves = collect($initial['admin_tree'])->flatMap(fn (array $section) => array_column($section['pages'] ?? [], 'url'))->all();
    if (! in_array($initial['settings_url'], $leaves, true)) {
        throw new RuntimeException('Open settings goes to '.$initial['settings_url'].', which is no item of the Admin menu');
    }
});
step('Pelican\'s Health page shows in Simplify\'s look', function () use ($admin) {
    $url = Health::getUrl(panel: 'admin');
    $response = visit($admin, $url);
    expectOk($response, $url);
    if (! str_contains($response->getContent(), 's-health')) {
        throw new RuntimeException('the Health page still uses Pelican\'s own view');
    }
});
step('the hourly check tells admins about plugin updates without failing', function () {
    if (! is_int(PluginUpdates::send())) {
        throw new RuntimeException('the plugin update check did not finish');
    }
});
step('a power action on a server the user cannot reach is refused', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    Livewire\Livewire::actingAs($admin)->test(Overview::class)->call('overviewPower', 'no-such-server', 'start')->assertForbidden();
});
step('a power action is written to the activity log once (K-B88)', function () use ($admin, $server) {
    // Pelican 1.0.0-rc.2 and later write it themselves when Wings takes the action; Simplify added a second line, so
    // Activity showed every restart twice and a webhook on server:power.restart was called twice.
    Http::fake(['*/api/servers/*/power' => Http::response('', 204), '*' => Http::response('', 500)]);
    Auth::login($admin);
    $lines = fn () => ActivityLog::query()->where('event', 'server:power.restart')->count();
    $before = $lines();
    Power::send($server, 'restart');
    if ($lines() - $before !== 1) {
        throw new RuntimeException('one restart wrote '.($lines() - $before).' lines to the activity log');
    }
});
step('server groups and the server order are saved for the account', function () use ($admin, $server) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    Livewire\Livewire::actingAs($admin)->test(Overview::class)
        ->call('saveOverviewGroups', [['id' => 'smoke', 'name' => 'Smoke group', 'color' => '#336699', 'servers' => [$server->uuid]]], 'sideways');
    $saved = Preferences::get($admin, 'overview.groups');
    if (($saved['groups'][0]['servers'] ?? null) !== [$server->uuid] || $saved['groups'][0]['color'] !== '#336699' || $saved['order'] !== 'name') {
        throw new RuntimeException('groups were not saved: '.json_encode($saved));
    }
    Preferences::set($admin, 'overview.groups', []);
});
step('the console page shows the Simplify header, server card and menu', function () use ($admin, $server) {
    $url = Filament\Facades\Filament::getPanel('server')->getUrl($server);
    $response = visit($admin, $url);
    // The panel's home redirects to the console page.
    if ($response->isRedirect()) {
        $url = (string) $response->headers->get('Location');
        $response = visit($admin, $url);
    }
    expectOk($response, $url);
    foreach (['simplifyServer(', 'ps-server-menu', '/simplify/assets/server.js?v=', 'is-console'] as $part) {
        if (! str_contains($response->getContent(), $part)) {
            throw new RuntimeException("$part is missing on $url");
        }
    }
});
step('an add-on adds its own page to the account menu, only on this panel', function ($app) use ($admin) {
    $app->tag([AccountAddon::class], AddonRegistry::TAG);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    $pages = Livewire\Livewire::actingAs($admin)->test(Overview::class)->instance()->initial()['account_pages'] ?? null;
    if (! is_array($pages) || count($pages) !== 1 || $pages[0]['url'] !== url('/steam-key') || $pages[0]['label'] !== 'Steam' || ! str_contains($pages[0]['icon'], '<svg') || ($pages[0]['key'] ?? '') === '') {
        throw new RuntimeException('the account pages are wrong: '.json_encode($pages));
    }
});
step('Connections: only with an add-on row, in the account menu and as a page with that row', function ($app) use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    $url = Connections::getUrl(panel: 'app');
    if (($status = visit($admin, $url)->getStatusCode()) !== 404) {
        throw new RuntimeException("the Connections page without rows answers $status, not 404");
    }
    if ((Livewire\Livewire::actingAs($admin)->test(Overview::class)->instance()->initial()['connections_url'] ?? null) !== null) {
        throw new RuntimeException('the account menu offers Connections without rows');
    }
    // The registry keeps the add-ons it found first: built again with the new one.
    $app->tag([ConnectionsAddon::class], AddonRegistry::TAG);
    $app->forgetInstance(AddonRegistry::class);
    // By path: a request in between can change the host that URLs are made with.
    $menu = Livewire\Livewire::actingAs($admin)->test(Overview::class)->instance()->initial()['connections_url'] ?? null;
    if (! is_string($menu) || parse_url($menu, PHP_URL_PATH) !== parse_url($url, PHP_URL_PATH)) {
        throw new RuntimeException('the account menu has no Connections with a row: '.json_encode($menu));
    }
    $page = visit($admin, $url);
    $html = (string) $page->getContent();
    if ($page->getStatusCode() !== 200 || ! str_contains($html, 'Steam account row') || substr_count($html, 'smoke-connection-row') !== 1 || ! str_contains($html, __('simplify::dashboard.ui.connections_intro'))) {
        throw new RuntimeException('the Connections page does not show its one row: '.$page->getStatusCode());
    }
});
step('add-ons give server links, customer links and host mode', function ($app) use ($admin, $server) {
    $app->tag([HostingAddon::class], AddonRegistry::TAG);
    $url = Filament\Facades\Filament::getPanel('server')->getUrl($server);
    $response = visit($admin, $url);
    if ($response->isRedirect()) {
        $url = (string) $response->headers->get('Location');
        $response = visit($admin, $url);
    }
    expectOk($response, $url);
    if (! str_contains($response->getContent(), 'mailto:help@example.com')) {
        throw new RuntimeException('the support link is missing');
    }
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('server'));
    auth()->setUser($admin);
    Filament\Facades\Filament::setTenant($server);
    $links = Livewire\Livewire::actingAs($admin)->test(ServerPage::class, ['server' => $server])->instance()->initial()['server']['links'];
    if (($links[0]['url'] ?? null) !== 'https://billing.example.com/s/'.$server->uuid) {
        throw new RuntimeException('the server link is missing: '.json_encode($links));
    }
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    $own = Livewire\Livewire::actingAs($admin)->test(Overview::class)->instance()->initial();
    if ($own['customer'] !== []) {
        throw new RuntimeException('admins get customer links: '.json_encode($own['customer']));
    }
    // A customer link with an icon is also in the account menu, for admins too (as in 0.7.0); one without stays in the greeting.
    $account = $own['account_links'];
    if (count($account) !== 1 || $account[0]['url'] !== 'https://billing.example.com/clientarea' || ! str_contains($account[0]['icon'], '<svg')) {
        throw new RuntimeException('the account menu links are wrong: '.json_encode($account));
    }
    $customer = User::query()->where('email', 'smoke-customer@example.com')->first()
        ?? User::withoutEvents(fn () => User::factory()->create(['username' => 'smoke-customer', 'email' => 'smoke-customer@example.com']));
    $links = collect(Livewire\Livewire::actingAs($customer)->test(Overview::class)->instance()->initial()['customer'])->pluck('url')->all();
    if ($links !== ['https://billing.example.com/clientarea', 'https://billing.example.com/order', 'mailto:help@example.com']) {
        throw new RuntimeException('the customer links are wrong: '.json_encode($links));
    }
});
step('a suspended server shows its owner why and the add-on\'s main link on the card, admins as before', function ($app) use ($admin, $server) {
    $app->tag([HostingAddon::class], AddonRegistry::TAG);
    $customer = User::query()->where('email', 'smoke-customer@example.com')->first()
        ?? User::withoutEvents(fn () => User::factory()->create(['username' => 'smoke-customer', 'email' => 'smoke-customer@example.com']));
    $owner = $server->owner_id;
    $server->forceFill(['owner_id' => $customer->id, 'status' => 'suspended'])->saveQuietly();
    try {
        $card = collect(Listing::for($customer))->firstWhere('uuid', $server->uuid);
        $links = $card['suspended'] ?? null;
        if (($card['status'] ?? null) !== 'suspended' || ! is_array($links) || ($links[0]['url'] ?? null) !== 'https://billing.example.com/pay/'.$server->uuid) {
            throw new RuntimeException('the owner sees no invoice link on the suspended card: '.json_encode($card));
        }
        $theirs = collect(Listing::for($admin))->firstWhere('uuid', $server->uuid);
        if (($theirs['suspended'] ?? []) !== []) {
            throw new RuntimeException('an admin gets the invoice link: '.json_encode($theirs['suspended']));
        }
    } finally {
        $server->forceFill(['owner_id' => $owner, 'status' => null])->saveQuietly();
    }
});
step('the server menu leaves Pelican\'s Open in Admin out, in every language (K-B90)', function () use ($admin, $server) {
    // Pelican names the item when the panel starts, in the panel's language; the menu is built in the account's. With
    // another language than the panel's the names differed, and the item showed in the menu, in English.
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('server'));
    auth()->setUser($admin);
    Filament\Facades\Filament::setTenant($server);
    foreach (['en', 'nl', 'de'] as $language) {
        app()->setLocale($language);
        $menu = ServerMenu::build();
        $links = collect([...$menu['main'], ...$menu['advanced']]);
        $admins = $links->filter(fn (array $link) => str_contains($link['url'], '/admin/'))->pluck('label')->all();
        if ($admins !== [] || $links->isEmpty()) {
            throw new RuntimeException("in $language the server menu has ".$links->count().' links, to Admin: '.json_encode($admins));
        }
    }
    app()->setLocale('en');
});
step('Files: a file\'s Delete is in its menu, not a red button of its own', function () use ($admin, $server) {
    Http::fake([
        '*/files/list-directory*' => Http::response([['name' => 'server.properties', 'created' => '2026-01-01T00:00:00Z', 'modified' => '2026-01-01T00:00:00Z', 'mode' => '-rw-r--r--', 'mode_bits' => '644', 'size' => 120, 'directory' => false, 'file' => true, 'symlink' => false, 'mime' => 'text/plain']]),
        '*' => Http::response('', 500),
    ]);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('server'));
    auth()->setUser($admin);
    Filament\Facades\Filament::setTenant($server);
    $page = Livewire\Livewire::actingAs($admin)->test(ListFiles::class);
    $names = function (Testable $page): array {
        $own = $menu = [];
        foreach ($page->instance()->getTable()->getRecordActions() as $action) {
            if ($action instanceof ActionGroup) {
                $menu = [...$menu, ...array_keys($action->getFlatActions())];
            } else {
                $own[] = $action->getName();
            }
        }

        return ['own' => $own, 'menu' => $menu];
    };
    foreach (['first load' => $page, 'a later request' => $page->call('$refresh')] as $when => $now) {
        $found = $names($now);
        if (in_array('delete', $found['own'], true) || ($found['menu'] === [] ? null : end($found['menu'])) !== 'delete') {
            throw new RuntimeException("on $when Delete is not the last item of the menu: ".json_encode($found));
        }
    }
    // The menu item has its name next to the bin, and still asks before it deletes.
    $delete = collect($page->instance()->getTable()->getRecordActions())->first(fn ($action) => $action instanceof ActionGroup)->getFlatActions()['delete'];
    if ($delete->isLabelHidden() || ! $delete->isConfirmationRequired()) {
        throw new RuntimeException('Delete in the menu has no name, or deletes without asking');
    }
    // The row itself, as the browser gets it: Delete is an item of the menu, in the colour of a dangerous action.
    // One button holds both the click and the classes, in the order its Filament version writes them.
    $html = $page->call('loadTable')->html();
    $item = '/<button\b(?=[^<]*wire:click="mountAction\(\'delete\')(?=[^<]*class="[^"]*fi-dropdown-list-item[^"]*fi-color-danger)/';
    if (! str_contains($html, 'server.properties') || preg_match($item, $html) !== 1) {
        throw new RuntimeException('the row of a file does not show Delete as an item of its menu');
    }
});
step('a server gets a colour and banner from its page, shown on its card', function () use ($admin, $server) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('server'));
    auth()->setUser($admin);
    Filament\Facades\Filament::setTenant($server);
    Livewire\Livewire::actingAs($admin)->test(ServerPage::class, ['server' => $server])
        ->set('banner', UploadedFile::fake()->image('banner.png', 600, 100))
        ->call('saveLook', '#AA3355')->assertHasNoErrors();
    $look = Looks::for($server);
    if ($look['color'] !== '#aa3355' || $look['banner'] === null) {
        throw new RuntimeException('the look was not saved: '.json_encode($look));
    }
    $banner = (string) parse_url($look['banner'], PHP_URL_PATH);
    expectOk(visit($admin, $banner), $banner);
    $card = collect(Listing::for($admin))->firstWhere('uuid', $server->uuid);
    if ($card['background'] !== $look['banner'] || $card['color'] !== '#aa3355') {
        throw new RuntimeException('the card does not show the banner and colour: '.json_encode($card));
    }
    Looks::save($server, null, null);
});
step('the server page renames the server, and someone without access cannot use it', function () use ($admin, $server) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('server'));
    auth()->setUser($admin);
    Filament\Facades\Filament::setTenant($server);
    $name = $server->name;
    $page = Livewire\Livewire::actingAs($admin)->test(ServerPage::class, ['server' => $server]);
    if ($page->instance()->saveName('Smoke renamed')['name'] !== 'Smoke renamed' || $server->fresh()->name !== 'Smoke renamed') {
        throw new RuntimeException('the server was not renamed');
    }
    $page->instance()->saveName($name);
    if (($page->instance()->refresh()['uuid'] ?? null) !== $server->uuid) {
        throw new RuntimeException('the server page does not refresh its card');
    }
    $stranger = User::withoutEvents(fn () => User::factory()->create());
    Livewire\Livewire::actingAs($stranger)->test(ServerPage::class, ['server' => $server])->call('power', 'start')->assertForbidden();
    Livewire\Livewire::actingAs($stranger)->test(ServerPage::class, ['server' => $server])->call('saveName', 'Taken')->assertForbidden();
    auth()->setUser($admin);
    $stranger->delete();
});
step('a server an add-on locks shows only its console, without address, and Simplify\'s actions refuse', function ($app) use ($admin, $server) {
    $app->tag([LockAddon::class], AddonRegistry::TAG);
    $name = $server->name;
    $server->forceFill(['name' => 'Egg update test: Paper'])->saveQuietly();
    try {
        Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('server'));
        auth()->setUser($admin);
        Filament\Facades\Filament::setTenant($server);
        $page = Livewire\Livewire::actingAs($admin)->test(ServerPage::class, ['server' => $server]);
        $card = $page->instance()->initial()['server'];
        if (! ($card['locked'] ?? false) || $card['address'] !== null || $card['sftp'] !== null || $card['can_edit'] || $card['can_delete']) {
            throw new RuntimeException('the locked server still shows a way in: '.json_encode($card));
        }
        $html = $page->html();
        if (! str_contains($html, 'smoke-lock-actions') || ! str_contains($html, 'This test server is removed') || str_contains($html, 'ps-power-main')) {
            throw new RuntimeException('the locked server page does not show only the add-on\'s buttons and its line');
        }
        foreach ([['power', 'start'], ['saveName', 'Taken'], ['acceptEula']] as $call) {
            Livewire\Livewire::actingAs($admin)->test(ServerPage::class, ['server' => $server])->call(...$call)->assertStatus(423);
        }
        // Every other page of the server goes back to the console, also typed into the address bar.
        $console = route('filament.server.pages.console', ['tenant' => $server]);
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'filament.server.') && $route->getName() !== 'filament.server.pages.console' && in_array('GET', $route->methods(), true) && $route->parameterNames() === ['tenant']) {
                $url = route($route->getName(), ['tenant' => $server]);
                $response = visit($admin, $url);
                if ($response->isRedirect() && rtrim((string) $response->headers->get('Location'), '/') !== rtrim($console, '/')) {
                    throw new RuntimeException($url.' goes to '.$response->headers->get('Location').' instead of the console');
                }
                if (! $response->isRedirect() && $response->getStatusCode() < 400) {
                    throw new RuntimeException($url.' opens on a locked server');
                }
            }
        }
        expectOk(visit($admin, $console), $console);
    } finally {
        $server->forceFill(['name' => $name])->saveQuietly();
    }
});
step('panel settings are saved and a bad value is refused', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    $bad = Livewire\Livewire::actingAs($admin)->test(Settings::class)->set('data.accent', 'red')->call('save');
    if ($bad->errors()->isEmpty()) {
        throw new RuntimeException('an accent colour that is not a colour was accepted');
    }
    try {
        PanelSettings::save(['overview_seconds' => 2]);
        throw new RuntimeException('a refresh of 2 seconds was accepted');
    } catch (ValidationException) {
    }
    // The refresh time is no longer on the settings page; a saved one stays when the page saves.
    PanelSettings::save(['overview_seconds' => 20]);
    noErrors(Livewire\Livewire::actingAs($admin)->test(Settings::class)->set('data.accent', '#336699')->call('save'));
    if (PanelSettings::get('overview_seconds') !== 20 || PanelSettings::get('accent') !== '#336699') {
        throw new RuntimeException('settings were not saved: '.json_encode(PanelSettings::all()));
    }
    $friends = PanelSettings::get('friends_mode');
    PanelSettings::save(['friends_mode' => true]);
    $on = PanelSettings::friendsMode();
    PanelSettings::save(['friends_mode' => false]);
    $off = PanelSettings::friendsMode();
    PanelSettings::save(['friends_mode' => (bool) $friends]);
    if ($on !== true || $off !== false) {
        throw new RuntimeException('friendsMode() does not follow the friends mode setting');
    }
});
step('Health limits: chosen on the settings page, red above yellow, and a node\'s state follows them (Khion 23-11)', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    try {
        $wrong = Livewire\Livewire::actingAs($admin)->test(Settings::class)->set('data.load_warning', 90)->set('data.load_critical', 80)->call('save');
        if ($wrong->errors()->isEmpty() || PanelSettings::get('load_critical') !== 95) {
            throw new RuntimeException('a red limit below the yellow one was accepted: '.json_encode([PanelSettings::get('load_warning'), PanelSettings::get('load_critical')]));
        }
        noErrors(Livewire\Livewire::actingAs($admin)->test(Settings::class)->set('data.load_warning', 70)->set('data.load_critical', 80)->call('save'));
        $limits = HostHealth::limits();
        $node = ['maintenance' => false, 'stats' => ['cpu_percent' => 75, 'memory_used' => 85, 'memory_total' => 100, 'disk_used' => 10, 'disk_total' => 100]];
        $summary = HostHealth::summarize([$node]);
        $shown = HostHealth::present($summary + ['label_key' => 'healthy', 'nodes' => []]);
        if ($limits !== [70, 80] || $summary['metric_states']['cpu'] !== 'warning' || $summary['metric_states']['memory'] !== 'critical' || ($shown['limits'] ?? null) !== [70, 80]) {
            throw new RuntimeException('the node state does not follow the saved limits: '.json_encode([$limits, $summary['metric_states'], $shown['limits'] ?? null]));
        }
    } finally {
        PanelSettings::save(['load_warning' => 85, 'load_critical' => 95]);
    }
});
step('a save that changed a setting loads the settings again, so the top bar shows it (K-B76)', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    $name = PanelSettings::get('brand_name');
    try {
        $changed = Livewire\Livewire::actingAs($admin)->test(Settings::class)->set('data.brand_name', 'Changed name')->call('save');
        noErrors($changed);
        if (! str_contains((string) ($changed->effects['redirect'] ?? ''), parse_url(Settings::getUrl(panel: 'admin'), PHP_URL_PATH))) {
            throw new RuntimeException('after a changed name the page was not loaded again: the top bar keeps the old name');
        }
        // Save without a change stays where it is.
        $same = Livewire\Livewire::actingAs($admin)->test(Settings::class)->call('save');
        noErrors($same);
        if (isset($same->effects['redirect'])) {
            throw new RuntimeException('a save without changes loaded the page again');
        }
    } finally {
        PanelSettings::save(['brand_name' => $name]);
    }
});
step('the accent colour and refresh rate reach the panel', function () use ($admin) {
    $url = Overview::getUrl(panel: 'app');
    $response = visit($admin, $url);
    expectOk($response, $url);
    $html = $response->getContent();
    if (! str_contains($html, Color::hex('#336699')[500])) {
        throw new RuntimeException('the accent colour is not used');
    }
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    if (Livewire\Livewire::actingAs($admin)->test(Overview::class)->instance()->initial()['performance']['overview_seconds'] !== 20) {
        throw new RuntimeException('the refresh rate is not used');
    }
});
step('an uploaded logo and panel name are shown and served', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    Storage::disk('local')->put(Brand::DIRECTORY.'/smoke-logo.png', $png);
    PanelSettings::save(['brand_name' => 'Smoke Panel', 'brand_logo' => Brand::DIRECTORY.'/smoke-logo.png']);
});
step('the panel uses the uploaded logo and name', function () use ($admin) {
    $url = Overview::getUrl(panel: 'app');
    $response = visit($admin, $url);
    expectOk($response, $url);
    if (! str_contains($response->getContent(), '/simplify/brand/smoke-logo.png')) {
        throw new RuntimeException('the logo is not used');
    }
    if (! str_contains($response->getContent(), 'Smoke Panel')) {
        throw new RuntimeException('the panel name is not used');
    }
    $logo = visit($admin, '/simplify/brand/smoke-logo.png');
    expectOk($logo, '/simplify/brand/smoke-logo.png');
    if (visit($admin, '/simplify/brand/missing.png')->getStatusCode() !== 404) {
        throw new RuntimeException('a missing logo is not a 404');
    }
});
step('host health loads for a root admin and names a node that does not answer', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    $health = Livewire\Livewire::actingAs($admin)->test(Overview::class)->instance()->refreshHostHealth();
    if (($health['hosts'] ?? 0) < 1 || $health['state'] !== 'unavailable' || ($health['nodes'][0]['problem'] ?? null) === null) {
        throw new RuntimeException('unexpected host health: '.json_encode($health));
    }
});
step('the Health button knows when Pelican\'s health checks need attention', function () use ($admin) {
    // The page under the button counts the checks that failed, crashed or warn; the button gets that count's words.
    $store = fn (array $statuses) => app()->instance(ResultStore::class, new class($statuses) implements ResultStore
    {
        /** @param list<string> $statuses */
        public function __construct(private array $statuses) {}

        public function save(Collection $checkResults): void {}

        public function latestResults(): ?StoredCheckResults
        {
            return new StoredCheckResults(now(), collect($this->statuses)->map(
                fn (string $status, int $i) => new StoredCheckResult("Smoke$i", "Smoke $i", 'smoke', 'smoke', $status),
            ));
        }
    });
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    $attention = function () use ($admin) {
        Once::flush();

        $initial = Livewire\Livewire::actingAs($admin)->test(Overview::class)->instance()->initial();

        return array_key_exists('health_attention', $initial) ? $initial['health_attention'] : 'missing';
    };
    $store(['ok', 'warning', 'failed', 'skipped', 'crashed']);
    if ($attention() !== '3 need attention') {
        throw new RuntimeException('three checks need attention, the button got: '.json_encode($attention()));
    }
    $store(['ok', 'warning']);
    if ($attention() !== '1 needs attention') {
        throw new RuntimeException('one check needs attention, the button got: '.json_encode($attention()));
    }
    $store(['ok', 'skipped']);
    if ($attention() !== null) {
        throw new RuntimeException('every check is fine, the button got: '.json_encode($attention()));
    }
    $player = User::where('email', 'smoke-player@example.com')->first()
        ?? User::withoutEvents(fn () => User::factory()->create(['email' => 'smoke-player@example.com']));
    $store(['failed']);
    Once::flush();
    if ((Livewire\Livewire::actingAs($player)->test(Overview::class)->instance()->initial()['health_attention'] ?? null) !== null) {
        throw new RuntimeException('someone who is no admin was told about the health checks');
    }
});
step('the panel can be installed as an app', function () use ($admin) {
    $manifest = visit($admin, '/simplify/manifest.webmanifest');
    expectOk($manifest, '/simplify/manifest.webmanifest');
    $data = json_decode($manifest->getContent(), true);
    if (($data['name'] ?? null) !== 'Smoke Panel' || count($data['icons'] ?? []) !== 3) {
        throw new RuntimeException('unexpected manifest: '.$manifest->getContent());
    }
    $worker = visit($admin, '/simplify-worker.js');
    expectOk($worker, '/simplify-worker.js');
    if (! str_contains((string) $worker->headers->get('Content-Type'), 'javascript') || str_contains($worker->getContent(), '__VERSION__')) {
        throw new RuntimeException('the worker is not served as filled-in JavaScript');
    }
    $url = Overview::getUrl(panel: 'app');
    $page = visit($admin, $url);
    expectOk($page, $url);
    if (! str_contains($page->getContent(), 'simplify/manifest.webmanifest') || ! str_contains($page->getContent(), 'simplify-worker.js')) {
        throw new RuntimeException('the page does not link the manifest and worker');
    }
});
step('app notifications: the bell sheet has every option, a device gets a message once and a dropped device is removed', function () use ($admin) {
    $url = Overview::getUrl(panel: 'app');
    $response = visit($admin, $url);
    expectOk($response, $url);
    foreach (["pushChoose('messages'", "pushChoose('pelican'", "pushChoose('updates'", 'ps-notify-devices', 'pushRemove('] as $part) {
        if (! str_contains($response->getContent(), $part)) {
            throw new RuntimeException("the bell sheet misses $part");
        }
    }
    if (Route::has('filament.app.pages.simplify-notifications')) {
        throw new RuntimeException('the separate notifications page is still registered');
    }
    $browser = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $subscription = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/smoke', 'keys' => ['p256dh' => WebPush::b64(WebPush::point($browser)), 'auth' => WebPush::b64(random_bytes(16))]];
    $removed = Push::subscribe($admin, $subscription, 'Smoke browser');
    Push::remove($admin, $removed->device);
    if (Push::current($admin, $subscription['endpoint']) !== null) {
        throw new RuntimeException('removing a device by its id kept it');
    }
    Push::subscribe($admin, $subscription, 'Smoke browser');
    // The push service takes the first message, then says the browser dropped the subscription.
    Http::fake(['fcm.googleapis.com/*' => Http::sequence()->push('', 201)->push('', 410)]);
    if (! Event::hasListeners('simplify.push')) {
        throw new RuntimeException('nobody listens to simplify.push');
    }
    $message = ['users' => [$admin->id], 'title' => 'Smoke', 'body' => 'Hello', 'url' => '/', 'tag' => 'smoke-1'];
    $first = array_sum(event('simplify.push', [$message]));
    $again = array_sum(event('simplify.push', [$message]));
    if ($first !== 1 || $again !== 0) {
        throw new RuntimeException("sent to $first and then $again devices, expected 1 and 0");
    }
    Http::assertSent(fn ($request) => $request->hasHeader('Content-Encoding', 'aes128gcm') && str_starts_with($request->header('Authorization')[0], 'vapid t='));
    if (array_sum(event('simplify.push', [[...$message, 'tag' => 'smoke-2']])) !== 0 || Push::devices($admin)->isNotEmpty()) {
        throw new RuntimeException('a device the browser dropped was kept');
    }
});
step('update notices tell root admins about a new Pelican once', function () use ($admin) {
    $browser = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $subscription = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/smoke-updates', 'keys' => ['p256dh' => WebPush::b64(WebPush::point($browser)), 'auth' => WebPush::b64(random_bytes(16))]];
    $device = Push::subscribe($admin, $subscription, 'Smoke browser');
    // Pelican up to rc.2 caches the latest versions under these keys, rc.3 and later the whole release.
    foreach (['panel:latest_version', 'wings:latest_version', 'panel:latest_release', 'wings:latest_release'] as $key) {
        cache()->forget($key);
    }
    Http::fake([
        'api.github.com/*' => Http::response(['tag_name' => 'v99.0.0', 'body' => '']),
        'fcm.googleapis.com/*' => Http::response('', 201),
        '*' => Http::response('', 500),
    ]);
    cache()->forget('simplify.update-notice.pelican-99.0.0');
    $bell = fn () => $admin->notifications()->where('data->title', 'Pelican 99.0.0 is available')->count();
    $admin->notifications()->where('data->title', 'Pelican 99.0.0 is available')->delete();
    if (Artisan::call('simplify:update-notices') !== 0 || ! in_array('pelican-99.0.0', $device->refresh()->received ?? [], true)) {
        throw new RuntimeException('no update notice: '.Artisan::output().json_encode($device->received));
    }
    if ($bell() !== 1) {
        throw new RuntimeException('the bell got '.$bell().' notices about the new Pelican, expected 1');
    }
    if (UpdateNotices::send() !== 0 || $bell() !== 1) {
        throw new RuntimeException('the same update was announced twice');
    }
    $device->delete();
    // The bell gets it without app notifications too: those are a choice per panel and per device.
    cache()->forget('simplify.update-notice.pelican-99.0.0');
    $admin->notifications()->where('data->title', 'Pelican 99.0.0 is available')->delete();
    PanelSettings::save(['push' => false]);
    try {
        UpdateNotices::send();
        if ($bell() !== 1) {
            throw new RuntimeException('with app notifications off the bell got '.$bell().' notices, expected 1');
        }
    } finally {
        PanelSettings::save(['push' => true]);
        $admin->notifications()->where('data->title', 'Pelican 99.0.0 is available')->delete();
    }
});
step('announcements: live bars show for the right people, and the admin list is for root admins', function () use ($admin) {
    $player = User::where('email', 'smoke-player@example.com')->first()
        ?? User::withoutEvents(fn () => User::factory()->create(['email' => 'smoke-player@example.com']));
    Announcement::query()->delete();
    Announcement::create(['category' => 'warning', 'title' => 'Smoke maintenance tonight', 'ends_at' => now()->addHour(), 'button_url' => '/']);
    Announcement::create(['title' => 'Smoke for admins', 'audience' => 'admins']);
    Announcement::create(['title' => 'Smoke later', 'starts_at' => now()->addDay()]);
    // The overview is the start page: Pelican's list at / sends there.
    $home = Overview::getUrl(panel: 'app');
    $response = visit($player, $home);
    expectOk($response, $home);
    $page = $response->getContent();
    if (! str_contains($page, 'Smoke maintenance tonight') || str_contains($page, 'Smoke for admins') || str_contains($page, 'Smoke later')) {
        throw new RuntimeException('the wrong bars show for a player');
    }
    if (! str_contains(visit($admin, $home)->getContent(), 'Smoke for admins')) {
        throw new RuntimeException('an admin misses the admins-only bar');
    }
    $url = AnnouncementResource::getUrl(panel: 'admin');
    $list = visit($admin, $url);
    expectOk($list, $url);
    if (! str_contains($list->getContent(), 'Smoke later') || str_contains((string) visit($player, $url)->getContent(), 'Smoke later')) {
        throw new RuntimeException('the admin list is wrong or opens for a player');
    }
});
step('announcements: a server page shows them as short rows in the top bar, not as bars above the console', function () use ($admin, $server) {
    $note = Announcement::create(['title' => 'Smoke card note', 'body' => 'Short and in the card']);
    try {
        $url = Filament\Facades\Filament::getPanel('server')->getUrl($server);
        $response = visit($admin, $url);
        if ($response->isRedirect()) {
            $url = (string) $response->headers->get('Location');
            $response = visit($admin, $url);
        }
        expectOk($response, $url);
        $page = $response->getContent();
        if (! str_contains($page, 'class="ps-boot is-server"')) {
            throw new RuntimeException('the server page has no placeholders while it starts');
        }
        $card = strpos($page, 'ps-info-notes');
        if ($card === false || strpos($page, 'Smoke card note', $card) === false || str_contains($page, 'simplify-announcement-')) {
            throw new RuntimeException('the announcement is not a row in the top bar');
        }
    } finally {
        $note->delete();
    }
});
step('announcements: unpin moves a bar to the bell, pin brings it back, and the scheduler fills the bell once', function () {
    $player = User::where('email', 'smoke-player@example.com')->firstOrFail();
    $live = Announcement::query()->where('title', 'Smoke maintenance tonight')->firstOrFail();
    auth()->setUser($player);
    Livewire\Livewire::test(AnnouncementBars::class)->call('unpin', $live->id);
    if (Announcement::barsFor($player)->whereKey($live->id)->exists() || ! AnnouncementBell::has($live->id, $player)) {
        throw new RuntimeException('unpinning did not move the bar to the bell');
    }
    Livewire\Livewire::test(AnnouncementBars::class)->call('pin', $live->id);
    if (Announcement::barsFor($player)->whereKey($live->id)->doesntExist() || AnnouncementBell::has($live->id, $player)) {
        throw new RuntimeException('pinning did not bring the bar back');
    }
    $bell = Announcement::create(['title' => 'Smoke in the bell', 'in_bell' => true]);
    if (Artisan::call('simplify:announcements') !== 0) {
        throw new RuntimeException('the scheduler command failed: '.Artisan::output());
    }
    Artisan::call('simplify:announcements');
    if ($player->notifications()->where('data->viewData->simplify_announcement', $bell->id)->count() !== 1) {
        throw new RuntimeException('the bell item did not arrive exactly once');
    }
});
step('announcement task: sends now to the owner once, and "when online" waits in the scheduler, then says it did not come online (K-B64)', function () use ($server) {
    $owner = User::query()->findOrFail($server->owner_id);
    $owner->notifications()->delete();
    $run = function (array $payload) use ($server) {
        $task = new Task(['payload' => TaskAnnouncement::encode($payload)]);
        $task->setRelation('server', $server);
        $task->setRelation('schedule', new Schedule(['name' => 'Smoke schedule']));
        (new AnnouncementTask)->runTask($task);
    };
    $run(['message' => 'Smoke task now', 'send' => 'now', 'notify' => true]);
    if ($owner->notifications()->where('data->body', 'Smoke task now')->count() !== 1) {
        throw new RuntimeException('"now" did not reach the owner exactly once');
    }
    $run(['message' => 'Smoke task online', 'send' => 'online', 'notify' => true]);
    $waits = fn () => DB::table(TaskWaits::TABLE)->where('server_id', $server->id);
    if ($waits()->count() !== 1) {
        throw new RuntimeException('"when online" did not wait in the table (the server has no Wings here, so it is offline)');
    }
    Artisan::call('simplify:announcements');
    if ($waits()->count() !== 1 || $owner->notifications()->where('data->body', 'Smoke task online')->exists()) {
        throw new RuntimeException('an offline server got the announcement, or stopped waiting before 10 minutes');
    }
    $waits()->update(['started_at' => time() - TaskAnnouncement::WAIT_SECONDS - 1]);
    Artisan::call('simplify:announcements');
    Artisan::call('simplify:announcements');
    $said = __('simplify::announcements.task.not_online', ['server' => $server->name], $owner->language ?: 'en');
    if ($waits()->exists() || $owner->notifications()->where('data->body', $said)->count() !== 1) {
        throw new RuntimeException('after 10 minutes the owner did not hear once that the server did not come online');
    }
});
step('announcements on a server start: no bar and no bell item at a time, the scheduler only remembers an offline server', function () use ($server) {
    $admin = User::query()->whereHas('roles')->firstOrFail();
    $announcement = Announcement::query()->create([
        'title' => 'Smoke back online', 'send_on' => StartTrigger::START, 'start_servers' => [$server->id],
        'in_bell' => true, 'push' => false, 'in_game' => true, 'created_by' => $admin->id,
    ]);
    try {
        if (Announcement::barsFor($admin)->whereKey($announcement->id)->exists()) {
            throw new RuntimeException('an announcement for a server start shows as a bar');
        }
        Artisan::call('simplify:announcements');
        $announcement->refresh();
        if ($admin->notifications()->where('data->viewData->simplify_announcement', $announcement->id)->exists()) {
            throw new RuntimeException('an announcement for a server start went to the bell before any start');
        }
        // Wings says the server is off: remembered as off, nothing sent.
        cache()->put("servers.$server->uuid.status", ContainerStatus::Offline, 60);
        cache()->forget('simplify-announcements.start.'.$announcement->id.'.'.intdiv(time(), 60));
        Artisan::call('simplify:announcements');
        $announcement->refresh();
        if ($announcement->start_seen != [$server->id => false] || $announcement->in_game_at !== null) {
            throw new RuntimeException('the scheduler did not remember the server as offline: '.json_encode($announcement->start_seen));
        }
        $announcement->update(['start_servers' => [$server->id, $server->id + 100000]]);
        if ($announcement->refresh()->start_seen !== null) {
            throw new RuntimeException('other servers did not make it look afresh');
        }
    } finally {
        $announcement->delete();
    }
});
step('announcements on a server start: a missed answer from Wings is no stop and start (K-B86)', function () use ($server) {
    $admin = User::query()->whereHas('roles')->firstOrFail();
    $announcement = Announcement::query()->create([
        'title' => 'Smoke never stopped', 'send_on' => StartTrigger::START, 'start_servers' => [$server->id],
        'in_bell' => true, 'push' => false, 'in_game' => false, 'created_by' => $admin->id,
    ]);
    $items = fn () => $admin->notifications()->where('data->viewData->simplify_announcement', $announcement->id)->count();
    // One look of the scheduler with what Wings says about the server: Pelican keeps that answer in its cache.
    $look = function (ContainerStatus $status, int $uptime = 0) use ($announcement, $server) {
        cache()->put("servers.$server->uuid.status", $status, 60);
        cache()->put("servers.$server->uuid.resources", $uptime ? ['uptime' => $uptime * 1000] : [], 60);
        cache()->forget('simplify-announcements.start.'.$announcement->id.'.'.intdiv(time(), 60));
        Artisan::call('simplify:announcements');

        return $announcement->refresh()->start_seen;
    };
    try {
        $look(ContainerStatus::Running, 3600);
        $seen = $look(ContainerStatus::Missing);
        if (! is_int($seen[$server->id] ?? null)) {
            throw new RuntimeException('with no answer from Wings the running server was not kept as running: '.json_encode($seen));
        }
        $look(ContainerStatus::Running, 3660);
        if ($items() !== 0) {
            throw new RuntimeException('a server that never stopped got its start announcement after one missed answer from Wings');
        }
        // A real restart while Wings was silent still goes out, once.
        $look(ContainerStatus::Missing);
        $look(ContainerStatus::Running, 20);
        $look(ContainerStatus::Running, 80);
        if ($items() !== 1) {
            throw new RuntimeException('a restart during the silence went out '.$items().' times, expected once');
        }
    } finally {
        cache()->forget("servers.$server->uuid.status");
        cache()->forget("servers.$server->uuid.resources");
        $announcement->delete();
    }
});
step('announcements on a server start: only starts on the chosen days go out (Khion 15-11)', function () use ($server) {
    $admin = User::query()->whereHas('roles')->firstOrFail();
    $today = now()->utc()->isoWeekday();
    $announcement = Announcement::query()->create([
        'title' => 'Smoke started on days', 'send_on' => StartTrigger::START, 'start_servers' => [$server->id],
        'weekly_days' => [$today % 7 + 1], 'weekly_zone' => 'UTC',
        'in_bell' => true, 'push' => false, 'in_game' => false, 'created_by' => $admin->id,
    ]);
    $items = fn () => $admin->notifications()->where('data->viewData->simplify_announcement', $announcement->id)->count();
    try {
        // What the scheduler does with a start it saw (StartAnnouncements::look).
        if (StartAnnouncements::startedToday($announcement, $server) || $items() !== 0) {
            throw new RuntimeException('a start on another day went out');
        }
        $announcement->update(['weekly_days' => [$today]]);
        if (! StartAnnouncements::startedToday($announcement->refresh(), $server) || $items() !== 1) {
            throw new RuntimeException('a start on a chosen day did not go out: '.$items());
        }
        $announcement->update(['weekly_days' => []]);
        if (! StartAnnouncements::startedToday($announcement->refresh(), $server) || $items() !== 1) {
            throw new RuntimeException('without days a start did not go out every day');
        }
    } finally {
        $announcement->delete();
    }
});
step('announcements on a server start: once per day sends the first start of each chosen day, in its hours, every week (K-B93)', function () use ($server) {
    $admin = User::query()->whereHas('roles')->firstOrFail();
    $now = now()->utc()->setTime(12, 0)->getTimestamp();
    $announcement = Announcement::query()->create([
        'title' => 'Smoke started once a day', 'send_on' => StartTrigger::START, 'start_servers' => [$server->id], 'start_daily' => true,
        'weekly_days' => [], 'weekly_zone' => 'UTC', 'start_from' => '10:00', 'start_until' => '22:00',
        'in_bell' => true, 'push' => false, 'in_game' => false, 'created_by' => $admin->id,
    ]);
    $items = fn () => $admin->notifications()->where('data->viewData->simplify_announcement', $announcement->id)->count();
    try {
        if (StartAnnouncements::startedToday($announcement, $server, $now - 4 * 3600) || $items() !== 0) {
            throw new RuntimeException('a start before its hours went out');
        }
        if (! StartAnnouncements::startedToday($announcement->refresh(), $server, $now) || $items() !== 1) {
            throw new RuntimeException('the first start of the day did not go out: '.$items());
        }
        if (StartAnnouncements::startedToday($announcement->refresh(), $server, $now + 3600) || $items() !== 1) {
            throw new RuntimeException('a restart later that day went out again: '.$items());
        }
        if ($announcement->refresh()->state() === 'ended') {
            throw new RuntimeException('once per day ended the announcement, so it would not come back next week');
        }
        if (! StartAnnouncements::startedToday($announcement->refresh(), $server, $now + 7 * 86400) || $items() < 1) {
            throw new RuntimeException('the next chosen day it did not go out again');
        }
    } finally {
        $announcement->delete();
    }
});
step('announcements on a server start: one that goes out once keeps its bell item when it ends (K-B68)', function () use ($server) {
    $admin = User::query()->whereHas('roles')->firstOrFail();
    $announcement = Announcement::query()->create([
        'title' => 'Smoke started once', 'send_on' => StartTrigger::START, 'start_servers' => [$server->id], 'start_once' => true,
        'in_bell' => true, 'push' => false, 'in_game' => false, 'created_by' => $admin->id,
    ]);
    $items = fn () => $admin->notifications()->where('data->viewData->simplify_announcement', $announcement->id)->count();
    try {
        // What the scheduler does on a start (StartAnnouncements::started): the bell item, then the end of a once-only one.
        StartAnnouncements::started($announcement, $server);
        if ($announcement->refresh()->state() !== 'ended') {
            throw new RuntimeException('an announcement that goes out once did not end after its start');
        }
        if ($items() !== 1) {
            throw new RuntimeException('the bell item of a server start was gone right after it was sent: '.$items());
        }
        // The list says Sent from then on: it kept saying "Waits for start" after the start had gone out (K-B89).
        if ($announcement->in_bell_at === null) {
            throw new RuntimeException('after its start went out the list still says the bell waits for a start');
        }
        // Ending it by hand keeps the message of the start as well; deleting it takes it away.
        $announcement->update(['ends_at' => now()->subMinute()]);
        if ($items() !== 1) {
            throw new RuntimeException('ending an announcement on a server start took its bell item away');
        }
    } finally {
        $announcement->delete();
    }
    if ($items() !== 0) {
        throw new RuntimeException('a deleted announcement left its bell item behind');
    }
});
step('announcements: Devices reached counts the devices of the latest sending, not every sending added up (K-B87)', function () use ($admin, $server) {
    $browser = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $device = Push::subscribe($admin, ['endpoint' => 'https://fcm.googleapis.com/fcm/send/smoke-reached', 'keys' => ['p256dh' => WebPush::b64(WebPush::point($browser)), 'auth' => WebPush::b64(random_bytes(16))]], 'Smoke browser');
    Http::fake(['fcm.googleapis.com/*' => Http::response('', 201), '*' => Http::response('', 500)]);
    $start = Announcement::query()->create([
        'title' => 'Smoke reached on a start', 'send_on' => StartTrigger::START, 'start_servers' => [$server->id],
        'in_bell' => false, 'push' => true, 'in_game' => false, 'created_by' => $admin->id,
    ]);
    $turn = now()->utc()->subMinutes(2)->startOfMinute();
    $weekly = Announcement::query()->create([
        'title' => 'Smoke reached each week', 'send_on' => Weekly::SEND_ON, 'weekly_days' => [$turn->isoWeekday()], 'weekly_at' => $turn->format('H:i'),
        'weekly_zone' => 'UTC', 'weekly_hours' => 1, 'in_bell' => false, 'push' => true, 'in_game' => false, 'created_by' => $admin->id,
    ]);
    try {
        // One device, three starts: the list said "Devices reached: 3" (Khion: 12, with one device).
        foreach ([1, 2, 3] as $nth) {
            StartAnnouncements::started($start->refresh(), $server);
            sleep(1);
        }
        if ($start->refresh()->pushed_to !== 1) {
            throw new RuntimeException('one device and three starts: Devices reached says '.$start->pushed_to);
        }
        // On days of the week: last week's turn reached 7 devices, this week's turn reaches the one there is now.
        $weekly->forceFill(['pushed_to' => 7, 'weekly_from' => $turn->clone()->subWeek(), 'weekly_until' => $turn->clone()->subWeek()->addHour()])->saveQuietly();
        WeeklyAnnouncements::runDue();
        Simplify\Core\Announcements\Services\Push::sendDue();
        Simplify\Core\Announcements\Services\Push::sendDue();
        if ($weekly->refresh()->pushed_to !== 1) {
            throw new RuntimeException('one device in this week\'s turn: Devices reached says '.$weekly->pushed_to);
        }
    } finally {
        $start->delete();
        $weekly->delete();
        $device->delete();
    }
});
step('announcements on days of the week: the form saves the days and time, each turn goes out once and shows the bar again (Khion 15-11)', function () use ($admin) {
    // The form: days, times, how long the bar stays and the author's time zone are saved as Simplify reads them.
    Livewire\Livewire::actingAs($admin)->test(ManageAnnouncements::class)
        ->call('mountAction', 'create')
        ->set('mountedActions.0.data.title', 'Smoke weekly form')
        ->set('mountedActions.0.data.when', FormData::WEEKLY)
        ->set('mountedActions.0.data.weekly_days', ['5', '1'])
        ->set('mountedActions.0.data.weekly_times', ['19:00', '12:00'])
        ->set('mountedActions.0.data.stay', 'for')
        ->set('mountedActions.0.data.stay_n', '30')
        ->set('mountedActions.0.data.stay_unit', 'minutes')
        ->call('callMountedAction')
        ->assertHasNoErrors();
    $saved = Announcement::query()->where('title', 'Smoke weekly form')->first();
    if (! $saved || $saved->send_on !== Weekly::SEND_ON || $saved->weekly_days !== [1, 5] || $saved->weekly_times !== ['12:00', '19:00'] || ! $saved->bar || $saved->stay !== 'for' || $saved->stay_minutes !== 30 || Weekly::zone($saved->weekly_zone) !== $saved->weekly_zone) {
        throw new RuntimeException('the form did not save an announcement for every week: '.json_encode($saved?->only(['send_on', 'weekly_days', 'weekly_times', 'bar', 'stay', 'stay_minutes', 'weekly_zone'])));
    }
    $saved->delete();

    // Its turn was two minutes ago, today (UTC): it goes out now, with the bar.
    $turn = now()->utc()->subMinutes(2)->startOfMinute();
    $announcement = Announcement::query()->create([
        'title' => 'Smoke weekly', 'send_on' => Weekly::SEND_ON, 'weekly_days' => [$turn->isoWeekday()], 'weekly_at' => $turn->format('H:i'),
        'weekly_zone' => 'UTC', 'weekly_hours' => 1, 'in_bell' => true, 'push' => false, 'in_game' => false, 'created_by' => $admin->id,
    ]);
    $other = Announcement::query()->create([
        'title' => 'Smoke weekly tomorrow', 'send_on' => Weekly::SEND_ON, 'weekly_days' => [$turn->isoWeekday() % 7 + 1], 'weekly_at' => $turn->format('H:i'),
        'weekly_zone' => 'UTC', 'weekly_hours' => 1, 'in_bell' => true, 'push' => false, 'in_game' => false, 'created_by' => $admin->id,
    ]);
    $items = fn () => $admin->notifications()->where('data->viewData->simplify_announcement', $announcement->id)->count();
    $bars = fn () => Announcement::barsFor($admin)->pluck('id')->map(fn ($id) => (int) $id)->all();
    try {
        if (WeeklyAnnouncements::runDue() !== 1 || $announcement->refresh()->weekly_from?->getTimestamp() !== $turn->getTimestamp()) {
            throw new RuntimeException('today\'s turn did not start: '.json_encode($announcement->only(['weekly_from', 'weekly_until'])));
        }
        AnnouncementBell::deliverDue();
        if ($items() !== 1 || ! in_array($announcement->id, $bars(), true) || in_array($other->id, $bars(), true)) {
            throw new RuntimeException('a turn did not reach the bell and the bars, or another day showed: bell '.$items().' bars '.json_encode($bars()));
        }
        // The next minute: the same turn, nothing new.
        AnnouncementResource::deliverNow();
        if (WeeklyAnnouncements::runDue() !== 0 || $items() !== 1) {
            throw new RuntimeException('the same turn went out twice');
        }
        // Unpinned last week: this week's turn shows the bar again and puts one new item in the bell.
        $announcement->dismissedBy()->syncWithoutDetaching([$admin->id => ['created_at' => now()]]);
        $announcement->forceFill(['weekly_from' => $turn->clone()->subWeek(), 'weekly_until' => $turn->clone()->subWeek()->addHour()])->saveQuietly();
        if (in_array($announcement->id, $bars(), true)) {
            throw new RuntimeException('the bar showed outside its hours');
        }
        if (WeeklyAnnouncements::runDue() !== 1) {
            throw new RuntimeException('a new week did not start a new turn');
        }
        AnnouncementBell::deliverDue();
        if ($items() !== 1 || ! in_array($announcement->id, $bars(), true)) {
            throw new RuntimeException('a new turn did not bring the bar and one bell item back: bell '.$items().' bars '.json_encode($bars()));
        }
        // The list says when it goes out.
        $list = visit($admin, AnnouncementResource::getUrl(panel: 'admin'))->getContent();
        if (! str_contains((string) $list, $turn->format('H:i'))) {
            throw new RuntimeException('the list does not show the days and time');
        }
    } finally {
        $announcement->delete();
        $other->delete();
    }
});
step('announcements: the form asks what, when, where and who, and a server moment is stored as the scheduler reads it (V65)', function () use ($admin, $server) {
    Livewire\Livewire::actingAs($admin)->test(ManageAnnouncements::class)
        ->call('mountAction', 'create')
        ->set('mountedActions.0.data.title', 'Smoke form stop')
        ->set('mountedActions.0.data.when', FormData::SERVER)
        ->set('mountedActions.0.data.start_event', 'stop')
        ->set('mountedActions.0.data.start_servers', [(string) $server->id])
        ->set('mountedActions.0.data.often', FormData::DAY)
        ->set('mountedActions.0.data.bar', true)
        ->set('mountedActions.0.data.stay', 'for')
        ->set('mountedActions.0.data.stay_n', '2')
        ->set('mountedActions.0.data.stay_unit', 'hours')
        ->set('mountedActions.0.data.in_bell', true)
        ->set('mountedActions.0.data.audience', Rules::SERVER_AUDIENCE)
        ->call('callMountedAction')
        ->assertHasNoErrors();
    $saved = Announcement::query()->where('title', 'Smoke form stop')->first();
    try {
        $want = ['send_on' => StartTrigger::START, 'start_event' => 'stop', 'start_servers' => [(int) $server->id], 'start_daily' => true, 'start_once' => false, 'bar' => true, 'stay' => 'for', 'stay_minutes' => 120, 'audience' => Rules::SERVER_AUDIENCE, 'in_game_target' => 'started'];
        if (! $saved || $saved->only(array_keys($want)) != $want || $saved->starts_at !== null) {
            throw new RuntimeException('the form did not save a server moment: '.json_encode($saved?->only([...array_keys($want), 'starts_at'])));
        }
        // What the form shows when it is opened again, and the sentence in the list.
        $shown = FormData::fill($saved->attributesToArray());
        if ($shown['when'] !== FormData::SERVER || $shown['often'] !== FormData::DAY || $shown['stay_n'] !== 2 || $shown['stay_unit'] !== 'hours') {
            throw new RuntimeException('a saved server moment does not open as it was filled in: '.json_encode($shown));
        }
        $list = (string) visit($admin, AnnouncementResource::getUrl(panel: 'admin'))->getContent();
        $sentence = __('simplify::announcements.summary.stop_day', ['servers' => $server->name]);
        if (! str_contains(html_entity_decode($list, ENT_QUOTES), $sentence)) {
            throw new RuntimeException('the list does not say what the announcement does: '.$sentence);
        }
        // A new form starts as "now, with a bar, for everyone", and an example fills in its answers.
        Livewire\Livewire::actingAs($admin)->test(ManageAnnouncements::class)
            ->call('mountAction', 'create')
            ->assertSet('mountedActions.0.data.when', FormData::NOW)
            ->assertSet('mountedActions.0.data.bar', true)
            ->assertSet('mountedActions.0.data.stay', 'ended')
            ->assertSet('mountedActions.0.data.audience', 'everyone')
            ->assertSet('mountedActions.0.data.in_bell', true)
            // Testlab 22-11: a click on the choice that is on emptied it, and Create refused.
            ->set('mountedActions.0.data.when', null)
            ->assertSet('mountedActions.0.data.when', FormData::NOW)
            ->set('mountedActions.0.data.audience', null)
            ->assertSet('mountedActions.0.data.audience', 'everyone')
            // Maintenance tonight comes with its date filled in.
            ->set('mountedActions.0.data.example', 'maintenance')
            ->assertSet('mountedActions.0.data.when', FormData::DATE)
            ->assertNotSet('mountedActions.0.data.starts_at', null)
            ->set('mountedActions.0.data.example', 'weekend')
            ->assertSet('mountedActions.0.data.when', FormData::SERVER)
            ->assertSet('mountedActions.0.data.often', FormData::DAY)
            ->assertSet('mountedActions.0.data.bar', false)
            ->assertSet('mountedActions.0.data.start_from', '10:00')
            ->assertSet('mountedActions.0.data.audience', Rules::SERVER_AUDIENCE);
    } finally {
        $saved?->delete();
    }
});
step('announcements on a server moment: "people with access to the server" reaches the owner and subusers only, in the bell and as a bar (K-B94)', function () use ($server) {
    $admin = User::query()->whereHas('roles')->firstOrFail();
    $owner = User::query()->findOrFail($server->owner_id);
    $player = User::where('email', 'smoke-player@example.com')->firstOrFail();
    $guest = User::where('email', 'smoke-guest@example.com')->first()
        ?? User::withoutEvents(fn () => User::factory()->create(['email' => 'smoke-guest@example.com']));
    DB::table('subusers')->where('server_id', $server->id)->whereIn('user_id', [$player->id, $guest->id])->delete();
    DB::table('subusers')->insert(['server_id' => $server->id, 'user_id' => $guest->id, 'permissions' => '[]', 'created_at' => now(), 'updated_at' => now()]);
    $announcement = Announcement::query()->create([
        'title' => 'Smoke for the server', 'send_on' => StartTrigger::START, 'start_servers' => [$server->id], 'audience' => Rules::SERVER_AUDIENCE,
        'bar' => true, 'stay' => 'for', 'stay_minutes' => 30, 'in_bell' => true, 'push' => false, 'in_game' => false, 'created_by' => $admin->id,
    ]);
    $items = fn (User $user) => $user->notifications()->where('data->viewData->simplify_announcement', $announcement->id)->count();
    $bar = fn (User $user) => Announcement::barsFor($user)->whereKey($announcement->id)->exists();
    try {
        if ($bar($owner) || $bar($guest)) {
            throw new RuntimeException('the bar of a server moment shows before the moment');
        }
        StartAnnouncements::started($announcement, $server);
        // An account without access got the notice with the server's name in it: it went to everyone on the panel.
        if ($items($player) !== 0 || $bar($player)) {
            throw new RuntimeException('an account without access to the server got the announcement: bell '.$items($player).' bar '.json_encode($bar($player)));
        }
        if ($items($owner) !== 1 || $items($guest) !== 1 || ! $bar($owner) || ! $bar($guest)) {
            throw new RuntimeException('the owner or a subuser missed it: owner '.$items($owner).' subuser '.$items($guest).' bars '.json_encode([$bar($owner), $bar($guest)]));
        }
        if ($announcement->refresh()->state() === 'ended' || $announcement->weekly_until?->getTimestamp() !== $announcement->weekly_from?->getTimestamp() + 1800) {
            throw new RuntimeException('the bar does not stay for its 30 minutes: '.json_encode($announcement->only(['weekly_from', 'weekly_until', 'ends_at'])));
        }
        // Its time is up: the bar is gone, the announcement waits for the next moment.
        $announcement->forceFill(['weekly_from' => now()->subHour(), 'weekly_until' => now()->subMinutes(30)])->saveQuietly();
        if ($bar($owner) || $announcement->refresh()->state() !== 'live') {
            throw new RuntimeException('the bar stayed after its time, or the announcement ended with it');
        }
    } finally {
        $announcement->delete();
        DB::table('subusers')->where('server_id', $server->id)->where('user_id', $guest->id)->delete();
    }
});
step('announcements on a server moment: a stop goes out when the server stops, a crash only when nobody asked for the stop', function () use ($server) {
    $admin = User::query()->whereHas('roles')->firstOrFail();
    $make = fn (string $event) => Announcement::query()->create([
        'title' => 'Smoke '.$event, 'send_on' => StartTrigger::START, 'start_event' => $event, 'start_servers' => [$server->id],
        'in_bell' => true, 'push' => false, 'in_game' => false, 'created_by' => $admin->id,
    ]);
    $stop = $make('stop');
    $crash = $make('crash');
    $items = fn (Announcement $announcement) => $admin->notifications()->where('data->viewData->simplify_announcement', $announcement->id)->count();
    $look = function (ContainerStatus $status, int $uptime = 0) use ($stop, $crash, $server) {
        cache()->put("servers.$server->uuid.status", $status, 60);
        cache()->put("servers.$server->uuid.resources", $uptime ? ['uptime' => $uptime * 1000] : [], 60);
        foreach ([$stop, $crash] as $announcement) {
            cache()->forget('simplify-announcements.start.'.$announcement->id.'.'.intdiv(time(), 60));
        }
        Artisan::call('simplify:announcements');
    };
    // The stop the scheduler saw a while ago: Wings has had its minute to say who asked for it.
    $waited = function () use ($crash, $server) {
        $pending = $crash->refresh()->start_pending;
        if (! is_array($pending) || ! isset($pending[$server->id])) {
            throw new RuntimeException('a stop was not kept to be looked at: '.json_encode($pending));
        }
        $pending[$server->id] = time() - StartTrigger::CRASH_WAIT - 1;
        $crash->forceFill(['start_pending' => $pending])->saveQuietly();
    };
    // What earlier steps did to this server is not part of this one.
    DB::table('activity_logs')->where('timestamp', '>=', now()->subMinutes(20))->where(fn ($q) => $q->where('event', 'like', 'server:power.%')->orWhere('event', 'server:console.command'))->update(['timestamp' => now()->subHour()]);
    try {
        $look(ContainerStatus::Running, 3600);
        if ($items($stop) !== 0 || $items($crash) !== 0) {
            throw new RuntimeException('a running server counted as a stop or a crash');
        }
        // Nobody pressed Stop: a stop right away, a crash after the wait.
        $look(ContainerStatus::Offline);
        if ($items($stop) !== 1 || $items($crash) !== 0) {
            throw new RuntimeException('a stop did not go out once, or a crash went out before the wait: stop '.$items($stop).' crash '.$items($crash));
        }
        $waited();
        $look(ContainerStatus::Offline);
        if ($items($crash) !== 1 || $items($stop) !== 1 || $crash->refresh()->start_pending !== null) {
            throw new RuntimeException('a stop nobody asked for is no crash: crash '.$items($crash).' stop '.$items($stop));
        }
        // Stopped from the panel: a stop, no crash.
        $look(ContainerStatus::Running, 30);
        $look(ContainerStatus::Offline);
        DB::table('activity_logs')->insert(['event' => 'server:power.stop', 'ip' => '127.0.0.1', 'actor_type' => $admin->getMorphClass(), 'actor_id' => $admin->id, 'properties' => '{}', 'timestamp' => now()]);
        DB::table('activity_log_subjects')->insert(['activity_log_id' => DB::getPdo()->lastInsertId(), 'subject_type' => $server->getMorphClass(), 'subject_id' => $server->id]);
        $waited();
        $look(ContainerStatus::Offline);
        if ($items($crash) !== 1) {
            throw new RuntimeException('a stop from the panel went out as a crash');
        }
        // Testlab 22-11: started again and crashed within ten minutes of that Stop. The Stop was for the earlier run.
        DB::table('activity_logs')->where('event', 'server:power.stop')->where('ip', '127.0.0.1')->where('actor_id', $admin->id)->update(['timestamp' => now()->subMinutes(5)]);
        AnnouncementBell::forget($crash->id);
        $look(ContainerStatus::Running, 100);
        $look(ContainerStatus::Offline);
        $waited();
        $look(ContainerStatus::Offline);
        if ($items($crash) !== 1) {
            throw new RuntimeException('a crash within ten minutes of a Stop from the panel was not told');
        }
    } finally {
        cache()->forget("servers.$server->uuid.status");
        cache()->forget("servers.$server->uuid.resources");
        DB::table('activity_logs')->where('event', 'server:power.stop')->where('ip', '127.0.0.1')->where('actor_id', $admin->id)->delete();
        $stop->delete();
        $crash->delete();
    }
});
step('announcements: the update keeps what each announcement did, and a server start now reaches the people of that server (V65)', function () use ($server) {
    $admin = User::query()->whereHas('roles')->firstOrFail();
    $old = fn (array $fields) => tap(Announcement::query()->create(['in_bell' => true, 'created_by' => $admin->id, ...$fields]), fn (Announcement $made) => DB::table('simplify_core_announcements')->where('id', $made->id)->update(['bar' => true, 'stay' => null, 'stay_minutes' => 180, 'in_game_target' => null, 'weekly_times' => null]));
    $timed = $old(['title' => 'Smoke old timed', 'ends_at' => now()->addDay()]);
    $open = $old(['title' => 'Smoke old open']);
    $weekly = $old(['title' => 'Smoke old weekly', 'send_on' => Weekly::SEND_ON, 'weekly_days' => [1], 'weekly_at' => '19:30', 'weekly_zone' => 'UTC', 'weekly_hours' => 6]);
    $start = $old(['title' => 'Smoke old start', 'send_on' => StartTrigger::START, 'start_servers' => [$server->id], 'start_daily' => true]);
    $forAdmins = $old(['title' => 'Smoke old start for admins', 'send_on' => StartTrigger::START, 'start_servers' => [$server->id], 'audience' => 'admins']);
    $row = fn (Announcement $made) => (array) DB::table('simplify_core_announcements')->where('id', $made->id)->first(['bar', 'stay', 'stay_minutes', 'in_game_target', 'weekly_times', 'audience', 'start_daily']);
    try {
        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_11_22_000002_add_open_model_to_simplify_core_announcements.php';
        $migration->up();
        $got = array_map($row, ['timed' => $timed, 'open' => $open, 'weekly' => $weekly, 'start' => $start, 'admins' => $forAdmins]);
        $ok = (int) $got['timed']['bar'] === 1 && $got['timed']['stay'] === 'until' && $got['timed']['in_game_target'] === 'servers'
            && (int) $got['open']['bar'] === 1 && $got['open']['stay'] === 'ended'
            && (int) $got['weekly']['bar'] === 1 && $got['weekly']['stay'] === 'for' && (int) $got['weekly']['stay_minutes'] === 360 && json_decode((string) $got['weekly']['weekly_times'], true) === ['19:30']
            && (int) $got['start']['bar'] === 0 && $got['start']['in_game_target'] === 'started' && $got['start']['audience'] === Rules::SERVER_AUDIENCE && (int) $got['start']['start_daily'] === 1
            && $got['admins']['audience'] === 'admins';
        if (! $ok) {
            throw new RuntimeException('old announcements were not carried over: '.json_encode($got));
        }
        // A second run changes nothing, also not what an admin chose since.
        DB::table('simplify_core_announcements')->where('id', $start->id)->update(['audience' => 'everyone', 'bar' => true]);
        $migration->up();
        if ($row($start)['audience'] !== 'everyone' || (int) $row($start)['bar'] !== 1) {
            throw new RuntimeException('running the migration again changed an announcement an admin had edited');
        }
    } finally {
        Announcement::query()->whereKey([$timed->id, $open->id, $weekly->id, $start->id, $forAdmins->id])->delete();
    }
});
step('test announcements: the check sends nothing, says a timed bell item came on time, and the tests make real announcements', function () use ($server) {
    $admin = User::query()->whereHas('roles')->firstOrFail();
    $timed = Announcement::create(['title' => 'Smoke diagnose', 'in_bell' => true, 'starts_at' => now()->subSeconds(20)]);
    Artisan::call('simplify:announcements');
    $count = Announcement::query()->count();
    $bell = DB::table('notifications')->count();
    $report = Diagnose::run($admin, 'Europe/Berlin');
    if (Announcement::query()->count() !== $count || DB::table('notifications')->count() !== $bell) {
        throw new RuntimeException('the check changed something');
    }
    $line = collect($report['checks'])->firstWhere('title', '"Smoke diagnose" (at a time)');
    if (! $line || ! str_contains($line['detail'], 'arrived') || ! str_contains($report['log'], 'on time')) {
        throw new RuntimeException('the check did not show when the bell item arrived: '.json_encode($line).' '.$report['log']);
    }
    if (! str_contains($report['log'], 'trace (last scheduler runs') || ! str_contains($report['log'], 'run bell=')) {
        throw new RuntimeException('the log has no scheduler runs: '.$report['log']);
    }
    if (Artisan::call('simplify:announcements', ['--diagnose' => true]) !== 0 || ! str_contains(Artisan::output(), 'Smoke diagnose')) {
        throw new RuntimeException('simplify:announcements --diagnose failed');
    }
    Diagnose::scheduleTest($admin, null);
    Diagnose::startTest($admin, (int) $server->id);
    $tests = Announcement::query()->where('title', Diagnose::TEST_TITLE)->get();
    if ($tests->count() !== 2 || $tests->where('send_on', StartTrigger::START)->first()?->start_servers !== [(int) $server->id] || $tests->where('send_on', StartTrigger::TIME)->first()?->starts_at?->isFuture() !== true) {
        throw new RuntimeException('the tests did not make a timed and a start announcement');
    }
    Announcement::query()->whereKey([...$tests->pluck('id'), $timed->id])->delete();
});
step('notifications: mark read, mark as unread, Clear read and ✕ move them to Archived and back', function () {
    $player = User::where('email', 'smoke-player@example.com')->firstOrFail();
    $player->notifications()->delete();
    Preferences::set($player, 'notifications.archive', []);
    foreach (['Smoke backup done', 'Smoke install done', 'Smoke subuser added'] as $title) {
        $player->notifyNow(Notification::make()->title($title)->toDatabase());
    }
    $keys = fn (array $page) => array_column($page['items'], 'title', 'key');
    $find = fn (string $title) => (string) $player->notifications()->where('data->title', $title)->value('id');
    [$backup, $install, $subuser] = [$find('Smoke backup done'), $find('Smoke install done'), $find('Smoke subuser added')];
    OverviewBell::mark($player, [$backup], true);
    if ($player->unreadNotifications()->count() !== 2) {
        throw new RuntimeException('Mark read did not mark one notification read');
    }
    OverviewBell::mark($player, [$backup], false);
    OverviewBell::mark($player, [$backup], true);
    OverviewBell::archiveNotifications($player);
    $page = OverviewBell::page($player);
    if (isset($keys($page)[$backup]) || count($page['items']) !== 2 || ! $page['archived'] || ! isset($keys(OverviewBell::page($player, 1, true))[$backup])) {
        throw new RuntimeException('Clear read did not move the read notification to Archived');
    }
    OverviewBell::archiveNotifications($player, [$install]);
    $archived = $keys(OverviewBell::page($player, 1, true));
    if (! isset($archived[$install]) || $player->unreadNotifications()->count() !== 1 || isset($keys(OverviewBell::page($player))[$install])) {
        throw new RuntimeException('✕ did not move one notification to Archived, read');
    }
    OverviewBell::mark($player, [$install, $backup], false);
    $page = OverviewBell::page($player);
    if (count($page['items']) !== 3 || OverviewBell::page($player, 1, true)['items'] !== [] || ! isset($keys($page)[$subuser])) {
        throw new RuntimeException('Mark as unread did not bring notifications back from Archived');
    }
    $player->notifications()->delete();
    Preferences::set($player, 'notifications.archive', []);
});
step('notifications: an announcement pinned to everyone\'s notifications stays on top, also after Clear read', function () {
    $player = User::where('email', 'smoke-player@example.com')->firstOrFail();
    $player->notifications()->delete();
    $pinned = Announcement::create(['title' => 'Smoke pinned in the bell', 'in_bell' => true, 'bell_pinned' => true]);
    try {
        Artisan::call('simplify:announcements');
        $player->notifyNow(Notification::make()->title('Smoke ordinary notification')->toDatabase());
        $page = OverviewBell::page($player);
        if (array_column($page['pinned'], 'title') !== ['Smoke pinned in the bell'] || in_array('Smoke pinned in the bell', array_column($page['items'], 'title'), true)) {
            throw new RuntimeException('the pinned announcement is not on top of the bell');
        }
        OverviewBell::mark($player, [], true);
        OverviewBell::archiveNotifications($player);
        $page = OverviewBell::page($player);
        if (count($page['pinned']) !== 1 || $page['items'] !== [] || $page['pinned'][0]['unread']) {
            throw new RuntimeException('Clear read moved the pinned announcement away');
        }
        $pinned->update(['bell_pinned' => false]);
        if (OverviewBell::page($player)['pinned'] !== []) {
            throw new RuntimeException('an unpinned announcement stays on top');
        }
    } finally {
        $pinned->delete();
        $player->notifications()->delete();
        Preferences::set($player, 'notifications.archive', []);
    }
});
step('embed: the server owner opens Website, and the settings page shows the code for a website', function () use ($admin, $server) {
    $url = EmbedWebsite::getUrl(panel: 'server', tenant: $server);
    expectOk(visit($admin, $url), $url);
    $url = EmbedSettings::getUrl(panel: 'admin');
    $response = visit($admin, $url);
    expectOk($response, $url);
    if (! str_contains($response->getContent(), 'simplify/embed.js')) {
        throw new RuntimeException('the code for a website is missing');
    }
});
step('embed: the public list shows only servers put on the website, without private details', function () use ($server) {
    $guest = new class(app())
    {
        use MakesHttpRequests;

        public function __construct(public $app) {}
    };
    EmbedServers::replace([]);
    $empty = $guest->get('/simplify/embed/servers.json');
    expectOk($empty, 'servers.json');
    if ($empty->headers->get('Access-Control-Allow-Origin') !== '*' || $empty->json('servers') !== []) {
        throw new RuntimeException('the empty list is wrong: '.$empty->getContent());
    }
    EmbedServers::setVisible($server, true);
    try {
        $servers = $guest->get('/simplify/embed/servers.json')->json('servers');
        if (count($servers) !== 1 || $servers[0]['name'] !== $server->name || array_key_exists('uuid', $servers[0]) || array_key_exists('node', $servers[0])) {
            throw new RuntimeException('unexpected list: '.json_encode($servers));
        }
        expectOk($guest->get('/simplify/embed.js'), 'embed.js');
        $page = $guest->get('/simplify/embed?page=1&style=dark&server=abc');
        expectOk($page, 'embed page');
        if (! str_contains($page->getContent(), 'data-server="abc"')) {
            throw new RuntimeException('the option did not reach the script');
        }
        // The list carries the panel's own name from Simplify's settings; it said Pelican (K-B91).
        $old = PanelSettings::get('brand_name');
        PanelSettings::save(['brand_name' => 'Smoke Hosting']);
        try {
            $name = $guest->get('/simplify/embed/servers.json')->json('name');
            if ($name !== 'Smoke Hosting' || ! str_contains($guest->get('/simplify/embed?page=1')->getContent(), 'Smoke Hosting')) {
                throw new RuntimeException('the list on a website names the panel '.json_encode($name).', not Smoke Hosting');
            }
        } finally {
            PanelSettings::save(['brand_name' => $old]);
        }
    } finally {
        EmbedServers::replace([]);
    }
});
step('embed: the Website page of a server saves what the website shows, only on Save', function () use ($admin, $server) {
    $guest = new class(app())
    {
        use MakesHttpRequests;

        public function __construct(public $app) {}
    };
    auth()->setUser($admin);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('server'));
    Filament\Facades\Filament::setTenant($server);
    EmbedServers::replace([]);
    try {
        expectOk(visit($admin, EmbedWebsite::getUrl(panel: 'server', tenant: $server)), 'Website page');
        $page = Livewire\Livewire::actingAs($admin)->test(EmbedWebsite::class)->set('data.visible', true);
        if (EmbedServers::visible($server)) {
            throw new RuntimeException('the switch saved before Save');
        }
        noErrors($page->set('data.description', "Build together\nwith quests")->set('data.download', 'https://modrinth.com/modpack/smoke/version/abc')
            ->set('data.address2', 'bedrock.example.com:19132')->set('data.position', 2)->set('data.banner', 'scene:bg-harbor.jpg')->call('save'));
        $entry = $guest->get('/simplify/embed/servers.json')->json('servers.0');
        if (! EmbedServers::visible($server) || ($entry['description'] ?? null) !== 'Build together with quests' || ($entry['download'] ?? null) !== 'https://modrinth.com/modpack/smoke/version/abc'
            || ($entry['address2'] ?? null) !== 'bedrock.example.com:19132' || ($entry['position'] ?? null) !== 2 || EmbedDetails::for($server)['banner'] !== 'scene:bg-harbor.jpg'
            // Only https links are published: on a test panel without https the image is left out.
            || (str_starts_with(url('/'), 'https://') ? ! str_contains((string) ($entry['banner'] ?? ''), 'bg-harbor') : ($entry['banner'] ?? null) !== null)) {
            throw new RuntimeException('unexpected entry: '.json_encode($entry));
        }
        Livewire\Livewire::actingAs($admin)->test(EmbedWebsite::class)->set('data.address2', '192.168.1.5:19132')->call('save')->assertHasErrors(['data.address2']);
        Livewire\Livewire::actingAs($admin)->test(EmbedWebsite::class)->set('data.download', 'http://example.com/a.zip')->call('save')->assertHasErrors(['data.download']);
        if ($guest->get('/simplify/embed/banners/'.str_repeat('a', 40).'.png')->getStatusCode() !== 404) {
            throw new RuntimeException('an unknown banner did not answer 404');
        }
    } finally {
        EmbedDetails::save($server, []);
        EmbedServers::replace([]);
    }
});
step('embed: Settings › Website saves the order, uptime, online first and the status address, which shows only the list', function () use ($admin, $server) {
    $guest = new class(app())
    {
        use MakesHttpRequests;

        public function __construct(public $app) {}
    };
    if (! array_key_exists('simplify:embed-uptime', Artisan::all())) {
        throw new RuntimeException('the uptime command is not registered');
    }
    $before = EmbedOptions::all();
    EmbedServers::replace([]);
    try {
        Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
        $page = Livewire\Livewire::actingAs($admin)->test(EmbedSettings::class)
            ->set('data.servers', [(int) $server->id])->set('data.options.online_first', true)->set('data.options.uptime', true)
            ->set('data.options.status_host', 'Status.Smoke.test');
        if (EmbedServers::visible($server)) {
            throw new RuntimeException('the list saved before Save');
        }
        noErrors($page->call('save'));
        $options = EmbedOptions::all();
        if (! EmbedServers::visible($server) || EmbedDetails::for($server)['position'] !== 1 || ! $options['online_first'] || $options['status_host'] !== 'status.smoke.test') {
            throw new RuntimeException('not saved: '.json_encode([EmbedDetails::for($server), $options]));
        }
        // The panel's own address would show only the list there: refused.
        Livewire\Livewire::actingAs($admin)->test(EmbedSettings::class)->set('data.options.status_host', StatusAddress::panelHost() ?: 'localhost')
            ->call('save')->assertHasErrors(['data.options.status_host']);

        Uptime::record([(int) $server->id => true], time() - 120);
        Uptime::record([(int) $server->id => true], time());
        $summary = Uptime::summary([(int) $server->id], time())[(int) $server->id];
        if ($summary['online'] !== true || $summary['since'] === null || count($summary['days']) !== Uptime::DAYS || end($summary['days']) !== 100.0) {
            throw new RuntimeException('unexpected uptime: '.json_encode($summary));
        }
        $entry = $guest->get('/simplify/embed/servers.json')->json('servers.0');
        $list = $guest->get('/simplify/embed/servers.json')->json();
        if (! is_array($entry['uptime'] ?? null) || count($entry['uptime']['days']) !== Uptime::DAYS || ! array_key_exists('logo', $entry)
            || array_key_exists('logos', (array) ($list['options'] ?? [])) || array_key_exists('status_host', (array) ($list['options'] ?? []))) {
            throw new RuntimeException('unexpected list: '.json_encode($list));
        }

        $status = $guest->get('http://status.smoke.test/');
        expectOk($status, 'status address');
        if ($status->headers->get(StatusAddress::HEADER) !== '1' || ! str_contains($status->getContent(), 'simplify/embed.js') || ! str_contains($status->getContent(), '<h1>')) {
            throw new RuntimeException('the status address does not show the list as a page');
        }
        expectOk($guest->get('http://status.smoke.test/simplify/embed/servers.json'), 'list on the status address');
        foreach (['/login', '/admin', '/simplify/brand/x.png'] as $path) {
            $code = $guest->get('http://status.smoke.test'.$path)->getStatusCode();
            if ($code !== 404) {
                throw new RuntimeException("the status address answers $path with HTTP $code, not 404");
            }
        }

        // Cloudflare: a new proxied CNAME to the panel, the same record again on a second save, never a record of someone else.
        $made = [];
        $send = function (string $method, string $path, ?array $body) use (&$made): array {
            if (str_starts_with($path, '/zones?')) {
                return ['success' => true, 'result' => [['id' => str_repeat('b', 32)]]];
            }
            if ($method === 'GET') {
                return ['success' => true, 'result' => array_values($made)];
            }
            $made['r1'] = $body + ['id' => 'r1'];

            return ['success' => true];
        };
        if (($problem = StatusAddress::connect('status.example.com', $send)) !== null || ($made['r1']['type'] ?? '') !== 'CNAME' || ($made['r1']['proxied'] ?? false) !== true
            || ($problem = StatusAddress::connect('status.example.com', $send)) !== null || count($made) !== 1) {
            throw new RuntimeException('the status record was not made: '.json_encode([$problem, $made]));
        }
        $made['r1']['comment'] = 'someone else';
        if (StatusAddress::connect('status.example.com', $send) === null) {
            throw new RuntimeException('a record of someone else was overwritten');
        }
    } finally {
        EmbedOptions::save($before);
        StatusAddress::forget();
        DB::table(Uptime::TABLE)->where('server_id', $server->id)->delete();
        EmbedDetails::save($server, []);
        EmbedServers::replace([]);
    }
});
// Cloudflare in memory for the subdomain steps: every call is kept, every write succeeds.
$records = [];
$calls = [];
Subdomains::$transport = function (string $method, string $path, ?array $body) use (&$records, &$calls): array {
    $calls[] = $method.' '.strtok($path, '?');
    if (str_starts_with($path, '/zones?')) {
        return ['success' => true, 'result' => [['id' => str_repeat('a', 32)]]];
    }
    if ($method === 'GET') {
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

        return ['success' => true, 'result' => array_values(array_filter($records, fn ($r) => ($r['comment'] ?? '') === ($query['comment_exact'] ?? $r['comment'] ?? '')
            && $r['name'] === ($query['name_exact'] ?? $r['name'])))];
    }
    $id = $method === 'POST' ? 'r'.count($calls) : basename($path);
    if ($method === 'DELETE') {
        unset($records[$id]);
    } else {
        $records[$id] = $body + ['id' => $id];
    }

    return ['success' => true];
};
step('subdomains: until Cloudflare is set up, a root admin sees the steps and the way there, an owner nothing', function () use ($admin, $server) {
    SubdomainSettings::save(['domain' => 'example.com', 'target' => 'play.example.com', 'port_min' => 1, 'port_max' => 65535, 'zone_id' => '', 'token' => ''], true);
    SubdomainSettings::flush();
    if (Subdomains::ready()) {
        throw new RuntimeException('still ready without a token');
    }
    // The server panel first, as the other server steps do: in one process a page of another panel stays current.
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('server'));
    $settings = AutomaticSubdomains::getUrl(panel: 'admin');
    $url = ServerSubdomain::getUrl(panel: 'server', tenant: $server);
    $page = visit($admin, $url);
    expectOk($page, $url);
    if (! str_contains($page->getContent(), 'Set up subdomains') || ! str_contains($page->getContent(), e($settings))) {
        throw new RuntimeException('the server page has no button to the settings');
    }
    $customer = User::query()->where('email', 'smoke-customer@example.com')->first()
        ?? User::withoutEvents(fn () => User::factory()->create(['username' => 'smoke-customer', 'email' => 'smoke-customer@example.com']));
    $owner = $server->owner_id;
    $server->forceFill(['owner_id' => $customer->id])->saveQuietly();
    try {
        $status = visit($customer, $url)->getStatusCode();
        if ($status !== 403) {
            throw new RuntimeException('the owner gets HTTP '.$status.' before Cloudflare is set up, not 403');
        }
    } finally {
        $server->forceFill(['owner_id' => $owner])->saveQuietly();
    }
    $page = visit($admin, $settings);
    expectOk($page, $settings);
    foreach (['https://dash.cloudflare.com/profile/api-tokens', 'Still missing: Cloudflare token, zone ID (press Test).'] as $text) {
        if (! str_contains($page->getContent(), $text)) {
            throw new RuntimeException('the settings page misses '.$text);
        }
    }
    // K-B36: the browser must not fill in the panel password as the Cloudflare token (that showed Changes made).
    if (! preg_match('/<input[^>]*type="password"[^>]*>/', $page->getContent(), $field) || ! str_contains($field[0], 'autocomplete="new-password"')) {
        throw new RuntimeException('the token field lets the browser fill in a saved password: '.($field[0] ?? 'no field'));
    }
});
step('subdomains: the settings and server pages open, and the token is stored encrypted', function () use ($admin, $server) {
    SubdomainSettings::save(['domain' => 'Example.com', 'target' => 'play.example.com', 'port_min' => 1, 'port_max' => 65535, 'zone_id' => str_repeat('a', 32), 'token' => 'smoke-token']);
    SubdomainSettings::flush();
    $stored = (string) DB::table(SubdomainSettings::TABLE)->where('key', 'token')->value('value');
    if (! Subdomains::ready() || SubdomainSettings::domain() !== 'example.com' || $stored === '' || str_contains($stored, 'smoke-token')) {
        throw new RuntimeException('settings not saved right: missing '.implode(', ', SubdomainSettings::missing()));
    }
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('server'));
    foreach ([ServerSubdomain::getUrl(panel: 'server', tenant: $server), AutomaticSubdomains::getUrl(panel: 'admin')] as $url) {
        expectOk(visit($admin, $url), $url);
    }
});
step('subdomains: a subdomain reaches Cloudflare and shows on the server card', function () use ($admin, $server, &$calls) {
    auth()->setUser($admin);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('server'));
    Filament\Facades\Filament::setTenant($server);
    $calls = [];
    noErrors(Livewire\Livewire::actingAs($admin)->test(ServerSubdomain::class)->set('data.name', 'smoke-oak')->call('save'));
    if (! in_array('POST /zones/'.str_repeat('a', 32).'/dns_records', $calls, true) || Subdomain::of($server->uuid)?->status !== Subdomain::ACTIVE) {
        throw new RuntimeException('not connected: '.json_encode($calls));
    }
    $badge = (string) (collect(Listing::for($admin))->firstWhere('uuid', $server->uuid)['badges'][0] ?? '');
    if (! str_starts_with($badge, 'smoke-oak.example.com')) {
        throw new RuntimeException('no address on the card: '.$badge);
    }
    // The server page shows only the subdomain as its address (K-B43): no game address with port next to it.
    $page = Listing::one($admin, $server->fresh(['allocation', 'egg']));
    if (! str_starts_with((string) $page['address'], 'smoke-oak.example.com') || preg_grep('/smoke-oak/', $page['badges']) !== []) {
        throw new RuntimeException('server page address: '.json_encode(['address' => $page['address'], 'badges' => $page['badges']]));
    }
    if (Artisan::call('simplify:subdomains') !== 0) {
        throw new RuntimeException('the sync command failed: '.Artisan::output());
    }
});
step('subdomains: forgetting a server removes its records', function () use ($server, &$records) {
    Subdomains::forget($server->uuid);
    if ($records !== [] || Subdomain::of($server->uuid) !== null) {
        throw new RuntimeException('records left: '.json_encode($records));
    }
});
step('subdomains: settings and subdomains from PelicanSimplify 0.6 and 0.7 are taken over', function () {
    Subdomain::query()->delete();
    DB::table(SubdomainSettings::TABLE)->delete();
    $disk = Storage::disk('local');
    $disk->put('simplify/subdomains.json', json_encode(['domain' => 'old.example', 'target' => 'play.old.example', 'port_min' => 25565, 'port_max' => 25600, 'zone_id' => str_repeat('b', 32), 'token' => encrypt('old-token')]));
    $disk->put('simplify/subdomains/dddddddd-0000-0000-0000-000000000004.json', json_encode(['name' => 'oak', 'status' => 'active', 'port' => 25566]));
    try {
        (require dirname(__DIR__, 2).'/database/migrations/2026_10_29_000004_import_pelicansimplify_subdomains.php')->up();
        SubdomainSettings::flush();
        if (SubdomainSettings::domain() !== 'old.example' || SubdomainSettings::credentials()[0] !== 'old-token' || Subdomain::of('dddddddd-0000-0000-0000-000000000004')?->name !== 'oak') {
            throw new RuntimeException('not taken over');
        }
    } finally {
        $disk->deleteDirectory('simplify/subdomains');
        $disk->delete('simplify/subdomains.json');
        Subdomain::query()->delete();
        DB::table(SubdomainSettings::TABLE)->delete();
        SubdomainSettings::flush();
    }
});
step('after an update the compiled pages of the previous version are thrown away once (K-B57)', function () {
    $files = app('files');
    $view = dirname(__DIR__, 2).'/resources/views/pages/overview.blade.php';
    $compiled = app('blade.compiler')->getCompiledPath($view);
    $files->ensureDirectoryExists(dirname($compiled));
    // What a 1.5.x panel had compiled: an overview that asks for a view 2.0 no longer has.
    $files->put($compiled, "<?php echo \$__env->make('simplify::dashboard.host')->render(); ?>");
    $files->put(FreshViews::marker(), '1.5.5');
    FreshViews::boot(Assets::version());
    if ($files->exists($compiled) || ! str_starts_with(trim((string) $files->get(FreshViews::marker())), Assets::version().'+')) {
        throw new RuntimeException('the old compiled overview was kept');
    }
    app('blade.compiler')->compile($view);
    FreshViews::boot(Assets::version());
    if (! $files->exists($compiled)) {
        throw new RuntimeException('the current compiled overview was thrown away again');
    }
});
step('a component missing right after an update opens the page once more instead of a 500 (K-B63)', function (Application $app) {
    $handler = $app->make(ExceptionHandler::class);
    $missing = new ComponentNotFoundException('Unable to find component: [simplify-hosting-settings-form]');
    $page = $handler->render(Request::create('/admin/simplify-settings?tab=addons'), new ViewException('in a view', 0, 1, 'addon-list.blade.php', 1, $missing));
    if ($page->getStatusCode() !== 302 || ! str_ends_with((string) $page->headers->get('Location'), '?tab=addons&'.StaleCode::RETRIED.'=1')) {
        throw new RuntimeException('the page was not opened once more: '.$page->getStatusCode());
    }
    $again = $handler->render(Request::create('/admin/simplify-settings?'.StaleCode::RETRIED.'=1'), $missing);
    if ($again->getStatusCode() < 500) {
        throw new RuntimeException('the second try did not show the error, so it could loop: '.$again->getStatusCode());
    }
    $call = Request::create('/livewire/update', 'POST');
    $call->headers->set('X-Livewire', 'true');
    if ($handler->render($call, $missing)->getStatusCode() !== 419) {
        throw new RuntimeException('a Livewire call did not ask to load the page again');
    }
});
if ($failed || $passed === 0) {
    exit(1);
}
echo "All steps passed\n";
