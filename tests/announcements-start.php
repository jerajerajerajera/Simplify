<?php

// "Send: when a server starts" without a panel: which servers count as started, and which servers are watched.
declare(strict_types=1);

require __DIR__.'/../src/Announcements/Support/StartTrigger.php';

use Simplify\Core\Announcements\Support\StartTrigger as S;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

// Started: off at the last look, running now.
$look = S::look([1 => false, 2 => true, 3 => false], [1 => true, 2 => true, 3 => false]);
check($look['started'] === [1], 'only the server that was off and runs now has started');
check($look['seen'] === [1 => true, 2 => true, 3 => false], 'what runs now is remembered for the next look');
check(S::look([], [1 => true, 2 => false])['started'] === [], 'a server seen for the first time does not count as started');
check(S::look([1 => true], [1 => true])['started'] === [], 'a server that keeps running does not start again');
check(S::look(['1' => false], [1 => true])['started'] === [1], 'ids read back from JSON as strings still match');
check(S::look([1 => false, 4 => false], [1 => true])['seen'] === [1 => true], 'a server no longer watched is forgotten');
// A restart between two looks (off for less than a minute) is missed; a restart that takes longer counts.
$off = S::look([5 => true], [5 => false]);
check($off['started'] === [] && S::look($off['seen'], [5 => true])['started'] === [5], 'off, then running again: started once');

// Watched servers.
check(S::servers([3, '4', 3, 0, -1, 'x', null, 4.0]) === [3, 4], 'whole ids above 0, each once');
check(S::servers(null) === [] && S::servers('3') === [], 'nothing usable is no servers');
check(count(S::servers(range(1, 50))) === S::SERVERS, 'at most 20 servers');

check(S::title('Survival SMP', 'Back online') === 'Survival SMP · Back online', 'the title says which server started');

if ($failures) {
    foreach ($failures as $failure) {
        echo "FAIL $failure\n";
    }
    exit(1);
}
echo "PASS announcements on a server start\n";
