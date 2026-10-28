<div class="s-card s-editor">
    <div class="s-editor-head">
        <h2 class="s-section-title">{{ __('pelican-simplify::overview.groups.title') }}</h2>
        <div class="s-bar">
            <button type="button" class="s-btn" wire:click="$set('editingGroups', false)">{{ __('pelican-simplify::overview.groups.cancel') }}</button>
            <button type="button" class="s-btn s-btn-primary" wire:click="saveGroups">{{ __('pelican-simplify::overview.groups.save') }}</button>
        </div>
    </div>

    @foreach ($draft as $index => $group)
        <div class="s-row" wire:key="draft-{{ $group['id'] }}">
            <input type="color" class="s-color" wire:model="draft.{{ $index }}.color" aria-label="{{ __('pelican-simplify::overview.groups.color') }}">
            <input
                type="text"
                class="s-input s-row-text"
                wire:model="draft.{{ $index }}.name"
                maxlength="{{ \Simplify\Core\Overview\ServerGroups::NAME_LENGTH }}"
                placeholder="{{ __('pelican-simplify::overview.groups.name') }}"
            >
            <button type="button" class="s-btn s-btn-icon" wire:click="moveGroup({{ $index }}, -1)" @disabled($loop->first) aria-label="{{ __('pelican-simplify::overview.groups.up') }}">
                <x-filament::icon icon="tabler-arrow-up" />
            </button>
            <button type="button" class="s-btn s-btn-icon" wire:click="moveGroup({{ $index }}, 1)" @disabled($loop->last) aria-label="{{ __('pelican-simplify::overview.groups.down') }}">
                <x-filament::icon icon="tabler-arrow-down" />
            </button>
            <button type="button" class="s-btn s-btn-icon" wire:click="removeGroup({{ $index }})" aria-label="{{ __('pelican-simplify::overview.groups.remove') }}">
                <x-filament::icon icon="tabler-trash" />
            </button>
        </div>
    @endforeach
    @if (count($draft) < \Simplify\Core\Overview\ServerGroups::LIMIT)
        <button type="button" class="s-btn" wire:click="addGroup">
            <x-filament::icon icon="tabler-plus" />
            {{ __('pelican-simplify::overview.groups.add') }}
        </button>
    @endif

    @if ($draft)
        <h3 class="s-editor-sub">{{ __('pelican-simplify::overview.groups.servers') }}</h3>
        @foreach ($this->allServers()->sortBy(fn ($server) => mb_strtolower($server->name)) as $server)
            <div class="s-row" wire:key="assign-{{ $server->id }}">
                <span class="s-row-text">{{ $server->name }}</span>
                <select class="s-select" wire:model="assign.{{ $server->id }}">
                    <option value="">{{ __('pelican-simplify::overview.groups.none') }}</option>
                    @foreach ($draft as $group)
                        <option value="{{ $group['id'] }}">{{ $group['name'] ?: __('pelican-simplify::overview.groups.unnamed') }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
    @endif
</div>
