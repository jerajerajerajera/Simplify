{{--
    Pelican's Health page in Simplify's look (Simplify puts this file before Pelican's own view). The page class, its
    refresh button and its data stay Pelican's: $checkResults and $lastRanAt.
--}}
@php
    $results = collect($checkResults?->storedCheckResults ?? []);
    $bad = $results->filter(fn ($result) => in_array(strtolower((string) $result->status), ['failed', 'crashed', 'warning'], true));
    $good = $results->diffKeys($bad);
    $stale = $lastRanAt && $lastRanAt->diffInMinutes() > 5;
    $label = function ($result): string {
        $key = 'admin/health.results.'.preg_replace('/\s+/', '', mb_strtolower((string) $result->label)).'.label';

        return trans()->has($key) ? trans($key) : (string) $result->label;
    };
    $icons = [
        'cache' => 'tabler-stack-2', 'database' => 'tabler-database', 'debugmode' => 'tabler-bug', 'environment' => 'tabler-box',
        'nodeversions' => 'tabler-server-2', 'panelversion' => 'tabler-shield-check', 'queue' => 'tabler-list-details',
        'schedule' => 'tabler-clock', 'useddiskspace' => 'tabler-device-floppy',
    ];
    $icon = fn ($result) => $icons[preg_replace('/\s+/', '', mb_strtolower((string) $result->label))] ?? 'tabler-heartbeat';
    $text = fn ($result) => trim((string) ($result->notificationMessage ?: $result->shortSummary));
    $fix = \Simplify\Core\Health\HealthFix::class;
@endphp
<x-filament-panels::page>
    <div class="s-card s-health">
        @if ($results->isEmpty())
            <p class="s-health-empty">{{ __('simplify::dashboard.health.page.not_run') }}</p>
        @else
            <div class="s-health-sum">
                @if ($bad->isNotEmpty())
                    <span class="s-health-dot is-bad"></span><span>{{ trans_choice('simplify::dashboard.health.page.attention', $bad->count(), ['count' => $bad->count()]) }}</span>
                    <span class="s-health-sep" aria-hidden="true"></span>
                @endif
                <span class="s-health-dot"></span><span>{{ __('simplify::dashboard.health.page.ok', ['count' => $good->count()]) }}</span>
                @if ($lastRanAt)
                    <span class="s-health-when @if ($stale) is-stale @endif">{{ trans('admin/health.checked', ['time' => $lastRanAt->diffForHumans()]) }}</span>
                @endif
            </div>

            @if ($bad->isNotEmpty())
                <div class="s-health-list">
                    @foreach ($bad as $result)
                        @php($action = $fix::for((string) $result->label))
                        <div class="s-health-row is-{{ strtolower((string) $result->status) === 'warning' ? 'warn' : 'bad' }}">
                            <span class="s-health-icon"><x-filament::icon :icon="$icon($result)" /></span>
                            <span class="s-health-text"><b>{{ $label($result) }}</b><small>{{ $text($result) }}</small></span>
                            @if ($action)
                                <a class="s-btn s-health-fix" href="{{ $action['url'] }}" @if ($action['external']) target="_blank" rel="noopener" @endif>{{ $action['label'] }}</a>
                            @endif
                            <span class="s-badge s-health-pill">{{ __('simplify::dashboard.health.page.status.'.strtolower((string) $result->status)) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($good->isNotEmpty())
                @if ($bad->isNotEmpty())
                    <span class="s-health-label">{{ __('simplify::dashboard.health.page.all_good') }}</span>
                @endif
                <div class="s-health-grid">
                    @foreach ($good as $result)
                        <div class="s-health-row is-{{ strtolower((string) $result->status) === 'ok' ? 'ok' : 'skip' }}">
                            <span class="s-health-icon"><x-filament::icon :icon="$icon($result)" /></span>
                            <span class="s-health-text"><b>{{ $label($result) }}</b><small>{{ $text($result) }}</small></span>
                            <span class="s-badge s-badge-ok s-health-pill">{{ __('simplify::dashboard.health.page.status.'.strtolower((string) $result->status)) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
