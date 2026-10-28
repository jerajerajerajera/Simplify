<?php

declare(strict_types=1);

namespace Simplify\Core\Filament\Server\Widgets;

use App\Enums\SubuserPermission;
use App\Models\Server;
use Filament\Widgets\Widget;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\AddonRegistry;
use Simplify\Core\Filament\App\Pages\Overview;
use Simplify\Core\Server\Looks;
use Simplify\Core\Support\Billing;

/**
 * A line above Pelican's console with what people look up most: the address to join, the SFTP login and what
 * add-ons know about the server. Pelican's own console, numbers and power buttons stay as they are.
 */
final class ServerDetails extends Widget
{
    use WithFileUploads;

    protected string $view = 'pelican-simplify::widgets.server-details';

    protected int|string|array $columnSpan = 'full';

    /** Cheap to render, so it comes with the page instead of popping in after it. */
    protected static bool $isLazy = false;

    public ?Server $server = null;

    public bool $customizing = false;

    public ?string $color = null;

    /** @var TemporaryUploadedFile|null a new banner while customizing */
    public $banner = null;

    public bool $removeBanner = false;

    /** @return array{color: ?string, banner: ?string} */
    public function look(): array
    {
        return $this->server === null ? ['color' => null, 'banner' => null] : Looks::for($this->server);
    }

    public function canCustomize(): bool
    {
        $user = user();

        return $this->server !== null && $user !== null && Looks::canEdit($user, $this->server);
    }

    public function customize(): void
    {
        abort_unless($this->canCustomize(), 403);
        $this->color = $this->look()['color'];
        $this->banner = null;
        $this->removeBanner = false;
        $this->customizing = true;
    }

    public function saveLook(): void
    {
        abort_unless($this->canCustomize(), 403);
        $this->validate([
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'banner' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);
        $banner = $this->removeBanner ? null : false;
        if ($this->banner instanceof TemporaryUploadedFile) {
            $extension = strtolower($this->banner->extension());
            $banner = $this->banner->storeAs(Looks::DIRECTORY, Str::random(40).'.'.($extension === 'jpeg' ? 'jpg' : $extension), 'local') ?: false;
        }
        Looks::save($this->server, $this->color, $banner);
        $this->customizing = false;
        $this->banner = null;
    }

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

    public function billingUrl(): ?string
    {
        return $this->server === null ? null : Billing::url($this->server);
    }

    public function overviewUrl(): string
    {
        return Overview::getUrl(panel: 'app');
    }
}
