<?php

// Loads Simplify inside a real Pelican panel and opens its pages. Run from the panel folder after
// `php artisan p:plugin:install pelican-simplify`: php plugins/pelican-simplify/tests/panel/smoke.php
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use Filament\Support\Colors\Color;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Filament\Admin\Pages\Setup;
use Simplify\Core\Filament\App\Pages\Notifications;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Filament\Server\ServerPage;
use Simplify\Core\Overview\Listing;
use Simplify\Core\People\People;
use Simplify\Core\Push\Push;
use Simplify\Core\Push\UpdateNotices;
use Simplify\Core\Push\WebPush;
use Simplify\Core\Server\Looks;
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

step('the first visit to the settings opens the setup, and finishing it saves the answers', function () use ($admin) {
    $response = visit($admin, Settings::getUrl(panel: 'admin'));
    if (! $response->isRedirect() || ! str_contains((string) $response->headers->get('Location'), 'simplify-setup')) {
        throw new RuntimeException('the settings did not open the setup first (HTTP '.$response->getStatusCode().')');
    }
    $url = Setup::getUrl(panel: 'admin');
    $page = visit($admin, $url);
    expectOk($page, $url);
    if (! str_contains($page->getContent(), 'hub.pelican.dev/plugins/simplify-server-maker')) {
        throw new RuntimeException('the setup does not list the add-ons');
    }
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    $setup = Livewire\Livewire::actingAs($admin)->test(Setup::class)
        ->set('data.profile', 'hosting')->set('data.billing_url', 'https://billing.example.com/s/{uuid}')
        ->set('data.support_url', 'mailto:help@example.com')->call('finish');
    noErrors($setup);
    if (! PanelSettings::get('setup_done') || PanelSettings::get('profile') !== 'hosting' || PanelSettings::get('support_url') !== 'mailto:help@example.com') {
        throw new RuntimeException('the setup was not saved: '.json_encode(PanelSettings::all()));
    }
});
step('settings page renders', function () use ($admin) {
    $url = Settings::getUrl(panel: 'admin');
    expectOk(visit($admin, $url), $url);
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
step('a hosting panel: signing in lands on the overview, and customers get billing and support links', function () use ($admin, $server) {
    event(new Login('web', $admin, false));
    if (session('url.intended') !== Overview::getUrl(panel: 'app')) {
        throw new RuntimeException('signing in does not lead to the overview: '.json_encode(session('url.intended')));
    }
    $url = Filament\Facades\Filament::getPanel('server')->getUrl($server);
    $response = visit($admin, $url);
    if ($response->isRedirect()) {
        $url = (string) $response->headers->get('Location');
        $response = visit($admin, $url);
    }
    expectOk($response, $url);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('server'));
    auth()->setUser($admin);
    Filament\Facades\Filament::setTenant($server);
    $billing = Livewire\Livewire::actingAs($admin)->test(ServerPage::class, ['server' => $server])->instance()->initial()['server']['billing_url'];
    if ($billing !== 'https://billing.example.com/s/'.$server->uuid) {
        throw new RuntimeException('the billing link is missing: '.json_encode($billing));
    }
    if (! str_contains($response->getContent(), 'mailto:help@example.com')) {
        throw new RuntimeException('the support link is missing');
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
if ($failed || $passed === 0) {
    exit(1);
}
echo "All steps passed\n";
