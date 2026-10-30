<x-filament-panels::page>
    <form wire:submit="save" class="s-settings" style="--s-group-panel: {{ json_encode(__('simplify::dashboard.ui.panel'), JSON_UNESCAPED_UNICODE) }}; --s-group-addons: {{ json_encode(__('simplify::settings.addons.heading'), JSON_UNESCAPED_UNICODE) }}">
        {{ $this->form }}
        <div class="s-bar">
            {{-- On a Pelican tab the same button saves Pelican's form in the frame, whose own button stays hidden. --}}
            <x-filament::button type="submit" x-on:click="
                const frame = $el.closest('form').querySelector('.fi-active .s-pelican-frame iframe');
                if (! frame) return;
                $event.preventDefault();
                const buttons = [...(frame.contentDocument?.querySelectorAll('.fi-header-actions-ctn .fi-ac > button') ?? [])];
                (buttons.find((button) => /save/i.test(button.getAttribute('wire:click') ?? '')) ?? buttons[0])?.click();
            ">{{ __('simplify::settings.save') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
