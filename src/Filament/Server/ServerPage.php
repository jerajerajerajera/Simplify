<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Server;

use App\Enums\SubuserPermission;
use App\Facades\Activity;
use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Simplify\Core\Overview\Listing;
use Simplify\Core\Overview\Power;
use Simplify\Core\Overview\Shell;
use Simplify\Core\Server\Looks;

/**
 * The top of every page of a server: Simplify's header, the server card with its power buttons and the live numbers.
 * Pelican's own page (the console, files, backups) sits next to the section menu, which is Pelican's server menu.
 */
final class ServerPage extends Component
{
    use Shell;
    use WithFileUploads;

    #[Locked]
    public Server $server;

    #[Locked]
    public bool $console = false;

    /** @var TemporaryUploadedFile|null a new background, saved by saveLook() */
    public $banner = null;

    public function render(): View
    {
        return view('simplify::pages.server', ['sections' => self::sections()]);
    }

    /** @return array<string, mixed> */
    public function initial(): array
    {
        return [...$this->shell($this->user()), 'server' => Listing::one($this->user(), $this->server), 'console' => $this->console];
    }

    #[Renderless]
    public function refresh(): array
    {
        return Listing::one($this->user(), $this->server);
    }

    #[Renderless]
    public function power(string $action): void
    {
        abort_unless(in_array($action, Power::ACTIONS, true) && Power::allowed($this->user(), $this->server, $action), 403);
        Power::send($this->server, $action);
    }

    #[Renderless]
    public function saveName(string $name): array
    {
        abort_unless($this->user()->can(SubuserPermission::SettingsRename, $this->server), 403);
        $name = trim($name);
        if ($name !== '' && mb_strlen($name) <= 191 && $name !== $this->server->name) {
            Activity::event('server:settings.rename')->subject($this->server)->property(['old' => $this->server->name, 'new' => $name])->log();
            $this->server->update(['name' => $name]);
        }

        return $this->refresh();
    }

    /** The uploaded file waits for saveLook(); the page itself is drawn in the browser, so nothing re-renders. */
    public function updatedBanner(): void
    {
        $this->skipRender();
    }

    /** Colour and background: an uploaded file replaces the background, $removeBanner drops it. */
    #[Renderless]
    public function saveLook(?string $color, bool $removeBanner = false): array
    {
        abort_unless(Looks::canEdit($this->user(), $this->server), 403);
        $banner = $removeBanner ? null : false;
        if ($this->banner instanceof TemporaryUploadedFile) {
            $this->validate(['banner' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:2048']]);
            $extension = strtolower($this->banner->extension());
            $banner = $this->banner->storeAs(Looks::DIRECTORY, Str::random(40).'.'.($extension === 'jpeg' ? 'jpg' : $extension), 'local') ?: false;
            $this->banner = null;
        }
        Looks::save($this->server, $color, $banner);

        return $this->refresh();
    }

    /**
     * Pelican's server menu (and the pages add-ons add to it), for the menu next to the page.
     *
     * @return list<NavigationItem>
     */
    public static function sections(): array
    {
        $items = [];
        foreach (Filament::getCurrentPanel()?->getNavigation() ?? [] as $group) {
            foreach ($group->getItems() as $item) {
                if ($item->isVisible()) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }
}
