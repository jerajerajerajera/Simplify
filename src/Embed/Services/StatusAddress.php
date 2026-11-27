<?php

declare(strict_types=1);

namespace Simplify\Core\Embed\Services;

use Closure;
use Illuminate\Support\Facades\Http;
use Simplify\Core\Subdomains\Services\CloudflareDns;
use Simplify\Core\Subdomains\Services\Settings as SubdomainSettings;
use Simplify\Core\Support\SafeCache;
use Throwable;

/**
 * The status page on an address of its own, such as status.example.com: that host shows the website list as a page
 * (StatusHost middleware). With Cloudflare set up under Subdomains, Simplify makes the DNS record itself, proxied so
 * Cloudflare gives the address its certificate, sets SSL "Full" for that address when the server's certificate does
 * not cover it, and removes both again when the address changes. Every save checks the address as a visitor reaches
 * it. (Cloudflare rewrites the Host header only on Enterprise plans, so the panel's web server must still answer for
 * the address; on a usual Pelican install it does.)
 */
final class StatusAddress
{
    public const HOST = '/^(?=.{4,190}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/';

    /** The header the page answers with, so the check knows it reached this panel. */
    public const HEADER = 'X-Simplify-Status';

    /** Cloudflare's comment on the record Simplify made, so it never touches a record of someone else. */
    public const COMMENT = 'pelican:simplify-status';

    private const CACHE = 'simplify-embed.status-host';

    /** Cloudflare's configuration rules of a zone, where the SSL rule for the status address goes. */
    private const PHASE = '/rulesets/phases/http_config_settings/entrypoint';

    /** The ref of Simplify's rule in that ruleset, so it never touches a rule of someone else. */
    public const RULE = 'simplify_status_ssl';

    /** The token permissions, as Cloudflare's token page names them. */
    public const PERMISSION_DNS = 'Zone › DNS › Edit';

    public const PERMISSION_RULES = 'Zone › Config Rules › Edit';

    /** The address, or '' for none; read on every request, so kept in the cache until the next save. */
    public static function host(): string
    {
        return (string) SafeCache::rememberForever(self::CACHE, fn (): string => EmbedOptions::all()['status_host']);
    }

    public static function forget(?string $host = null): void
    {
        SafeCache::forget(self::CACHE);
        if ($host !== null && $host !== '') {
            SafeCache::forget(self::CACHE.'.state.'.md5($host));
        }
    }

    /**
     * The status page's address for links in alerts and the feed: its own address once it works (as the last check
     * saw it), else /status on the panel. $path is a file next to it, such as feed.xml.
     */
    public static function pageUrl(string $path = ''): string
    {
        $host = self::host();
        $saved = $host === '' ? null : SafeCache::get(self::CACHE.'.state.'.md5($host));
        if (is_array($saved) && ($saved['state'] ?? null) === 'live') {
            return 'https://'.$host.'/'.$path;
        }

        return url('/status'.($path === '' ? '' : '/'.$path));
    }

