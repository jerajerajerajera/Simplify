{{-- The accent colour: ready-made pastels, and "+" for your own in a small window with a colour field, a hue slider and the hex code. --}}
@php($t = 'simplify::settings.appearance.')
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div wire:ignore class="s-accent" x-data="{
        state: $wire.$entangle(@js($getStatePath())),
        pastels: @js(array_values($pastels)),
        open: false,
        h: 345, s: 32, v: 96,
        hex: '',
        get own() { return !! this.state && ! this.pastels.includes(String(this.state).toLowerCase()) },
        toHex() {
            const s = this.s / 100, v = this.v / 100;
            const f = (n) => { const k = (n + this.h / 60) % 6; return v - v * s * Math.max(0, Math.min(k, 4 - k, 1)) };
            return '#' + [f(5), f(3), f(1)].map((x) => Math.round(x * 255).toString(16).padStart(2, '0')).join('');
        },
        fromHex(hex) {
            const n = parseInt(hex.slice(1), 16), r = (n >> 16 & 255) / 255, g = (n >> 8 & 255) / 255, b = (n & 255) / 255;
            const max = Math.max(r, g, b), d = max - Math.min(r, g, b);
            let h = 0;
            if (d) h = max === r ? ((g - b) / d) % 6 : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
            if (d) this.h = Math.round((h * 60 + 360) % 360);
            this.s = max ? Math.round(d / max * 100) : 0;
            this.v = Math.round(max * 100);
        },
        pick(hex) { this.state = hex; this.open = false },
        toggle() {
            this.open = ! this.open;
            if (! this.open) return;
            this.fromHex(/^#[0-9a-f]{6}$/i.test(this.state || '') ? this.state : this.pastels[0]);
            this.hex = this.toHex();
        },
        apply() { this.state = this.hex = this.toHex() },
        drag(event, area) {
            const el = event.currentTarget;
            el.setPointerCapture(event.pointerId);
            const move = (e) => {
                const r = el.getBoundingClientRect(), x = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width));
                if (area) { this.s = Math.round(x * 100); this.v = Math.round((1 - Math.max(0, Math.min(1, (e.clientY - r.top) / r.height))) * 100) }
                else this.h = Math.round(x * 360);
                this.apply();
            };
            move(event);
            el.onpointermove = move;
            el.onpointerup = () => (el.onpointermove = el.onpointerup = null);
        },
        typed() {
            const v = this.hex.trim();
            if (/^#?[0-9a-f]{6}$/i.test(v)) { this.state = (v[0] === '#' ? v : '#' + v).toLowerCase(); this.fromHex(this.state) }
        },
    }" x-on:keydown.escape="open = false" x-on:click.outside="open = false">
        <div class="s-accent-swatches" role="radiogroup" aria-label="{{ $getLabel() }}">
            @foreach ($pastels as $name => $hex)
                <button type="button" class="s-swatch" style="--s-swatch: {{ $hex }}" role="radio" title="{{ __($t.'pastels.'.$name) }}" aria-label="{{ __($t.'pastels.'.$name) }}"
                    x-bind:aria-checked="String(state).toLowerCase() === @js($hex)" x-on:click="pick(@js($hex))"></button>
            @endforeach
            <button type="button" class="s-swatch s-swatch-own" role="radio" title="{{ __($t.'own_colour') }}" aria-label="{{ __($t.'own_colour') }}" aria-haspopup="dialog"
                x-bind:aria-checked="own" x-bind:aria-expanded="open" x-bind:style="own && { '--s-swatch': state }" x-on:click="toggle()">
                <x-filament::icon icon="tabler-plus" class="s-swatch-plus" />
            </button>
        </div>
        <div class="s-accent-pop" role="dialog" aria-label="{{ __($t.'own_colour') }}" x-show="open" x-cloak x-transition.opacity.duration.100ms>
            <div class="s-accent-field" x-bind:style="{ '--s-hue': h }" x-on:pointerdown.prevent="drag($event, true)">
                <b x-bind:style="{ left: s + '%', top: (100 - v) + '%', background: state }"></b>
            </div>
            <div class="s-accent-hue" x-on:pointerdown.prevent="drag($event, false)">
                <b x-bind:style="{ left: (h / 3.6) + '%', background: `hsl(${h} 100% 50%)` }"></b>
            </div>
            <div class="s-accent-hex">
                <span x-bind:style="{ background: state }"></span>
                <input type="text" maxlength="7" spellcheck="false" aria-label="{{ __($t.'hex') }}" x-model="hex" x-on:input="typed()">
            </div>
        </div>
        <button type="button" class="s-link" x-show="state" x-cloak x-on:click="pick(null)">{{ __($t.'accent_reset') }}</button>
    </div>
</x-dynamic-component>
