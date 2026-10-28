@php
    $address = $this->address();
    $sftp = $this->sftp();
    $badges = $this->badges();
    $look = $this->look();
@endphp
<x-filament-widgets::widget>
    @if ($look['banner'])
        <img class="s-banner" src="{{ $look['banner'] }}" alt="">
    @endif
    <div @class(['s-details', 's-details-colored' => $look['color']]) @if ($look['color']) style="--s-tile-accent: {{ $look['color'] }}" @endif>
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
        @if ($billing = $this->billingUrl())
            <a class="s-btn" href="{{ $billing }}" target="_blank" rel="noopener">
                <x-filament::icon icon="tabler-receipt" />
                {{ __('pelican-simplify::settings.billing') }}
            </a>
        @endif
        @foreach ($badges as $badge)
            <span class="s-badge">{{ $badge }}</span>
        @endforeach
        @if ($this->canCustomize() && ! $customizing)
            <button type="button" class="s-btn s-btn-icon s-details-end" wire:click="customize"
                title="{{ __('pelican-simplify::server.customize') }}" aria-label="{{ __('pelican-simplify::server.customize') }}">
                <x-filament::icon icon="tabler-brush" />
            </button>
        @endif
    </div>
    @if ($customizing)
        <form class="s-card s-look-editor" wire:submit="saveLook">
            <label class="s-look-field">
                <span>{{ __('pelican-simplify::server.color') }}</span>
                <input type="color" class="s-color" wire:model="color" value="{{ $color ?? '#888888' }}">
                <button type="button" class="s-btn s-btn-small" wire:click="$set('color', null)">{{ __('pelican-simplify::server.no_color') }}</button>
            </label>
            <label class="s-look-field">
                <span>{{ __('pelican-simplify::server.banner') }}</span>
                <input type="file" accept="image/png,image/jpeg,image/webp" wire:model="banner">
            </label>
            @if ($look['banner'])
                <label class="s-look-field">
                    <input type="checkbox" wire:model="removeBanner">
                    <span>{{ __('pelican-simplify::server.remove_banner') }}</span>
                </label>
            @endif
            <p class="s-muted">{{ __('pelican-simplify::server.banner_help') }}</p>
            @error('banner') <p class="s-error">{{ $message }}</p> @enderror
            @error('color') <p class="s-error">{{ $message }}</p> @enderror
            <div class="s-bar">
                <button type="button" class="s-btn" wire:click="$set('customizing', false)">{{ __('pelican-simplify::server.cancel') }}</button>
                <button type="submit" class="s-btn s-btn-primary" wire:loading.attr="disabled">{{ __('pelican-simplify::server.save') }}</button>
            </div>
        </form>
    @endif
</x-filament-widgets::widget>
