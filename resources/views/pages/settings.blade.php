<x-filament-panels::page>
    {{-- Nothing saves itself (P-38): a change shows "Changes made" with Save and Discard (unsaved.js). --}}
    <form wire:submit="save" class="s-settings" data-simplify-unsaved="data">
        {{ $this->form }}
        <div class="s-bar">
            <x-filament::button type="submit">{{ __('simplify::settings.save') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
