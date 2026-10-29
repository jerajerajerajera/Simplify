<?php

// The rules of an announcement without a panel: when it is live, its state and which links are allowed.
declare(strict_types=1);

require __DIR__.'/../src/Announcements/Support/Rules.php';

use Simplify\Core\Announcements\Support\Rules;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

$now = 1_800_000_000;
check(Rules::isLive(null, null, $now), 'without times it is live');
check(Rules::isLive($now, null, $now), 'it is live from its start');
check(! Rules::isLive($now + 60, null, $now), 'it is not live before its start');
check(! Rules::isLive(null, $now, $now), 'it is not live at its end');
check(Rules::isLive($now - 60, $now + 60, $now), 'it is live between start and end');

check(Rules::state($now + 60, null, $now) === 'scheduled', 'a later start is scheduled');
check(Rules::state(null, $now + 60, $now) === 'live', 'a later end is live');
check(Rules::state($now + 60, $now - 60, $now) === 'ended', 'a past end is ended, whatever the start');

foreach (['https://example.com', 'https://example.com/news?id=1', '/server/abc123', '/'] as $url) {
    check(Rules::isLink($url), "$url is a link");
}
foreach (['http://example.com', '//evil.example', 'javascript:alert(1)', 'https://', 'https://a b', '/a b', ''] as $url) {
    check(! Rules::isLink($url), "$url is not a link");
}
check(! Rules::isLink('/'.str_repeat('a', Rules::URL_LENGTH)), 'a link is at most '.Rules::URL_LENGTH.' characters');

check(Rules::color('warning') === 'warning' && Rules::icon('success') === 'tabler-circle-check', 'each category has a color and an icon');
check(Rules::color('other') === 'info', 'an unknown category looks like Info');

if ($failures) {
    echo "FAIL rules:\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo "PASS announcement rules\n";
