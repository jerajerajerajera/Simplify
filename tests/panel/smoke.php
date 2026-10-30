<?php

// Loads Simplify inside a real Pelican panel and opens its pages. Run from the panel folder after
// `php artisan p:plugin:install simplify`: php plugins/simplify/tests/panel/smoke.php
use App\Filament\Admin\Pages\Health;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Addons\Button;
use Simplify\Core\Announcements\Filament\AnnouncementResource;
use Simplify\Core\Announcements\Livewire\AnnouncementBars;
use Simplify\Core\Announcements\Models\Announcement;
use Simplify\Core\Announcements\Services\Bell as AnnouncementBell;
use Simplify\Core\Embed\Filament\EmbedSettings;
use Simplify\Core\Embed\Filament\Website as EmbedWebsite;
use Simplify\Core\Embed\Services\EmbedServers;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Filament\Admin\Pages\Setup;
use Simplify\Core\Filament\Admin\Pages\SetupDone;
use Simplify\Core\Filament\App\Pages\Notifications;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Filament\Server\ServerPage;
use Simplify\Core\Models\Setting;
use Simplify\Core\Overview\Listing;
use Simplify\Core\People\People;
use Simplify\Core\Push\PluginUpdates;
use Simplify\Core\Push\Push;
use Simplify\Core\Push\UpdateNotices;
use Simplify\Core\Push\WebPush;
use Simplify\Core\Server\Looks;
use Simplify\Core\Subdomains\Filament\AutomaticSubdomains;
use Simplify\Core\Subdomains\Filament\ServerSubdomain;
use Simplify\Core\Subdomains\Models\Subdomain;
use Simplify\Core\Subdomains\Services\Settings as SubdomainSettings;
use Simplify\Core\Subdomains\Subdomains;
use Simplify\Core\Support\Assets;
use Simplify\Core\Support\Brand;
use Simplify\Core\Support\PanelSettings;
use Simplify\Core\Support\Preferences;

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
function visit(User $user, string $url): TestResponse
{
    $client = new class(app())
    {
        use InteractsWithAuthentication, InteractsWithSession, MakesHttpRequests;

        public function __construct(public $app) {}
    };

    return $client->actingAs($user)->get($url);
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
        return [new Button('Upgrade this server', 'https://billing.example.com/s/'.$server->uuid)];
    }

    public function customerLinks(User $user): array
    {
        return [new Button('Client area', 'https://billing.example.com/clientarea')];
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
    if (! $response->isRedirect() || ! str_contains((string) $response->headers->get('Location'), 'simplify-setup')) {
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
        ->set('data.brand_font', 'pixel')->set('data.brand_style', 'glow')->call('finish');
    noErrors($setup);
    if (! PanelSettings::get('setup_done') || PanelSettings::get('support_url') !== 'mailto:help@example.com'
        || PanelSettings::get('brand_font') !== 'pixel' || PanelSettings::get('brand_style') !== 'glow' || PanelSettings::get('accent') !== '#a9b4f2') {
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
    PanelSettings::save(['brand_font' => 'default', 'brand_style' => 'plain']);
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
step('settings page renders with its menu, Pelican tabs included', function () use ($admin) {
    $url = Settings::getUrl(panel: 'admin');
    $response = visit($admin, $url);
    expectOk($response, $url);
    auth()->setUser($admin);
    $menu = collect(Settings::menu())->pluck('group', 'key');
    if (! str_contains($response->getContent(), 's-settings-tabs') || ($menu['settings:appearance'] ?? null) !== 'panel' || ($menu['settings:pelican-mail'] ?? null) !== 'pelican') {
        throw new RuntimeException('the settings menu is incomplete: '.json_encode($menu));
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
step('the stylesheet and logo are served with a long cache', function () use ($admin) {
    foreach (['simplify.css' => 'text/css', 'logo.svg' => 'image/svg+xml'] as $name => $type) {
        $url = Assets::url($name);
        $response = visit($admin, $url);
        expectOk($response, $url);
        if (! str_starts_with((string) $response->headers->get('Content-Type'), $type)) {
            throw new RuntimeException("$name has type ".$response->headers->get('Content-Type'));
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
$server = Server::query()->first() ?? Server::factory()->create(['owner_id' => $admin->id, 'name' => 'Smoke server']);
step('the overview renders with the server card', function () use ($admin, $server) {
    $url = Overview::getUrl(panel: 'app');
    $response = visit($admin, $url);
    expectOk($response, $url);
    if (! str_contains($response->getContent(), 'pelicanSimplify(') || ! str_contains($response->getContent(), '/simplify/assets/dashboard.js?v=')) {
        throw new RuntimeException('the overview script is missing');
    }
    $card = collect(Listing::for($admin))->firstWhere('uuid', $server->uuid);
    if ($card === null || $card['name'] !== $server->name || ! isset($card['status'], $card['usage'])) {
        throw new RuntimeException('the server has no card: '.json_encode($card));
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
step('host mode: the overview carries the admin tree, the health page and the health checks', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    $page = Livewire\Livewire::actingAs($admin)->test(Overview::class)->assertSee('ps-host-tree');
    $initial = $page->instance()->initial();
    $pages = collect($initial['admin_pages'][0]['items'] ?? [])->pluck('key');
    if (! $pages->contains('health') || ! is_array($initial['health_checks']) || ! is_bool($initial['host_mode_auto'])) {
        throw new RuntimeException('host mode is missing parts: '.json_encode([$pages, $initial['health_checks'], $initial['host_mode_auto']]));
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
    if ($own['customer'] !== [] || $own['host_mode_auto'] !== true) {
        throw new RuntimeException('admins get customer links or no host mode default: '.json_encode([$own['customer'], $own['host_mode_auto']]));
    }
    $customer = User::query()->where('email', 'smoke-customer@example.com')->first()
        ?? User::withoutEvents(fn () => User::factory()->create(['username' => 'smoke-customer', 'email' => 'smoke-customer@example.com']));
    $links = collect(Livewire\Livewire::actingAs($customer)->test(Overview::class)->instance()->initial()['customer'])->pluck('url')->all();
    if ($links !== ['https://billing.example.com/clientarea', 'mailto:help@example.com']) {
        throw new RuntimeException('the customer links are wrong: '.json_encode($links));
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
step('panel settings are saved and a bad value is refused', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    $bad = Livewire\Livewire::actingAs($admin)->test(Settings::class)->set('data.overview_seconds', 2)->call('save');
    if ($bad->errors()->isEmpty()) {
        throw new RuntimeException('a refresh of 2 seconds was accepted');
    }
    noErrors(Livewire\Livewire::actingAs($admin)->test(Settings::class)
        ->set('data.overview_seconds', 20)->set('data.accent', '#336699')->call('save'));
    if (PanelSettings::get('overview_seconds') !== 20 || PanelSettings::get('accent') !== '#336699') {
        throw new RuntimeException('settings were not saved: '.json_encode(PanelSettings::all()));
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
step('people and roles: the page lists people and roles keep their safety rules', function () use ($admin) {
    $url = Simplify\Core\Filament\Admin\Pages\People::getUrl(panel: 'admin');
    $response = visit($admin, $url);
    expectOk($response, $url);
    if (! str_contains($response->getContent(), e($admin->username))) {
        throw new RuntimeException('the admin is not listed');
    }
    auth()->login($admin);
    $known = array_key_first(array_values(People::permissionGroups())[0]);
    $role = People::saveRole(null, 'Smoke role', [$known, 'not a permission']);
    if ($role->permissions()->pluck('name')->all() !== [$known]) {
        throw new RuntimeException('unknown permissions were stored: '.json_encode($role->permissions()->pluck('name')));
    }
    $refused = 0;
    foreach ([
        fn () => People::deleteUser($admin),
        fn () => People::saveRole(Role::getRootAdmin(), 'Renamed', []),
        fn () => People::updateUser($admin, ['username' => $admin->username, 'email' => $admin->email, 'roles' => []]),
    ] as $unsafe) {
        try {
            $unsafe();
        } catch (ValidationException) {
            $refused++;
        }
    }
    if ($refused !== 3 || ! $admin->refresh()->isRootAdmin()) {
        throw new RuntimeException("only $refused of 3 unsafe changes were refused");
    }
    People::deleteRole($role);
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
step('app notifications: the page opens, a device gets a message once and a dropped device is removed', function () use ($admin) {
    $url = Notifications::getUrl(panel: 'app');
    $response = visit($admin, $url);
    expectOk($response, $url);
    if (! str_contains($response->getContent(), 'simplifyPush')) {
        throw new RuntimeException('the notifications page has no device switch');
    }
    $browser = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $subscription = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/smoke', 'keys' => ['p256dh' => WebPush::b64(WebPush::point($browser)), 'auth' => WebPush::b64(random_bytes(16))]];
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
    cache()->forget('panel:latest_version');
    cache()->forget('wings:latest_version');
    Http::fake([
        'api.github.com/*' => Http::response(['tag_name' => 'v99.0.0', 'body' => '']),
        'fcm.googleapis.com/*' => Http::response('', 201),
        '*' => Http::response('', 500),
    ]);
    if (Artisan::call('simplify:update-notices') !== 0 || ! in_array('pelican-99.0.0', $device->refresh()->received ?? [], true)) {
        throw new RuntimeException('no update notice: '.Artisan::output().json_encode($device->received));
    }
    if (UpdateNotices::send() !== 0) {
        throw new RuntimeException('the same update was announced twice');
    }
    $device->delete();
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
    } finally {
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
step('subdomains: the settings and server pages open, and the token is stored encrypted', function () use ($admin, $server) {
    SubdomainSettings::save(['domain' => 'Example.com', 'target' => 'play.example.com', 'port_min' => 1, 'port_max' => 65535, 'zone_id' => str_repeat('a', 32), 'token' => 'smoke-token']);
    SubdomainSettings::flush();
    $stored = (string) DB::table(SubdomainSettings::TABLE)->where('key', 'token')->value('value');
    if (! Subdomains::ready() || SubdomainSettings::domain() !== 'example.com' || $stored === '' || str_contains($stored, 'smoke-token')) {
        throw new RuntimeException('settings not saved right: missing '.implode(', ', SubdomainSettings::missing()));
    }
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
    $badges = Listing::one($admin, $server->fresh(['allocation', 'egg']))['badges'];
    if (! str_starts_with((string) ($badges[0] ?? ''), 'smoke-oak.example.com')) {
        throw new RuntimeException('no address on the card: '.json_encode($badges));
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
if ($failed || $passed === 0) {
    exit(1);
}
echo "All steps passed\n";
