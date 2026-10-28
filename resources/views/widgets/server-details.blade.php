@php
    $address = $this->address();
    $sftp = $this->sftp();
    $badges = $this->badges();
@endphp
<x-filament-widgets::widget>
    <div class="s-details">
        <a class="s-btn" href="{{ $this->overviewUrl() }}">
            <x-filament::icon icon="tabler-layout-grid" />
            {{ __('pelican-simplify::server.overview') }}
        </a>
        @if ($address)
            <x-pelican-simplify::copy-field :label="__('pelican-simplify::server.address')" :value="$address" />
        @endif
        @if ($sftp)
            <x-pelican-simplify::copy-field :label="__('pelican-simplify::server.sftp')" :value="$sftp['url']" />
            <x-pelican-simplify::copy-field :label="__('pelican-simplify::server.username')" :value="$sftp['username']" />
        @endif
        @foreach ($badges as $badge)
            <span class="s-badge">{{ $badge }}</span>
        @endforeach
    </div>
</x-filament-widgets::widget>
