<?php

// The announcement form without a panel: what it stores for each answer, what it shows for a stored announcement,
// the sentence "What this does", how long a bar stays, and which stops count as a crash.
declare(strict_types=1);

foreach (['Rules', 'Weekly', 'StartTrigger', 'FormData', 'Summary'] as $class) {
    require __DIR__.'/../src/Announcements/Support/'.$class.'.php';
}

use Simplify\Core\Announcements\Support\FormData as F;
use Simplify\Core\Announcements\Support\Rules as R;
use Simplify\Core\Announcements\Support\StartTrigger as S;
use Simplify\Core\Announcements\Support\Summary;
use Simplify\Core\Announcements\Support\Weekly as W;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

// How long a bar stays.
check(R::minutes(3, 'hours') === 180 && R::minutes('30', 'minutes') === 30 && R::minutes(2, 'days') === 2880, 'an amount and a unit are minutes');
check(R::minutes(0) === 1 && R::minutes(999, 'days') === R::STAY_MINUTES && R::minutes('x') === R::DEFAULT_STAY_MINUTES, 'between one minute and 30 days');
check(R::amount(180) === [3, 'hours'] && R::amount(90) === [90, 'minutes'] && R::amount(2880) === [2, 'days'], 'minutes read back as the largest whole unit');
check(R::windowEnd(1000, 'for', 30, null) === 1000 + 1800, 'for a while: its minutes after it went out');
check(R::windowEnd(1000, 'for', 30, 1600) === 1600, 'never past the end of the announcement');
check(R::windowEnd(1000, 'ended', 30, null) > 1000 + 86400 * 365 && R::windowEnd(1000, 'ended', 30, 5000) === 5000, 'until it is ended: far away, or its end');
check(R::stay('for') === 'for' && R::stay('x') === 'ended' && R::stay(null) === 'ended', 'an unknown choice is until it is ended');

// Several times on one day.
check(W::times(['21:30', '9:00', '21:30', 'x', ['at' => '12:00']]) === ['09:00', '12:00', '21:30'], 'times are HH:MM, each once, in order');
check(W::times(null, '19:00') === ['19:00'] && W::times([], null) === [], 'no times: the one time it had before');
check(count(W::times(['01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00'])) === W::TIMES, 'at most six times');
$at = fn (string $local): int => (new DateTimeImmutable($local, new DateTimeZone('Europe/Berlin')))->getTimestamp();
check(W::last([5], ['12:00', '19:00'], 'Europe/Berlin', $at('2026-11-27 15:00')) === $at('2026-11-27 12:00'), 'between two times: the earlier one went out last');
check(W::last([5], ['12:00', '19:00'], 'Europe/Berlin', $at('2026-11-27 19:30')) === $at('2026-11-27 19:00'), 'after both: the later one went out last');
check(W::next([5], ['12:00', '19:00'], 'Europe/Berlin', $at('2026-11-27 15:00')) === $at('2026-11-27 19:00'), 'between two times: the later one is next');
check(W::next([5], ['12:00', '19:00'], 'Europe/Berlin', $at('2026-11-27 19:30')) === $at('2026-12-04 12:00'), 'after both: next week at the first time');
check(W::last([5], '19:00', 'Europe/Berlin', $at('2026-11-27 19:30')) === $at('2026-11-27 19:00'), 'one time as text still works');

// Stops and crashes.
$look = S::look([1 => 1000, 2 => true, 3 => false, 4 => 1000], [1 => false, 2 => false, 3 => false, 4 => 1400]);
check($look['stopped'] === [1, 2, 4] && $look['started'] === [4], 'ran and does not now: stopped; started again in between: stopped and started');
check(S::look([], [1 => false])['stopped'] === [] && S::look([1 => 1000], [1 => null])['stopped'] === [], 'never seen, or no answer from Wings: no stop');
check(S::due([1 => 1000, 2 => 1050], 1080) === ['due' => [1 => 1000], 'pending' => [2 => 1050]], 'a stop waits 75 seconds before it can be called a crash');
check(S::due(['7' => 1000], 2000)['due'] === [7 => 1000], 'ids read back from JSON as strings still match');
// Lab 22-11: a Stop from the panel, a start, and a crash within ten minutes: the Stop hid the crash.
check(S::lookBack(5000, null) === 4400 && S::lookBack(5000, true) === 4400, 'without a known start the ten minutes before the stop count');
check(S::lookBack(5000, 4800) === 4800, 'only what happened since the server last started counts');
check(S::lookBack(5000, 1000) === 4400 && S::lookBack(5000, 6000) === 4400, 'a start long ago, or one that cannot be, leaves the ten minutes');
check(S::due([1 => 1000, 'since' => [1 => 900]], 2000) === ['due' => [1 => 1000], 'pending' => []], 'what is kept beside the waiting stops is no stop');
check(S::asked([['event' => 'server:power.stop']], null) && S::asked([['event' => 'server:power.restart']], 'stop') && S::asked([['event' => 'server:power.kill']], ''), 'Stop, Restart and Kill from the panel are asked for');
check(S::asked([['event' => 'server:console.command', 'command' => ' /Stop ']], 'stop') && S::asked([['event' => 'server:console.command', 'command' => 'stop']], '/stop'), 'the stop command in the console is asked for');
check(! S::asked([['event' => 'server:console.command', 'command' => 'say stop']], 'stop') && ! S::asked([['event' => 'server:power.start'], ['event' => 'server:file.write']], 'stop') && ! S::asked([], 'stop'), 'anything else is a crash');
check(S::event('crash') === 'crash' && S::event('x') === 'start' && S::event(null) === 'start', 'an unknown moment is a start');

