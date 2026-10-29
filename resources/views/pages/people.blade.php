<x-filament-panels::page>
    <div class="s-people">
        <div class="s-toolbar">
            <div class="s-segments" role="group" aria-label="{{ __('simplify::people.title') }}">
                <button type="button" wire:click="$set('tab', 'people')" @class(['s-segment', 'is-active' => $tab === 'people'])>
                    <x-filament::icon icon="tabler-users" /> {{ __('simplify::people.people') }}
                </button>
                <button type="button" wire:click="$set('tab', 'roles')" @class(['s-segment', 'is-active' => $tab === 'roles'])>
                    <x-filament::icon icon="tabler-shield-lock" /> {{ __('simplify::people.roles.title') }}
                </button>
            </div>
            @if ($tab === 'people')
                <input type="search" class="s-search" wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('simplify::people.search') }}" aria-label="{{ __('simplify::people.search') }}">
                <div class="s-bar">
                    <button type="button" class="s-btn s-btn-primary" wire:click="mountAction('addPerson')">
                        <x-filament::icon icon="tabler-user-plus" /> {{ __('simplify::people.add') }}
                    </button>
                </div>
            @else
                <div class="s-bar">
                    <button type="button" class="s-btn s-btn-primary" wire:click="mountAction('addRole')">
                        <x-filament::icon icon="tabler-shield-plus" /> {{ __('simplify::people.roles.add') }}
                    </button>
                </div>
            @endif
        </div>

        @if ($tab === 'people')
            @php($people = $this->people())
            <div class="s-card">
                @forelse ($people['rows'] as $person)
                    <div class="s-row" wire:key="person-{{ $person['id'] }}">
                        <span class="s-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($person['username'], 0, 1)) }}</span>
                        <div class="s-row-text">
                            <div>
                                {{ $person['username'] }}
                                @if ($person['self'])
                                    <span class="s-badge">{{ __('simplify::people.you') }}</span>
                                @endif
                            </div>
                            <div class="s-muted">{{ $person['email'] }}</div>
                            <div class="s-chips">
                                @forelse ($person['roles'] as $role)
                                    <span @class(['s-badge', 's-badge-ok' => $role === \App\Models\Role::ROOT_ADMIN])>{{ $role }}</span>
                                @empty
                                    <span class="s-badge">{{ __('simplify::people.no_role') }}</span>
                                @endforelse
                            </div>
                        </div>
                        <span class="s-muted s-person-meta" title="{{ __('simplify::people.servers') }}">
                            <x-filament::icon icon="tabler-server" /> {{ $person['servers'] }}
                        </span>
                        <span @class(['s-badge', 's-badge-ok' => $person['mfa']]) title="{{ __($person['mfa'] ? 'simplify::people.mfa_on' : 'simplify::people.mfa_off') }}">2FA</span>
                        <div class="s-bar">
                            <button type="button" class="s-btn s-btn-small" wire:click="mountAction('editPerson', { user: {{ $person['id'] }} })">
                                {{ __('simplify::people.edit_short') }}
                            </button>
                            <button type="button" class="s-btn s-btn-icon" wire:click="mountAction('resetPassword', { user: {{ $person['id'] }} })"
                                title="{{ __('simplify::people.reset') }}" aria-label="{{ __('simplify::people.reset') }}">
                                <x-filament::icon icon="tabler-key" />
                            </button>
                            <button type="button" class="s-btn s-btn-icon" @disabled($person['self'] || $person['servers'])
                                wire:click="mountAction('deletePerson', { user: {{ $person['id'] }} })"
                                title="{{ __('simplify::people.delete') }}" aria-label="{{ __('simplify::people.delete') }}">
                                <x-filament::icon icon="tabler-trash" />
                            </button>
                        </div>
                    </div>
                @empty
                    <p class="s-muted">{{ __('simplify::people.none', ['search' => $search]) }}</p>
                @endforelse
            </div>
            @if ($people['pages'] > 1)
                <div class="s-bar s-pages">
                    <button type="button" class="s-btn s-btn-small" @disabled($peoplePage <= 1) wire:click="setPage({{ $peoplePage - 1 }})">{{ __('simplify::people.previous') }}</button>
                    <span class="s-muted">{{ __('simplify::people.page', ['page' => $peoplePage, 'pages' => $people['pages']]) }}</span>
                    <button type="button" class="s-btn s-btn-small" @disabled($peoplePage >= $people['pages']) wire:click="setPage({{ $peoplePage + 1 }})">{{ __('simplify::people.next') }}</button>
                </div>
            @endif
        @else
            <div class="s-card">
                @foreach ($this->roles() as $role)
                    <div class="s-row" wire:key="role-{{ $role['id'] }}">
                        <x-filament::icon :icon="$role['root'] ? 'tabler-crown' : 'tabler-shield'" class="s-row-icon" />
                        <div class="s-row-text">
                            <div>{{ $role['name'] }}</div>
                            <div class="s-muted">
                                {{ trans_choice('simplify::people.roles.members', $role['members']) }} ·
                                {{ $role['root'] ? __('simplify::people.roles.everything') : trans_choice('simplify::people.roles.permission_count', $role['permissions']) }}
                            </div>
                        </div>
                        @if ($role['root'])
                            <span class="s-muted">{{ __('simplify::people.roles.locked') }}</span>
                        @else
                            <div class="s-bar">
                                <button type="button" class="s-btn s-btn-small" wire:click="mountAction('editRole', { role: {{ $role['id'] }} })">{{ __('simplify::people.edit_short') }}</button>
                                <button type="button" class="s-btn s-btn-icon" wire:click="mountAction('deleteRole', { role: {{ $role['id'] }} })"
                                    title="{{ __('simplify::people.roles.delete') }}" aria-label="{{ __('simplify::people.roles.delete') }}">
                                    <x-filament::icon icon="tabler-trash" />
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
