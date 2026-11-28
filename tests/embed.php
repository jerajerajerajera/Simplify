<?php

// The parts of the embed that need no panel: options, public addresses, the code for a website and cleaning
// what other plugins add. Run: php tests/embed.php
declare(strict_types=1);

require __DIR__.'/../src/Server/Looks.php';
require __DIR__.'/../src/Embed/Services/DnsMissing.php';
foreach (['EggLogo', 'EmbedDetails', 'EmbedOptions', 'IncidentRules', 'PublicAddress', 'ServerList', 'Snippet', 'StatusAddress', 'StatusCss', 'Uptime'] as $class) {
    require __DIR__.'/../src/Embed/Services/'.$class.'.php';
}

use Simplify\Core\Embed\Services\DnsMissing;
use Simplify\Core\Embed\Services\EggLogo;
use Simplify\Core\Embed\Services\EmbedOptions;
use Simplify\Core\Embed\Services\IncidentRules;
use Simplify\Core\Embed\Services\PublicAddress;
use Simplify\Core\Embed\Services\ServerList;
use Simplify\Core\Embed\Services\Snippet;
use Simplify\Core\Embed\Services\StatusAddress;
use Simplify\Core\Embed\Services\StatusCss;
use Simplify\Core\Embed\Services\Uptime;

$failures = [];
$checks = 0;
function same(string $what, mixed $want, mixed $have): void
{
    global $failures, $checks;
    $checks++;
    if ($want !== $have) {
        $failures[] = $what.': wanted '.var_export($want, true).', got '.var_export($have, true);
    }
}

// Options: unknown keys and bad values fall back to the defaults.
same('defaults', EmbedOptions::DEFAULTS, EmbedOptions::normalize([]));
same('valid options', ['style' => 'dark', 'corners' => 'square', 'accent' => '#abcdef', 'players' => false, 'modpack' => true, 'join' => false,
    'uptime' => false, 'online_first' => true, 'status_host' => 'status.example.com', 'logos' => [3 => 'simplify/embed/logos/abc.png']] + EmbedOptions::DEFAULTS,
    EmbedOptions::normalize(['style' => 'dark', 'corners' => 'square', 'accent' => '#ABCDEF', 'players' => 0, 'join' => '', 'evil' => 'x',
        'uptime' => 0, 'online_first' => 1, 'status_host' => 'Status.Example.com', 'logos' => [3 => 'simplify/embed/logos/abc.png']]));
same('bad status host', '', EmbedOptions::normalize(['status_host' => 'evil.com/<script>'])['status_host']);
same('status host with scheme', '', EmbedOptions::normalize(['status_host' => 'https://status.example.com'])['status_host']);
same('logo outside the folder', [], EmbedOptions::normalize(['logos' => [1 => '../../.env', 2 => 'simplify/embed/logos/x.svg']])['logos']);
same('logo for a bad egg id', [], EmbedOptions::normalize(['logos' => ['x' => 'simplify/embed/logos/a.png', 0 => 'simplify/embed/logos/b.png']])['logos']);
same('bad style', 'auto', EmbedOptions::normalize(['style' => 'neon'])['style']);
same('bad accent', '', EmbedOptions::normalize(['accent' => 'red;background:url(x)'])['accent']);

// Public addresses only.
foreach (['play.example.com:25565', '203.0.113.9:25565', '8.8.8.8:27015', '[2001:4860::1]:25565'] as $address) {
    same("reachable $address", true, PublicAddress::reachable($address));
}
foreach (['192.168.1.10:25565', '10.0.0.2:1', '127.0.0.1:25565', '100.101.102.103:25565', '0.0.0.0:25565', 'box.tail1234.ts.net:25565',
    'server.local:25565', 'localhost:25565', '[fd7a:115c:a1e0::1]:25565', '[::1]:25565', 'nodots:25565'] as $address) {
    same("unreachable $address", false, PublicAddress::reachable($address));
}
same('join ipv4', '203.0.113.9:25565', PublicAddress::join('203.0.113.9', 25565));
same('join ipv6', '[2001:db8::1]:25565', PublicAddress::join('2001:db8::1', 25565));
same('join without port', null, PublicAddress::join('203.0.113.9', 0));
same('first reachable', 'play.example.com:25566', PublicAddress::first([null, '192.168.1.2:25566', 'play.example.com:25566']));
same('none reachable', null, PublicAddress::first(['10.0.0.1:1', '']));

