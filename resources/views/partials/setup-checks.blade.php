<div class="s-card">
    @foreach ($this->checks() as $check)
        <div class="s-row">
            <x-filament::icon :icon="$check['ok'] ? 'tabler-circle-check' : 'tabler-alert-triangle'" @class(['s-row-icon', 's-ok' => $check['ok'], 's-warn' => ! $check['ok']]) />
            <div class="s-row-text">{{ $check['text'] }}</div>
            <span @class(['s-badge', 's-badge-ok' => $check['ok'], 's-badge-warn' => ! $check['ok']])>
                {{ __($check['ok'] ? 'simplify::setup.checks.ok' : 'simplify::setup.checks.missing') }}
            </span>
        </div>
    @endforeach
</div>
