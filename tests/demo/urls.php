<?php

// Every Simplify page in the demo panel, found from the panels themselves, so a new add-on page shows up in the demo
// without changing this file. Prints JSON: {app: [...], admin: [...], server: {<server name>: [...]}, servers: {...}}.
// Run from the panel folder: php urls.php
use App\Models\Server;
use Filament\Facades\Filament;
use Illuminate\Contracts\Console\Kernel;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$ours = fn (string $class) => str_starts_with($class, 'Simplify\\');
$entry = fn (string $class, string $url) => ['class' => $class, 'title' => rescue(fn () => (string) ($class::getNavigationLabel() ?: class_basename($class)), class_basename($class), false), 'url' => $url];
$out = ['app' => [], 'admin' => [], 'server' => [], 'servers' => []];
foreach (['app', 'admin'] as $id) {
    $panel = Filament::getPanel($id);
    Filament::setCurrentPanel($panel);
    foreach ($panel->getPages() as $page) {
        if ($ours($page)) {
            $out[$id][] = $entry($page, $page::getUrl(panel: $id));
        }
    }
    foreach ($panel->getResources() as $resource) {
        if ($ours($resource)) {
            $out[$id][] = $entry($resource, $resource::getUrl('index', panel: $id));
        }
    }
}
$panel = Filament::getPanel('server');
Filament::setCurrentPanel($panel);
foreach (Server::query()->orderBy('id')->get() as $server) {
    $out['servers'][$server->name] = $panel->getUrl($server);
    foreach ($panel->getPages() as $page) {
        if ($ours($page)) {
            $out['server'][$server->name][] = $entry($page, rescue(fn () => $page::getUrl(panel: 'server', tenant: $server), '', false));
        }
    }
}
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
