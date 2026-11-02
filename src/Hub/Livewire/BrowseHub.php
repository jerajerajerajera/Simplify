<?php

declare(strict_types=1);

namespace Simplify\Core\Hub\Livewire;

use App\Models\Plugin;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Simplify\Core\Filament\Admin\Pages\Settings;
use Simplify\Core\Hub\HubCatalog;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Settings › Browse Hub: the themes, plugins and languages on the Pelican Hub, with their makers up front. A shop
 * window only: "Install on Hub" opens the item on the Hub, which installs it through its own link with this panel.
 * It loads when its tab is first shown, so a slow Hub never holds up the settings.
 */
#[Lazy]
final class BrowseHub extends Component
{
    public const SORTS = ['downloads', 'updated', 'name'];

    public string $type = 'theme';

    public string $maker = '';

    public string $search = '';

    public string $sort = 'downloads';

    /** The item open in the details popup. */
    public ?string $open = null;

    public function mount(): void
    {
        abort_unless(Settings::canAccess(), 403);
    }

    public function placeholder(): View
    {
        return view('simplify::hub.placeholder');
    }

    public function render(): View
    {
        abort_unless(Settings::canAccess(), 403);
        $catalog = HubCatalog::catalog();
        $type = in_array($this->type, HubCatalog::TYPES, true) ? $this->type : 'theme';
        $installed = array_flip(Plugin::query()->pluck('id')->all());
        $search = mb_strtolower(trim($this->search));

        $items = array_values(array_filter($catalog['items'], fn (array $item) => $item['type'] === $type
            && ($this->maker === '' || $item['author'] === $this->maker)
            && ($search === '' || str_contains(mb_strtolower($item['title'].' '.$item['author'].' '.$item['summary']), $search))));
        usort($items, match ($this->sort) {
            'updated' => fn (array $a, array $b) => strcmp((string) $b['updated'], (string) $a['updated']),
            'name' => fn (array $a, array $b) => strcasecmp($a['title'], $b['title']),
            default => fn (array $a, array $b) => $b['downloads'] <=> $a['downloads'],
        });
        $counts = array_fill_keys(HubCatalog::TYPES, 0);
        foreach ($catalog['items'] as $item) {
            $counts[$item['type']]++;
        }
        $open = $this->open ? collect($catalog['items'])->firstWhere('slug', $this->open) : null;
        // A plugin's screenshots are read when it is opened; a theme's came with the list.
        if ($open && $open['screenshots'] === [] && ($details = HubCatalog::details($open['slug']))) {
            $open = [...$open, 'screenshots' => $details['screenshots'], 'license' => $details['license']];
        }

        return view('simplify::hub.browse', [
            'items' => array_map(fn (array $item) => [...$item, 'installed' => isset($installed[$item['slug']])], $items),
            'makers' => array_slice(HubCatalog::makers($catalog['items'], $type), 0, 12),
            'counts' => $counts,
            'error' => $catalog['error'],
            'at' => $catalog['at'],
            'item' => $open ? [...$open, 'installed' => isset($installed[$open['slug']])] : null,
            'themeTools' => $type !== 'theme' || self::canBuildThemes(),
        ]);
    }

    public function updatedType(): void
    {
        $this->maker = '';
    }

    public function show(string $slug): void
    {
        $this->open = HubCatalog::validSlug($slug) ? $slug : null;
    }

    /** Reads the Hub again, at most once per five minutes however often it is pressed. */
    public function readAgain(): void
    {
        abort_unless(Settings::canAccess(), 403);
        $at = HubCatalog::catalog()['at'];
        if (is_int($at) && time() - $at < 300) {
            Notification::make()->title(__('simplify::settings.hub.up_to_date'))->info()->send();

            return;
        }
        HubCatalog::forget();
        HubCatalog::refresh();
    }

    /** Pelican rebuilds the panel's style after a theme is installed, which needs Node.js and Yarn on the server. */
    private static function canBuildThemes(): bool
    {
        $finder = new ExecutableFinder;

        return $finder->find('node') !== null && $finder->find('yarn') !== null;
    }
}
