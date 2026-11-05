{{-- Simplify settings → General → Server banners: every uploaded server banner, the servers that show it, and a real
     button to remove it. --}}
@php($uploads = \Simplify\Core\Server\Looks::uploads())
@if ($uploads === [])
    <p class="s-muted">{{ __('simplify::settings.appearance.banners_none') }}</p>
@else
    <div class="s-banners">
        @foreach ($uploads as $upload)
            <div class="s-banner" wire:key="banner-{{ $upload['file'] }}">
                <img src="{{ $upload['url'] }}" alt="" loading="lazy">
                <div class="s-row-text">
                    <div>{{ __('simplify::settings.appearance.banner_used_by') }}</div>
                    <div class="s-muted">{{ implode(', ', $upload['servers']) }}</div>
                </div>
                <button type="button" class="s-btn" wire:click="deleteBanner(@js($upload['file']))"
                    wire:confirm="{{ __('simplify::settings.appearance.banner_remove_confirm') }} {{ __('simplify::settings.appearance.banner_remove_help') }}">
                    <x-filament::icon icon="tabler-trash" />
                    <span>{{ __('simplify::settings.appearance.banner_remove') }}</span>
                </button>
            </div>
        @endforeach
    </div>
@endif
