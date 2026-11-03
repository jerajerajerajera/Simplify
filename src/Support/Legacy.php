<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

/**
 * The legacy panel: Pelican's own admin and account pages, as Pelican draws them, for whoever picks it in the account
 * menu. Kept for this browser session; "Back to Simplify" ends it. Everywhere else those pages open inside Simplify.
 */
final class Legacy
{
    public const KEY = 'simplify_legacy';

    public static function on(): bool
    {
        return (bool) session(self::KEY, false);
    }

    /** The link that opens a URL in the legacy panel ($on) or back in Simplify. */
    public static function url(string $url, bool $on): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').self::KEY.'='.($on ? '1' : '0');
    }
}
