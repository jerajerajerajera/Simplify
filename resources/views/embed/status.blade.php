{{-- How the own address answers, as the check found it at the last save (or a minute ago): Live, or what is wrong in
     plain words with a spot for an Explain button. Below it the steps to set it up by hand, filled in with the real
     addresses: only without Cloudflare, or when the web server or its certificate is the problem. --}}
@php($t = 'simplify::embed.settings.')
{{-- A text with :placeholders shown as code: the text is escaped first, then each value goes in as <code>. --}}
@php($code = fn (string $key, array $values) => strtr(e(__($t.$key)), collect($values)->mapWithKeys(fn ($value, $name) => [':'.$name => '<code>'.e($value).'</code>'])->all()))
@if ($host !== '' && $state !== null)
    @php($live = $state['state'] === 'live')
    <div class="se-status">
        <div class="se-state {{ $live ? 'is-ok' : 'is-warn' }}" data-state="{{ $state['state'] }}">
            <x-filament::icon :icon="$live ? 'tabler-circle-check' : 'tabler-alert-triangle'" />
            <div>
                <strong>{{ __($t.'state_'.$state['state']) }}</strong>
                <p>{{ $message }}</p>
                @if ($live)
                    <p class="se-muted">{{ __($t.'live_tip') }}</p>
                @endif
            </div>
            @unless ($live)
                <span class="se-state-actions">{{ \Filament\Support\Facades\FilamentView::renderHook('simplify::problem.actions', scopes: ['simplify-problem', 'simplify-problem:'.\Simplify\Core\Problems\StatusProblems::PREFIX.$state['state']]) }}</span>
            @endunless
        </div>
        @if (! $cloudflare || in_array($state['state'], ['other', 'ssl'], true))
            <details class="se-setup" @if (! $live) open @endif>
                <summary>{{ __($t.'manual') }}</summary>
                <ol class="se-setup-steps">
                    <li>
                        <strong>{{ __($t.'setup_dns') }}</strong>
                        <p>{{ __($cloudflare ? $t.'setup_dns_done' : $t.'setup_dns_help') }}</p>
                        <pre class="se-status-record">{{ $record }}</pre>
                    </li>
                    <li>
                        <strong>{{ __($t.'setup_server') }}</strong>
                        <p>{!! $code('setup_server_help', ['name' => 'server_name', 'file' => '/etc/nginx/sites-available/pelican.conf']) !!}</p>
                        <pre class="se-status-record">server_name {{ $panel }} {{ $host }};

sudo nginx -t && sudo systemctl reload nginx</pre>
                        <p>{!! $code('setup_apache', ['line' => 'ServerAlias '.$host]) !!}</p>
                    </li>
                    <li>
                        <strong>{{ __($t.'setup_cert') }}</strong>
                        <p>{!! $code('setup_cert_help', ['wildcard' => $wildcard]) !!}</p>
                        <pre class="se-status-record">sudo certbot --nginx -d {{ $panel }} -d {{ $host }}</pre>
                    </li>
                </ol>
                <p class="se-muted se-setup-after">{{ __($t.'setup_after') }}</p>
            </details>
        @endif
    </div>
@endif
<style>
    .se-status { display: grid; gap: 10px; }
    .se-state { display: flex; gap: 10px; align-items: flex-start; border-radius: 10px; padding: 10px 12px; font-size: 13px; border: 1px solid var(--s-line); background: var(--s-raised); }
    .se-state > svg { width: 18px; height: 18px; flex: none; margin-top: 1px; }
    .se-state.is-ok > svg, .se-state.is-ok strong { color: var(--s-ok); }
    .se-state.is-warn > svg, .se-state.is-warn strong { color: var(--s-warn); }
    .se-state > div { flex: 1; min-width: 0; display: grid; gap: 2px; }
    .se-state p { margin: 0; overflow-wrap: anywhere; }
    .se-state-actions { flex: none; }
    .se-status-record { margin: 0; padding: 10px 12px; border-radius: 8px; background: #0b0b0e; color: #e4e4e7; font: 12.5px/1.55 ui-monospace, Menlo, Consolas, monospace; white-space: pre; overflow-x: auto; }
    .se-setup { border: 1px solid var(--s-line); border-radius: 10px; min-width: 0; }
    .se-setup summary { cursor: pointer; padding: 10px 12px; font-size: 13px; font-weight: 600; }
    .se-setup-steps { display: grid; gap: 14px; margin: 0; padding: 4px 14px 12px 34px; }
    .se-setup-steps li { display: grid; gap: 6px; min-width: 0; font-size: 13px; }
    .se-setup-steps p { margin: 0; color: var(--s-muted); font-size: 13px; }
    .se-setup-steps code { font: 12.5px ui-monospace, Menlo, Consolas, monospace; }
    .se-setup-after { padding: 0 14px 14px; }
</style>
