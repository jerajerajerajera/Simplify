{{-- Settings › Browse Hub (Hub\Livewire\BrowseHub). Only links to the Hub; nothing is downloaded here. --}}
<div class="s-hub">
    <div class="s-hub-bar">
        <h2>{{ __('simplify::settings.hub.tab') }}</h2>
        <label class="s-hub-search">
            <x-filament::icon icon="tabler-search" />
            <input type="search" wire:model.live.debounce.300ms="search" x-on:keydown.enter.prevent placeholder="{{ __('simplify::settings.hub.search') }}" aria-label="{{ __('simplify::settings.hub.search') }}">
        </label>
        <div class="s-hub-types" role="tablist">
            @foreach ($counts as $key => $count)
                <button type="button" role="tab" @class(['is-active' => $type === $key]) aria-selected="{{ $type === $key ? 'true' : 'false' }}" wire:click="$set('type', '{{ $key }}')">
                    {{ __('simplify::settings.hub.types.'.$key) }} <small>{{ $count }}</small>
                </button>
            @endforeach
        </div>
        <select wire:model.live="sort" aria-label="{{ __('simplify::settings.hub.sort') }}">
            @foreach (\Simplify\Core\Hub\Livewire\BrowseHub::SORTS as $key)
                <option value="{{ $key }}">{{ __('simplify::settings.hub.sorts.'.$key) }}</option>
            @endforeach
        </select>
        <button type="button" class="s-btn s-btn-icon" wire:click="readAgain" title="{{ __('simplify::settings.hub.read_again') }}" aria-label="{{ __('simplify::settings.hub.read_again') }}">
            <x-filament::icon icon="tabler-refresh" />
        </button>
    </div>

    <p class="s-muted s-hub-intro">{{ __('simplify::settings.hub.intro') }}</p>

    @if ($makers)
        <div class="s-hub-makers">
            @foreach ($makers as $entry)
                <button type="button" @class(['s-hub-maker', 'is-active' => $maker === $entry['name']]) wire:click="$set('maker', @js($maker === $entry['name'] ? '' : $entry['name']))">
                    <span class="s-hub-avatar" style="--s-hue: {{ crc32($entry['name']) % 360 }}">{{ mb_strtoupper(mb_substr($entry['name'], 0, 1)) }}</span>
                    <span><b>{{ $entry['name'] }}</b><small>{{ __('simplify::settings.hub.maker_line', ['count' => $entry['count'], 'downloads' => number_format($entry['downloads'])]) }}</small></span>
                </button>
            @endforeach
        </div>
    @endif

    @unless ($themeTools)
        <p class="s-hub-note">{{ __('simplify::settings.hub.theme_tools') }}</p>
    @endunless

    @if ($error)
        <p class="s-muted s-hub-empty">{{ __('simplify::settings.hub.unreachable') }}</p>
    @elseif ($items === [])
        <p class="s-muted s-hub-empty">{{ __('simplify::settings.hub.nothing') }}</p>
    @else
        <div class="s-hub-grid">
            @foreach ($items as $entry)
                @include('simplify::hub.card', ['entry' => $entry])
            @endforeach
        </div>
    @endif

    <p class="s-muted s-hub-foot">
        {{ __('simplify::settings.hub.source') }}
        @if ($at)
            · {{ __('simplify::settings.hub.read_at', ['time' => \Illuminate\Support\Carbon::createFromTimestamp($at)->diffForHumans()]) }}
        @endif
    </p>

    @if ($item)
        {{-- On the page itself, above everything (z-index 40), not clipped by the settings card. --}}
        <template x-teleport="body">
        <div class="s-hub-layer" wire:click.self="$set('open', null)" x-on:keydown.escape.window="$wire.set('open', null)" x-init="$el.querySelector('button')?.focus()">
            <div class="s-hub-dialog" role="dialog" aria-modal="true" aria-label="{{ $item['title'] }}">
                <header>
                    <h3>{{ $item['title'] }}</h3>
                    <a class="s-btn s-btn-primary" href="{{ $item['url'] }}" target="_blank" rel="noopener">
                        <x-filament::icon icon="tabler-external-link" />{{ __('simplify::settings.hub.'.($item['installed'] ? 'view' : 'install')) }}
                    </a>
                    <button type="button" class="s-btn s-btn-icon" wire:click="$set('open', null)" aria-label="{{ __('simplify::settings.hub.close') }}"><x-filament::icon icon="tabler-x" /></button>
                </header>
                <div class="s-hub-dialog-body">
                    @include('simplify::hub.maker', ['name' => $item['author']])
                    @if ($item['based_on'])
                        <p class="s-muted">{{ __('simplify::settings.hub.based_on') }} <a href="{{ $item['based_on']['url'] }}" target="_blank" rel="noopener noreferrer">{{ $item['based_on']['name'] }}</a></p>
                    @endif
                    <p>{{ $item['about'] }}</p>
                    <p class="s-hub-meta">
                        @if ($item['version'])<span>v{{ $item['version'] }}</span>@endif
                        <span>{{ __('simplify::settings.hub.downloads', ['count' => number_format($item['downloads'])]) }}</span>
                        @if ($item['updated'])<span>{{ __('simplify::settings.hub.updated', ['date' => $item['updated']]) }}</span>@endif
                        @if ($item['license'])<span>{{ $item['license'] }}</span>@endif
                    </p>
                    @foreach ($item['screenshots'] as $shot)
                        <img class="s-hub-dialog-shot" src="{{ $shot }}" alt="" loading="lazy">
                    @endforeach
                </div>
            </div>
        </div>
        </template>
    @endif
</div>
