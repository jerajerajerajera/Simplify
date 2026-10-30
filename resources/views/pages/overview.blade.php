@php($u = fn (string $key, array $values = []) => __('simplify::dashboard.ui.'.$key, $values))
<x-filament-panels::page>
@include('simplify::dashboard.assets')
<div class="ps" data-simplify-root x-cloak wire:ignore.self
     x-data="pelicanSimplify($wire, @js($this->initial()))"
     x-on:resize.window.debounce.150ms="applyScale(); restoreOverviewView()"
     :class="{'dark': themeDark, 'host-mode': hostMode, 'is-full-width': overviewPreferences.fullWidth, 'ps-halloween': halloween()}"
     :data-season="seasonKeys()"
     :style="accentStyle('overview')">
    @include('simplify::dashboard.icons')
    @include('simplify::dashboard.halloween')
    @include('simplify::dashboard.seasons')
    <main class="ps-main">
        @include('simplify::dashboard.header', ['notes' => 'host'])
        @include('simplify::dashboard.host')
        @include('simplify::dashboard.overview')
    </main>
    @include('simplify::dashboard.tabbar')
</div>
</x-filament-panels::page>