// What the form stores.
$form = ['title' => 'T', 'example' => 'weekend', 'when' => 'now', 'starts_at' => '2026-11-28 20:00:00', 'bar' => true, 'stay' => 'for', 'stay_n' => 3, 'stay_unit' => 'hours', 'audience' => 'server', 'bell_pinned' => true, 'in_game_repeat' => 5];
$now = F::clean($form);
check($now['send_on'] === 'time' && $now['starts_at'] === null && $now['stay'] === 'for' && $now['stay_minutes'] === 180, 'now: at a time without a start, a bar for three hours');
check($now['audience'] === 'everyone' && $now['in_game_target'] === 'servers', 'the people of "the server" only exist for a server moment');
check(! array_key_exists('when', $now) && ! array_key_exists('example', $now) && ! array_key_exists('stay_n', $now) && $now['title'] === 'T', 'the form-only fields are not stored, the rest is kept');
$date = F::clean([...$form, 'when' => 'date', 'bar' => false]);
check($date['starts_at'] === '2026-11-28 20:00:00' && $date['bar'] === false && $date['bell_pinned'] === false && $date['in_game_repeat'] === 0, 'no bar: nothing stays, so no pin and no repeat in chat');
$weekly = F::clean([...$form, 'when' => 'weekly', 'weekly_days' => [6, '5', 9], 'weekly_times' => [['at' => '19:00'], ['at' => '12:00']], 'often' => 'once', 'stay' => 'until', 'more' => true, 'stop_after' => '2026-12-31 00:00:00']);
check($weekly['send_on'] === 'weekly' && $weekly['weekly_days'] === [5, 6] && $weekly['weekly_times'] === ['12:00', '19:00'] && $weekly['weekly_at'] === '12:00', 'every week: its days and its times');
check($weekly['start_once'] === true && $weekly['start_daily'] === false && $weekly['stay'] === 'ended' && $weekly['ends_at'] === '2026-12-31 00:00:00' && $weekly['starts_at'] === null, 'only the next time, no "until a date" for one that comes back, and Stop after is its end');
$server = F::clean([...$form, 'when' => 'server', 'start_event' => 'crash', 'start_servers' => [3, 3, '4'], 'often' => 'day', 'more' => true, 'weekly_days' => [6, 7], 'start_from' => '10:00', 'start_until' => '', 'in_game_target' => 'servers']);
check($server['send_on'] === 'start' && $server['start_event'] === 'crash' && $server['start_servers'] === [3, 4] && $server['start_daily'] === true && $server['start_once'] === false, 'a server moment: what happens, on which servers, how often');
check($server['weekly_days'] === [6, 7] && $server['start_from'] === '10:00' && $server['start_until'] === null && $server['ends_at'] === null && $server['audience'] === 'server' && $server['in_game_target'] === 'servers', 'its days, hours, people and chat');
$plain = F::clean([...$form, 'when' => 'server', 'more' => false, 'weekly_days' => [6], 'start_from' => '10:00']);
check($plain['weekly_days'] === [] && $plain['start_from'] === null && $plain['in_game_target'] === 'started', 'More options off: no days and no hours, chat in the server itself');

// Email: one at a time and every week, never a server moment (it comes back with each start).
check(F::clean([...$form, 'email' => true])['email'] === true && F::clean([...$form, 'when' => 'weekly', 'email' => true])['email'] === true, 'email is kept for one at a time and every week');
check(F::clean([...$form, 'when' => 'server', 'email' => true])['email'] === false, 'a server moment is never emailed');

