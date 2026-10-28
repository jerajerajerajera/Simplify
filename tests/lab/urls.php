<?php

// The URL of every Simplify page in a lab panel, as JSON. Run from the panel folder.
use App\Models\Server;
use Filament\Facades\Filament;
use Simplify\Core\Filament\Admin\Pages\People;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Filament\Admin\Pages\Setup;
use Simplify\Core\Filament\App\Pages\Notifications;
use Simplify\Core\Filament\App\Pages\Overview;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$pages = [
    'overview' => Overview::getUrl(panel: 'app'),
    'notifications' => Notifications::getUrl(panel: 'app'),
    'settings' => Settings::getUrl(panel: 'admin'),
    'setup' => Setup::getUrl(panel: 'admin'),
    'people' => People::getUrl(panel: 'admin'),
];
Filament::setCurrentPanel(Filament::getPanel('server'));
foreach (Server::query()->orderBy('id')->get() as $server) {
    $pages['servers'][$server->name] = Filament::getPanel('server')->getUrl($server);
}
echo json_encode($pages, JSON_UNESCAPED_SLASHES), "\n";
