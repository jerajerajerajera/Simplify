<x-filament-panels::page>
    <form wire:submit="finish" class="s-settings">
        <p class="s-muted">{{ __('pelican-simplify::setup.intro') }}</p>
        {{ $this->form }}
    </form>
</x-filament-panels::page>