// The code for a website.
$all = Snippet::make('https://panel.example.com/');
same('script', '<div id="simplify-servers"></div>'."\n".'<script src="https://panel.example.com/simplify/embed.js" defer></script>', $all['script']);
same('page', 'https://panel.example.com/simplify/embed?page=1', $all['page']);
$one = Snippet::make('https://panel.example.com', ['server' => '1a2b3c4d']);
same('one server attribute', true, str_contains($one['script'], ' data-server="1a2b3c4d"'));
same('one server iframe', true, str_contains($one['iframe'], '/simplify/embed?server=1a2b3c4d" ') && str_contains($one['iframe'], 'height:190px'));
same('one server page', 'https://panel.example.com/simplify/embed?server=1a2b3c4d&page=1', $one['page']);
$game = Snippet::make('https://panel.example.com', ['game' => "Don't Starve"]);
same('game escaped', true, str_contains($game['script'], 'data-game="Don&#039;t Starve"'));
same('bad server ignored', Snippet::make('https://p.example')['script'], Snippet::make('https://p.example', ['server' => '"><script>'])['script']);
same('servers list', true, str_contains(Snippet::make('https://p.example', ['servers' => ['ab', 'c d', 'ef']])['script'], 'data-servers="ab,ef"'));

// What other plugins add is cut to size and only https links survive.
$clean = ServerList::clean([
    'id' => 'abc', 'name' => str_repeat('n', 200), 'game' => "Mine\x07craft", 'state' => 'weird', 'players' => '7', 'max_players' => -1,
    'icon' => 'javascript:alert(1)', 'address' => 'play.example.com:25565',
    'modpack' => ['name' => 'All the Mods', 'version' => '1.2', 'url' => 'http://example.com', 'icon' => 'https://cdn.example.com/a.png'],
]);
same('name cut', 80, mb_strlen($clean['name']));
same('control characters', 'Mine craft', $clean['game']);
same('unknown state', 'offline', $clean['state']);
same('players', 7, $clean['players']);
same('negative max', null, $clean['max_players']);
same('javascript icon', null, $clean['icon']);
same('http modpack link', null, $clean['modpack']['url']);
same('https modpack icon', 'https://cdn.example.com/a.png', $clean['modpack']['icon']);
same('description cleaned', 'Line one  line two', ServerList::clean(['id' => 'a', 'name' => 'a', 'game' => 'a', 'state' => 'online', 'description' => " Line one\r\nline two "])['description']);
same('description cut', 300, mb_strlen(ServerList::clean(['id' => 'a', 'name' => 'a', 'game' => 'a', 'state' => 'online', 'description' => str_repeat('d', 900)])['description']));
same('empty description', null, ServerList::clean(['id' => 'a', 'name' => 'a', 'game' => 'a', 'state' => 'online', 'description' => '  '])['description']);
same('https banner', 'https://panel.example.com/simplify/embed/banners/abc.png', ServerList::clean(['id' => 'a', 'name' => 'a', 'game' => 'a', 'state' => 'online', 'banner' => 'https://panel.example.com/simplify/embed/banners/abc.png'])['banner']);
same('http banner', null, ServerList::clean(['id' => 'a', 'name' => 'a', 'game' => 'a', 'state' => 'online', 'banner' => 'http://panel.example.com/a.png'])['banner']);
same('no banner', null, ServerList::clean(['id' => 'a', 'name' => 'a', 'game' => 'a', 'state' => 'online'])['banner']);
$base = ['id' => 'a', 'name' => 'a', 'game' => 'a', 'state' => 'online'];
same('https download', 'https://modrinth.com/modpack/x/version/abc', ServerList::clean([...$base, 'download' => 'https://modrinth.com/modpack/x/version/abc'])['download']);
same('javascript download', null, ServerList::clean([...$base, 'download' => 'javascript:alert(1)'])['download']);
same('modpack download', 'https://www.feed-the-beast.com/modpacks/1', ServerList::clean([...$base, 'modpack' => ['name' => 'FTB', 'download' => 'https://www.feed-the-beast.com/modpacks/1']])['modpack']['download']);
same('second address', 'bedrock.example.com:19132', ServerList::clean([...$base, 'address2' => 'bedrock.example.com:19132'])['address2']);
same('position', 3, ServerList::clean([...$base, 'position' => '3'])['position']);
same('negative position', null, ServerList::clean([...$base, 'position' => -2])['position']);
$order = ServerList::sorted([
    ['name' => 'Zeta', 'state' => 'offline', 'position' => 1],
    ['name' => 'beta', 'state' => 'online', 'position' => null],
    ['name' => 'Alpha', 'state' => 'online', 'position' => null],
    ['name' => 'Gamma', 'state' => 'online', 'position' => 2],
    ['name' => 'Delta', 'state' => 'offline', 'position' => null],
]);
same('order: position, then name', ['Zeta', 'Gamma', 'Alpha', 'beta', 'Delta'], array_column($order, 'name'));
$order = ServerList::sorted([
    ['name' => 'Zeta', 'state' => 'offline', 'position' => 1],
    ['name' => 'beta', 'state' => 'online', 'position' => null],
    ['name' => 'Alpha', 'state' => 'online', 'position' => null],
    ['name' => 'Gamma', 'state' => 'online', 'position' => 2],
    ['name' => 'Delta', 'state' => 'offline', 'position' => null],
], true);
same('order: online first, then position, then name', ['Gamma', 'Alpha', 'beta', 'Zeta', 'Delta'], array_column($order, 'name'));
same('no modpack without a name', null, ServerList::clean(['id' => 'a', 'name' => 'a', 'game' => 'a', 'state' => 'online', 'modpack' => ['url' => 'https://x.example']])['modpack']);

