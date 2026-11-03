{{-- The Plugins page's categories as a filter in the table's toolbar (CategoryFilter): one choice sets Pelican's activeTab, so only the list changes. --}}
@php($current = collect($options)->firstWhere('key', $active) ?? $options[0])
<div class="ps-filter ps-category-filter" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
    <button type="button" class="ps-chip ps-filter-button{{ $current['key'] !== $options[0]['key'] ? ' is-on' : '' }}" aria-haspopup="menu" :aria-expanded="open" x-on:click="open = ! open">
        @if ($current['icon'])<x-filament::icon :icon="$current['icon']" />@endif
        <span>{{ $current['label'] }}</span>
        @if ($current['count'] !== null)<small class="ps-filter-count">{{ $current['count'] }}</small>@endif
        <x-filament::icon icon="tabler-chevron-right" class="ps-filter-chevron" />
    </button>
    <div class="ps-dropdown ps-filter-menu" role="menu" x-show="open" x-cloak x-transition.opacity>
        @foreach ($options as $option)
            <button type="button" class="ps-menu-item" role="menuitemradio" aria-checked="{{ $option['key'] === $current['key'] ? 'true' : 'false' }}" wire:click="$set('activeTab', @js($option['key']))" x-on:click="open = false">
                @if ($option['icon'])<x-filament::icon :icon="$option['icon']" />@endif
                <span>{{ $option['label'] }}</span>
                @if ($option['count'] !== null)<small class="ps-filter-count">{{ $option['count'] }}</small>@endif
                <x-filament::icon icon="tabler-check" class="ps-filter-check" />
            </button>
        @endforeach
    </div>
</div>
