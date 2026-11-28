<?php

// Announcements by email without a panel: when the panel has a mail server, and what the list says about how far the
// emails got.
declare(strict_types=1);

require __DIR__.'/../src/Announcements/Services/Email.php';

use Simplify\Core\Announcements\Services\Email as E;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

check(E::configured('smtp', 'panel@example.com') && E::configured('ses', 'noreply@example.com') && E::configured('mailgun', ' a@b.co '), 'a mail driver and a sender address is a mail server');
check(! E::configured('log', 'panel@example.com') && ! E::configured('array', 'panel@example.com') && ! E::configured('', 'panel@example.com') && ! E::configured('LOG', 'panel@example.com'), 'log, array or nothing never reaches anyone');
check(! E::configured('smtp', '') && ! E::configured('smtp', 'hello@example'), 'no sender address is no mail server');

check(E::progress(false, 0, 0, false) === null, 'not emailed: nothing in the list');
check(E::progress(true, 0, 0, false) === 'waiting', 'not started yet: waits for start');
check(E::progress(true, 20, 42, false) === 'sending' && E::progress(true, 42, 42, false) === 'sent', 'sending, then sent to all');
check(E::progress(true, 20, 42, true) === 'failed', 'the mail server refused: the list says so');
check(E::BATCH > 0 && E::BATCH <= 100, 'a few each minute');

if ($failures) {
    foreach ($failures as $failure) {
        echo "FAIL $failure\n";
    }
    exit(1);
}
echo "PASS announcement emails: mail server check and progress\n";
