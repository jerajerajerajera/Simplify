<?php

// "Send: on days of the week" without a panel: when it last went out, when it goes next, and how long its bar shows.
declare(strict_types=1);

require __DIR__.'/../src/Announcements/Support/Weekly.php';

use Simplify\Core\Announcements\Support\Weekly as W;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

function at(string $local, string $zone = 'Europe/Berlin'): int
{
    return (new DateTimeImmutable($local, new DateTimeZone($zone)))->getTimestamp();
}

// Cleaning what the form sends.
check(W::days([5, '1', 5, 0, 8, 'x', null, 3.5, 7]) === [1, 5, 7], 'days are 1 to 7, each once, in week order');
check(W::days('1') === [] && W::days(null) === [], 'nothing usable is no days');
check(W::time('9:05') === '09:05' && W::time('19:30:00') === '19:30' && W::time(' 07:00 ') === '07:00', 'a time is HH:MM');
check(W::time('24:00') === null && W::time('7') === null && W::time(null) === null && W::time('12:60') === null, 'no time is null');
check(W::hours('6') === 6 && W::hours(5) === W::DEFAULT_HOURS && W::hours(null) === W::DEFAULT_HOURS, 'only the offered hours');
check(W::zone('Europe/Berlin') === 'Europe/Berlin' && W::zone('Mars/Base') === 'UTC' && W::zone(null) === 'UTC', 'an unknown zone is UTC');

// Friday 13 November 2026, 18:00 in Berlin. Monday and Friday at 19:00.
$now = at('2026-11-13 18:00');
$days = [1, 5];
check(W::last($days, '19:00', 'Europe/Berlin', $now) === at('2026-11-09 19:00'), 'before today\'s time: the last turn was Monday');
check(W::next($days, '19:00', 'Europe/Berlin', $now) === at('2026-11-13 19:00'), 'the next turn is tonight');
check(W::last($days, '19:00', 'Europe/Berlin', at('2026-11-13 19:00')) === at('2026-11-13 19:00'), 'at the minute itself it goes out');
check(W::last([5], '19:00', 'Europe/Berlin', $now) === at('2026-11-06 19:00'), 'one day a week: last week');
check(W::next([5], '19:00', 'Europe/Berlin', at('2026-11-13 19:00')) === at('2026-11-20 19:00'), 'just after it went out: next week');
// The time is the author's own clock, not UTC.
check(W::last([5], '19:00', 'Europe/Berlin', at('2026-11-13 18:30', 'UTC')) === at('2026-11-13 19:00'), '19:00 in Berlin is 18:00 UTC');
check(W::last([1], '00:30', 'Europe/Berlin', at('2026-11-08 23:45', 'UTC')) === at('2026-11-09 00:30'), 'a Monday in Berlin while it is still Sunday in UTC');
// Summer time ends on 25 October 2026: still 19:00 on the clock.
check(W::last([1], '19:00', 'Europe/Berlin', at('2026-10-26 20:00')) === at('2026-10-26 19:00'), 'the same clock time after summer time ends');
check(W::next([1], '19:00', 'Europe/Berlin', at('2026-10-20 12:00')) === at('2026-10-26 19:00'), 'the next turn crosses the change of time');

// Only inside "Active from" and "Active until".
$from = at('2026-11-12 00:00');
check(W::last($days, '19:00', 'Europe/Berlin', $now, $from) === null, 'nothing before its period started');
check(W::next($days, '19:00', 'Europe/Berlin', at('2026-11-01 12:00'), $from) === at('2026-11-13 19:00'), 'the first turn is in its period');
$until = at('2026-11-13 19:00');
check(W::next($days, '19:00', 'Europe/Berlin', $now, null, $until) === null, 'no turn at or after its end');
check(W::last($days, '19:00', 'Europe/Berlin', $now, null, $until) === at('2026-11-09 19:00'), 'earlier turns stay');
check(W::next([], '19:00', 'Europe/Berlin', $now) === null && W::last($days, 'x', 'Europe/Berlin', $now) === null, 'no days or no time: never');

// The bar shows for its hours, then goes until the next turn.
$turn = at('2026-11-09 19:00');
check(W::showing($turn, 3, $turn) && W::showing($turn, 3, $turn + 3 * 3600 - 1), 'shown during its hours');
check(! W::showing($turn, 3, $turn + 3 * 3600) && ! W::showing(null, 3, $turn) && ! W::showing($turn, 3, $turn - 1), 'not shown after, before or without a turn');

// "When a server starts" on some days only: the day is the author's, no days is every day.
check(W::onDay([], 'Europe/Berlin', $now), 'no days: a start counts every day');
check(W::onDay([5], 'Europe/Berlin', $now) && ! W::onDay([6, 7], 'Europe/Berlin', $now), 'Friday counts on Friday only');
check(W::onDay([6], 'Europe/Berlin', at('2026-11-13 23:30', 'UTC')), 'Saturday in Berlin counts while it is still Friday in UTC');

if ($failures) {
    foreach ($failures as $failure) {
        echo "FAIL $failure\n";
    }
    exit(1);
}
echo "PASS announcements on days of the week\n";
