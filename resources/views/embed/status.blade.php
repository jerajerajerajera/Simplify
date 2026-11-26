{{-- Whether the status address works, and how to set it up in three steps (DNS record, web server, certificate),
     filled in with the real addresses. The steps are open until the address answers. --}}
@php($t = 'simplify::embed.settings.')
{{-- A text with :placeholders shown as code: the text is escaped first, then each value goes in as <code>. --}}
@php($code = fn (string $key, array $values) => strtr(e(__($t.$key)), collect($values)->mapWithKeys(fn ($value, $name) => [':'.$name => '<code>'.e($value).'</code>'])->all()))
@if ($host !== '')
    <div class="se-status">
        <p class="se-status-line {{ $works ? 'is-ok' : 'is-warn' }}">
            <x-filament::icon :icon="$works ? 'tabler-circle-check' : 'tabler-clock'" />
            <span>{{ $works ? __($t.'status_works', ['host' => $host]) : __($t.'status_not_yet', ['host' => $host]) }}</span>
        </p>
        <details class="se-setup" @if (! $works) open @endif>
            <summary>{{ __($t.'setup') }}</summary>
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
    </div>
@endif
<style>
    .se-status { display: grid; gap: 8px; }
    .se-status-line { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 13px; font-weight: 600; }
    .se-status-line svg { width: 18px; height: 18px; flex: none; }
    .se-status-line.is-ok { color: var(--s-ok); }
    .se-status-line.is-warn { color: var(--s-warn); }
    .se-status-record { margin: 0; padding: 10px 12px; border-radius: 8px; background: #0b0b0e; color: #e4e4e7; font: 12.5px/1.55 ui-monospace, Menlo, Consolas, monospace; white-space: pre; overflow-x: auto; }
    .se-setup { border: 1px solid var(--s-line); border-radius: 10px; min-width: 0; }
    .se-setup summary { cursor: pointer; padding: 10px 12px; font-size: 13px; font-weight: 600; }
    .se-setup-steps { display: grid; gap: 14px; margin: 0; padding: 4px 14px 12px 34px; }
    .se-setup-steps li { display: grid; gap: 6px; min-width: 0; font-size: 13px; }
    .se-setup-steps p { margin: 0; color: var(--s-muted); font-size: 13px; }
    .se-setup-steps code { font: 12.5px ui-monospace, Menlo, Consolas, monospace; }
    .se-setup-after { padding: 0 14px 14px; }
</style>
