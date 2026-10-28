@php
    use Simplify\Core\Overview\Format;

    $tone = match ($health['state'] ?? null) {
        'healthy' => 'ok',
        'warning', 'maintenance', 'partly' => 'warn',
        'critical', 'unavailable' => 'danger',
        default => null,
    };
    $meters = $health === null || $health['available'] === 0 ? [] : [
        'cpu' => [$health['cpu'], Format::percent($health['cpu'])],
        'memory' => [$health['memory_used'] / max(1, $health['memory_total']) * 100, Format::bytes($health['memory_used']).' / '.Format::bytes($health['memory_total'])],
        'disk' => [$health['disk_used'] / max(1, $health['disk_total']) * 100, Format::bytes($health['disk_used']).' / '.Format::bytes($health['disk_total'])],
    ];
    $problems = collect($health['nodes'] ?? [])->filter(fn ($node) => $node['problem'] !== null)
        ->map(fn ($node) => $node['name'].': '.__('pelican-simplify::health.problem.'.$node['problem'][0], $node['problem'][1]));
@endphp
<div class="s-card s-health" wire:init="load" wire:poll.30s.visible="load">
    <div class="s-health-head">
        <x-filament::icon icon="tabler-heart-rate-monitor" class="s-row-icon" />
        <span class="s-health-title">{{ __('pelican-simplify::health.title') }}</span>
        <span @class(['s-badge', 's-badge-'.$tone => $tone])>
            {{ $health === null ? __('pelican-simplify::overview.loading') : __('pelican-simplify::health.state.'.$health['state']) }}
        </span>
        @if ($health !== null)
            <span class="s-muted">{{ trans_choice('pelican-simplify::health.hosts', $health['hosts'], ['available' => $health['available'], 'hosts' => $health['hosts']]) }}</span>
        @endif
        <button type="button" class="s-btn s-btn-icon s-health-refresh" wire:click="refresh" wire:loading.attr="disabled"
            title="{{ __('pelican-simplify::health.refresh') }}" aria-label="{{ __('pelican-simplify::health.refresh') }}">
            <x-filament::icon icon="tabler-refresh" />
        </button>
    </div>
    @if ($meters)
        <dl class="s-stats s-health-meters">
            @foreach ($meters as $key => [$percent, $text])
                <div>
                    <dt>{{ __('pelican-simplify::overview.'.$key) }}</dt>
                    <dd>{{ $text }}</dd>
                    <div @class(['s-meter', 's-meter-warn' => $percent >= 85 && $percent < 95, 's-meter-danger' => $percent >= 95])>
                        <span style="width: {{ min(100, max(0, round($percent))) }}%"></span>
                    </div>
                </div>
            @endforeach
        </dl>
    @endif
    @foreach ($problems as $problem)
        <div class="s-muted s-health-problem">{{ $problem }}</div>
    @endforeach
</div>
