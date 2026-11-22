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
$off = S::look([5 => true], [5 => false]);
check($off['started'] === [] && S::look($off['seen'], [5 => true])['started'] === [5], 'off, then running again: started once');
// K-B81: a restart between two looks. The server ran at both, but started again since: a running server is remembered
// by when it started (from Wings' uptime). A few seconds of difference is the same start read at another moment.
check(S::look([5 => 1000], [5 => 1400])['started'] === [5], 'running at both looks, but started again in between: a start');
check(S::look([5 => 1000], [5 => 1400])['seen'] === [5 => 1400], 'the new start is remembered, so it counts once');
check(S::look([5 => 1000], [5 => 1012])['started'] === [] && S::look([5 => 1000], [5 => 990])['started'] === [], 'the same start read a little later or earlier is no new start');
check(S::look([5 => true], [5 => 5000])['started'] === [], 'a running server whose start was not known yet only gets remembered');
check(S::look([5 => 1000], [5 => true])['started'] === [], 'running, but Wings does not say since when: no start');
check(S::look([5 => false], [5 => 5000])['started'] === [5] && S::look(['5' => 1000], [5 => 5000])['started'] === [5], 'off or restarted, with the start known: started');
check(S::since(90500, 10000) === 9910 && S::since('90500', 10000) === 9910, 'the start is now minus the uptime, which Wings gives in milliseconds');
check(S::since(0, 10000) === true && S::since(null, 10000) === true, 'no uptime: running since an unknown moment');

// K-B86: Wings did not answer in time (Pelican waits one second and then calls the server "missing"). That is not
// "off": the next answer, a minute later, would be a start, and the announcement went out again and again for a server
// that never stopped. An unknown state keeps what the last look saw.
check(S::runs('running') === true && S::runs('offline') === false && S::runs('starting') === false && S::runs('exited') === false, 'running is running, offline, starting and exited are not');
check(S::runs('missing') === null, 'missing is unknown: Wings did not answer, or does not have the server');
$silent = S::look([5 => 1000, 6 => true, 7 => false], [5 => null, 6 => null, 7 => null]);
check($silent['started'] === [] && $silent['seen'] === [5 => 1000, 6 => true, 7 => false], 'no answer from Wings: nothing started, and the last known state is kept');
check(S::look($silent['seen'], [5 => 1012, 6 => true, 7 => false])['started'] === [], 'the answer a minute later is no start');
check(S::look($silent['seen'], [5 => 5000, 6 => true, 7 => true])['started'] === [5, 7], 'a real restart or start during the silence counts once Wings answers again');
check(S::look(['5' => 1000], [5 => null])['seen'] === [5 => 1000], 'the kept state survives ids read back from JSON as strings');
check(S::look([], [5 => null]) === ['started' => [], 'seen' => []], 'a server never seen stays unknown');

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
