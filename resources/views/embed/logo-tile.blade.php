{{-- The logo an egg gets without an upload (EggLogo): chosen while nothing is uploaded, and pressing it removes the
     upload through the upload's own remove button (upload-tile.blade.php). --}}
<button type="button" class="s-tile s-tile-default se-logo-tile" title="{{ $label }}" aria-label="{{ $label }}"
    x-on:click="$el.closest('.s-tile-field')?.querySelector('.filepond--action-remove-item')?.click()">
    @if ($url)
        <img src="{{ $url }}" alt="">
    @else
        <x-filament::icon icon="tabler-device-gamepad-2" />
    @endif
</button>
<style>
    .se-logo-tile img { object-fit: contain; padding: 10px; box-sizing: border-box; }
</style>
