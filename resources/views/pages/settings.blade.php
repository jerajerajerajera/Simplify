<x-filament-panels::page>
    <form wire:submit="save" class="s-settings" style="--s-group-panel: {{ json_encode(__('pelican-simplify::dashboard.ui.panel'), JSON_UNESCAPED_UNICODE) }}; --s-group-addons: {{ json_encode(__('pelican-simplify::settings.addons.heading'), JSON_UNESCAPED_UNICODE) }}">
        {{ $this->form }}
        <div class="s-bar">
            <x-filament::button type="submit">{{ __('pelican-simplify::settings.save') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
