{{-- Simplify's header over Pelican's admin pages while an add-on's host mode is on (AdminTop). --}}
<div class="ps server-active host-mode ps-admin-top" data-simplify-root x-cloak wire:ignore.self
     x-data="simplifyAdminTop($wire, @js($this->initial()))"
     x-on:resize.window.debounce.150ms="applyScale()"
     :class="{'dark': themeDark}"
     :data-season="seasonKeys()">
    @include('simplify::dashboard.assets')
    @include('simplify::dashboard.icons')
    @include('simplify::dashboard.halloween')
    @include('simplify::dashboard.seasons')
    <div class="ps-main">
        @include('simplify::dashboard.header', ['notes' => 'host'])
    </div>
</div>
