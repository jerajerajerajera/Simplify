<x-filament-panels::page>
    <div class="s-done">
        <div class="s-done-head">
            <span class="s-done-check"><x-filament::icon icon="tabler-check" /></span>
            <div>
                <h2>{{ __('simplify::setup.addon_pages') }}</h2>
                <p class="s-muted">{{ __('simplify::setup.addon_pages_help') }}</p>
            </div>
        </div>
        @include('simplify::partials.addon-list')
    </div>
</x-filament-panels::page>
