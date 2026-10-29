<?php

declare(strict_types=1);

namespace Simplify\Core\Subdomains;

use App\Models\Server;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Subdomains\Filament\AutomaticSubdomains;
use Simplify\Core\Subdomains\Models\Subdomain;

/** Subdomains in Simplify's add-on list and on server cards, like an add-on. Left out while the part is off (Parts). */
final class SubdomainsAddon extends Addon
{
    public function id(): string
    {
        return 'simplify-subdomains';
    }

    public function name(): string
    {
        return 'Subdomains';
    }

    public function description(): string
    {
        return __('pelican-simplify::subdomains.addon.description');
    }

    public function icon(): string
    {
        return 'tabler-world';
    }

    public function settingsUrl(): ?string
    {
        return user()?->isRootAdmin() ? AutomaticSubdomains::getUrl(panel: 'admin') : null;
    }

    /** @var array<string, ?string>|null active subdomain names by server uuid: one query for all tiles of a page */
    private ?array $active = null;

    public function tileBadges(Server $server): array
    {
        if (! Subdomains::ready()) {
            return [];
        }
        $this->active ??= Subdomain::query()->where('status', Subdomain::ACTIVE)->pluck('name', 'server_uuid')->all();
        $address = Subdomains::join($server, $this->active[$server->uuid] ?? null);

        return $address === null ? [] : [$address];
    }
}