    /** The panel's own host, which the record points to. */
    public static function panelHost(): string
    {
        return strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    /** The record to add by hand, as a DNS provider shows it. */
    public static function record(string $host): string
    {
        return $host.'  CNAME  '.self::panelHost();
    }

    /** The certificate name that covers $host and its neighbours: status.example.com gives *.example.com. */
    public static function wildcard(string $host): string
    {
        $dot = strpos($host, '.');

        return $dot === false ? $host : '*'.substr($host, $dot);
    }

    /** True when Cloudflare is set up under Subdomains, so Simplify can make the record. */
    public static function cloudflare(): bool
    {
        [$token] = SubdomainSettings::credentials();

        return $token !== '';
    }

    /**
     * Makes or updates the Cloudflare record for $host. Returns null when it is right, or why not.
     *
     * @param  (Closure(string, string, ?array<string, mixed>): array<string, mixed>)|null  $send  for tests
     */
    public static function connect(string $host, ?Closure $send = null): ?string
    {
        $problem = self::putRecord($host, $send);

        return $problem === null ? null : self::message($problem);
    }

    /**
     * Sets the status address up after a save. With Cloudflare: removes what Simplify made for the old address, makes
     * the proxied record for the new one, checks it the way a visitor reaches it, and when Cloudflare cannot check the
     * server's certificate for it (525/526) sets SSL "Full" for that one address and checks again. Without Cloudflare
     * it only checks. The result is remembered for the settings page.
     *
     * @param  (Closure(string, string, ?array<string, mixed>): array<string, mixed>)|null  $send  Cloudflare, for tests
     * @param  (Closure(string): array{status: int, header: ?string, location: ?string})|null  $probe  for tests
     * @return array{state: string, code: int, to: string, error: string}
     */
    public static function apply(string $old, string $new, ?Closure $send = null, ?Closure $probe = null): array
    {
        self::forget($old);
        self::forget($new);
        $send ??= self::cloudflare() ? CloudflareDns::http(SubdomainSettings::credentials()[0]) : null;
        if ($send !== null && $old !== '' && $old !== $new) {
            self::remove($old, $send);
        }
        if ($new === '') {
            return self::result('none');
        }
        if ($send !== null && ($problem = self::putRecord($new, $send)) !== null) {
            return self::remember($new, $problem);
        }
        $state = self::check($new, $probe);
        if ($send !== null && $state['state'] === 'ssl') {
            if (($problem = self::sslRule($new, $send)) !== null) {
                return self::remember($new, $problem);
            }
            // Cloudflare takes a moment to use a new rule.
            if ($probe === null) {
                usleep(2_000_000);
            }
            $state = self::check($new, $probe);
        }

        return self::remember($new, $state);
    }

    /**
     * Removes what Simplify made in Cloudflare for $host: its record and its SSL rule, never anything else.
     *
     * @param  Closure(string, string, ?array<string, mixed>): array<string, mixed>  $send
     */
    public static function remove(string $host, Closure $send): void
    {
        try {
            $zone = CloudflareDns::findZone($send, $host);
            if (! $zone['ok']) {
                return;
            }
            $base = '/zones/'.rawurlencode((string) $zone['zone_id']);
            $found = $send('GET', $base.'/dns_records?'.http_build_query(['name.exact' => $host, 'per_page' => 100]), null);
            foreach ((array) ($found['result'] ?? []) as $have) {
                if (($have['comment'] ?? '') === self::COMMENT && isset($have['id'])) {
                    $send('DELETE', $base.'/dns_records/'.rawurlencode((string) $have['id']), null);
                }
            }
            [$ruleset, $rule] = self::ourRule($base, $send);
            if ($ruleset !== null && $rule !== null) {
                $send('DELETE', $base.'/rulesets/'.rawurlencode($ruleset).'/rules/'.rawurlencode($rule), null);
            }
        } catch (Throwable) {
            // Cloudflare unreachable: the old record stays, which shows the list and harms nothing.
        }
    }

    /**
     * How the address answers a visitor: live (this panel's status page), dns (not found), redirect (to another
     * address), ssl (Cloudflare cannot check the server's certificate), offline (Cloudflare reaches no web server),
     * other (another page or site) or unreachable.
     *
     * @param  (Closure(string): array{status: int, header: ?string, location: ?string})|null  $probe  for tests
     * @return array{state: string, code: int, to: string, error: string}
     */
    public static function check(string $host, ?Closure $probe = null): array
    {
        try {
            $answer = ($probe ?? self::probe(...))($host);
        } catch (DnsMissing) {
            return self::result('dns');
        } catch (Throwable $error) {
            return self::result('unreachable', error: mb_substr($error->getMessage(), 0, 200));
        }

        return self::classify($host, $answer['status'], $answer['header'], $answer['location']);
    }

    /** @return array{state: string, code: int, to: string, error: string} */
    public static function classify(string $host, int $status, ?string $header, ?string $location): array
    {
        if ($header === '1' && $status === 200) {
            return self::result('live', $status);
        }
        if ($status >= 300 && $status < 400 && is_string($location) && $location !== '') {
            $to = strtolower((string) parse_url($location, PHP_URL_HOST));
            $path = (string) (parse_url($location, PHP_URL_PATH) ?? '/');
            // http → https on the same address is fine on its own, but the page must answer on https.
            if ($to === '' || ($to === $host && in_array($path, ['', '/'], true))) {
                return self::result('other', $status);
            }

            return self::result('redirect', $status, mb_substr($location, 0, 300));
        }

        return match (true) {
            in_array($status, [525, 526], true) => self::result('ssl', $status),
            in_array($status, [520, 521, 522, 523, 524, 530], true) => self::result('offline', $status),
            default => self::result('other', $status),
        };
    }

    /**
     * The state the settings page shows: the one remembered at the last save, else a fresh check, kept a minute (the
     * page draws again on every change).
     *
     * @return array{state: string, code: int, to: string, error: string}
     */
    public static function state(string $host): array
    {
        $saved = SafeCache::get(self::CACHE.'.state.'.md5($host));
        if (is_array($saved) && isset($saved['state'])) {
            return $saved;
        }

        return self::remember($host, self::check($host), 60);
    }

    /** True when the address may be used: never the panel's own address, which would then only show the list. */
    public static function allowed(string $host): bool
    {
        return preg_match(self::HOST, $host) === 1 && $host !== self::panelHost();
    }

    /**
     * Asks https://$host/ as a visitor does: the address found through public DNS (Cloudflare's 1.1.1.1), so the
     * panel's own DNS or hosts file cannot make it look right (K-B99), and without following a redirect.
     *
     * @return array{status: int, header: ?string, location: ?string}
     */
    private static function probe(string $host): array
    {
        $ips = self::publicAddresses($host);
        $options = $ips === null ? [] : ['curl' => [CURLOPT_RESOLVE => [$host.':443:'.$ips[0]]]];
        if ($ips === []) {
            throw new DnsMissing;
        }
        $response = Http::timeout(8)->withoutRedirecting()->withOptions($options)->get('https://'.$host.'/');

        return ['status' => $response->status(), 'header' => $response->header(self::HEADER) ?: null, 'location' => $response->header('Location') ?: null];
    }

    /**
     * The IPv4 addresses of $host in public DNS; [] when it has none, null when public DNS could not be asked (then
     * the server's own lookup is used).
     *
     * @return list<string>|null
     */
    private static function publicAddresses(string $host): ?array
    {
        try {
            $answer = Http::timeout(4)->accept('application/dns-json')
                ->get('https://cloudflare-dns.com/dns-query', ['name' => $host, 'type' => 'A'])->json();
        } catch (Throwable) {
            return null;
        }
        if (! is_array($answer) || ! isset($answer['Status'])) {
            return null;
        }
        $ips = [];
        foreach ((array) ($answer['Answer'] ?? []) as $record) {
            if ((int) ($record['type'] ?? 0) === 1 && filter_var($record['data'] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ips[] = (string) $record['data'];
            }
        }

        return $ips;
    }

    /**
     * @param  (Closure(string, string, ?array<string, mixed>): array<string, mixed>)|null  $send
     * @return array{state: string, code: int, to: string, error: string}|null
     */
    private static function putRecord(string $host, ?Closure $send): ?array
    {
        [$token] = SubdomainSettings::credentials();
        if ($send === null && $token === '') {
            return self::result('cloudflare', error: __('simplify::embed.status.no_cloudflare'));
        }
        $send ??= CloudflareDns::http($token);
        $zone = CloudflareDns::findZone($send, $host);
        if (! $zone['ok']) {
            return self::result('cloudflare', error: $zone['message']);
        }
        $base = '/zones/'.rawurlencode((string) $zone['zone_id']).'/dns_records';
        $record = ['type' => 'CNAME', 'name' => $host, 'content' => self::panelHost(), 'proxied' => true, 'ttl' => 1, 'comment' => self::COMMENT];
        try {
            $found = $send('GET', $base.'?'.http_build_query(['name.exact' => $host, 'per_page' => 100]), null);
            if (self::denied($found)) {
                return self::result('permission', error: self::PERMISSION_DNS);
            }
            $ours = null;
            foreach ((array) ($found['result'] ?? []) as $have) {
                if (($have['comment'] ?? '') !== self::COMMENT) {
                    return self::result('cloudflare', error: __('simplify::subdomains.errors.record_exists', ['name' => $host]));
                }
                $ours = $have;
            }
            $result = $ours === null
                ? $send('POST', $base, $record)
                : $send('PUT', $base.'/'.rawurlencode((string) $ours['id']), $record);
            if (self::denied($result)) {
                return self::result('permission', error: self::PERMISSION_DNS);
            }

            return ($result['success'] ?? false) ? null : self::result('cloudflare', error: __('simplify::subdomains.errors.refused', ['name' => $host, 'error' => (string) ($result['errors'][0]['message'] ?? 'unknown error')]));
        } catch (Throwable $error) {
            return self::result('cloudflare', error: __('simplify::subdomains.errors.unreachable', ['error' => $error->getMessage()]));
        }
    }

    /**
     * One Configuration Rule for $host only: SSL "Full", so Cloudflare accepts the server's certificate although it
     * is made for the panel's own address. Other addresses keep their SSL setting.
     *
     * @param  Closure(string, string, ?array<string, mixed>): array<string, mixed>  $send
     * @return array{state: string, code: int, to: string, error: string}|null
     */
    private static function sslRule(string $host, Closure $send): ?array
    {
        try {
            $zone = CloudflareDns::findZone($send, $host);
            if (! $zone['ok']) {
                return self::result('cloudflare', error: $zone['message']);
            }
            $base = '/zones/'.rawurlencode((string) $zone['zone_id']);
            $entry = $send('GET', $base.self::PHASE, null);
            if (self::denied($entry)) {
                return self::result('permission', error: self::PERMISSION_RULES);
            }
            $rule = [
                'ref' => self::RULE,
                'description' => 'Simplify: status page '.$host.' (SSL Full)',
                'expression' => '(http.host eq "'.$host.'")',
                'action' => 'set_config',
                'action_parameters' => ['ssl' => 'full'],
                'enabled' => true,
            ];
            [$ruleset, $existing] = self::ourRule($base, $send, $entry);
            $result = match (true) {
                $ruleset === null => $send('PUT', $base.self::PHASE, ['rules' => [$rule]]),
                $existing === null => $send('POST', $base.'/rulesets/'.rawurlencode($ruleset).'/rules', $rule),
                default => $send('PATCH', $base.'/rulesets/'.rawurlencode($ruleset).'/rules/'.rawurlencode($existing), $rule),
            };
            if (self::denied($result)) {
                return self::result('permission', error: self::PERMISSION_RULES);
            }

            return ($result['success'] ?? false) ? null : self::result('cloudflare', error: (string) ($result['errors'][0]['message'] ?? 'unknown error'));
        } catch (Throwable $error) {
            return self::result('cloudflare', error: __('simplify::subdomains.errors.unreachable', ['error' => $error->getMessage()]));
        }
    }

    /**
     * The zone's configuration ruleset and Simplify's rule in it, by their ids (null when there is none).
     *
     * @param  Closure(string, string, ?array<string, mixed>): array<string, mixed>  $send
     * @param  array<string, mixed>|null  $entry  the entry point, when already asked
     * @return array{0: ?string, 1: ?string}
     */
    private static function ourRule(string $base, Closure $send, ?array $entry = null): array
    {
        $entry ??= $send('GET', $base.self::PHASE, null);
        $ruleset = $entry['result']['id'] ?? null;
        if (! ($entry['success'] ?? false) || ! is_string($ruleset)) {
            return [null, null];
        }
        foreach ((array) ($entry['result']['rules'] ?? []) as $rule) {
            if (($rule['ref'] ?? '') === self::RULE && is_string($rule['id'] ?? null)) {
                return [$ruleset, $rule['id']];
            }
        }

        return [$ruleset, null];
    }

    /** @param  array<string, mixed>  $result */
    private static function denied(array $result): bool
    {
        foreach ((array) ($result['errors'] ?? []) as $error) {
            if (in_array((int) ($error['code'] ?? 0), [9109, 10000, 10001], true) || stripos((string) ($error['message'] ?? ''), 'authentication error') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{state: string, code: int, to: string, error: string}  $state
     * @return array{state: string, code: int, to: string, error: string}
     */
    private static function remember(string $host, array $state, int $seconds = 600): array
    {
        SafeCache::put(self::CACHE.'.state.'.md5($host), $state, $seconds);

        return $state;
    }

    /** @return array{state: string, code: int, to: string, error: string} */
    private static function result(string $state, int $code = 0, string $to = '', string $error = ''): array
    {
        return ['state' => $state, 'code' => $code, 'to' => $to, 'error' => $error];
    }

    /**
     * The state in plain words, as the settings page and the notification after Save show it.
     *
     * @param  array{state: string, code: int, to: string, error: string}  $state
     */
    public static function message(array $state, string $host = ''): string
    {
        return __('simplify::embed.settings.state_'.$state['state'].'_help', [
            'host' => $host,
            'url' => 'https://'.$host,
            'to' => $state['to'],
            'code' => (string) $state['code'],
            'error' => $state['error'],
            'permission' => $state['error'],
        ]);
    }
}
