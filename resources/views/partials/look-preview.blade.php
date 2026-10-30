{{-- One preview for the whole Look: the top bar and a server card in the colour, name style and logo being chosen,
     dark or light. It reads the form in the browser, so it follows every click and key without a round trip; only an
     uploaded logo comes from the server. It stays in view while the fields beside it scroll (simplify.css). --}}
@php($t = 'simplify::settings.appearance.preview.')
@include('simplify::partials.look-script')
<div class="s-look-preview" x-data="{
    dark: document.documentElement.classList.contains('dark'),
    get accent() { const c = $wire.$get('data.accent'); return /^#[0-9a-f]{6}$/i.test(c || '') ? c : null },
    get name() { return String($wire.$get('data.brand_name') || '').trim() || @js($fallback) },
    get show() { return @js((bool) $image) ? ($wire.$get('data.brand_show') || 'logo') : 'text' },
    get nameStyle() {
        const get = (key) => $wire.$get('data.' + key);
        return SimplifyLook.css(Object.fromEntries(['brand_font', 'brand_weight', 'brand_colour', 'brand_colour1', 'brand_colour2', 'brand_effect', 'brand_spacing', 'brand_uppercase'].map((k) => [k, get(k)])), this.accent, this.dark);
    },
    get logoHeight() { return Math.round((Number($wire.$get('data.logo_height')) || 32) * .75) + 'px' },
    get colours() {
        if (! this.accent) return {};
        const n = parseInt(this.accent.slice(1), 16), light = (0.2126 * (n >> 16 & 255) + 0.7152 * (n >> 8 & 255) + 0.0722 * (n & 255)) / 255 > .55;
        return { '--s-preview-accent': this.accent, '--s-preview-on-accent': light ? '#18181b' : '#fff' };
    },
}" x-bind:style="colours" x-bind:class="dark ? 's-look-dark' : 's-look-light'">
    <div class="s-look-preview-head">
        <span class="s-preview-label"><x-filament::icon icon="tabler-eye" /> {{ __($t.'title') }}</span>
        <span class="s-look-mode" role="group">
            <button type="button" x-bind:aria-pressed="dark" x-on:click="dark = true"><x-filament::icon icon="tabler-moon" /><span class="fi-sr-only">{{ __($t.'dark') }}</span></button>
            <button type="button" x-bind:aria-pressed="! dark" x-on:click="dark = false"><x-filament::icon icon="tabler-sun" /><span class="fi-sr-only">{{ __($t.'light') }}</span></button>
        </span>
    </div>
    <div class="s-look-screen">
        <div class="s-preview-bar">
            @if ($image)
                <img src="{{ $image }}" alt="" x-show="show !== 'text'" x-bind:style="{ height: logoHeight }">
            @endif
            <span class="s-preview-name" x-show="show !== 'logo'"><span x-bind:style="nameStyle" x-text="name"></span><span class="s-preview-suffix" x-show="$wire.$get('data.pelican_suffix')">{{ __('simplify::dashboard.ui.pelican') }}</span></span>
            <span class="s-preview-bar-nav"><i class="is-on"></i><i></i></span>
            <span class="s-preview-bar-me"></span>
        </div>
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
    </div>
</div>
