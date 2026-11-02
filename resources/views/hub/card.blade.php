<article class="s-hub-card" wire:key="hub-{{ $entry['slug'] }}">
    @if ($entry['type'] === 'theme')
        <div class="s-hub-shot" style="--s-hue: {{ crc32($entry['slug']) % 360 }}">
            @if ($entry['screenshots'])
                <img src="{{ $entry['screenshots'][0] }}" alt="" loading="lazy">
            @endif
        </div>
    @endif
    @include('simplify::hub.maker', ['name' => $entry['author']])
    <div class="s-hub-title">
        <b>{{ $entry['title'] }}</b>
        @if ($entry['version'])<small>v{{ $entry['version'] }}</small>@endif
        @if ($entry['installed'])<span class="s-hub-badge">{{ __('simplify::settings.hub.installed') }}</span>@endif
    </div>
    <p class="s-hub-summary">{{ $entry['summary'] }}</p>
    <p class="s-hub-meta">
        <span><x-filament::icon icon="tabler-download" />{{ number_format($entry['downloads']) }}</span>
        @if ($entry['votes'] && $entry['rating'] !== null)
            <span><x-filament::icon icon="tabler-star" />{{ __('simplify::settings.hub.rating', ['percent' => $entry['rating'], 'votes' => $entry['votes']]) }}</span>
        @endif
        @if ($entry['updated'])<span>{{ __('simplify::settings.hub.updated', ['date' => $entry['updated']]) }}</span>@endif
    </p>
    <div class="s-hub-actions">
        <a class="s-btn s-btn-small s-hub-install" href="{{ $entry['url'] }}" target="_blank" rel="noopener">
            <x-filament::icon icon="tabler-external-link" />{{ __('simplify::settings.hub.'.($entry['installed'] ? 'view' : 'install')) }}
        </a>
        <button type="button" class="s-btn s-btn-small" wire:click="show(@js($entry['slug']))">{{ __('simplify::settings.hub.details') }}</button>
    </div>
</article>
