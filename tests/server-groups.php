<?php

// Server groups without a panel: what is kept when saving, and how servers are split over the groups.
declare(strict_types=1);

require __DIR__.'/../src/Overview/ServerGroups.php';

use Simplify\Core\Overview\ServerGroups;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

$clean = ServerGroups::clean([
    ['id' => 'a', 'name' => '  Survival   worlds ', 'color' => '#FF8800', 'servers' => [3, '4', 3, -1, 'x']],
    ['id' => 'a', 'name' => 'Duplicate id'],
    ['id' => 'bad id!', 'name' => 'Bad id'],
    ['id' => 'b', 'name' => '', 'color' => 'red', 'servers' => [4, 5]],
    'not a group',
]);
check(count($clean) === 2, 'duplicate and invalid groups are dropped: '.json_encode($clean));
check($clean[0] === ['id' => 'a', 'name' => 'Survival worlds', 'color' => '#ff8800', 'servers' => [3, 4]], 'names are tidied, colours lower case, servers once: '.json_encode($clean[0]));
check($clean[1] === ['id' => 'b', 'name' => 'Group', 'color' => null, 'servers' => [5]], 'a server sits in one group, an empty name becomes "Group", a bad colour is dropped: '.json_encode($clean[1]));
check(ServerGroups::clean('nonsense') === [], 'anything else reads as no groups');
check(count(ServerGroups::clean(array_map(fn ($i) => ['id' => "g$i", 'name' => "G$i"], range(1, 40)))) === ServerGroups::LIMIT, 'at most '.ServerGroups::LIMIT.' groups');
check(mb_strlen(ServerGroups::clean([['id' => 'x', 'name' => str_repeat('é', 60)]])[0]['name']) === ServerGroups::NAME_LENGTH, 'names are cut to '.ServerGroups::NAME_LENGTH.' characters');

$servers = array_map(fn ($id) => (object) ['id' => $id], [1, 2, 3, 4, 5]);
$split = ServerGroups::split($clean, $servers);
check(array_map(fn ($g) => $g['group']['id'], $split['groups']) === ['a', 'b'], 'groups keep their order');
check(array_map(fn ($s) => $s->id, $split['groups'][0]['servers']) === [3, 4], 'a group holds its servers in list order');
check(array_map(fn ($s) => $s->id, $split['rest']) === [1, 2], 'servers in no group are the rest');
$filtered = ServerGroups::split($clean, [(object) ['id' => 1], (object) ['id' => 5]]);
check(array_map(fn ($g) => $g['group']['id'], $filtered['groups']) === ['b'], 'a group with no server on screen is left out');

if ($failures) {
    echo "FAIL server groups:\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo "PASS server groups\n";
