<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Server\Widgets;

use App\Enums\SubuserPermission;
use App\Models\Server;
use Filament\Widgets\Widget;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Filament\App\Pages\Overview;

/**
 * A line above Pelican's console with what people look up most: the address to join, the SFTP login and what
 * add-ons know about the server. Pelican's own console, numbers and power buttons stay as they are.
 */
final class ServerDetails extends Widget
{
    protected string $view = 'pelican-simplify::widgets.server-details';

    protected int|string|array $columnSpan = 'full';

    /** Cheap to render, so it comes with the page instead of popping in after it. */
    protected static bool $isLazy = false;

    public ?Server $server = null;

    public function address(): ?string
    {
        $allocation = $this->server?->allocation;

        return $allocation === null ? null : ($allocation->alias ?: $allocation->ip).':'.$allocation->port;
    }

    /** @return array{url: string, username: string}|null only for people who may use SFTP on this server */
    public function sftp(): ?array
    {
        $user = user();
        if ($this->server === null || $user === null || ! $user->can(SubuserPermission::FileSftp, $this->server)) {
            return null;
        }

        return ['url' => $this->server->getSftpUrl(), 'username' => $user->username.'.'.$this->server->uuid_short];
    }

    /** @return list<string> */
    public function badges(): array
    {
        return $this->server === null ? [] : app(AddonRegistry::class)->collect(fn (Addon $addon) => $addon->tileBadges($this->server));
    }

    public function overviewUrl(): string
    {
        return Overview::getUrl(panel: 'app');
    }
}
