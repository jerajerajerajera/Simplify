<x-filament-panels::page>
    <form wire:submit="save" class="s-settings">
        {{ $this->form }}
        <div class="s-bar">
            <x-filament::button type="submit">{{ __('pelican-simplify::settings.save') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
