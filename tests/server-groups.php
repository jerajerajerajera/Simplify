<?php

// Server groups without a panel: what is kept when saving.
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

$clean = ServerGroups::clean(['order' => 'port', 'groups' => [
    ['id' => 'a', 'name' => '  Survival   worlds ', 'color' => '#FF8800', 'sort' => 'manual', 'servers' => ['u3', 'u4', 'u3', 'bad uuid!', 4]],
    ['id' => 'a', 'name' => 'Duplicate id'],
    ['id' => 'bad id!', 'name' => 'Bad id'],
    ['id' => 'b', 'name' => '', 'color' => 'red', 'sort' => 'nonsense', 'servers' => ['u4', 'u5']],
    'not a group',
]]);
$groups = $clean['groups'];
check($clean['order'] === 'port' && count($groups) === 2, 'duplicate and invalid groups are dropped: '.json_encode($clean));
check($groups[0] === ['id' => 'a', 'name' => 'Survival worlds', 'servers' => ['u3', 'u4'], 'sort' => 'manual', 'color' => '#ff8800'], 'names are tidied, colours lower case, servers once: '.json_encode($groups[0]));
check($groups[1] === ['id' => 'b', 'name' => 'Group', 'servers' => ['u5'], 'sort' => 'overview'], 'a server sits in one group, an empty name becomes "Group", a bad colour or order is dropped: '.json_encode($groups[1]));
check(ServerGroups::clean('nonsense') === ['groups' => [], 'order' => 'name'], 'anything else reads as no groups, sorted by name');
check(count(ServerGroups::clean(['groups' => array_map(fn ($i) => ['id' => "g$i", 'name' => "G$i"], range(1, 40))])['groups']) === ServerGroups::LIMIT, 'at most '.ServerGroups::LIMIT.' groups');
check(mb_strlen(ServerGroups::clean(['groups' => [['id' => 'x', 'name' => str_repeat('é', 60)]]])['groups'][0]['name']) === ServerGroups::NAME_LENGTH, 'names are cut to '.ServerGroups::NAME_LENGTH.' characters');

if ($failures) {
    echo "FAIL server groups:\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo "PASS server groups\n";
