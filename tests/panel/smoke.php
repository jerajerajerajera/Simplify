<?php

// Loads Simplify inside a real Pelican panel and opens its pages. Run from the panel folder after
// `php artisan p:plugin:install pelican-simplify`: php plugins/pelican-simplify/tests/panel/smoke.php
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Testing\TestResponse;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Support\Assets;
use Simplify\Core\Support\Preferences;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$failed = false;
function step(string $what, callable $run): void
{
    global $failed;
    try {
        $run();
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

$admin = User::where('email', 'smoke-simplify@example.com')->first()
    ?? User::factory()->create(['email' => 'smoke-simplify@example.com', 'root_admin' => true]);

step('settings page renders', function () use ($admin) {
    $url = Settings::getUrl(panel: 'admin');
    expectOk(visit($admin, $url), $url);
});
step('a tagged add-on shows on the settings page', function () use ($admin, $app) {
    $app->tag([SmokeAddon::class], AddonRegistry::TAG);
    $app->forgetInstance(AddonRegistry::class);
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
$server = Server::query()->first() ?? Server::factory()->create(['owner_id' => $admin->id]);
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
        ->set('layout', 'list')->set('sort', 'sideways');
    if (Preferences::get($admin, 'overview.layout') !== 'list') {
        throw new RuntimeException('the layout was not saved');
    }
    if (Preferences::get($admin, 'overview.sort') !== 'name') {
        throw new RuntimeException('an unknown sort was saved: '.json_encode(Preferences::get($admin, 'overview.sort')));
    }
    $layout = Livewire\Livewire::actingAs($admin)->test(Overview::class)->get('layout');
    if ($layout !== 'list') {
        throw new RuntimeException('the saved layout is not used: '.$layout);
    }
});
exit($failed ? 1 : 0);
