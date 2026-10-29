{{-- One of Pelican's own settings tabs in a frame, as tall as Pelican's form (head.blade.php sizes it). --}}
<div class="s-pelican-frame">
    <p>{{ __('simplify::settings.pelican_frame', ['label' => $label]) }}</p>
    <iframe data-ps-frame="settings" data-ps-tab="1" src="{{ $url }}" title="{{ __('simplify::settings.pelican_frame_title', ['label' => $label]) }}" loading="lazy"></iframe>
</div>
