{{-- Above the address: whether Simplify can set the address up itself (Cloudflare under Subdomains, which also gives
     every server an address of its own), and the status page's address that always works. --}}
@php($t = 'simplify::embed.settings.')
<div class="se-status-intro" x-data="{
    copied: null,
    async take(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.copied = 'copied';
        } catch (e) {
            const range = document.createRange();
            range.selectNodeContents(this.$refs.always);
            getSelection().removeAllRanges();
            getSelection().addRange(range);
            this.copied = 'selected';
        }
        setTimeout(() => this.copied = null, 1800);
    },
}">
    <div class="se-auto {{ $cloudflare ? 'is-on' : 'is-off' }}">
        <x-filament::icon :icon="$cloudflare ? 'tabler-circle-check' : 'tabler-info-circle'" />
        <div>
            <strong>{{ __($t.'auto_title') }}</strong>
            <p>
                {{ $cloudflare ? __($t.'auto_on', ['domain' => $domain !== '' ? $domain : 'Cloudflare', 'example' => 'mc.'.($domain !== '' ? $domain : 'example.com')]) : __($t.'auto_off') }}
                @if ($subdomains !== '')
                    <a href="{{ $subdomains }}" class="se-link">{{ __($t.'auto_link') }}</a>
                @endif
            </p>
        </div>
    </div>
    <div class="se-always">
        <strong>{{ __($t.'always') }}</strong>
        <div class="se-always-row">
            <code x-ref="always">{{ $always }}</code>
            <button type="button" class="s-btn s-btn-small" x-on:click="take({{ \Illuminate\Support\Js::from($always) }})">
                <x-filament::icon icon="tabler-copy" />
                <span x-text="copied === 'copied' ? {{ \Illuminate\Support\Js::from(__('simplify::embed.code.copied')) }} : (copied === 'selected' ? {{ \Illuminate\Support\Js::from(__('simplify::embed.code.selected')) }} : {{ \Illuminate\Support\Js::from(__('simplify::embed.code.copy_link')) }})">{{ __('simplify::embed.code.copy_link') }}</span>
            </button>
        </div>
        <p class="se-muted">{{ __($t.'always_help') }}</p>
    </div>
</div>
<style>
    .se-status-intro { display: grid; gap: 14px; }
    .se-auto { display: flex; gap: 12px; align-items: flex-start; border-radius: 10px; padding: 12px 14px; background: var(--s-raised); border: 1px solid var(--s-line); }
    .se-auto.is-on { border-color: var(--s-accent); }
    .se-auto > svg { width: 20px; height: 20px; flex: none; color: var(--s-accent); margin-top: 1px; }
    .se-auto strong { display: block; font-size: 14px; }
    .se-auto p { margin: 2px 0 0; color: var(--s-muted); font-size: 13px; }
    .se-always { display: grid; gap: 6px; min-width: 0; }
    .se-always strong { font-size: 13px; }
    .se-always-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; min-width: 0; }
    .se-always-row code { flex: 1; min-width: 0; overflow-wrap: anywhere; border: 1px solid var(--s-line); border-radius: 8px; padding: 7px 10px; font: 13px ui-monospace, Menlo, Consolas, monospace; background: var(--s-surface); }
    .se-always .se-muted { margin: 0; font-size: 12.5px; }
</style>
