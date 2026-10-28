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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Filament\Admin\Pages\Setup;
use Simplify\Core\Filament\App\Pages\Notifications;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Livewire\HostHealthCard;
use Simplify\Core\People\People;
use Simplify\Core\Push\Push;
use Simplify\Core\Push\UpdateNotices;
use Simplify\Core\Push\WebPush;
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
    Livewire\Livewire::actingAs($admin)->test(Setup::class)
        ->set('data.profile', 'hosting')->set('data.billing_url', 'https://billing.example.com/s/{uuid}')
        ->set('data.support_url', 'mailto:help@example.com')->call('finish')->assertHasNoErrors();
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
step('the overview renders with the server tile', function () use ($admin, $server) {
    $url = Overview::getUrl(panel: 'app');
    $response = visit($admin, $url);
    expectOk($response, $url);
    if (! str_contains($response->getContent(), e($server->name))) {
        throw new RuntimeException('the server is not on the overview');
    }
});
step('live numbers load without Wings and leave unknown values empty', function () use ($admin, $server) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    $usage = Livewire\Livewire::actingAs($admin)->test(Overview::class)
        ->call('refreshUsage')->get('usage');
    if (! isset($usage[$server->id]['state'])) {
        throw new RuntimeException('no state for the server: '.json_encode($usage));
    }
});
step('a power action on a server the user cannot reach is refused', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    Livewire\Livewire::actingAs($admin)->test(Overview::class)
        ->call('power', 999999, 'start')->assertNotified(__('pelican-simplify::overview.power.denied'));
});
step('a layout choice is saved for the account and an unknown one is refused', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    Livewire\Livewire::actingAs($admin)->test(Overview::class)
        ->set('display', 'list')->set('sort', 'sideways');
    if (Preferences::get($admin, 'overview.display') !== 'list') {
        throw new RuntimeException('the layout was not saved');
    }
    if (Preferences::get($admin, 'overview.sort') !== 'name') {
        throw new RuntimeException('an unknown sort was saved: '.json_encode(Preferences::get($admin, 'overview.sort')));
    }
    $layout = Livewire\Livewire::actingAs($admin)->test(Overview::class)->get('display');
    if ($layout !== 'list') {
        throw new RuntimeException('the saved layout is not used: '.$layout);
    }
});
step('server groups are saved and shown as sections', function () use ($admin, $server) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('app'));
    $page = Livewire\Livewire::actingAs($admin)->test(Overview::class)
        ->call('editGroups')->call('addGroup');
    $id = $page->get('draft')[0]['id'];
    $page->set('draft.0.name', 'Smoke group')->set('draft.0.color', '#336699')->set('assign.'.$server->id, $id)->call('saveGroups')
        ->assertSee('Smoke group');
    $saved = Preferences::get($admin, 'overview.groups');
    if (($saved[0]['servers'] ?? null) !== [$server->id] || $saved[0]['color'] !== '#336699') {
        throw new RuntimeException('groups were not saved: '.json_encode($saved));
    }
});
step('the console page shows the Simplify details above the console', function () use ($admin, $server) {
    $url = Filament\Facades\Filament::getPanel('server')->getUrl($server);
    $response = visit($admin, $url);
    // The panel's home redirects to the console page.
    if ($response->isRedirect()) {
        $url = (string) $response->headers->get('Location');
        $response = visit($admin, $url);
    }
    expectOk($response, $url);
    if (! str_contains($response->getContent(), 's-details')) {
        throw new RuntimeException('the details are missing on '.$url);
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
    if (! str_contains($response->getContent(), 'billing.example.com/s/'.$server->uuid)) {
        throw new RuntimeException('the billing link is missing');
    }
    if (! str_contains($response->getContent(), 'mailto:help@example.com')) {
        throw new RuntimeException('the support link is missing');
    }
});
step('panel settings are saved and a bad value is refused', function () use ($admin) {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));
    Livewire\Livewire::actingAs($admin)->test(Settings::class)
        ->set('data.overview_seconds', 2)->call('save')->assertHasErrors();
    Livewire\Livewire::actingAs($admin)->test(Settings::class)
        ->set('data.overview_seconds', 20)->set('data.accent', '#336699')->call('save')->assertHasNoErrors();
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
    if (! str_contains($html, 'wire:poll.20s.visible')) {
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
    $health = Livewire\Livewire::actingAs($admin)->test(HostHealthCard::class)->call('load')->get('health');
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
    Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);
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
    Http::fake(['fcm.googleapis.com/*' => Http::response('', 410)]);
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
