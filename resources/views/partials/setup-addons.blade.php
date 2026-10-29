@php($catalog = $this->catalog())
<div class="s-setup-addons">
    <h3 class="s-setup-addons-title">{{ __('pelican-simplify::setup.addons_title') }}</h3>
    <p class="s-muted">{{ __('pelican-simplify::setup.addons_intro') }}</p>
    <div class="s-setup-addons-count">
        <span>{{ __('pelican-simplify::setup.addons_count', ['installed' => count(array_filter(array_column($catalog, 'installed'))), 'total' => count($catalog)]) }}</span>
        <a class="s-btn" href="{{ \Simplify\Core\Filament\Admin\Pages\Setup::getUrl(panel: 'admin') }}">
            <x-filament::icon icon="tabler-refresh" /> {{ __('pelican-simplify::setup.check_again') }}
        </a>
    </div>
    <div class="s-addon-cards">
        @foreach ($catalog as $addon)
            <article class="s-addon-card" style="--s-tone: {{ $addon['tone'] }}" wire:key="catalog-{{ $addon['id'] }}">
                <div class="s-addon-card-banner">
                    <span class="s-addon-card-icon"><x-filament::icon :icon="$addon['icon']" /></span>
                    <span class="s-addon-card-name"><small>Simplify</small>{{ $addon['name'] }}</span>
                    @unless ($addon['installed'])
                        <span class="s-addon-card-tag">{{ __('pelican-simplify::setup.recommended') }}</span>
                    @endunless
                </div>
                <div class="s-addon-card-body">
                    <p class="s-muted">{{ $addon['description'] }}</p>
                    <div class="s-addon-card-foot">
                        @if ($addon['installed'])
                            <span class="s-badge s-badge-ok"><x-filament::icon icon="tabler-check" /> {{ __('pelican-simplify::setup.installed') }}</span>
                            <span class="s-muted">{{ __('pelican-simplify::setup.set_up_later') }}</span>
                        @else
                            <span class="s-muted">{{ __('pelican-simplify::setup.free_on_hub') }}</span>
                            <a class="s-btn s-btn-primary" href="{{ $addon['url'] }}" target="_blank" rel="noopener">
                                {{ __('pelican-simplify::setup.hub') }} <x-filament::icon icon="tabler-external-link" />
                            </a>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</div>
