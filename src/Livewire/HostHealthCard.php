<?php

declare(strict_types=1);

namespace Simplify\Core\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Simplify\Core\Health\HostHealth;

/**
 * The host health card on the overview, for root admins only. It loads after the page, so a slow node never holds
 * the overview up.
 */
final class HostHealthCard extends Component
{
    /** @var array<string, mixed>|null */
    public ?array $health = null;

    public function load(): void
    {
        abort_unless((bool) user()?->isRootAdmin(), 403);
        $this->health = HostHealth::snapshot();
    }

    public function refresh(): void
    {
        abort_unless((bool) user()?->isRootAdmin(), 403);
        $this->health = HostHealth::refresh();
    }

    public function render(): View
    {
        return view('pelican-simplify::livewire.host-health');
    }
}
