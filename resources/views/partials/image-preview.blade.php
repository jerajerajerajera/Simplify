{{-- Where an uploaded app image ends up, drawn small: a phone's home screen or Android's status bar. --}}
@php($t = 'simplify::settings.appearance.preview.')
<div class="s-preview">
    <span class="s-preview-label"><x-filament::icon icon="tabler-eye" /> {{ __($t.$kind) }}</span>
    @if ($kind === 'icon')
        <div class="s-preview-home">
            @foreach ([1, 2, 3] as $other)
                <span class="s-preview-app"><i></i><b></b></span>
            @endforeach
            <span class="s-preview-app is-ours"><img src="{{ $icon }}" alt=""><b>{{ $name }}</b></span>
        </div>
    @else
        <div class="s-preview-android">
            <div class="s-preview-status">
                <span>9:41</span>
                <img src="{{ $image }}" alt="">
                <x-filament::icon icon="tabler-wifi" class="s-preview-status-end" />
            </div>
            <div class="s-preview-note">
                <img src="{{ $icon }}" alt="">
                <span><strong>{{ $name }}</strong> {{ __($t.'note') }}</span>
            </div>
        </div>
    @endif
</div>
