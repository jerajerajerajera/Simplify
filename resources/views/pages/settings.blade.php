<x-filament-panels::page>
    <x-filament::section :heading="__('pelican-simplify::settings.addons.heading')">
        @forelse ($this->addons() as $addon)
            <div class="flex items-center gap-3 py-2">
                <x-filament::icon :icon="$addon->icon()" class="h-5 w-5 text-gray-500" />
                <div class="min-w-0 flex-1">
                    <div class="font-medium">{{ $addon->name() }}</div>
                    @if ($addon->description() !== '')
                        <div class="text-sm text-gray-500">{{ $addon->description() }}</div>
                    @endif
                </div>
                @if ($url = $addon->settingsUrl())
                    <x-filament::button tag="a" :href="$url" color="gray" size="sm">
                        {{ __('pelican-simplify::settings.addons.open') }}
                    </x-filament::button>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-500">{{ __('pelican-simplify::settings.addons.none') }}</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
