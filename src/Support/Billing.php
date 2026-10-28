<?php

declare(strict_types=1);

namespace Simplify\Core\Support;

use App\Models\Server;

/** The hoster's billing page for a server, for panels set up for a hosting company. Simplify never bills itself. */
final class Billing
{
    public static function url(Server $server): ?string
    {
        $url = PanelSettings::get('billing_url');
        if (PanelSettings::get('profile') !== 'hosting' || $url === null) {
            return null;
        }

        return strtr($url, [
            '{uuid}' => rawurlencode((string) $server->uuid),
            '{external_id}' => rawurlencode((string) $server->external_id),
        ]);
    }
}
