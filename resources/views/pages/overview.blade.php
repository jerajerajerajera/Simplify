@php
    $servers = $this->servers();
    $sections = $this->sections();
    $actions = $this->actions();
@endphp
<x-filament-panels::page>
    <div x-data="{ search: '' }" wire:init="refreshUsage" wire:poll.{{ $this->refreshSeconds() }}s.visible="refreshUsage" class="s-overview">
        <div class="s-toolbar">
            @if ($this->scopeShown())
                <div class="s-segments" role="group" aria-label="{{ __('pelican-simplify::overview.scope.label') }}">
                    @foreach (\Simplify\Core\Filament\App\Pages\Overview::SCOPES as $option)
                        <button type="button" wire:click="$set('scope', '{{ $option }}')" @class(['s-segment', 'is-active' => $scope === $option])>
                            {{ __('pelican-simplify::overview.scope.'.$option) }}
                        </button>
                    @endforeach
                </div>
            @endif
            <input
                type="search"
                class="s-search"
                x-model="search"
                placeholder="{{ __('pelican-simplify::overview.search') }}"
                aria-label="{{ __('pelican-simplify::overview.search') }}"
            >
            <div class="s-bar">
                <button type="button" class="s-btn" wire:click="editGroups">
                    <x-filament::icon icon="tabler-folders" />
                    {{ __('pelican-simplify::overview.groups.edit') }}
                </button>
                <select class="s-select" wire:model.live="sort" aria-label="{{ __('pelican-simplify::overview.sort.label') }}">
                    @foreach (\Simplify\Core\Filament\App\Pages\Overview::SORTS as $option)
                        <option value="{{ $option }}">{{ __('pelican-simplify::overview.sort.'.$option) }}</option>
                    @endforeach
                </select>
                <div class="s-segments" role="group" aria-label="{{ __('pelican-simplify::overview.layout.label') }}">
                    @foreach (['cards' => 'tabler-layout-grid', 'compact' => 'tabler-layout-list', 'list' => 'tabler-list'] as $option => $icon)
                        <button
                            type="button"
                            wire:click="$set('display', '{{ $option }}')"
                            @class(['s-segment', 's-segment-icon', 'is-active' => $display === $option])
                            title="{{ __('pelican-simplify::overview.layout.'.$option) }}"
                            aria-label="{{ __('pelican-simplify::overview.layout.'.$option) }}"
                        >
                            <x-filament::icon :icon="$icon" />
                        </button>
                    @endforeach
                </div>
                @foreach ($actions as $action)
                    <a href="{{ $action->url }}" @class(['s-btn', 's-btn-primary' => $action->primary])>
                        @if ($action->icon)
                            <x-filament::icon :icon="$action->icon" />
                        @endif
                        {{ $action->label }}
                    </a>
                @endforeach
            </div>
        </div>

        @if ($editingGroups)
            @include('pelican-simplify::partials.group-editor')
        @endif

        @if ($servers->isEmpty())
            <div class="s-card s-muted">{{ __('pelican-simplify::overview.empty') }}</div>
        @else
            @foreach ($sections['groups'] as $section)
                <section class="s-section" wire:key="group-{{ $section['group']['id'] }}">
                    <h2 class="s-section-title">
                        <x-filament::icon icon="tabler-folder" class="s-section-icon" :style="$section['group']['color'] ? 'color: '.$section['group']['color'] : null" />
                        {{ $section['group']['name'] }}
                    </h2>
                    <div class="s-tiles s-tiles-{{ $display }}">
                        @foreach ($section['servers'] as $server)
                            <x-pelican-simplify::server-tile
                                :server="$server"
                                :usage="$usage[$server->id] ?? null"
                                :url="$this->serverUrl($server)"
                                :badges="$this->badges($server)"
                                :power="$this->powerActions($server)"
                                :color="$section['group']['color']"
                            />
                        @endforeach
                    </div>
                </section>
            @endforeach
            @if ($sections['rest'])
                <section class="s-section">
                    @if ($sections['groups'])
                        <h2 class="s-section-title">{{ __('pelican-simplify::overview.groups.other') }}</h2>
                    @endif
                    <div class="s-tiles s-tiles-{{ $display }}">
                        @foreach ($sections['rest'] as $server)
                            <x-pelican-simplify::server-tile
                                :server="$server"
                                :usage="$usage[$server->id] ?? null"
                                :url="$this->serverUrl($server)"
                                :badges="$this->badges($server)"
                                :power="$this->powerActions($server)"
                            />
                        @endforeach
                    </div>
                </section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
