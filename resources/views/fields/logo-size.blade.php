{{-- The logo's height as a slider between a small and a large logo; it stops at Brand::HEIGHTS. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="s-size" x-data="{
        state: $wire.$entangle(@js($getStatePath())),
        heights: @js(\Simplify\Core\Support\Brand::HEIGHTS),
        get step() { const h = Number(this.state) || 32; return this.heights.reduce((best, v, i) => Math.abs(v - h) < Math.abs(this.heights[best] - h) ? i : best, 0) },
    }">
        <img src="{{ $image }}" alt="" class="s-size-small">
        <input type="range" min="0" max="{{ count(\Simplify\Core\Support\Brand::HEIGHTS) - 1 }}" step="1" aria-label="{{ $getLabel() }}"
            x-bind:value="step" x-on:input="state = heights[$event.target.value]">
        <img src="{{ $image }}" alt="" class="s-size-large">
    </div>
</x-dynamic-component>
