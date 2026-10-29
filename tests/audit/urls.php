<?php

// Every Simplify page (and every Pelican admin page) in the audit panel, found from the panels themselves, so a new add-on page shows up in the demo
// without changing this file. Prints JSON: {app: [...], admin: [...], server: {<server name>: [...]}, servers: {...}}.
// Run from the panel folder: php urls.php
use App\Filament\Server\Resources\Schedules\ScheduleResource;
use App\Models\Server;
use Filament\Facades\Filament;
use Illuminate\Contracts\Console\Kernel;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$ours = fn (string $class) => str_starts_with($class, 'Simplify\\');
$entry = fn (string $class, string $url) => ['class' => $class, 'title' => rescue(fn () => (string) ($class::getNavigationLabel() ?: class_basename($class)), class_basename($class), false), 'url' => $url];
$out = ['app' => [], 'admin' => [], 'server' => [], 'servers' => [], 'profile' => rescue(fn () => Filament::getPanel('app')->getProfileUrl(), '', false)];
foreach (['app', 'admin'] as $id) {
    $panel = Filament::getPanel($id);
    Filament::setCurrentPanel($panel);
    foreach ($panel->getPages() as $page) {
        if ($ours($page) || $id === 'admin') {
            $out[$id][] = $entry($page, rescue(fn () => $page::getUrl(panel: $id), '', false));
        }
    }
    foreach ($panel->getResources() as $resource) {
        if ($ours($resource) || $id === 'admin') {
            $out[$id][] = $entry($resource, rescue(fn () => $resource::getUrl('index', panel: $id), '', false));
        }
    }
    $out[$id] = array_values(array_filter($out[$id], fn ($e) => $e['url'] !== ''));
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
// The Edit schedule page of each server that has a schedule (a Pelican page, recorded for its layout).
foreach (Server::query()->orderBy('id')->get() as $server) {
    $schedule = $server->schedules()->first();
    if ($schedule) {
        $out['schedules'][$server->name] = rescue(fn () => ScheduleResource::getUrl('edit', ['record' => $schedule], panel: 'server', tenant: $server), '', false);
    }
}
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
