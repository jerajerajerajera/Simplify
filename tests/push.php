<?php

// Web Push without a panel: decrypts a message the way a browser does (RFC 8291) and checks the VAPID signature.
declare(strict_types=1);

require __DIR__.'/../src/Push/WebPush.php';

use Simplify\Core\Push\WebPush;

$failures = [];
function check(bool $ok, string $what): void
{
    global $failures;
    if (! $ok) {
        $failures[] = $what;
    }
}

// The browser's side: its own key pair and secret.
$browser = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
$browserPublic = WebPush::point($browser);
$secret = random_bytes(16);
$subscription = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'p256dh' => WebPush::b64($browserPublic), 'auth' => WebPush::b64($secret)];

check(WebPush::keysValid($subscription['p256dh'], $subscription['auth']), 'valid browser keys are accepted');
check(! WebPush::keysValid($subscription['p256dh'], WebPush::b64('short')), 'a short secret is refused');
check(WebPush::endpointAllowed('https://web.push.apple.com/QGx'), 'Apple is a push service');
check(! WebPush::endpointAllowed('https://evil.example/fcm.googleapis.com'), 'another host is refused');
check(! WebPush::endpointAllowed('https://fcm.googleapis.com.evil.example/x'), 'a look-alike host is refused');
check(! WebPush::endpointAllowed('http://fcm.googleapis.com/x'), 'plain http is refused');

$keys = WebPush::createKeys();
$message = '{"title":"Hello","body":"Ünïcode ✓"}';
$request = WebPush::request($subscription, $message, $keys, 'https://panel.example', now: 1000);

// Decrypt like the browser.
$body = $request['body'];
$salt = substr($body, 0, 16);
$senderPublic = substr($body, 21, 65);
check(unpack('N', substr($body, 16, 4))[1] === 4096 && ord($body[20]) === 65, 'the record header has size and key length');
$shared = openssl_pkey_derive(WebPush::publicKey($senderPublic), $browser, 32);
$ikm = WebPush::hkdf($secret, $shared, "WebPush: info\0".$browserPublic.$senderPublic, 32);
$plain = openssl_decrypt(
    substr($body, 86, -16), 'aes-128-gcm', WebPush::hkdf($salt, $ikm, "Content-Encoding: aes128gcm\0", 16),
    OPENSSL_RAW_DATA, WebPush::hkdf($salt, $ikm, "Content-Encoding: nonce\0", 12), substr($body, -16),
);
check($plain === $message."\x02", 'the browser can decrypt the message');

// The VAPID token is signed with the panel's key and names the push service.
preg_match('/^vapid t=([^,]+), k=(.+)$/', $request['headers']['Authorization'], $vapid);
[$head, $claims, $signature] = explode('.', $vapid[1] ?? '..');
$raw = WebPush::unb64($signature);
function derInteger(string $bytes): string
{
    $bytes = ltrim($bytes, "\0") ?: "\0";
    $bytes = (ord($bytes[0]) > 0x7F ? "\0" : '').$bytes;

    return "\x02".chr(strlen($bytes)).$bytes;
}
$sequence = derInteger(substr($raw, 0, 32)).derInteger(substr($raw, 32));
$der = "\x30".chr(strlen($sequence)).$sequence;
$public = WebPush::publicKey(WebPush::unb64($vapid[2] ?? ''));
check(openssl_verify($head.'.'.$claims, $der, $public, OPENSSL_ALGO_SHA256) === 1, 'the VAPID token is signed with the panel key');
check(json_decode(WebPush::unb64($claims), true) === ['aud' => 'https://fcm.googleapis.com', 'exp' => 1000 + 43200, 'sub' => 'https://panel.example'], 'the token names the push service');
check($request['headers']['Content-Encoding'] === 'aes128gcm' && $request['headers']['TTL'] === '86400', 'the headers are set');

try {
    WebPush::request([...$subscription, 'endpoint' => 'https://evil.example/x'], 'x', $keys, 'mailto:a@b.c');
    check(false, 'another host is never posted to');
} catch (InvalidArgumentException) {
}

if ($failures !== []) {
    echo 'FAIL push: '.implode('; ', $failures)."\n";
    exit(1);
}
echo "PASS push\n";
