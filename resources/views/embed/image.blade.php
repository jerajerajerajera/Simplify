{{-- The image on the website: the server's own banner first, then Simplify's scenes and banners, and Upload. A picked file shows as the chosen tile until Save. --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="se-images" x-data="{ state: $wire.$entangle(@js($getStatePath())) }">
        @if ($pending)
            <button type="button" class="se-image is-picked" title="{{ __('simplify::embed.server.image_new') }}" aria-pressed="true">
                <img src="{{ $pending }}" alt="">
            </button>
        @endif
        @foreach ($choices as $choice)
            <button type="button" class="se-image" x-on:click="state = @js($choice['id']); $wire.set('upload', null)"
                x-bind:class="{ 'is-picked': {{ $pending ? 'false' : 'true' }} && (state ?? '') === @js($choice['id']) }"
                x-bind:aria-pressed="(state ?? '') === @js($choice['id'])" title="{{ $choice['label'] }}">
                <img src="{{ $choice['url'] }}" alt="" loading="lazy">
                @if ($choice['label'] !== '')
                    <span>{{ $choice['label'] }}</span>
                @endif
            </button>
        @endforeach
        <label class="se-image se-upload">
            <input type="file" accept="image/png,image/jpeg,image/webp" wire:model="upload" x-on:change="state = 'upload'" aria-label="{{ __('simplify::embed.server.image_upload') }}">
            <x-filament::icon icon="tabler-upload" />
            <span>{{ __('simplify::embed.server.image_upload') }}</span>
        </label>
    </div>
    @error('upload') <p class="se-error">{{ $message }}</p> @enderror
</x-dynamic-component>
<style>
    .se-images { display: flex; flex-wrap: wrap; gap: 8px; }
    .se-image { position: relative; width: 96px; height: 44px; padding: 0; overflow: hidden; border-radius: 8px; border: 2px solid transparent; background: rgb(127 127 127 / .12); cursor: pointer; display: grid; place-items: center; }
    .se-image img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .se-image > span { position: relative; padding: 1px 6px; border-radius: 5px; background: rgb(0 0 0 / .55); color: #fff; font-size: 11px; font-weight: 600; white-space: nowrap; }
    .se-image.is-picked { border-color: var(--primary-500, #5b8fd0); }
    .se-upload { border: 1px dashed rgb(127 127 127 / .45); background: transparent; display: flex; gap: 4px; align-items: center; justify-content: center; font-size: 12px; color: rgb(113 113 122); }
    .se-upload svg { width: 16px; height: 16px; }
    /* The file field covers the tile, invisible, so it stays inside it and the whole tile opens the file picker. */
    .se-upload input { position: absolute; inset: 0; width: 100%; height: 100%; margin: 0; opacity: 0; cursor: pointer; }
    .se-error { margin: 6px 0 0; font-size: 13px; color: rgb(220 38 38); }
</style>
