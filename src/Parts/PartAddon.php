<?php

declare(strict_types=1);

namespace Simplify\Core\Parts;

use App\Models\Server;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Subdomains\Subdomains;

/** One of Simplify's own parts in the add-on list, like an add-on. Left out while the part is off (Parts). */
final class PartAddon extends Addon
{
    public function __construct(private readonly string $part) {}

    public function id(): string
    {
        return 'simplify-'.$this->part;
    }

    public function name(): string
    {
        return __('pelican-simplify::'.Parts::PARTS[$this->part]['name']);
    }

    public function description(): string
    {
        return __('pelican-simplify::'.Parts::PARTS[$this->part]['description']);
    }

    public function icon(): string
    {
        return Parts::PARTS[$this->part]['icon'];
    }

    public function settingsUrl(): ?string
    {
        return user()?->isRootAdmin() ? Parts::PARTS[$this->part]['page']::getUrl(panel: 'admin') : null;
    }

    public function tileBadges(Server $server): array
    {
        $badge = $this->part === 'subdomains' ? Subdomains::badge($server) : null;

        return $badge === null ? [] : [$badge];
    }
}
