@props(['server', 'usage', 'url', 'badges', 'power', 'color' => null, 'owner' => null])
@php
    use Simplify\Core\Overview\Format;

    $state = $usage['state'] ?? null;
    $tone = match ($state) {
        'running' => 'ok',
        'starting', 'stopping', 'restarting', 'installing' => 'warn',
        null, 'offline' => null,
        default => 'danger',
    };
    $address = $server->allocation ? ($server->allocation->alias ?: $server->allocation->ip).':'.$server->allocation->port : null;
@endphp
<div
    class="s-card s-tile"
    @if ($color) style="--s-tile-accent: {{ $color }}" @endif
    wire:key="server-{{ $server->id }}"
    x-bind:hidden="search !== '' && ! @js(mb_strtolower($server->name)).includes(search.toLowerCase())"
>
    <div class="s-tile-head">
        <a class="s-tile-name s-tile-link" href="{{ $url }}">{{ $server->name }}</a>
        <span @class(['s-badge', 's-badge-'.$tone => $tone])>
            {{ $state === null ? __('pelican-simplify::overview.loading') : __('pelican-simplify::overview.state.'.$state) }}
        </span>
    </div>
    <div class="s-muted s-tile-sub">
        @if ($owner){{ $owner }} · @endif{{ $server->egg?->name }}@if ($address) · {{ $address }}@endif
    </div>
    <dl class="s-stats">
        <div>
            <dt>{{ __('pelican-simplify::overview.cpu') }}</dt>
            <dd>{{ Format::percent($usage['cpu'] ?? null) }}</dd>
        </div>
        <div>
            <dt>{{ __('pelican-simplify::overview.memory') }}</dt>
            <dd>{{ Format::bytes($usage['memory'] ?? null) }} <span class="s-muted">/ {{ Format::limit((int) $server->memory) }}</span></dd>
        </div>
        <div>
            <dt>{{ __('pelican-simplify::overview.disk') }}</dt>
            <dd>{{ Format::bytes($usage['disk'] ?? null) }} <span class="s-muted">/ {{ Format::limit((int) $server->disk) }}</span></dd>
        </div>
    </dl>
    @if ($badges || $power)
        <div class="s-tile-foot">
            @foreach ($badges as $badge)
                <span class="s-badge">{{ $badge }}</span>
            @endforeach
            @if ($power)
                <div class="s-tile-power">
                    @foreach ($power as $action)
                        <button
                            type="button"
                            class="s-btn s-btn-icon"
                            wire:click="power({{ $server->id }}, '{{ $action }}')"
                            wire:loading.attr="disabled"
                            title="{{ __('pelican-simplify::overview.power.'.$action) }}"
                            aria-label="{{ __('pelican-simplify::overview.power.'.$action) }}"
                        >
                            <x-filament::icon :icon="['start' => 'tabler-player-play', 'stop' => 'tabler-player-stop', 'restart' => 'tabler-reload', 'kill' => 'tabler-skull'][$action]" />
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