// What the form shows for a stored announcement, and back again.
check(F::fill(['send_on' => 'time'])['when'] === 'now' && F::fill(['send_on' => 'time', 'starts_at' => '2026-11-28 20:00:00'])['when'] === 'date' && F::fill([])['when'] === 'now', 'at a time: now, or at its date');
$shown = F::fill(['send_on' => 'start', 'start_daily' => true, 'weekly_days' => [6, 7], 'stay_minutes' => 30]);
check($shown['when'] === 'server' && $shown['often'] === 'day' && $shown['more'] === true && $shown['stay_n'] === 30 && $shown['stay_unit'] === 'minutes', 'a server moment with days opens More options');
$shown = F::fill(['send_on' => 'weekly', 'start_once' => true, 'ends_at' => '2026-12-31 00:00:00', 'stay_minutes' => 1440]);
check($shown['when'] === 'weekly' && $shown['often'] === 'once' && $shown['more'] === true && $shown['stop_after'] === '2026-12-31 00:00:00' && $shown['stay_n'] === 1 && $shown['stay_unit'] === 'days', 'every week with an end opens More options');
check(F::fill(['send_on' => 'time', 'ends_at' => '2026-12-31 00:00:00'])['more'] === false && F::fill(['send_on' => 'start'])['more'] === false, 'nothing extra: More options stays closed');
foreach (F::examples() as $name => $example) {
    $stored = F::clean([...$example, 'title' => 'T', 'start_servers' => [1], 'weekly_times' => ['19:00']]);
    check(in_array($stored['send_on'], ['time', 'weekly', 'start'], true) && in_array($stored['audience'], R::AUDIENCES, true), "the example $name stores a known moment and people");
}

// "What this does".
$en = require __DIR__.'/../lang/en/announcements.php';
$text = function (string $key, array $replace) use ($en): string {
    $line = $en['summary'][$key] ?? 'MISSING '.$key;
    foreach ($replace as $name => $value) {
        $line = str_replace(':'.$name, $value, $line);
    }

    return $line;
};
$names = ['days' => [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'], 'servers' => [1 => 'Survival', 2 => 'Creative'], 'date' => fn (mixed $date): string => (string) $date];
$weekend = [...F::examples()['weekend'], 'start_servers' => [1]];
$says = Summary::text($weekend, $text, $names);
check($says === 'Goes out the first time each day that Survival starts. Only on Sat, Sun. Only from 10:00 to 22:00. The people with access to that server get it in: the bell, an app notification. Nothing stays on screen.', 'the weekend example reads as a sentence: '.$says);
$says = Summary::text([...F::examples()['weekly'], 'weekly_times' => ['19:00', '12:00']], $text, $names);
check($says === 'Goes out every Fri, Sat at 12:00, 19:00. Everyone gets it in: a bar at the top of every page, the bell, an app notification. The bar stays for 3 hours.', 'the weekly example: '.$says);
$says = Summary::text(['when' => 'now', 'bar' => false], $text, $names);
check($says === 'Goes out as soon as you save. Nobody sees it yet: turn on a place under Where. Nothing stays on screen.', 'nothing turned on says so: '.$says);
$says = Summary::text(['when' => 'date', 'starts_at' => '2026-12-24 10:00', 'bar' => false, 'email' => true], $text, $names);
check($says === 'Goes out on 2026-12-24 10:00. Everyone gets it in: an email. Nothing stays on screen.', 'email is a place: '.$says);
$says = Summary::text(['when' => 'server', 'start_servers' => [1], 'bar' => false, 'email' => true, 'in_bell' => true], $text, $names);
check(! str_contains($says, 'an email'), 'a server moment never says email: '.$says);
$says = Summary::text(['when' => 'server', 'start_event' => 'crash', 'often' => 'once', 'start_servers' => [], 'bar' => true, 'in_bell' => true, 'bell_pinned' => true, 'audience' => 'admins', 'stay' => 'ended'], $text, $names);
check($says === 'Goes out once, the next time … crashes. Admins get it in: a bar at the top of every page, the bell, pinned on top. The bar stays until you end it.', 'a crash, once, for admins: '.$says);
check(! str_contains(implode(' ', array_map(fn (array $e): string => Summary::text($e, $text, $names), F::examples())), 'MISSING'), 'every example has all its lines');

if ($failures) {
    foreach ($failures as $failure) {
        echo "FAIL $failure\n";
    }
    exit(1);
}
echo "PASS the announcement form, its sentence, bar length, several times, stops and crashes\n";
