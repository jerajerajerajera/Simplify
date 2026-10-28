@props(['server', 'usage', 'url', 'badges'])
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
<a
    href="{{ $url }}"
    class="s-card s-tile"
    wire:key="server-{{ $server->id }}"
    x-bind:hidden="search !== '' && ! @js(mb_strtolower($server->name)).includes(search.toLowerCase())"
>
    <div class="s-tile-head">
        <span class="s-tile-name">{{ $server->name }}</span>
        <span @class(['s-badge', 's-badge-'.$tone => $tone])>
            {{ $state === null ? __('pelican-simplify::overview.loading') : __('pelican-simplify::overview.state.'.$state) }}
        </span>
    </div>
    <div class="s-muted s-tile-sub">
        {{ $server->egg?->name }}@if ($address) · {{ $address }}@endif
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
    @if ($badges)
        <div class="s-tile-badges">
            @foreach ($badges as $badge)
                <span class="s-badge">{{ $badge }}</span>
            @endforeach
        </div>
    @endif
</a>
