<?php

// Fills a lab panel for Simplify: a root admin, a second person, a node on the fake Wings, a few eggs and servers
// (some of the admin, some of the other person, one where the admin is a subuser). Prints the fake Wings' servers.json.
// Run from the panel folder: php seed.php <wings-port> > servers.json
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Role;
use App\Models\Server;
use App\Models\Subuser;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

define('LARAVEL_START', microtime(true));
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$port = (int) ($argv[1] ?? 8090);

$admin = User::withoutEvents(fn () => User::factory()->create(['username' => 'lab-admin', 'email' => 'lab@example.com', 'password' => bcrypt('LabPassword123!')]));
$admin->syncRoles(Role::getRootAdmin());
$friend = User::withoutEvents(fn () => User::factory()->create(['username' => 'sam', 'email' => 'sam@example.com']));
$node = Node::factory()->create(['name' => 'Lab node', 'fqdn' => '127.0.0.1', 'scheme' => 'http', 'daemon_listen' => $port, 'daemon_connect' => $port, 'memory' => 16384, 'disk' => 500000, 'cpu' => 400]);

$servers = [
    'Survival' => ['egg' => 'Paper', 'owner' => $admin, 'state' => 'running'],
    'Creative build' => ['egg' => 'Paper', 'owner' => $admin, 'state' => 'running'],
    'Modpack Monday' => ['egg' => 'Fabric', 'owner' => $admin, 'state' => 'offline'],
    'Rust weekend' => ['egg' => 'Rust', 'owner' => $friend, 'state' => 'running'],
    'Valheim with Sam' => ['egg' => 'Valheim', 'owner' => $friend, 'state' => 'starting', 'subuser' => $admin],
    'Discord bot' => ['egg' => 'Node.js', 'owner' => $admin, 'state' => 'offline'],
];
$eggs = [];
$out = [];
$i = 0;
foreach ($servers as $name => $spec) {
    $egg = $eggs[$spec['egg']] ??= Egg::factory()->create(['name' => $spec['egg'], 'docker_images' => ['ghcr.io/pelican-eggs/yolks:java_21']]);
    $allocation = Allocation::factory()->create(['node_id' => $node->id, 'ip' => '127.0.0.1', 'port' => 25565 + $i++]);
    $server = Server::factory()->create(['name' => $name, 'owner_id' => $spec['owner']->id, 'node_id' => $node->id, 'allocation_id' => $allocation->id, 'egg_id' => $egg->id,
        'image' => 'ghcr.io/pelican-eggs/yolks:java_21', 'memory' => 4096, 'disk' => 10240, 'cpu' => 200, 'installed_at' => now(), 'status' => null]);
    $allocation->update(['server_id' => $server->id]);
    if (isset($spec['subuser'])) {
        Subuser::query()->create(['user_id' => $spec['subuser']->id, 'server_id' => $server->id, 'permissions' => ['control.console', 'control.start', 'control.stop', 'control.restart']]);
    }
    $out[] = ['uuid' => $server->uuid, 'name' => $name, 'state' => $spec['state'], 'files' => ['/server.properties' => "motd=Lab\n"]];
}
echo json_encode($out, JSON_UNESCAPED_SLASHES), "\n";
fprintf(STDERR, "seeded %d servers on node %d (wings :%d)\n", count($out), $node->id, $port);