// Logos: the software an egg runs, from its name, tags or image.
foreach (['Paper' => 'logo-paper.svg', 'PaperMC 1.21' => 'logo-paper.svg', 'Velocity Proxy' => 'logo-velocity.svg', 'Spigot' => 'logo-spigot.svg',
    'Modrinth modpack' => 'logo-modrinth.svg', 'CurseForge Generic' => 'logo-curseforge.svg', 'Python Generic' => 'logo-python.svg',
    'discord.py bot' => 'logo-python.svg', 'ghcr.io/parkervcp/yolks:nodejs_21' => 'logo-nodejs.svg', 'discord.js' => 'logo-nodejs.svg',
    'ghcr.io/parkervcp/yolks:go_1.22' => 'logo-go.svg', 'Golang Generic' => 'logo-go.svg', '.NET Generic' => 'logo-dotnet.svg',
    'Red-DiscordBot' => 'logo-discord.svg', 'Newspaper' => null, 'Rust' => null, 'Vanilla Minecraft' => null, 'Let go' => 'logo-go.svg'] as $text => $logo) {
    same("logo for $text", $logo, EggLogo::software($text));
}
same('valid upload', true, EggLogo::validUpload('simplify/embed/logos/a-1_b.webp'));
foreach (['simplify/embed/logos/a.svg', 'simplify/embed/logos/../x.png', 'other/a.png', 'simplify/embed/logos/a.png/b.png'] as $file) {
    same("invalid upload $file", false, EggLogo::validUpload($file));
}

// Uptime: per day and in all, from stretches of online and offline time.
$day = 86400;
$starts = [0, $day, 2 * $day];
$now = 2 * $day + 3600;
$summary = Uptime::summarize([
    ['online' => true, 'from' => 0, 'to' => 43200],             // day 1: half a day online, then nothing seen
    ['online' => true, 'from' => $day, 'to' => $day + 64800],   // day 2: 18 h online
    ['online' => false, 'from' => $day + 64800, 'to' => $now - 60], // 6 h offline, then 59 min into day 3
], $starts, $now);
same('uptime day 1', 100.0, $summary['days'][0]);
same('uptime day 2', 75.0, $summary['days'][1]);
same('uptime day 3', 0.0, $summary['days'][2]);
same('uptime state', false, $summary['online']);
same('uptime since', $day + 64800, $summary['since']);
same('uptime percent', round((43200 + 64800) / (43200 + 86400 + 3540) * 100, 1), $summary['percent']);
$empty = Uptime::summarize([], $starts, $now);
same('no uptime data', [null, null, null], $empty['days']);
same('no uptime state', null, $empty['online']);
same('no uptime since', null, $empty['since']);
$stale = Uptime::summarize([['online' => true, 'from' => 0, 'to' => $day]], $starts, $now);
same('stale: state unknown', null, $stale['online']);
same('stale: since unknown', null, $stale['since']);
$joined = Uptime::summarize([['online' => true, 'from' => 100, 'to' => 5000], ['online' => true, 'from' => 5000, 'to' => $now]], $starts, $now);
same('touching stretches join', 100, $joined['since']);
$gap = Uptime::summarize([['online' => true, 'from' => 100, 'to' => 5000], ['online' => true, 'from' => 9000, 'to' => $now]], $starts, $now);
same('a gap starts again', 9000, $gap['since']);

