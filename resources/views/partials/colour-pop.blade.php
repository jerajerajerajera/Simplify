{{-- A colour swatch that opens a small colour window for $model (an Alpine value in the field around it). --}}
@php($t = 'simplify::settings.appearance.')
<span class="s-pop" x-data="SimplifyLook.pop(() => {{ $model }}, (v) => {{ $model }} = v)" x-on:keydown.escape="open = false" x-on:click.outside="open = false">
    <button type="button" class="s-chip s-pop-swatch" aria-haspopup="dialog" x-bind:aria-expanded="open" x-on:click="toggle({{ $fallback }})">
        <i x-bind:style="{ background: {{ $model }} || {{ $fallback }} }"></i><span>{{ $label }}</span>
    </button>
    <span class="s-accent-pop" role="dialog" aria-label="{{ $label }}" x-show="open" x-cloak x-transition.opacity.duration.100ms>
        <span class="s-accent-field" x-bind:style="{ '--s-hue': h }" x-on:pointerdown.prevent="drag($event, true)">
            <b x-bind:style="{ left: s + '%', top: (100 - v) + '%', background: {{ $model }} }"></b>
        </span>
        <span class="s-accent-hue" x-on:pointerdown.prevent="drag($event, false)">
            <b x-bind:style="{ left: (h / 3.6) + '%', background: `hsl(${h} 100% 50%)` }"></b>
        </span>
        <span class="s-accent-hex">
            <span x-bind:style="{ backgroundColor: /^#[0-9a-f]{6}$/i.test(hex) ? hex : {{ $model }} }"></span>
            <input type="text" maxlength="7" spellcheck="false" aria-label="{{ __($t.'hex') }}" x-model="hex" x-on:input="typed()">
        </span>
    </span>
</span>
