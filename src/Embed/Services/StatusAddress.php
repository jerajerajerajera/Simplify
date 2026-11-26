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
 * Cloudflare gives the address its certificate; otherwise the settings show the one record to add.
 */
final class StatusAddress
{
    public const HOST = '/^(?=.{4,190}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/';

    /** The header the page answers with, so the check knows it reached this panel. */
    public const HEADER = 'X-Simplify-Status';

    /** Cloudflare's comment on the record Simplify made, so it never touches a record of someone else. */
    public const COMMENT = 'pelican:simplify-status';

    private const CACHE = 'simplify-embed.status-host';

    /** The address, or '' for none; read on every request, so kept in the cache until the next save. */
    public static function host(): string
    {
        return (string) SafeCache::rememberForever(self::CACHE, fn (): string => EmbedOptions::all()['status_host']);
    }

    public static function forget(?string $host = null): void
    {
        SafeCache::forget(self::CACHE);
        if ($host !== null && $host !== '') {
            SafeCache::forget(self::CACHE.'.works.'.md5($host));
        }
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
        [$token] = SubdomainSettings::credentials();
        if ($send === null && $token === '') {
            return __('simplify::embed.status.no_cloudflare');
        }
        $send ??= CloudflareDns::http($token);
        $zone = CloudflareDns::findZone($send, $host);
        if (! $zone['ok']) {
            return $zone['message'];
        }
        $base = '/zones/'.rawurlencode((string) $zone['zone_id']).'/dns_records';
        $record = ['type' => 'CNAME', 'name' => $host, 'content' => self::panelHost(), 'proxied' => true, 'ttl' => 1, 'comment' => self::COMMENT];
        try {
            $found = $send('GET', $base.'?'.http_build_query(['name.exact' => $host, 'per_page' => 100]), null);
            $ours = null;
            foreach ((array) ($found['result'] ?? []) as $have) {
                if (($have['comment'] ?? '') !== self::COMMENT) {
                    return __('simplify::subdomains.errors.record_exists', ['name' => $host]);
                }
                $ours = $have;
            }
            $result = $ours === null
                ? $send('POST', $base, $record)
                : $send('PUT', $base.'/'.rawurlencode((string) $ours['id']), $record);

            return ($result['success'] ?? false) ? null : __('simplify::subdomains.errors.refused', ['name' => $host, 'error' => (string) ($result['errors'][0]['message'] ?? 'unknown error')]);
        } catch (Throwable $error) {
            return __('simplify::subdomains.errors.unreachable', ['error' => $error->getMessage()]);
        }
    }

    /** works(), remembered for a minute: the settings page draws again on every change. */
    public static function checked(string $host): bool
    {
        return (bool) SafeCache::remember(self::CACHE.'.works.'.md5($host), 60, fn (): bool => self::works($host));
    }

    /** True when the address may be used: never the panel's own address, which would then only show the list. */
    public static function allowed(string $host): bool
    {
        return preg_match(self::HOST, $host) === 1 && $host !== self::panelHost();
    }

    /** True when the address answers with this panel's status page. */
    public static function works(string $host): bool
    {
        try {
            return Http::timeout(5)->withoutRedirecting()->get('https://'.$host.'/')->header(self::HEADER) === '1';
        } catch (Throwable) {
            return false;
        }
    }
}