// The certificate name that covers a status address.
same('wildcard', '*.example.com', StatusAddress::wildcard('status.example.com'));
same('wildcard deeper', '*.eu.example.com', StatusAddress::wildcard('status.eu.example.com'));

// How the check reads the answer of https://<address>/ (K-B99): only this panel's page with its header is live.
$state = fn (int $status, ?string $header = null, ?string $location = null) => StatusAddress::classify('status.example.com', $status, $header, $location)['state'];
same('live', 'live', $state(200, '1'));
same('a page without the header is another site', 'other', $state(200));
same('not found is another site', 'other', $state(404));
same('a catch-all redirect is sent somewhere else', 'redirect', $state(301, null, 'https://example.com/'));
same('the redirect says where to', 'https://example.com/', StatusAddress::classify('status.example.com', 301, null, 'https://example.com/')['to']);
same('a redirect to a path on the same address too', 'redirect', $state(302, null, 'https://status.example.com/login'));
same('https on the same address is not live yet', 'other', $state(301, null, 'https://status.example.com/'));
same('the certificate does not fit (526)', 'ssl', $state(526));
same('the certificate does not fit (525)', 'ssl', $state(525));
same('Cloudflare reaches no server (522)', 'offline', $state(522));
same('a redirect with the header is still a redirect', 'redirect', $state(301, '1', 'https://example.com/'));

// The status page's own look: plain text, known values only.
$page = EmbedOptions::normalize(['page_title' => "<b>Ice</b>\nGaming", 'page_intro' => "Hi\r\nthere <script>alert(1)</script>", 'page_logo' => 'elsewhere',
    'page_logo_file' => '../../.env', 'page_style' => 'look', 'page_accent' => 'red', 'page_css' => '@import url(https://example.com/a.css); h1{color:red}']);
same('page title is plain text on one line', 'IceGaming', $page['page_title']);
same('page intro keeps its line break, not its tags', "Hi\nthere alert(1)", $page['page_intro']);
same('page title at most 80 characters', 80, mb_strlen(EmbedOptions::normalize(['page_title' => str_repeat('é', 200)])['page_title']));
same('page intro at most 300 characters', 300, mb_strlen(EmbedOptions::normalize(['page_intro' => str_repeat('a', 900)])['page_intro']));
same('unknown logo choice', 'panel', $page['page_logo']);
same('logo outside the folder', '', $page['page_logo_file']);
same('"same as Look" is stored empty', '', $page['page_style']);
same('bad page accent', '', $page['page_accent']);
same('own CSS is cleaned when saved', 'h1{color:red}', $page['page_css']);

// Own CSS: styles stay, nothing that loads a file, runs code or ends the style block.
$css = fn (string $in) => StatusCss::clean($in);
same('plain CSS stays', ['css' => 'h1 > span{color:red}', 'removed' => []], $css('h1 > span{color:red}'));
same('@import goes', ['css' => 'a{b:c}', 'removed' => ['import']], $css('@import "x.css"; a{b:c}'));
same('url() to a file is made invalid', ['css' => 'a{background:blocked()}', 'removed' => ['url']], $css('a{background:URL( https://example.com/x.png )}'));
same('a small data: image stays', [], $css('a{background:url("data:image/png;base64,iVBORw0KGgo=")}')['removed']);
same('an svg data: image does not', ['url'], $css('a{background:url(data:image/svg+xml;base64,PHN2Zz4=)}')['removed']);
same('image-set() is made invalid', ['url'], $css('a{background:image-set("x.png" 1x)}')['removed']);
same('expression() is made invalid', 'a{width:blocked(alert(1))}', $css('a{width:expression(alert(1))}')['css']);
same('javascript: and bindings go', ['url', 'script'], $css('a{-moz-binding:url(x)}b{behavior:x}')['removed']);
same('no way out of the style block', false, str_contains($css('</style><script>alert(1)</script>')['css'], '<'));
same('escapes cannot spell a blocked word', 'a{b:u72l(x)}', $css('a{b:u\\72l(x)}')['css']);
same('a comment cannot split a blocked word', 'a{b:c}', $css('@imp/**/ort x; a{b:c}')['css']);
same('at most 10 KB', StatusCss::MAX, strlen($css(str_repeat('a{b:c}', 3000))['css']));

