{{-- Own server groups and server order, saved with the account; a server sits in one group at most. --}}
<div class="ps-overview-appearance ps-overview-groups ps-nav-view" x-show="!server && !hostMode && overviewPreferences.showGroupsButton" x-on:click.outside="groupsMenu=false" x-on:keydown.escape.stop="groupsMenu=false; $refs.overviewGroupsButton.focus()">
    <button type="button" x-ref="overviewGroupsButton" class="ps-btn is-icon is-subtle" data-tip="{{ $u('server_groups') }}" aria-label="{{ $u('server_groups') }}" aria-controls="ps-overview-groups-menu" :aria-expanded="groupsMenu"
            x-on:click="groupsMenu=!groupsMenu; appearanceMenu=false; accountMenu=false; notifyMenu=false">
        <svg aria-hidden="true"><use href="#ps-files"/></svg><span class="ps-mobile-label">{{ $u('server_groups') }}</span>
    </button>
    <div id="ps-overview-groups-menu" class="ps-dropdown is-end ps-overview-appearance-menu" x-show="groupsMenu" x-cloak x-transition.opacity>
        <strong class="ps-dropdown-title">{{ $u('server_groups') }}</strong>
        <p class="ps-dropdown-hint">{{ $u('saved_with_your_account_so_every') }}</p>
        <div class="ps-customize">
            <div class="ps-customize-row">
                <div><strong>{{ $u('server_order') }}</strong><small>{{ $u('for_the_overview_and_for_groups') }}</small></div>
                <div class="ps-segmented" role="group" aria-label="{{ $u('server_order') }}">
                    <template x-for="order in SERVER_ORDERS" :key="order.key">
                        <button type="button" :aria-pressed="overviewServerOrder===order.key" x-on:click="setServerOrder(order.key)" x-text="order.label"></button>
                    </template>
                </div>
            </div>
            <span class="ps-customize-head">{{ $u('my_groups') }}</span>
            <small class="ps-group-note" x-show="!overviewGroups.length">{{ $u('make_your_own_groups_such_as') }}</small>
            <template x-for="(group, index) in overviewGroups" :key="group.id">
                <div class="ps-group-edit" :class="{'is-open': groupEditing===group.id}">
                    <div class="ps-group-row">
                        <button type="button" class="ps-btn is-sm is-icon is-subtle ps-group-toggle" x-on:click="groupEditing = groupEditing===group.id ? null : group.id" :aria-expanded="groupEditing===group.id" :data-tip="groupEditing===group.id ? @js($u('done')) : @js($u('servers_and_order'))" :aria-label="Simplify.fill(@js($u('servers_and_order_for')), { group: group.name })"><svg aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                        <span class="ps-group-dot" x-show="group.color" :style="{'background-color': group.color}" aria-hidden="true"></span>
                        <input type="text" class="ps-input ps-group-name" :value="group.name" maxlength="40" aria-label="{{ $u('group_name') }}" x-on:keydown.enter.prevent="$el.blur()" x-on:change="$el.value = renameOverviewGroup(group.id, $el.value)">
                        <small class="ps-group-count" x-text="Simplify.choice(@js($u('server_count')), groupCount(group))"></small>
                        <button type="button" class="ps-btn is-sm is-icon is-subtle ps-group-up" :disabled="index===0" x-on:click="moveOverviewGroup(group.id, -1)" data-tip="{{ $u('move_up') }}" :aria-label="Simplify.fill(@js($u('move_up_name')), { name: group.name })"><svg aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                        <button type="button" class="ps-btn is-sm is-icon is-subtle ps-group-down" :disabled="index===overviewGroups.length-1" x-on:click="moveOverviewGroup(group.id, 1)" data-tip="{{ $u('move_down') }}" :aria-label="Simplify.fill(@js($u('move_down_name')), { name: group.name })"><svg aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                        <button type="button" class="ps-btn is-sm is-icon is-subtle" x-on:click="removeOverviewGroup(group.id)" data-tip="{{ $u('remove_group') }}" :aria-label="Simplify.fill(@js($u('remove_name')), { name: group.name })"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
                    </div>
                    <div class="ps-group-servers" x-show="groupEditing===group.id">
                        <div class="ps-group-order ps-group-color"><span>{{ $u('group_color') }}</span>
                            <span class="ps-group-color-choice">
                                <label class="ps-swatch ps-group-swatch" :class="{'is-empty': !group.color}" :style="group.color ? {'background-color': group.color} : {}" :data-tip="group.color ? @js($u('change_group_color')) : @js($u('pick_a_group_color'))"><input type="color" class="ps-color-input" :value="group.color || '#878787'" x-on:change="setGroupColor(group.id, $el.value)" :aria-label="Simplify.fill(@js($u('color_for')), { name: group.name })"></label>
                                <button type="button" class="ps-btn is-sm is-subtle" x-show="group.color" x-on:click="setGroupColor(group.id, null)">{{ $u('remove_color') }}</button>
                                <small x-show="!group.color">{{ $u('off_every_server_keeps_its_own') }}</small>
                            </span>
                        </div>
                        <label class="ps-group-order"><span>{{ $u('order_in_this_group') }}</span>
                            <select class="ps-input" :value="group.sort" x-on:change="setGroupOrder(group.id, $el.value)">
                                <template x-for="order in GROUP_ORDERS" :key="order.key"><option :value="order.key" x-text="order.label" :selected="group.sort===order.key"></option></template>
                            </select>
                        </label>
                        <template x-for="(s, position) in groupServerList(group)" :key="s.uuid">
                            <div class="ps-group-server" :class="{'is-member': group.servers.includes(s.uuid)}">
                                <button type="button" class="ps-customize-switch" role="switch" :aria-checked="group.servers.includes(s.uuid)" x-on:click="toggleGroupServer(group.id, s.uuid)">
                                    <span><strong x-text="cardNames(s).large"></strong><small x-show="serverGroup(s.uuid) && serverGroup(s.uuid).id!==group.id" x-text="Simplify.fill(@js($u('now_in')), { group: serverGroup(s.uuid)?.name })"></small></span>
                                    <span class="ps-switch" :class="{'is-on': group.servers.includes(s.uuid)}" aria-hidden="true"></span>
                                </button>
                                <span class="ps-group-server-move" x-show="group.sort==='manual' && group.servers.includes(s.uuid)">
                                    <button type="button" class="ps-btn is-sm is-icon is-subtle ps-group-up" :disabled="position===0" x-on:click="moveGroupServer(group.id, s.uuid, -1)" data-tip="{{ $u('move_up') }}" :aria-label="Simplify.fill(@js($u('move_up_name')), { name: cardNames(s).large })"><svg aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                                    <button type="button" class="ps-btn is-sm is-icon is-subtle ps-group-down" :disabled="position===groupCount(group)-1" x-on:click="moveGroupServer(group.id, s.uuid, 1)" data-tip="{{ $u('move_down') }}" :aria-label="Simplify.fill(@js($u('move_down_name')), { name: cardNames(s).large })"><svg aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                                </span>
                            </div>
                        </template>
                        <small class="ps-group-note" x-show="!(boot.servers||[]).length">{{ $u('no_servers_yet') }}</small>
                    </div>
                </div>
            </template>
            <button type="button" class="ps-btn is-sm ps-group-add" x-show="overviewGroups.length < 30" x-on:click="addOverviewGroup(); $nextTick(() => [...$el.parentElement.querySelectorAll('.ps-group-name')].pop()?.select())"><svg aria-hidden="true"><use href="#ps-plus"/></svg>{{ $u('new_group') }}</button>
        </div>
    </div>
</div>

