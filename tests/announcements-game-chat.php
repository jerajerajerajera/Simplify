<?php

// Posting in a game's chat without a panel: commands per game, the message and when a repeat is due.
declare(strict_types=1);

require __DIR__.'/../src/Announcements/Support/GameChat.php';

use Simplify\Core\Announcements\Support\GameChat;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

check(GameChat::defaultCommand('Paper') === 'say {message}', 'Paper uses say');
check(GameChat::defaultCommand('Velocity') === null, 'Velocity has no chat command');
check(GameChat::defaultCommand('BungeeCord') === 'alert {message}', 'BungeeCord uses alert');
check(GameChat::defaultCommand('Project Zomboid') === 'servermsg "{message}"', 'Zomboid uses servermsg');
check(GameChat::defaultCommand('Valheim') === null, 'Valheim has no chat command');
check(GameChat::defaultCommand('Trustworthy') === null, '"rust" inside a word is not Rust');

check(GameChat::commandFor('Paper', null) === 'say {message}', 'without an own command the default is used');
check(GameChat::commandFor('Paper', 'broadcast {message}') === 'broadcast {message}', 'an own command wins');
check(GameChat::commandFor('Paper', '-') === null, '"-" skips the game');
check(GameChat::commandFor('Valheim', 'say {message}') === 'say {message}', 'an own command works for any game');

check(GameChat::isValidCommand('say {message}') && GameChat::isValidCommand('-'), 'a command with {message}, or "-"');
check(! GameChat::isValidCommand('say hello'), 'a command needs {message}');
check(! GameChat::isValidCommand("say {message}\nstop"), 'a command is one line');

check(GameChat::message('[Announcement]', 'Restart', "At nine.\nBe ready") === '[Announcement] Restart - At nine. Be ready', 'one line with title and message');
check(GameChat::message('[Announcement]', 'Say "hi"', null) === "[Announcement] Say 'hi'", 'double quotes become single quotes');
check(mb_strlen(GameChat::message('[Announcement]', str_repeat('a', 300), null)) === GameChat::MESSAGE_LENGTH, 'a long message is cut');
check(GameChat::render('servermsg "{message}"', 'hi') === 'servermsg "hi"', '{message} is replaced');

$now = 1_800_000_000;
check(GameChat::isDue(null, 0, $now), 'it is due when it never went out');
check(! GameChat::isDue($now - 3600, 0, $now), 'only once means not again');
check(GameChat::isDue($now - 300, 5, $now) && ! GameChat::isDue($now - 120, 5, $now), 'a repeat is due after its minutes');

if ($failures) {
    echo "FAIL game chat:\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo "PASS game chat\n";
