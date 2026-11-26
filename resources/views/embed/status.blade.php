{{-- Whether the status address works, and the one DNS record to add when Simplify cannot make it itself. --}}
@php($t = 'simplify::embed.settings.')
@if ($host !== '')
    <div class="se-status">
        <p class="se-status-line {{ $works ? 'is-ok' : 'is-warn' }}">
            <x-filament::icon :icon="$works ? 'tabler-circle-check' : 'tabler-clock'" />
            <span>{{ $works ? __($t.'status_works', ['host' => $host]) : __($t.'status_not_yet', ['host' => $host]) }}</span>
        </p>
        @if (! $cloudflare)
            <p class="se-muted">{{ __($t.'status_record') }}</p>
            <pre class="se-status-record">{{ $record }}</pre>
        @endif
        <p class="se-muted">{{ __($t.'status_server') }}</p>
    </div>
@endif
<style>
    .se-status { display: grid; gap: 8px; }
    .se-status-line { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 13px; font-weight: 600; }
    .se-status-line svg { width: 18px; height: 18px; flex: none; }
    .se-status-line.is-ok { color: var(--s-ok); }
    .se-status-line.is-warn { color: var(--s-warn); }
    .se-status-record { margin: 0; padding: 10px 12px; border-radius: 8px; background: #0b0b0e; color: #e4e4e7; font: 12.5px/1.55 ui-monospace, Menlo, Consolas, monospace; white-space: pre-wrap; overflow-wrap: anywhere; }
</style>
