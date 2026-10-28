<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

use App\Models\User;

/** The hoster's own links for customers, on panels set up for a hosting company. Simplify never sells or bills itself. */
final class Hosting
{
    public static function active(): bool
    {
        return PanelSettings::get('profile') === 'hosting';
    }

    public static function clientArea(): ?string
    {
        return self::active() ? PanelSettings::get('client_area_url') : null;
    }

    public static function order(): ?string
    {
        return self::active() ? PanelSettings::get('order_url') : null;
    }

    /** Customers get a greeting with their links; the people who run the panel do not. */
    public static function greets(?User $user): bool
    {
        return self::active() && $user !== null && ! $user->isRootAdmin();
    }
}
