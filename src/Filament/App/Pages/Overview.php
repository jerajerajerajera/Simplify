<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\App\Pages;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Addons\Button;
use Simplify\Core\Overview\ServerQuery;
use Simplify\Core\Overview\Usage;
use Throwable;

/**
 * All servers of the user as tiles. The tiles come from the database, so the page shows at once; the live numbers
 * follow in one request after the page loaded and refresh every 15 seconds while the page is visible.
 */
final class Overview extends Page
{
    protected static ?string $slug = 'simplify';

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-layout-grid';

    protected static ?int $navigationSort = -1;

    protected string $view = 'pelican-simplify::pages.overview';

    /** @var array<int, array{state: string, cpu: ?float, memory: ?int, disk: ?int}> live numbers per server id */
    public array $usage = [];

    public static function getNavigationLabel(): string
    {
        return __('pelican-simplify::overview.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pelican-simplify::overview.title');
    }

    /** @return Collection<int, Server> */
    public function servers(): Collection
    {
        $user = user();

        return $user === null ? collect() : ServerQuery::for($user)->get();
    }

    /** @return list<Button> */
    public function actions(): array
    {
        return app(AddonRegistry::class)->collect(fn (Addon $addon) => $addon->dashboardActions());
    }

    /** @return list<string> */
    public function badges(Server $server): array
    {
        return app(AddonRegistry::class)->collect(fn (Addon $addon) => $addon->tileBadges($server));
    }

    public function serverUrl(Server $server): string
    {
        return Filament::getPanel('server')->getUrl($server);
    }

    public function refreshUsage(): void
    {
        $usage = [];
        foreach ($this->servers() as $server) {
            $usage[$server->id] = $this->read($server)->toArray();
        }
        $this->usage = $usage;
    }

    private function read(Server $server): Usage
    {
        if ($server->status !== null) {
            // Installing, suspended or restoring: Wings has nothing useful to add.
            return new Usage($server->status->value, null, null, null);
        }
        try {
            return Usage::fromWings($server->retrieveStatus()->value, $server->retrieveResources());
        } catch (Throwable) {
            return Usage::unknown();
        }
    }
}