// Pinned servers and the incident options: known values only.
same('pinned: positive ids, once, in order', [4, 2], EmbedOptions::normalize(['pinned' => ['4', 2, 4, -1, 'x', 0]])['pinned']);
same('pinned stays private', true, in_array('pinned', EmbedOptions::PRIVATE, true));
same('incident after: at least a minute', 1, EmbedOptions::normalize(['incident_after' => 0])['incident_after']);
same('incident after: at most an hour', 60, EmbedOptions::normalize(['incident_after' => 999])['incident_after']);
same('banner after: 0 is at once', 0, EmbedOptions::normalize(['banner_after' => '0'])['banner_after']);
same('banner after: not a number keeps the default', 15, EmbedOptions::normalize(['banner_after' => 'soon'])['banner_after']);
same('pinned first, then online first', ['p', 'b', 'a'], array_column(ServerList::sorted([
    ['name' => 'a', 'state' => 'offline', 'position' => 1],
    ['name' => 'b', 'state' => 'online', 'position' => 2],
    ['name' => 'p', 'state' => 'offline', 'position' => 3, 'pinned' => true],
], true), 'name'));

// Incidents: when one opens, closes and opens again.
$now = 100000;
$decide = fn (array $online, array $stretches, array $open = [], array $closed = []) => IncidentRules::decide($online, $stretches, $open, $closed, $now, 180);
same('down shorter than the wait: nothing', [], $decide([1 => false], [1 => ['online' => false, 'since' => $now - 120]]));
same('down for the wait: opens where it went down', [['do' => 'open', 'server' => 1, 'at' => $now - 180]], $decide([1 => false], [1 => ['online' => false, 'since' => $now - 180]]));
same('already open: nothing new', [], $decide([1 => false], [1 => ['online' => false, 'since' => $now - 900]], [1 => 7]));
same('back: closes where it came back', [['do' => 'close', 'server' => 1, 'incident' => 7, 'at' => $now - 60]], $decide([1 => true], [1 => ['online' => true, 'since' => $now - 60]], [1 => 7]));
same('down again soon after: the same incident reopens', [['do' => 'reopen', 'server' => 1, 'incident' => 7]],
    $decide([1 => false], [1 => ['online' => false, 'since' => $now - 200]], [], [1 => ['id' => 7, 'ended' => $now - 400]]));
same('down again much later: a new incident', [['do' => 'open', 'server' => 1, 'at' => $now - 200]],
    $decide([1 => false], [1 => ['online' => false, 'since' => $now - 200]], [], [1 => ['id' => 7, 'ended' => $now - 2000]]));
same('Wings did not answer: nothing changes', [], $decide([], [1 => ['online' => false, 'since' => $now - 900]], [1 => 7]));
same('no stretch yet: nothing changes', [], $decide([1 => false], []));
same('online without an incident: nothing', [], $decide([1 => true], [1 => ['online' => true, 'since' => $now - 900]]));
same('a schedule that ran just before is a planned stop', true, IncidentRules::scheduled([$now - 120], $now));
same('a schedule from yesterday is not', false, IncidentRules::scheduled([$now - 86400, null], $now));

