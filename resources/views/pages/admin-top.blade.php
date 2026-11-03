@php($u = fn (string $key, array $values = []) => __('simplify::dashboard.ui.'.$key, $values))
{{-- Simplify's header over Pelican's admin and account pages (AdminTop). --}}
<div class="ps server-active host-mode ps-admin-top" data-simplify-root x-cloak wire:ignore.self
     x-data="simplifyAdminTop($wire, @js($this->initial()))"
     x-on:resize.window.debounce.150ms="applyScale()"
     x-on:simplify:create.window="createButtons().length && (openCreate(null, createButtons()[0]) || (location.href = createButtons()[0].url))"
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
