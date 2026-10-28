<div class="s-card">
    <p class="s-muted s-setup-note">{{ __('pelican-simplify::setup.addons_intro') }}</p>
    @foreach ($this->catalog() as $addon)
        <div class="s-row" wire:key="catalog-{{ $addon['id'] }}">
            <x-filament::icon :icon="$addon['icon']" class="s-row-icon" />
            <div class="s-row-text">
                <div>{{ $addon['name'] }}</div>
                <div class="s-muted">{{ $addon['description'] }}</div>
            </div>
            @if ($addon['installed'])
                <span class="s-badge s-badge-ok">{{ __('pelican-simplify::setup.installed') }}</span>
            @else
                <a class="s-btn s-btn-small" href="{{ $addon['url'] }}" target="_blank" rel="noopener">
                    {{ __('pelican-simplify::setup.hub') }} <x-filament::icon icon="tabler-external-link" />
                </a>
            @endif
        </div>
    @endforeach
</div>
