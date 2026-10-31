<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Server;

use App\Enums\SubuserPermission;
use App\Facades\Activity;
use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Servers\ReinstallServerService;
use App\Services\Servers\ServerDeletionService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Overview\Listing;
use Simplify\Core\Overview\Power;
use Simplify\Core\Overview\Shell;
use Simplify\Core\Server\Installation;
use Simplify\Core\Server\Looks;
use Simplify\Core\Server\ServerMenu;
use Simplify\Core\Subdomains\Services\Games;
use Throwable;

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
        return view('simplify::pages.server', ['menu' => ServerMenu::build()]);
    }

    /** @return array<string, mixed> */
    public function initial(): array
    {
        return [...$this->shell($this->user()), 'server' => Listing::one($this->user(), $this->server), 'console' => $this->console, 'colors' => Looks::COLORS];
    }

    #[Renderless]
    public function refresh(): array
    {
        return Listing::one($this->user(), $this->server);
    }

    /**
     * The last console lines from Wings, for the strip under the server card on pages other than the console.
     *
     * @return list<string>
     */
    #[Renderless]
    public function logLines(): array
    {
        abort_unless($this->user()->can(SubuserPermission::ControlConsole, $this->server), 403);
        try {
            $lines = app(DaemonServerRepository::class)->setServer($this->server)->getHttpClient()
                ->timeout(5)->get('/api/servers/'.$this->server->uuid.'/logs', ['size' => 6])->throw()->json('data');
        } catch (Throwable) {
            return [];
        }

        // Without colour codes: the strip is plain text.
        return array_values(array_map(fn (string $line) => (string) preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', $line), array_filter((array) $lines, 'is_string')));
    }

    #[Renderless]
    public function power(string $action): void
    {
        abort_unless(in_array($action, Power::ACTIONS, true) && Power::allowed($this->user(), $this->server, $action), 403);
        Power::send($this->server, $action);
    }

    /** Try again after a failed installation: Pelican runs the install script again. */
    #[Renderless]
    public function reinstall(): array
    {
        abort_unless($this->user()->can(SubuserPermission::SettingsReinstall, $this->server), 403);
        abort_unless(in_array($this->server->status?->value, Installation::FAILED, true), 409);
        app(ReinstallServerService::class)->handle($this->server);

        return Listing::one($this->user(), $this->server->refresh());
    }

    /**
     * Whether a Minecraft Java server still waits for its EULA (as in 0.7.0): only for people who may accept it, on an
     * installed server, and quietly false when the node cannot be reached.
     */
    #[Renderless]
    public function eulaNeeded(): bool
    {
        $egg = $this->server->egg;
        $java = in_array('eula', (array) ($egg?->features ?? []), true)
            || (Games::minecraft($this->server) && preg_match('/velocity|bungee|waterfall/i', (string) $egg?->name) !== 1);
        if (! $java || ! $this->canAcceptEula() || $this->server->status !== null) {
            return false;
        }
        try {
            $content = app(DaemonFileRepository::class)->setServer($this->server)->getContent('eula.txt', 65536);
        } catch (FileNotFoundException) {
            return true;
        } catch (Throwable) {
            return false;
        }

        return preg_match('/^\s*eula\s*=\s*true\s*$/mi', $content) !== 1;
    }

    /** Accept and start: writes eula.txt and starts the server. */
    #[Renderless]
    public function acceptEula(): array
    {
        abort_unless($this->canAcceptEula(), 403);
        abort_if($this->server->isInConflictState(), 409);
        app(DaemonFileRepository::class)->setServer($this->server)
            ->putContent('eula.txt', '# Accepted in Simplify by '.$this->user()->username.".\neula=true\n")->throw();
        Power::send($this->server, 'start');

        return $this->refresh();
    }

    private function canAcceptEula(): bool
    {
        return $this->user()->can(SubuserPermission::FileUpdate, $this->server)
            && $this->user()->can(SubuserPermission::ControlStart, $this->server);
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

    /** The owner (or a root admin) deletes a stopped server after typing its exact name, as in Pelican's admin. */
    public function deleteServer(string $confirmation): void
    {
        $user = $this->user();
        abort_unless($user->isRootAdmin() || $this->server->owner_id === $user->id, 403);
        abort_unless(hash_equals($this->server->name, $confirmation), 422);
        // A stopped server, and one that cannot run: Wings does not know it, or its install failed.
        abort_unless(in_array(Listing::one($user, $this->server)['status'], ['offline', 'missing', 'install failed', 'suspended'], true), 409);
        app(ServerDeletionService::class)->handle($this->server);
        $this->redirect(Overview::getUrl(panel: 'app'));
    }

    /**
     * The backgrounds to pick from: Simplify's scenes and the uploads on servers this person may change.
     *
     * @return list<array{id: string, url: string, upload: bool}>
     */
    #[Renderless]
    public function banners(): array
    {
        abort_unless(Looks::canEdit($this->user(), $this->server), 403);

        return Looks::choices($this->user());
    }

    /** A picked background: 'auto', a scene, or an upload that is already on another server of theirs. */
    #[Renderless]
    public function chooseBanner(string $id): array
    {
        abort_unless(Looks::canEdit($this->user(), $this->server), 403);
        $banner = Looks::stored($this->user(), $id);
        abort_if($banner === false, 422);
        Looks::save($this->server, Looks::for($this->server)['color'], $banner);

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
}
