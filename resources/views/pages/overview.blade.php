@php($u = fn (string $key, array $values = []) => __('pelican-simplify::dashboard.ui.'.$key, $values))
<x-filament-panels::page>
@assets
    @foreach (['tokens.css', 'components.css', 'dashboard.css'] as $file)
        <link rel="stylesheet" href="{{ \Simplify\Core\Support\Assets::url($file) }}">
    @endforeach
    <script>window.simplifyLang = @js(__('pelican-simplify::js'));</script>
    @foreach (['core.js', 'motd.js', 'dashboard.js'] as $file)
        <script src="{{ \Simplify\Core\Support\Assets::url($file) }}"></script>
    @endforeach
@endassets
<div class="ps" data-simplify-root x-cloak wire:ignore.self
     x-data="pelicanSimplify($wire, @js($this->initial()))"
     x-on:resize.window.debounce.150ms="applyScale(); restoreOverviewView()"
     :class="{'dark': themeDark, 'is-full-width': overviewPreferences.fullWidth, 'ps-halloween': halloween()}"
     :style="accentStyle('overview')">
    @include('pelican-simplify::dashboard.icons')
    {{-- Halloween (25 October to 2 November): a faint web with a spider in the corner. Clicks pass through. --}}
    <template x-if="halloween()">
        <div class="ps-hw-corner" aria-hidden="true">
            <svg class="ps-hw-web" viewBox="0 0 100 100"><path d="M100 0H0M100 0 10 45M100 0 40 80M100 0V100M100 0 72 95M78 0Q81.1 7 80.2 9.9Q85.5 15.8 86.8 17.6Q92.3 21.3 93.8 20.9Q98.9 23.4 100 22M60 0Q64 11 64 18Q72 27 76 32Q84.4 37 88.8 38Q96.4 41 100 40M40 0Q45 15.5 46 27Q57 39.5 64 48Q75.6 54.5 83.2 57Q93.6 60.5 100 60M20 0Q26 20 28 36Q42 52 52 64Q66.8 72 77.6 76Q90.8 80 100 80"/></svg>
            <span class="ps-hw-spider"><svg viewBox="0 0 14 14"><circle cx="7" cy="7" r="3.2"/><path d="M4 5 1 2M4 7H0M4 9l-3 3M10 5l3-3M10 7h4M10 9l3 3"/></svg></span>
        </div>
    </template>
    <main class="ps-main">
        @include('pelican-simplify::dashboard.header')
        @include('pelican-simplify::dashboard.overview')
    </main>
    @include('pelican-simplify::dashboard.tabbar')
</div>
</x-filament-panels::page>
