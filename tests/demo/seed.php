<?php

// Fills the demo panel with example data for the core and the add-ons: an admin, a customer and a few more people,
// one node on the fake Wings, ten game servers with colours and groups, announcements, subdomains, the website list,
// hosting links, egg updates and Toolkit choices. Prints the fake Wings' servers.json.
// Run from the panel folder: php seed.php <wings-port> > servers.json
// Every part after the servers is optional: a part whose table or class an add-on no longer has is skipped.
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Simplify\Core\Server\Looks;
use Simplify\Core\Subdomains\Models\Subdomain;
use Simplify\Core\Support\PanelSettings;
use Simplify\Core\Support\Preferences;
use Simplify\Hosting\Services\Settings;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$port = (int) ($argv[1] ?? 8090);
$part = function (string $name, callable $fn) {
    try {
        $fn();
        fprintf(STDERR, "seed: %s\n", $name);
    } catch (Throwable $e) {
        fprintf(STDERR, "seed: %s skipped (%s)\n", $name, strtok($e->getMessage(), "\n"));
    }
};

$admin = User::withoutEvents(fn () => User::factory()->create(['username' => 'jeroen', 'email' => 'demo@example.com', 'password' => bcrypt('DemoPassword123!')]));
$admin->syncRoles(Role::getRootAdmin());
auth()->setUser($admin);
$customer = User::withoutEvents(fn () => User::factory()->create(['username' => 'sam', 'email' => 'sam@example.com', 'password' => bcrypt('DemoPassword123!')]));
$node = Node::factory()->create(['name' => 'Amsterdam', 'fqdn' => '127.0.0.1', 'scheme' => 'http', 'daemon_listen' => $port, 'daemon_connect' => $port, 'memory' => 32768, 'disk' => 500000, 'cpu' => 800]);

// name, egg, state, memory used (MB), CPU %, owner, colour
$list = [
    ['Survival SMP', 'Paper', 'running', 2900, 38, $customer, '#4caf50'],
    ['Creative Builds', 'Paper', 'running', 1800, 12, $admin, '#5b8fd0'],
    ['All the Mods 10', 'Forge', 'running', 7100, 64, $admin, '#f2b33d'],
    ['Skyblock', 'Paper', 'running', 1400, 9, $admin, null],
    ['Valheim Vikings', 'Valheim', 'running', 3300, 41, $customer, '#8e6bd8'],
    ['Palworld Island', 'Palworld', 'starting', 5200, 22, $admin, '#e5735c'],
    ['Rust Weekend', 'Rust', 'running', 6100, 55, $admin, '#c0392b'],
    ['Arena', 'Counter-Strike 2', 'offline', 0, 0, $admin, null],
    ['Terraria Co-op', 'Terraria', 'offline', 0, 0, $admin, null],
    ['Discord bot', 'Node.js', 'running', 180, 2, $admin, null],
];
$minecraft = ['Paper', 'Forge'];
$eggs = [];
$out = [];
$servers = [];
foreach ($list as $i => [$name, $eggName, $state, $memory, $cpu, $owner, $color]) {
    $eggs[$eggName] ??= Egg::factory()->create(['name' => $eggName, 'author' => 'panel@example.com', 'features' => in_array($eggName, $minecraft, true) ? ['eula', 'java_version'] : [],
        'docker_images' => ['ghcr.io/pelican-eggs/yolks:java_21'], 'update_url' => null]);
    $allocation = Allocation::factory()->create(['node_id' => $node->id, 'ip' => '127.0.0.1', 'ip_alias' => 'play.example.com', 'port' => 25565 + $i]);
    $server = Server::factory()->create(['name' => $name, 'owner_id' => $owner->id, 'node_id' => $node->id, 'allocation_id' => $allocation->id, 'egg_id' => $eggs[$eggName]->id,
        'image' => 'ghcr.io/pelican-eggs/yolks:java_21', 'memory' => $memory > 4096 ? 8192 : 4096, 'disk' => 20480, 'cpu' => 200, 'installed_at' => now(), 'status' => null,
        'created_at' => now()->subDays(20 - $i)]);
    $allocation->update(['server_id' => $server->id]);
    $servers[$name] = $server;
    $files = ['/logs/latest.log' => "[12:00:05 INFO]: Done (4.2s)!\n"];
    if (in_array($eggName, $minecraft, true)) {
        $files += ['/server.properties' => "motd=\\u00a7a$name \\u00a77- welcome!\nmax-players=20\ndifficulty=normal\ngamemode=survival\npvp=true\nwhite-list=false\nview-distance=10\n",
            '/eula.txt' => "eula=true\n"];
        $files += $eggName === 'Paper'
            ? ['/plugins/EssentialsX-2.21.0.jar' => 'jar', '/plugins/LuckPerms-Bukkit-5.4.141.jar' => 'jar', '/plugins/WorldEdit-7.3.9.jar' => 'jar']
            : ['/mods/jei-1.21.1-forge-19.21.0.jar' => 'jar', '/mods/create-1.21.1-6.0.4.jar' => 'jar'];
    }
    $out[] = ['uuid' => $server->uuid, 'name' => $name, 'port' => $allocation->port, 'state' => $state, 'memory' => $memory, 'cpu' => $cpu, 'files' => $files];
}
echo json_encode($out, JSON_UNESCAPED_SLASHES), "\n";
$uuid = fn (string $name) => $servers[$name]->uuid;

