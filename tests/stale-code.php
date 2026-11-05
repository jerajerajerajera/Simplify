<?php

// K-B63: right after an update of an add-on (Hosting beta.5) a page gave a 500 "Unable to find component:
// [simplify-hosting-settings-form]". The request that sees the new version starts PHP's opcache over, but that
// request still runs the previous version's classes, which did not register the add-on's new component. Simplify
// answers such an error with one fresh try instead of a 500.
declare(strict_types=1);

require __DIR__.'/../src/Support/StaleCode.php';
require __DIR__.'/fixtures/livewire-component-not-found.php';

use Livewire\Exceptions\ComponentNotFoundException;
use Simplify\Core\Support\StaleCode;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

check(StaleCode::answer(true, 'POST', false) === StaleCode::EXPIRED, 'a Livewire call asks the browser to load the page again (419)');
check(StaleCode::answer(true, 'POST', true) === StaleCode::EXPIRED, 'a Livewire call never loops: the browser asks before it reloads');
check(StaleCode::answer(false, 'GET', false) === StaleCode::RELOAD, 'a page opens once more, now on the new code');
check(StaleCode::answer(false, 'HEAD', false) === StaleCode::RELOAD, 'HEAD is a page too');
check(StaleCode::answer(false, 'GET', true) === StaleCode::ERROR, 'the second try shows the real error, so a missing component never loops');
check(StaleCode::answer(false, 'POST', false) === StaleCode::ERROR, 'a form post is never sent twice');

check(StaleCode::retryUrl('https://panel.test/admin/settings?tab=addons') === 'https://panel.test/admin/settings?tab=addons&'.StaleCode::RETRIED.'=1', 'the retry keeps the query');
check(StaleCode::retryUrl('https://panel.test/') === 'https://panel.test/?'.StaleCode::RETRIED.'=1', 'the retry marks a plain address');

$missing = new ComponentNotFoundException('Unable to find component: [simplify-hosting-settings-form]');
check(StaleCode::isMissingComponent($missing), "Livewire's error for a missing component counts");
check(StaleCode::isMissingComponent(new ErrorException('in a view', 0, 1, 'settings.blade.php', 1, $missing)), 'also when a Blade page wraps it');
check(StaleCode::isMissingComponent(new RuntimeException('Unable to find component: [simplify-hosting-settings-form]')) === false, 'only Livewire\'s own error counts');

$provider = (string) file_get_contents(__DIR__.'/../src/Providers/SimplifyServiceProvider.php');
check(str_contains($provider, 'StaleCode::register('), 'Simplify registers the answer when it boots');

if ($failures !== []) {
    echo "stale-code: FAIL\n  - ".implode("\n  - ", $failures)."\n";
    exit(1);
}
echo "stale-code: ok\n";
