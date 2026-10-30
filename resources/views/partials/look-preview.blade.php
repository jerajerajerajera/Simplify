{{-- The top bar, and with the colours a server card, in the colour, font and style being chosen. It reads the form
     in the browser, so it follows every click and key without a round trip; only an uploaded logo comes from the server. --}}
@php($t = 'simplify::settings.appearance.preview.')
<div class="s-look-preview" x-data="{
    get accent() { const c = $wire.$get('data.accent'); return /^#[0-9a-f]{6}$/i.test(c || '') ? c : null },
    get name() { return String($wire.$get('data.brand_name') || '').trim() || @js($fallback) },
    get nameClass() { return 's-brand s-brand-font-' + ($wire.$get('data.brand_font') || 'default') + ' s-brand-style-' + ($wire.$get('data.brand_style') || 'plain') },
    get logoHeight() { return Math.round((Number($wire.$get('data.logo_height')) || 32) * .75) + 'px' },
    get colours() {
        if (! this.accent) return {};
        const n = parseInt(this.accent.slice(1), 16), light = (0.2126 * (n >> 16 & 255) + 0.7152 * (n >> 8 & 255) + 0.0722 * (n & 255)) / 255 > .55;
        return { '--s-brand-tint': this.accent, '--s-preview-accent': this.accent, '--s-preview-on-accent': light ? '#18181b' : '#fff' };
    },
}" x-bind:style="colours">
    <span class="s-preview-label"><x-filament::icon icon="tabler-eye" /> {{ __($t.'logo') }}</span>
    <div class="s-preview-bar">
        @if ($image)
            <img src="{{ $image }}" alt="" x-bind:style="{ height: logoHeight }">
        @else
            <span class="s-preview-name"><span x-bind:class="nameClass" x-text="name"></span><span class="s-preview-suffix" x-show="$wire.$get('data.pelican_suffix')">{{ __('simplify::dashboard.ui.pelican') }}</span></span>
        @endif
        <span class="s-preview-bar-nav"><i class="is-on"></i><i></i><i></i></span>
        <span class="s-preview-bar-me"></span>
    </div>
    @if ($card)
        <span class="s-preview-label"><x-filament::icon icon="tabler-eye" /> {{ __($t.'server') }}</span>
        <div class="s-preview-server">
            <div class="s-preview-server-top">
                <span class="s-preview-server-icon"><x-filament::icon icon="tabler-cube" /></span>
                <span class="s-preview-server-name"><strong>{{ __($t.'server_name') }}</strong><small>play.example.com · 7/20</small></span>
                <span class="s-preview-server-state">{{ __('simplify::js.status.running') }}</span>
            </div>
            <div class="s-preview-server-meters">
                <span>{{ __('simplify::dashboard.ui.cpu') }}<i style="--s-fill: 38%"></i></span>
                <span>{{ __('simplify::dashboard.ui.ram') }}<i style="--s-fill: 62%"></i></span>
            </div>
            <div class="s-preview-server-actions">
                <span class="s-preview-button is-primary"><x-filament::icon icon="tabler-terminal-2" /> {{ __('simplify::dashboard.ui.live_console') }}</span>
                <span class="s-preview-button">{{ __('simplify::dashboard.ui.restart') }}</span>
            </div>
        </div>
    @endif
</div>
