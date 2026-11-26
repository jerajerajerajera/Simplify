<?php

// The parts of the embed that need no panel: options, public addresses, the code for a website and cleaning
// what other plugins add. Run: php tests/embed.php
declare(strict_types=1);

require __DIR__.'/../src/Server/Looks.php';
foreach (['EggLogo', 'EmbedDetails', 'EmbedOptions', 'PublicAddress', 'ServerList', 'Snippet', 'StatusAddress', 'Uptime'] as $class) {
    require __DIR__.'/../src/Embed/Services/'.$class.'.php';
}

use Simplify\Core\Embed\Services\EggLogo;
use Simplify\Core\Embed\Services\EmbedOptions;
use Simplify\Core\Embed\Services\PublicAddress;
use Simplify\Core\Embed\Services\ServerList;
use Simplify\Core\Embed\Services\Snippet;
use Simplify\Core\Embed\Services\StatusAddress;
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
    'uptime' => false, 'online_first' => true, 'status_host' => 'status.example.com', 'logos' => [3 => 'simplify/embed/logos/abc.png']],
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

if ($failures) {
    echo "FAIL embed:\n  ".implode("\n  ", $failures)."\n";
    exit(1);
}
echo "PASS embed: $checks checks\n";
