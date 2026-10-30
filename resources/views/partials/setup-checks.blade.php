@php($checks = $this->checks())
@php($failed = array_filter($checks, fn ($check) => ! $check['ok']))
<div class="s-checks">
    <div class="s-row">
        <x-filament::icon :icon="$failed ? 'tabler-alert-triangle' : 'tabler-circle-check'" @class(['s-row-icon', 's-ok' => ! $failed, 's-warn' => $failed]) />
        <div class="s-row-text">
            {{ __($failed ? 'simplify::setup.checks.attention' : 'simplify::setup.checks.ready_all') }}
            <span class="s-muted">· {{ __('simplify::setup.checks.passed', ['passed' => count($checks) - count($failed), 'total' => count($checks)]) }}</span>
        </div>
    </div>
    @foreach ($failed as $check)
        <div class="s-row">
            <x-filament::icon icon="tabler-alert-triangle" class="s-row-icon s-warn" />
            <div class="s-row-text">
                {{ $check['text'] }}
                @if ($check['fix'] !== '')
                    <span class="s-row-fix s-muted">{{ $check['fix'] }}</span>
                @endif
                @isset($check['code'])
                    <code class="s-code">{{ $check['code'] }}</code>
                @endisset
            </div>
            <span class="s-badge s-badge-warn">{{ __('simplify::setup.checks.missing') }}</span>
        </div>
    @endforeach
</div>
