<?php

// The "Announcement" task of a schedule without a panel: its payload, who gets it, the game chat and waiting for online.
declare(strict_types=1);

require __DIR__.'/../src/Announcements/Support/GameChat.php';
require __DIR__.'/../src/Announcements/Support/TaskAnnouncement.php';

use Simplify\Core\Announcements\Support\GameChat;
use Simplify\Core\Announcements\Support\TaskAnnouncement as T;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

// Payload.
check(T::parse(T::encode(T::defaults())) === ['message' => '', 'send' => 'now', 'game_chat' => false, 'notify' => true], 'defaults: now, notification on, game chat off');
check(T::parse('Restart at nine')['message'] === 'Restart at nine', 'a plain payload is read as the message');
check(T::parse(null)['message'] === '', 'no payload is an empty message');
check(T::parse('{"message":"Hi\nthere\u0007","send":"online"}') === ['message' => 'Hi there', 'send' => 'online', 'game_chat' => false, 'notify' => true], 'one line, no control characters');
check(T::parse('{"message":"x","send":"tomorrow"}')['send'] === 'now', 'an unknown moment becomes now');
check(T::parse('{"message":"x","game_chat":"1","notify":0}')['game_chat'] === true && T::parse('{"message":"x","notify":0}')['notify'] === false, 'switches from strings and numbers');
check(mb_strlen(T::parse(json_encode(['message' => str_repeat('é', 300)]))['message']) === GameChat::MESSAGE_LENGTH, 'a long message is cut to 240');
check(str_contains(T::encode(['message' => 'Café / 1']), 'Café / 1'), 'encoded as readable JSON');

check(T::problem(['message' => '  ', 'notify' => true]) === 'message_required', 'a message is required');
check(T::problem(['message' => str_repeat('a', 241), 'notify' => true]) === 'message_long', 'at most 240 characters');
check(T::problem(['message' => str_repeat('a', 240), 'notify' => true]) === null, '240 characters is fine');
check(T::problem(['message' => 'Hi', 'game_chat' => false, 'notify' => false]) === 'send_to_required', 'game chat or notification is required');
check(T::problem(['message' => 'Hi', 'game_chat' => true, 'notify' => false]) === null, 'game chat alone is fine');

// Recipients: the owner and the people the server is shared with, each once.
check(T::recipients(3, [5, 3, '7', 5, 0, null, 'x']) === [3, 5, 7], 'owner and subusers, each once');
check(T::recipients(null, [4]) === [4], 'without an owner only the subusers');
check(T::recipients(2, []) === [2], 'without subusers only the owner');

// Game chat: skipped when not asked, when the game has no chat command, or when the server is offline.
check(T::postsInGame(true, 'say {message}', true), 'posted when asked, with a command, while running');
check(! T::postsInGame(true, 'say {message}', false), 'offline: skipped');
check(! T::postsInGame(true, GameChat::commandFor('Valheim', null), true), 'no chat command: skipped');
check(! T::postsInGame(false, 'say {message}', true), 'not asked: skipped');

// "When the server is online": wait 30 seconds at a time, give up after 10 minutes.
$start = 1_800_000_000;
check(T::step($start, $start + 30, false) === T::WAIT, 'offline: wait');
check(T::step($start, $start + 599, false) === T::WAIT, 'still waiting just before 10 minutes');
check(T::step($start, $start + 600, false) === T::GIVE_UP, 'gives up after 10 minutes');
check(T::step($start, $start + 590, true) === T::SEND, 'online: send');
check(T::WAIT_SECONDS === 600, 'waits for at most 10 minutes');

// K-B64: waiting needs no queue. The scheduler looks at the waiting rows every minute.
$sent = [];
$forgotten = [];
$servers = [1 => (object) ['id' => 1, 'running' => true], 2 => (object) ['id' => 2, 'running' => false], 3 => (object) ['id' => 3, 'running' => false]];
$rows = [
    ['id' => 10, 'server_id' => 1, 'payload' => T::encode(['message' => 'Up', 'send' => 'online']), 'schedule' => 'Nightly', 'started_at' => $start],
    ['id' => 11, 'server_id' => 2, 'payload' => T::encode(['message' => 'Wait']), 'schedule' => null, 'started_at' => $start + 60],
    ['id' => 12, 'server_id' => 3, 'payload' => T::encode(['message' => 'Late']), 'schedule' => null, 'started_at' => $start - 600],
    ['id' => 13, 'server_id' => 9, 'payload' => T::encode(['message' => 'Gone']), 'schedule' => null, 'started_at' => $start],
];
$done = T::runWaits(
    $rows,
    $start + 120,
    fn (int $id) => $servers[$id] ?? null,
    fn (object $server) => $server->running,
    function (object $server, array $payload, ?string $schedule, bool $cameOnline) use (&$sent) {
        $sent[] = [$server->id, $payload['message'], $schedule, $cameOnline];
    },
    function (int $id) use (&$forgotten) {
        $forgotten[] = $id;
    },
);
check($done === 2, 'two announcements were finished');
check(in_array([1, 'Up', 'Nightly', true], $sent, true), 'a server that came online gets the announcement, with its schedule');
check(in_array([3, 'Late', null, false], $sent, true), 'after 10 minutes the people hear it did not come online');
check(! in_array(2, array_column($sent, 0), true) && ! in_array(11, $forgotten, true), 'a server that is still starting keeps waiting');
check($forgotten === [10, 12, 13], 'finished and gone rows are forgotten, so nothing is sent twice');
$task = (string) file_get_contents(__DIR__.'/../src/Announcements/Tasks/AnnouncementTask.php');
$command = (string) file_get_contents(__DIR__.'/../src/Announcements/Console/DeliverAnnouncements.php');
check(str_contains($task, 'TaskWaits::add(') && ! str_contains($task, '::dispatch('), 'the task waits in the table, not on the queue');
check(str_contains($command, 'TaskWaits::runDue()'), 'the scheduler command looks at the waiting announcements');

// Two recipients in different languages each get Simplify's own words in their language.
$groups = T::byLanguage([3 => 'nl', 5 => 'de', 7 => null, 9 => '../x'], 'en');
check($groups === ['nl' => [3], 'de' => [5], 'en' => [7, 9]], 'grouped by language, unknown falls back to the panel language');
$nl = (require __DIR__.'/../lang/nl/announcements.php')['task']['not_online'] ?? '';
$de = (require __DIR__.'/../lang/de/announcements.php')['task']['not_online'] ?? '';
$en = (require __DIR__.'/../lang/en/announcements.php')['task']['not_online'] ?? '';
check($nl !== '' && $de !== '' && $nl !== $de && $nl !== $en, '"did not come online" differs per language');
check(str_contains($nl, ':server') && str_contains($de, ':server'), 'each language names the server');

if ($failures) {
    echo "FAIL announcement task:\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo "PASS announcement task\n";
