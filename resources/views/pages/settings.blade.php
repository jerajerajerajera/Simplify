<x-filament-panels::page>
    <x-filament::section :heading="__('pelican-simplify::settings.addons.heading')">
        @forelse ($this->addons() as $addon)
            <div class="s-row">
                <x-filament::icon :icon="$addon->icon()" class="s-row-icon" />
                <div class="s-row-text">
                    <div>{{ $addon->name() }}</div>
                    @if ($addon->description() !== '')
                        <div class="s-muted">{{ $addon->description() }}</div>
                    @endif
                </div>
                @if ($url = $addon->settingsUrl())
                    <a class="s-btn" href="{{ $url }}">{{ __('pelican-simplify::settings.addons.open') }}</a>
                @endif
            </div>
        @empty
            <p class="s-muted">{{ __('pelican-simplify::settings.addons.none') }}</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
