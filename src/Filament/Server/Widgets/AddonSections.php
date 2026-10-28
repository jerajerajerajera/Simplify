<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Server\Widgets;

use App\Models\Server;
use Filament\Widgets\Widget;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;

/** What add-ons show on a server page, such as Mods' plugin list, under Pelican's console. */
final class AddonSections extends Widget
{
    protected string $view = 'pelican-simplify::widgets.addon-sections';

    protected int|string|array $columnSpan = 'full';

    public ?Server $server = null;

    public static function canView(): bool
    {
        return app(AddonRegistry::class)->all() !== [];
    }

    /** @return list<class-string> */
    public function sections(): array
    {
        return $this->server === null ? [] : app(AddonRegistry::class)->collect(fn (Addon $addon) => $addon->serverSections($this->server));
    }
}
