<x-filament-panels::page>
    {{-- A Pelican tab that is not shown is emptied, so its page stops polling and cannot take the "Settings saved" notice of this page. --}}
    <form wire:submit="save" class="s-settings"
        x-data x-on:click="$event.target.closest('[role=tab]') && setTimeout(() => $el.querySelectorAll('iframe[data-ps-frame]').forEach((f) => {
            if (f.checkVisibility()) { if (f.dataset.src) { f.src = f.dataset.src; delete f.dataset.src } }
            else if (! f.dataset.src) { f.dataset.src = f.getAttribute('src'); f.src = 'about:blank' }
        }), 50)" style="--s-group-panel: {{ json_encode(__('pelican-simplify::dashboard.ui.panel'), JSON_UNESCAPED_UNICODE) }}; --s-group-addons: {{ json_encode(__('pelican-simplify::settings.addons.heading'), JSON_UNESCAPED_UNICODE) }}">
        {{ $this->form }}
        <div class="s-bar">
            <x-filament::button type="submit">{{ __('pelican-simplify::settings.save') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
