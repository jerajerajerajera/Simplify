<?php

// Loads Simplify inside a real Pelican panel and opens its pages. Run from the panel folder after
// `php artisan p:plugin:install pelican-simplify`: php plugins/pelican-simplify/tests/panel/smoke.php
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Testing\TestResponse;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Support\Assets;
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
if ($failed || $passed === 0) {
    exit(1);
}
echo "All steps passed\n";
