@php
    $servers = $this->servers();
    $actions = $this->actions();
@endphp
<x-filament-panels::page>
    <div x-data="{ search: '' }" wire:init="refreshUsage" wire:poll.15s.visible="refreshUsage" class="s-overview">
        <div class="s-toolbar">
            <input
                type="search"
                class="s-search"
                x-model="search"
                placeholder="{{ __('pelican-simplify::overview.search') }}"
                aria-label="{{ __('pelican-simplify::overview.search') }}"
            >
            @if ($actions)
                <div class="s-bar">
                    @foreach ($actions as $action)
                        <a href="{{ $action->url }}" @class(['s-btn', 's-btn-primary' => $action->primary])>
                            @if ($action->icon)
                                <x-filament::icon :icon="$action->icon" />
                            @endif
                            {{ $action->label }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($servers->isEmpty())
            <div class="s-card s-muted">{{ __('pelican-simplify::overview.empty') }}</div>
        @else
            <div class="s-tiles">
                @foreach ($servers as $server)
                    <x-pelican-simplify::server-tile
                        :server="$server"
                        :usage="$usage[$server->id] ?? null"
                        :url="$this->serverUrl($server)"
                        :badges="$this->badges($server)"
                        :power="$this->powerActions($server)"
                    />
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
