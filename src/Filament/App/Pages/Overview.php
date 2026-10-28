<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\App\Pages;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Addons\Button;
use Simplify\Core\Overview\Power;
use Simplify\Core\Overview\ServerGroups;
use Simplify\Core\Overview\ServerQuery;
use Simplify\Core\Overview\Usage;
use Simplify\Core\Support\Preferences;
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

    public const LAYOUTS = ['cards', 'compact', 'list'];

    public const SCOPES = ['all', 'mine', 'others'];

    public const SORTS = ['name', 'created'];

    /** @var array<int, array{state: string, cpu: ?float, memory: ?int, disk: ?int}> live numbers per server id */
    public array $usage = [];

    /** cards, compact or list; not $layout, which Filament's Page already has */
    public string $display = 'cards';

    public string $scope = 'all';

    public string $sort = 'name';

    /** @var list<array{id: string, name: string, color: ?string, servers: list<int>}> */
    public array $groups = [];

    public bool $editingGroups = false;

    /** @var list<array{id: string, name: string, color: ?string}> the groups while editing */
    public array $draft = [];

    /** @var array<int|string, string> group id per server id while editing; '' is no group */
    public array $assign = [];

    /** @var Collection<int, Server>|null the servers of this request, read once */
    private ?Collection $servers = null;

    /** @var list<int>|null ids of the servers the user owns or is a subuser of */
    private ?array $mine = null;

    public function mount(): void
    {
        $user = user();
        if ($user === null) {
            return;
        }
        $this->display = Preferences::choice($user, 'overview.display', self::LAYOUTS, 'cards');
        $this->scope = Preferences::choice($user, 'overview.scope', self::SCOPES, 'all');
        $this->sort = Preferences::choice($user, 'overview.sort', self::SORTS, 'name');
        $this->groups = ServerGroups::clean(Preferences::get($user, 'overview.groups'));
    }

    /** Saves a layout choice for the account, so every device shows the same. */
    public function updated(string $property): void
    {
        $allowed = ['display' => self::LAYOUTS, 'scope' => self::SCOPES, 'sort' => self::SORTS][$property] ?? null;
        $user = user();
        if ($allowed === null || $user === null) {
            return;
        }
        if (! in_array($this->{$property}, $allowed, true)) {
            $this->{$property} = $allowed[0];
        }
        Preferences::set($user, 'overview.'.$property, $this->{$property});
    }

    public static function getNavigationLabel(): string
    {
        return __('pelican-simplify::overview.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pelican-simplify::overview.title');
    }

    /** @return Collection<int, Server> every server the user sees, before the owner filter */
    public function allServers(): Collection
    {
        $user = user();

        return $this->servers ??= $user === null ? collect() : ServerQuery::for($user)->get();
    }

    /** @return Collection<int, Server> the servers on screen: owner filter and sort applied */
    public function servers(): Collection
    {
        $servers = match ($this->scopeShown() ? $this->scope : 'all') {
            'mine' => $this->allServers()->filter(fn (Server $server) => $this->isMine($server)),
            'others' => $this->allServers()->reject(fn (Server $server) => $this->isMine($server)),
            default => $this->allServers(),
        };

        return $this->sort === 'created'
            ? $servers->sortByDesc('created_at')->values()
            : $servers->sortBy(fn (Server $server) => mb_strtolower($server->name))->values();
    }

    /** @return array{groups: list<array{group: array{id: string, name: string, color: ?string, servers: list<int>}, servers: list<Server>}>, rest: list<Server>} */
    public function sections(): array
    {
        return ServerGroups::split($this->groups, $this->servers()->all());
    }

    public function editGroups(): void
    {
        $this->draft = array_map(fn (array $group) => ['id' => $group['id'], 'name' => $group['name'], 'color' => $group['color']], $this->groups);
        $this->assign = [];
        foreach ($this->groups as $group) {
            foreach ($group['servers'] as $id) {
                $this->assign[$id] = $group['id'];
            }
        }
        $this->editingGroups = true;
    }

    public function addGroup(): void
    {
        if (count($this->draft) < ServerGroups::LIMIT) {
            $this->draft[] = ['id' => Str::random(12), 'name' => '', 'color' => null];
        }
    }

    public function removeGroup(int $index): void
    {
        unset($this->draft[$index]);
        $this->draft = array_values($this->draft);
    }

    public function moveGroup(int $index, int $step): void
    {
        $target = $index + $step;
        if (isset($this->draft[$index], $this->draft[$target])) {
            [$this->draft[$index], $this->draft[$target]] = [$this->draft[$target], $this->draft[$index]];
        }
    }

    public function saveGroups(): void
    {
        $user = user();
        if ($user === null) {
            return;
        }
        $visible = $this->allServers()->pluck('id')->all();
        $groups = array_map(function (array $group) use ($visible) {
            $servers = array_keys(array_filter($this->assign, fn ($id) => $id === $group['id']));

            return [...$group, 'servers' => array_values(array_intersect(array_map('intval', $servers), $visible))];
        }, $this->draft);
        // Servers this user cannot see now keep their group, so a group is not emptied by a filter or lost access.
        foreach ($this->groups as $old) {
            foreach ($groups as &$group) {
                if ($group['id'] === $old['id']) {
                    $group['servers'] = [...$group['servers'], ...array_diff($old['servers'], $visible)];
                }
            }
            unset($group);
        }
        $this->groups = ServerGroups::clean($groups);
        Preferences::set($user, 'overview.groups', $this->groups);
        $this->editingGroups = false;
    }

    /** The owner filter only shows when the list holds both own servers and servers of others (admins). */
    public function scopeShown(): bool
    {
        $mine = $this->allServers()->filter(fn (Server $server) => $this->isMine($server))->count();

        return $mine > 0 && $mine < $this->allServers()->count();
    }

    private function isMine(Server $server): bool
    {
        $this->mine ??= user()?->directAccessibleServers()->pluck('servers.id')->all() ?? [];

        return in_array($server->id, $this->mine, true);
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

    /** @return list<string> the power buttons this user may use on this tile right now */
    public function powerActions(Server $server): array
    {
        $user = user();
        $state = $this->usage[$server->id]['state'] ?? null;

        return $user === null ? [] : array_values(array_filter(
            Power::actionsFor($state),
            fn (string $action) => Power::allowed($user, $server, $action),
        ));
    }

    public function power(int $serverId, string $action): void
    {
        $user = user();
        $server = $user === null ? null : ServerQuery::for($user)->find($serverId);
        if ($server === null || ! in_array($action, Power::ACTIONS, true) || ! Power::allowed($user, $server, $action)) {
            Notification::make()->danger()->title(__('pelican-simplify::overview.power.denied'))->send();

            return;
        }
        try {
            Power::send($server, $action);
        } catch (Throwable $error) {
            report($error);
            Notification::make()->danger()->title(__('pelican-simplify::overview.power.failed', ['server' => $server->name]))->send();

            return;
        }
        $this->usage[$server->id] = $this->read($server)->toArray();
    }

    public function refreshUsage(): void
    {
        $usage = [];
        foreach ($this->allServers() as $server) {
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
