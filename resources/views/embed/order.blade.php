{{-- The servers on the website, in their order: ticked servers on top, dragged by the grip or moved with the arrows;
     the others below by name. The state is the ticked ids in order, saved with Save. A ticked server can be pinned:
     pinned servers stay at the top of the list (options.pinned). --}}
@php($t = 'simplify::embed.settings.')
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="se-order" x-data="{
        state: $wire.$entangle(@js($getStatePath())),
        servers: @js($servers),
        pins: $wire.$entangle(@js($pinned)),
        pinIds() { return (Array.isArray(this.pins) ? this.pins : Object.values(this.pins ?? {})).map(Number) },
        pinned(id) { return this.pinIds().includes(id) },
        pin(id) { this.pins = this.pinned(id) ? this.pinIds().filter((other) => other !== id) : [...this.pinIds(), id] },
        query: '',
        dragging: null,
        over: null,
        ids() { return (Array.isArray(this.state) ? this.state : Object.values(this.state ?? {})).map(Number) },
        on(id) { return this.ids().includes(id) },
        rows() {
            const ids = this.ids();
            const byId = Object.fromEntries(this.servers.map((server) => [server.id, server]));
            const rows = [...ids.map((id) => byId[id]).filter(Boolean), ...this.servers.filter((server) => ! ids.includes(server.id))];
            const query = this.query.trim().toLowerCase();
            return query === '' ? rows : rows.filter((server) => server.name.toLowerCase().includes(query));
        },
        toggle(id) {
            if (this.on(id) && this.pinned(id)) this.pin(id);
            this.state = this.on(id) ? this.ids().filter((other) => other !== id) : [...this.ids(), id];
        },
        move(id, step) {
            const ids = this.ids();
            const from = ids.indexOf(id);
            const to = from + step;
            if (from < 0 || to < 0 || to >= ids.length) return;
            ids.splice(to, 0, ids.splice(from, 1)[0]);
            this.state = ids;
        },
        drop(target) {
            const ids = this.ids();
            const from = ids.indexOf(this.dragging);
            const to = ids.indexOf(target);
            if (from >= 0 && to >= 0 && from !== to) {
                ids.splice(to, 0, ids.splice(from, 1)[0]);
                this.state = ids;
            }
            this.dragging = this.over = null;
        },
    }">
        <label class="se-order-search">
            <x-filament::icon icon="tabler-search" />
            <input type="search" x-model="query" placeholder="{{ __($t.'search') }}" aria-label="{{ __($t.'search') }}">
        </label>
        <ol class="se-order-list">
            <template x-for="(server, index) in rows()" :key="server.id">
                <li class="se-order-row" x-bind:class="{ 'is-on': on(server.id), 'is-dragging': dragging === server.id, 'is-over': over === server.id && dragging !== server.id }"
                    x-bind:draggable="on(server.id) && query === ''"
                    x-on:dragstart="dragging = server.id; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', String(server.id))"
                    x-on:dragover="if (dragging !== null && on(server.id)) { $event.preventDefault(); over = server.id }"
                    x-on:drop.prevent="drop(server.id)"
                    x-on:dragend="dragging = over = null">
                    <span class="se-order-grip" x-bind:class="{ 'is-off': ! on(server.id) || query !== '' }" aria-hidden="true"><x-filament::icon icon="tabler-grip-vertical" /></span>
                    <input type="checkbox" class="fi-checkbox-input" x-bind:checked="on(server.id)" x-on:change="toggle(server.id)" x-bind:aria-label="server.name">
                    <template x-if="server.logo"><img class="se-order-logo" x-bind:src="server.logo" alt="" loading="lazy"></template>
                    <template x-if="! server.logo"><span class="se-order-logo" aria-hidden="true" x-text="server.name.slice(0, 1).toUpperCase()"></span></template>
                    <span class="se-order-name" x-text="server.name"></span>
                    <button type="button" class="se-order-pin" x-show="on(server.id)" x-on:click="pin(server.id)" x-bind:class="{ 'is-pinned': pinned(server.id) }"
                        x-bind:aria-pressed="pinned(server.id) ? 'true' : 'false'"
                        x-bind:aria-label="@js(__($t.'pin')).replace(':name', server.name)" x-bind:title="@js(__($t.'pin')).replace(':name', server.name)">
                        <x-filament::icon icon="tabler-pin" />
                    </button>
                    <span class="se-order-moves" x-show="on(server.id) && query === ''">
                        <button type="button" class="se-order-move" x-on:click="move(server.id, -1)" x-bind:disabled="ids().indexOf(server.id) === 0"
                            x-bind:aria-label="@js(__($t.'move_up')).replace(':name', server.name)" x-bind:title="@js(__($t.'move_up')).replace(':name', server.name)">
                            <x-filament::icon icon="tabler-chevron-up" />
                        </button>
                        <button type="button" class="se-order-move" x-on:click="move(server.id, 1)" x-bind:disabled="ids().indexOf(server.id) === ids().length - 1"
                            x-bind:aria-label="@js(__($t.'move_down')).replace(':name', server.name)" x-bind:title="@js(__($t.'move_down')).replace(':name', server.name)">
                            <x-filament::icon icon="tabler-chevron-down" />
                        </button>
                    </span>
                </li>
            </template>
        </ol>
        <p class="se-muted" x-show="rows().length === 0" x-cloak>{{ __($t.'search_none') }}</p>
    </div>
</x-dynamic-component>
<style>
    .se-order { display: grid; gap: 8px; }
    .se-order-search { display: flex; align-items: center; gap: 8px; border: 1px solid var(--s-line); border-radius: 8px; padding: 0 10px; background: var(--s-surface); }
    .se-order-search svg { width: 16px; height: 16px; color: var(--s-muted); flex: none; }
    .se-order-search input { flex: 1; min-width: 0; border: 0; background: transparent; padding: 8px 0; font: inherit; color: inherit; outline: 0; box-shadow: none; }
    .se-order-list { display: grid; gap: 2px; margin: 0; padding: 0; list-style: none; max-height: 420px; overflow-y: auto; }
    .se-order-row { display: flex; align-items: center; gap: 10px; min-width: 0; padding: 6px 8px; border-radius: 8px; border: 1px solid transparent; }
    .se-order-row.is-on { background: var(--s-raised); }
    .se-order-row.is-dragging { opacity: .5; }
    .se-order-row.is-over { border-color: var(--s-accent); }
    .se-order-grip { display: grid; place-items: center; color: var(--s-muted); cursor: grab; }
    .se-order-grip.is-off { visibility: hidden; }
    .se-order-grip svg, .se-order-move svg { width: 16px; height: 16px; }
    .se-order-logo { width: 24px; height: 24px; flex: none; border-radius: 6px; object-fit: contain; display: grid; place-items: center; background: var(--s-surface); font-size: 12px; font-weight: 700; color: var(--s-muted); }
    .se-order-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .se-order-moves { display: flex; gap: 2px; }
    .se-order-pin { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 6px; color: var(--s-muted); }
    .se-order-pin svg { width: 16px; height: 16px; }
    .se-order-pin:hover { background: var(--s-surface); color: var(--s-ink); }
    .se-order-pin.is-pinned { color: var(--s-accent); background: var(--s-surface); }
    .se-order-move { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 6px; color: var(--s-muted); }
    .se-order-move:hover:not(:disabled) { background: var(--s-surface); color: var(--s-ink); }
    .se-order-move:disabled { opacity: .35; }
</style>