// The webhook: only Discord's own addresses; saved, it shows without its token.
$hook = 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz_ABC-123';
same('a Discord webhook', true, IncidentRules::validWebhook($hook));
same('the ptb and canary hosts too', true, IncidentRules::validWebhook(str_replace('discord.com', 'ptb.discord.com', $hook)));
foreach (['http://discord.com/api/webhooks/123456/abcdefghijklmnopqrstuvwxyz', 'https://discord.com.evil.example/api/webhooks/123456/abcdefghijklmnopqrstuvwxyz',
    'https://evil.example/api/webhooks/123456/abcdefghijklmnopqrstuvwxyz', $hook.'?wait=true', $hook.'/../x', 'https://user@discord.com/api/webhooks/123456/abcdefghijklmnopqrstuvwxyz'] as $bad) {
    same("not a webhook: $bad", false, IncidentRules::validWebhook($bad));
}
same('hidden without its token', 'https://discord.com/api/webhooks/•••••••', IncidentRules::hidden($hook));
same('hidden never shows the id or token', false, str_contains(IncidentRules::hidden($hook), 'abcdefghij') || str_contains(IncidentRules::hidden($hook), '123456789'));
$message = IncidentRules::discord('IceGaming servers', '@everyone is unreachable', 'Since <t:1:f>.', 'https://status.example.com/', true);
same('Discord: nobody is mentioned', ['parse' => []], $message['allowed_mentions']);
same('Discord: red while down', 0xDA373C, $message['embeds'][0]['color']);
same('Discord: green when back', 0x23A55A, IncidentRules::discord('x', 'y', 'z', 'https://e.com/', false)['embeds'][0]['color']);

// The feed: valid XML, newest first, every text escaped.
$feed = IncidentRules::feed('A & B', 'https://status.example.com/', 'https://status.example.com/feed.xml', 'Incidents', [
    ['guid' => 'incident-1-down-10', 'title' => 'Old <b>', 'body' => 'x', 'at' => 10],
    ['guid' => 'incident-2-down-20', 'title' => 'New', 'body' => '"quoted" & more', 'at' => 20],
]);
$xml = simplexml_load_string($feed);
same('feed: valid XML', true, $xml !== false);
same('feed: newest first', 'New', $xml === false ? null : (string) $xml->channel->item[0]->title);
same('feed: text escaped', 'Old <b>', $xml === false ? null : (string) $xml->channel->item[1]->title);
same('feed: no raw markup', false, str_contains($feed, '<b>'));

// A note is plain text on one line.
same('note: tags and line breaks go', 'Out of memory; raised to 8 GB.', IncidentRules::note("<b>Out of memory;</b>\nraised to 8 GB."));
same('note: empty is none', null, IncidentRules::note('   '));
same('note: at most 300 characters', IncidentRules::NOTE, mb_strlen((string) IncidentRules::note(str_repeat('é', 400))));

// K-B101: an address without an address in public DNS said "Undefined array key 0" instead of "not in DNS yet".
$missing = null;
// As in Laravel, a warning is an error.
set_error_handler(fn (int $level, string $message) => throw new ErrorException($message, 0, $level));
try {
    StatusAddress::pinned('status.example.com', []);
} catch (Throwable $error) {
    $missing = get_class($error).($error instanceof DnsMissing ? '' : ': '.$error->getMessage());
}
restore_error_handler();
same('no address in public DNS is DnsMissing', DnsMissing::class, $missing);
same('public DNS not asked: the server\'s own lookup', [], StatusAddress::pinned('status.example.com', null));
same('IPv4 is pinned', ['status.example.com:443:203.0.113.7'], StatusAddress::pinned('status.example.com', ['203.0.113.7'])['curl'][CURLOPT_RESOLVE] ?? null);
same('a CNAME chain gives its A records', ['203.0.113.7'], StatusAddress::addresses(['Status' => 0, 'Answer' => [['type' => 5, 'data' => 'panel.example.com.'], ['type' => 1, 'data' => '203.0.113.7']]], 1, FILTER_FLAG_IPV4));
same('IPv6 only', ['2001:db8::7'], StatusAddress::addresses(['Status' => 0, 'Answer' => [['type' => 28, 'data' => '2001:db8::7']]], 28, FILTER_FLAG_IPV6));
same('no answer', [], StatusAddress::addresses(['Status' => 3], 1, FILTER_FLAG_IPV4));
same('IPv6 is pinned in brackets', ['status.example.com:443:[2001:db8::7]'], StatusAddress::pinned('status.example.com', ['2001:db8::7'])['curl'][CURLOPT_RESOLVE] ?? null);

if ($failures) {
    echo "FAIL embed:\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo "PASS embed: $checks checks\n";
