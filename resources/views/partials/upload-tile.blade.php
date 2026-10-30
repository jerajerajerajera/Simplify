{{-- The tile before a small upload: what is used without one. It is chosen while nothing is uploaded (simplify.css,
     :has), and pressing it removes the upload through the upload's own remove button. --}}
@php($t = 'simplify::settings.appearance.')
<button type="button" class="s-tile s-tile-default" title="{{ __($t.'default_'.$kind) }}" aria-label="{{ __($t.'default_'.$kind) }}"
    x-on:click="$el.closest('.s-tile-field')?.querySelector('.filepond--action-remove-item')?.click()">
    @if ($kind === 'badge')
        <img src="{{ \Simplify\Core\Support\Assets::url('app-badge-96.png') }}" alt="" class="s-tile-badge">
    @else
        <img src="{{ \Simplify\Core\Support\Assets::url('app-icon-192.png') }}" alt="">
    @endif
</button>
