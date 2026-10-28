@php($u = fn (string $key, array $values = []) => __('pelican-simplify::dashboard.ui.'.$key, $values))
<x-filament-panels::page>
@include('pelican-simplify::dashboard.assets')
<div class="ps" data-simplify-root x-cloak wire:ignore.self
     x-data="pelicanSimplify($wire, @js($this->initial()))"
     x-on:resize.window.debounce.150ms="applyScale(); restoreOverviewView()"
     :class="{'dark': themeDark, 'host-mode': hostMode, 'is-full-width': overviewPreferences.fullWidth, 'ps-halloween': halloween()}"
     :style="accentStyle('overview')">
    @include('pelican-simplify::dashboard.icons')
    @include('pelican-simplify::dashboard.halloween')
    <main class="ps-main">
        @include('pelican-simplify::dashboard.header')
        @include('pelican-simplify::dashboard.host')
        @include('pelican-simplify::dashboard.overview')
    </main>
    @include('pelican-simplify::dashboard.tabbar')
</div>
</x-filament-panels::page>
