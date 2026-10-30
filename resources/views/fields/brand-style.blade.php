{{-- The panel name's style: six ready-made styles, each shown with the panel's own name, and "Fine-tune" with one
     row per setting (font, weight, colour, effect, letter spacing, capitals). The settings themselves sit in hidden
     fields next to this one (SettingsFields::look). --}}
@php($t = 'simplify::settings.appearance.')
@php($base = \Illuminate\Support\Str::beforeLast($getStatePath(), '.').'.')
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @include('simplify::partials.look-script')
    <div wire:ignore class="s-style" x-data="{
        brand_font: $wire.$entangle(@js($getStatePath())),
        brand_weight: $wire.$entangle(@js($base.'brand_weight')),
        brand_colour: $wire.$entangle(@js($base.'brand_colour')),
        brand_colour1: $wire.$entangle(@js($base.'brand_colour1')),
        brand_colour2: $wire.$entangle(@js($base.'brand_colour2')),
        brand_effect: $wire.$entangle(@js($base.'brand_effect')),
        brand_spacing: $wire.$entangle(@js($base.'brand_spacing')),
        brand_uppercase: $wire.$entangle(@js($base.'brand_uppercase')),
        presets: @js(\Simplify\Core\Support\Brand::PRESETS),
        fine: false,
        open: null,
        get name() { return String($wire.$get(@js($base.'brand_name')) || '').trim() || @js($fallback) },
        get accent() { const c = $wire.$get(@js($base.'accent')); return /^#[0-9a-f]{6}$/i.test(c || '') ? c : null },
        get st() {
            return { brand_font: this.brand_font, brand_weight: Number(this.brand_weight), brand_colour: this.brand_colour, brand_colour1: this.brand_colour1, brand_colour2: this.brand_colour2,
                brand_effect: this.brand_effect, brand_spacing: Number(this.brand_spacing), brand_uppercase: !! this.brand_uppercase };
        },
        get preset() { return Object.keys(this.presets).find((k) => Object.entries(this.presets[k]).every(([key, v]) => this.st[key] === v)) ?? null },
        use(key) { Object.assign(this, this.presets[key]) },
        css(change = {}) { return SimplifyLook.css({ ...this.st, ...change }, this.accent) },
        toggle(row) { this.open = this.open === row ? null : row },
    }">
        <div class="s-presets" role="radiogroup" aria-label="{{ $getLabel() }}">
            @foreach (array_keys(\Simplify\Core\Support\Brand::PRESETS) as $key)
                <button type="button" class="s-preset" role="radio" x-bind:aria-checked="preset === @js($key)" x-on:click="use(@js($key))">
                    <span class="s-preset-bar"><span x-bind:style="css(presets[@js($key)])" x-text="name"></span></span>
                    <span class="s-preset-label">{{ __($t.'presets.'.$key) }}</span>
                </button>
            @endforeach
        </div>
        <button type="button" class="s-link s-fine-toggle" x-bind:aria-expanded="fine" x-on:click="fine = ! fine">
            <x-filament::icon icon="tabler-adjustments-horizontal" />
            <span x-text="fine ? @js(__($t.'fine_tune_hide')) : @js(__($t.'fine_tune'))"></span>
        </button>
        <div class="s-fine" x-show="fine" x-cloak>
            @php($rows = [
                'font' => [__($t.'font'), 'brand_font', array_combine(array_keys(\Simplify\Core\Support\Brand::FONTS), array_map(fn ($f) => __($t.'font_'.$f), array_keys(\Simplify\Core\Support\Brand::FONTS)))],
                'weight' => [__($t.'weight'), 'brand_weight', array_combine([400, 500, 600, 700, 800, 900], array_map(fn ($w) => __($t.'weight_'.$w), [400, 500, 600, 700, 800, 900]))],
                'colour' => [__($t.'colour'), 'brand_colour', array_combine(['solid', 'accent', 'gradient'], array_map(fn ($c) => __($t.'colour_'.$c), ['solid', 'accent', 'gradient']))],
                'effect' => [__($t.'effect'), 'brand_effect', array_combine(['plain', 'glow', 'shadow'], array_map(fn ($e) => __($t.'effect_'.$e), ['plain', 'glow', 'shadow']))],
                'spacing' => [__($t.'letter_spacing'), 'brand_spacing', array_combine(\Simplify\Core\Support\Brand::SPACINGS, array_map(fn ($s) => __($t.'spacing_'.['-5' => 'tight', '0' => 'normal', '8' => 'wide', '20' => 'wider'][(string) $s]), \Simplify\Core\Support\Brand::SPACINGS))],
            ])
            @foreach ($rows as $row => [$label, $key, $options])
                <div class="s-fine-row">
                    <button type="button" class="s-fine-head" x-bind:aria-expanded="open === @js($row)" x-on:click="toggle(@js($row))">
                        <span class="s-fine-key">{{ $label }}</span>
                        <span class="s-fine-value" x-text="@js($options)[{{ $key }}] ?? ''"></span>
                        <x-filament::icon icon="tabler-chevron-right" class="s-fine-chevron" />
                    </button>
                    <div class="s-fine-pane" x-show="open === @js($row)" x-cloak>
                        @if ($row === 'font')
                            <div class="s-font-tiles">
                                @foreach ($options as $value => $name)
                                    <button type="button" class="s-font-tile" x-bind:aria-pressed="brand_font === @js($value)" x-on:click="brand_font = @js($value)">
                                        <b x-bind:style="'font-family: ' + (SimplifyLook.fonts[@js($value)] || 'inherit')">Aa</b><span>{{ $name }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="s-chips">
                                @foreach ($options as $value => $name)
                                    <button type="button" class="s-chip" x-bind:aria-pressed="String({{ $key }}) === @js((string) $value)" x-on:click="{{ $key }} = @js($value)">{{ $name }}</button>
                                @endforeach
                            </div>
                            @if ($row === 'colour')
                                <div class="s-chips" x-show="brand_colour !== 'accent'">
                                    <span x-show="brand_colour === 'solid'">
                                        @include('simplify::partials.colour-pop', ['model' => 'brand_colour1', 'fallback' => "'#fafafa'", 'label' => __($t.'text_colour')])
                                    </span>
                                    <span x-show="brand_colour === 'gradient'">
                                        @include('simplify::partials.colour-pop', ['model' => 'brand_colour1', 'fallback' => "'#fafafa'", 'label' => __($t.'start_colour')])
                                    </span>
                                    <span x-show="brand_colour === 'gradient'">
                                        @include('simplify::partials.colour-pop', ['model' => 'brand_colour2', 'fallback' => "(accent || '#a9b4f2')", 'label' => __($t.'end_colour')])
                                    </span>
                                    <button type="button" class="s-link" x-show="brand_colour1 || brand_colour2" x-on:click="brand_colour1 = null; brand_colour2 = null">{{ __($t.'accent_reset') }}</button>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
            <div class="s-fine-row">
                <label class="s-fine-head">
                    <span class="s-fine-key">{{ __($t.'uppercase') }}</span>
                    <input type="checkbox" class="s-switch" x-model="brand_uppercase">
                </label>
            </div>
        </div>
    </div>
</x-dynamic-component>