$part('people and roles', function () {
    $moderator = Role::firstOrCreate(['name' => 'Moderator', 'guard_name' => 'web']);
    $support = Role::firstOrCreate(['name' => 'Support', 'guard_name' => 'web']);
    foreach ([['robin', $moderator], ['kai', $support], ['ana', null], ['lee', null]] as [$name, $role]) {
        $user = User::withoutEvents(fn () => User::factory()->create(['username' => $name, 'email' => $name.'@example.com', 'password' => bcrypt('DemoPassword123!')]));
        if ($role) {
            $user->syncRoles($role);
        }
    }
});
$part('settings (setup done)', fn () => PanelSettings::save(['setup_done' => true, 'brand_name' => 'Northwind Games', 'support_url' => 'https://discord.gg/example']));
$part('server colours', function () use ($list, $servers) {
    foreach ($list as [$name, , , , , , $color]) {
        if ($color) {
            Looks::save($servers[$name], $color, null);
        }
    }
});
$part('server groups', fn () => Preferences::set($admin, 'overview.groups', ['order' => 'name', 'groups' => [
    ['id' => 'mc', 'name' => 'Minecraft', 'color' => '#4caf50', 'sort' => 'overview', 'servers' => [$uuid('Survival SMP'), $uuid('Creative Builds'), $uuid('All the Mods 10'), $uuid('Skyblock')]],
    ['id' => 'friends', 'name' => 'Friends', 'color' => '#8e6bd8', 'sort' => 'overview', 'servers' => [$uuid('Valheim Vikings'), $uuid('Palworld Island'), $uuid('Terraria Co-op')]],
]]));
$part('announcements', function () use ($admin) {
    DB::table('simplify_core_announcements')->insert([
        ['category' => 'warning', 'title' => 'Maintenance on Saturday', 'body' => 'The servers restart between 07:00 and 07:30.', 'audience' => 'everyone', 'in_bell' => true, 'starts_at' => now()->subHour(), 'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now()],
        ['category' => 'info', 'title' => 'New node in Amsterdam', 'body' => 'New servers start faster from today.', 'audience' => 'everyone', 'in_bell' => false, 'starts_at' => now()->subHour(), 'created_by' => $admin->id, 'created_at' => now()->subMinute(), 'updated_at' => now()],
    ]);
});
$part('subdomains', function () use ($uuid) {
    Simplify\Core\Subdomains\Services\Settings::save(['domain' => 'play.example.com', 'target' => 'node.example.com', 'port_min' => 25565, 'port_max' => 25600, 'zone_id' => str_repeat('a1', 16)]);
    foreach (['Survival SMP' => 'survival', 'Creative Builds' => 'creative', 'All the Mods 10' => 'atm10', 'Skyblock' => 'skyblock'] as $name => $sub) {
        DB::table('simplify_core_subdomains')->insert(['server_uuid' => $uuid($name), 'name' => $sub, 'status' => Subdomain::ACTIVE, 'message' => '', 'created_at' => now(), 'updated_at' => now()]);
    }
});
$part('website list', function () use ($servers) {
    foreach (['Survival SMP', 'Creative Builds', 'All the Mods 10', 'Valheim Vikings', 'Rust Weekend'] as $name) {
        DB::table('simplify_core_embed_servers')->insert(['server_id' => $servers[$name]->id, 'created_at' => now(), 'updated_at' => now()]);
    }
});
$part('hosting links', fn () => Settings::save(['billing_url' => 'https://billing.example.com/clientarea.php?action=productdetails&id={external_id}',
    'client_area_url' => 'https://billing.example.com/clientarea.php', 'order_url' => 'https://billing.example.com/order', 'host_mode' => false]));
$part('egg updates', function () use ($eggs) {
    DB::table('simplify_egg_updates')->insert([
        ['egg_id' => $eggs['Paper']->id, 'found_hash' => md5('paper-new'), 'found_at' => now()->subHours(3), 'held' => false, 'created_at' => now(), 'updated_at' => now()],
        ['egg_id' => $eggs['Forge']->id, 'found_hash' => md5('forge-new'), 'found_at' => now()->subDay(), 'held' => false, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('simplify_egg_update_backups')->insert(['egg_id' => $eggs['Valheim']->id, 'definition' => '{}', 'created_at' => now()->subDays(2)]);
});
$part('toolkit software', function () use ($servers) {
    foreach (['Survival SMP' => 'paper', 'Creative Builds' => 'paper', 'Skyblock' => 'paper', 'All the Mods 10' => 'forge'] as $name => $loader) {
        DB::table('simplify_toolkit_servers')->insert(['server_id' => $servers[$name]->id, 'loader' => $loader, 'minecraft' => '1.21.1', 'created_at' => now(), 'updated_at' => now()]);
    }
});
