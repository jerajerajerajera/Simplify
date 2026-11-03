<?php

// The server side of opening Admin tools, measured on the audit panel: each part Simplify adds to an admin page, with
// its time and database queries (the whole answer is timed in the browser, speed.cjs). Writes JSON to stdout.
// Run from the panel folder: php speed.php [rounds]
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Simplify\Core\Filament\Admin\AdminSide;
use Simplify\Core\Filament\Admin\AdminTop;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Health\HostHealth;
use Simplify\Core\Overview\AdminMenu;
use Simplify\Core\Overview\AdminTree;
use Simplify\Core\Overview\Bell;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$rounds = max(1, (int) ($argv[1] ?? 5));
$admin = User::query()->where('email', 'demo@example.com')->firstOrFail();

$queries = 0;
DB::listen(function () use (&$queries) {
    $queries++;
});
// Median time (ms) and queries of one call, over the rounds.
$time = function (callable $fn) use (&$queries, $rounds): array {
    $ms = [];
    $count = 0;
    for ($i = 0; $i < $rounds; $i++) {
        $queries = 0;
        $start = hrtime(true);
        try {
            $fn();
        } catch (Throwable $e) {
            return ['error' => strtok($e->getMessage(), "\n")];
        }
        $ms[] = (hrtime(true) - $start) / 1e6;
        $count = $queries;
    }
    sort($ms);

    return ['ms' => round($ms[intdiv(count($ms), 2)], 1), 'queries' => $count];
};

$result = ['rounds' => $rounds, 'parts' => []];

// Simplify's parts of an admin page, each on its own.
auth()->setUser($admin);
Filament::setCurrentPanel(Filament::getPanel('admin'));
// AdminMenu::items keeps its answer for the rest of a request: its own work is collect().
$collect = new ReflectionMethod(AdminMenu::class, 'collect');
$livewire = fn (string $class) => app('livewire')->mount($class);
$result['parts'] = [
    'AdminMenu::collect' => $time(fn () => $collect->invoke(null)),
    'Settings::menu' => $time(fn () => Settings::menu()),
    'Settings::addonRows' => $time(fn () => Settings::addonRows()),
    'Settings::pelicanTabs' => $time(fn () => Settings::pelicanTabs()),
    'AdminTree::build' => $time(fn () => AdminTree::build()),
    'AdminMenu::createServer' => $time(fn () => AdminMenu::createServer()),
    'HostHealth::checks' => $time(fn () => HostHealth::checks()),
    'HostHealth::cached' => $time(fn () => HostHealth::cached()),
    'Bell::page' => $time(fn () => Bell::page($admin)),
    'AdminTop::initial' => $time(fn () => (new AdminTop)->initial()),
    'AdminTop (whole component)' => $time(fn () => $livewire(AdminTop::class)),
    'AdminSide (whole component)' => $time(fn () => $livewire(AdminSide::class)),
];
try {
    $result['sizes'] = [
        'AdminTop initial JSON' => strlen(json_encode((new AdminTop)->initial())),
        'AdminTop HTML' => strlen((string) $livewire(AdminTop::class)),
        'AdminSide HTML' => strlen((string) $livewire(AdminSide::class)),
    ];
} catch (Throwable $e) {
    $result['sizes'] = ['error' => strtok($e->getMessage(), "\n")];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
