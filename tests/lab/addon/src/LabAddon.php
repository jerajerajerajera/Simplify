<?php

declare(strict_types=1);

namespace Simplify\Lab;

use App\Models\Server;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\Button;

/** Two badges per card (a version and a long address) and a toolbar button, as Mods, Subdomains and Plugins give them. */
final class LabAddon extends Addon
{
    public function id(): string
    {
        return 'simplify-lab';
    }

    public function name(): string
    {
        return 'Lab add-on';
    }

    public function tileBadges(Server $server): array
    {
        return ['Paper 1.21.4', str($server->name)->slug().'.example.com'];
    }

    public function dashboardActions(): array
    {
        return [new Button('Browse Hub', 'https://hub.pelican.dev')];
    }
}
