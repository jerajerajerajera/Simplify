{{-- Every add-on as a section that opens: first what it does, then its settings. An add-on that gives its settings
     as a form (Addon::settingsForm) shows it here; an older one links to its own settings page. --}}
<div class="s-addons">
    @forelse (\Simplify\Core\Filament\Admin\Pages\Settings::addonRows() as $addon)
        {{-- wire:ignore.self keeps a section open or closed while the page updates. --}}
        <details class="s-addon" wire:ignore.self wire:key="addon-{{ $addon['id'] }}" @if (request()->query('addon') === $addon['id']) open @endif>
            <summary class="s-addon-head">
                <x-filament::icon icon="tabler-chevron-right" class="s-addon-chevron" />
                <x-filament::icon :icon="$addon['icon']" class="s-row-icon" />
                <span class="s-addon-name">{{ $addon['name'] }}</span>
                @if ($addon['form'] || $addon['url'])
                    <span class="s-addon-tag">{{ __('simplify::settings.addons.open') }}</span>
                @endif
            </summary>
            <div class="s-addon-body">
                @if ($addon['description'] !== '')
                    <p class="s-muted">{{ $addon['description'] }}</p>
                @endif
                @if ($addon['form'])
                    {{-- Enter in a field must not submit Simplify's own form around it. --}}
                    <div class="s-addon-form" x-on:keydown.enter="$event.target.matches('input') && $event.preventDefault()">
                        @livewire($addon['form'], key('addon-form-'.$addon['id']))
                    </div>
                @elseif ($addon['url'])
                    <div><a class="s-btn" href="{{ $addon['url'] }}">{{ __('simplify::settings.addons.open') }}</a></div>
                @endif
            </div>
        </details>
    @empty
        <p class="s-muted">{{ __('simplify::settings.addons.none') }}</p>
    @endforelse
</div>
