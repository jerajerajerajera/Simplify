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
use App\Models\Schedule;
use App\Models\Server;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
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
// Egg descriptions as the pelican-eggs repository has them.
$about = [
    'Paper' => 'High performance Spigot fork that aims to fix gameplay and mechanics inconsistencies.',
    'Forge' => 'Minecraft Forge Server. Minecraft Forge is a modding API (Application Programming Interface), which makes it easier to create mods, and also make sure mods are compatible with each other.',
    'Valheim' => 'A brutal exploration and survival game for 1-10 players, set in a procedurally-generated purgatory inspired by viking culture.',
    'Palworld' => 'Fight, farm, build and work alongside mysterious creatures called "Pals" in this completely new multiplayer, open world survival and crafting game!',
    'Rust' => 'The only aim in Rust is to survive. To do this you will need to overcome struggles such as hunger, thirst and cold. Build a fire. Build a shelter. Kill animals for meat. Protect yourself from other players, and kill them for meat.',
    'Counter-Strike 2' => 'For over two decades, Counter-Strike has offered an elite competitive experience, one shaped by millions of players from across the globe.',
    'Terraria' => 'Dig, fight, explore, build! Nothing is impossible in this action-packed adventure game. The world is your canvas and the ground itself is your paint.',
    'Node.js' => 'A generic Node.js egg. Runs a JavaScript app such as a Discord bot from its own files.',
];
$eggs = [];
// A 64x64 grass block as server-icon.png (base64; the fake Wings decodes .png files).
$icon = 'iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAIAAAAlC+aJAAABhElEQVR42u2ZPWrDQBCFdScXDiadCoNRoUZNqlSGBFy5ygVSBnKD1Gl8AF8u3fAC+eBJ06R48KrRzs8nGM3uaphuc+nh47E0fh1Ly/dT6Xx/Lf0H3yEAAWgC6AN1oECajOzkq3bNSzVoTIofgAB0AdSBRA3nrNfi1JcAtDgV5Q1AALoA5KBFkN0ZTLSGgEnU6AEIQBfAaSxqVho0znpqXF1DAL8GWQAC0AR4Ww6lz5dT6TLtStd5X1K7+r4/j39KY6poDeWivAEIQBdAHxCAU7T66hpH9LJUlDcAAegCkAOJmpI+BmSnmGp3PgwBCEAXgBY5og+A8yIoJjUu2QMQgC7A2uFFRVACp3GdgUjDLgAB6AJQcU4C5+DibMicDR+9rAAEoAtAzUEFOU1G9rWbP4LHQ30AArABwCnaOdQ7l180KMnX2SAGIABdABpGGogKJTkXVRSfIK1DfQACsAFgbbM6l7vOTw0aUlQ0Xu4GIABNAOfA7vwEcYaaU9zajV0AAtAE+AEjkzL/ywwRRQAAAABJRU5ErkJggg==';
// The console of Survival SMP ends in a port error, for the Explain screen (capture.cjs shows the same lines).
$console = json_decode(file_get_contents(__DIR__.'/console.json'), true);
$out = [];
$servers = [];
foreach ($list as $i => [$name, $eggName, $state, $memory, $cpu, $owner, $color]) {
    $eggs[$eggName] ??= Egg::factory()->create(['name' => $eggName, 'description' => $about[$eggName] ?? '', 'author' => 'panel@example.com', 'features' => in_array($eggName, $minecraft, true) ? ['eula', 'java_version'] : [],
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
            '/eula.txt' => "eula=true\n", '/server-icon.png' => $icon];
        $files += $eggName === 'Paper'
            ? ['/plugins/EssentialsX-2.21.0.jar' => 'jar', '/plugins/LuckPerms-Bukkit-5.4.141.jar' => 'jar', '/plugins/WorldEdit-7.3.9.jar' => 'jar']
            : ['/mods/jei-1.21.1-forge-19.21.0.jar' => 'jar', '/mods/create-1.21.1-6.0.4.jar' => 'jar'];
    }
    // All the Mods 10 runs a CurseForge modpack: its manifest.json makes the Mods page fold the pack's own mods into one row.
    if ($name === 'All the Mods 10') {
        $files['/manifest.json'] = json_encode(['manifestType' => 'minecraftModpack', 'manifestVersion' => 1, 'name' => 'All the Mods 10', 'version' => '4.2', 'author' => 'ATMTeam',
            'minecraft' => ['version' => '1.21.1', 'modLoaders' => [['id' => 'neoforge-21.1.172', 'primary' => true]]], 'files' => []]);
        foreach (['appliedenergistics2-19.2.10', 'mekanism-1.21.1-10.7.14', 'sophisticatedbackpacks-1.21.1-3.23.18', 'botania-1.21.1-449', 'ars_nouveau-1.21.1-5.8.2',
            'ftbquests-neoforge-2101.1.9', 'waystones-neoforge-1.21.1-21.1.13', 'allthemodium-3.0.4', 'mysticalagriculture-1.21.1-8.0.12', 'farmersdelight-1.21.1-1.2.7'] as $jar) {
            $files['/mods/'.$jar.'.jar'] = 'jar';
        }
    }
    $out[] = ['uuid' => $server->uuid, 'name' => $name, 'port' => $allocation->port, 'state' => $state, 'memory' => $memory, 'cpu' => $cpu, 'files' => $files]
        + ($name === 'Survival SMP' ? ['console' => $console] : []);
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
    Simplify\Core\Subdomains\Services\Settings::save(['domain' => 'play.example.com', 'target' => 'node.example.com', 'port_min' => 25565, 'port_max' => 25600, 'zone_id' => str_repeat('a1', 16), 'token' => 'demo-not-a-real-token']);
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
// Explain with a placeholder key and its answer for Survival SMP's console already in the cache, as LogCheck::run
// keeps it, so the answer window opens without asking an AI service.
$part('explain', function () use ($console) {
    Simplify\Explain\Services\Settings::save(['provider' => 'anthropic', 'key' => 'demo-not-a-real-key', 'audience' => 'everyone']);
    $settings = Simplify\Explain\Services\Settings::current();
    $lines = Simplify\Explain\Services\LogCheck::sanitize($console);
    Cache::forever('simplify-explain.answer.'.sha1($settings['provider'].$settings['model'].'en'.'Game or software: Paper'.implode("\n", $lines)), [
        'found' => true,
        'summary' => 'The server could not start because port 25565 is already in use, most likely by a copy of this server that is still running.',
        'issues' => [[
            'title' => 'Port 25565 is already in use',
            'cause' => 'Paper tried to listen on port 25565, but another program on the node already holds that port. This usually happens when an earlier copy of the server did not shut down, or when two servers were given the same port.',
            'steps' => ['Stop the server, wait a minute and start it again, so an old copy can let go of the port.', 'Open Network and check that no other server on this node uses port 25565.',
                'Still stuck? Ask your host to check which program on the node uses port 25565.'],
        ]],
        'lines' => count($lines),
        'model' => Simplify\Explain\Ai\Providers::get($settings['provider'])::modelName($settings['model']),
    ]);
});
$part('toolkit software', function () use ($servers) {
    foreach (['Survival SMP' => 'paper', 'Creative Builds' => 'paper', 'Skyblock' => 'paper', 'All the Mods 10' => 'forge'] as $name => $loader) {
        DB::table('simplify_toolkit_servers')->insert(['server_id' => $servers[$name]->id, 'loader' => $loader, 'minecraft' => '1.21.1', 'created_at' => now(), 'updated_at' => now()]);
    }
});
// One schedule with three tasks, so the Edit schedule page shows its Tasks block (old plugin issue #236: no way to scroll to it).
$part('schedule', function () use ($servers) {
    $schedule = Schedule::create(['server_id' => $servers['Survival SMP']->id, 'name' => 'Nightly restart', 'cron_minute' => '0', 'cron_hour' => '4',
        'cron_day_of_month' => '*', 'cron_month' => '*', 'cron_day_of_week' => '*', 'only_when_online' => true, 'next_run_at' => now()->addDay()->setTime(4, 0)]);
    foreach ([['command', 'say Restarting in 5 minutes', 0], ['command', 'save-all', 240], ['power', 'restart', 60]] as $i => [$action, $payload, $offset]) {
        Task::create(['schedule_id' => $schedule->id, 'sequence_id' => $i + 1, 'action' => $action, 'payload' => $payload, 'time_offset' => $offset]);
    }
});
