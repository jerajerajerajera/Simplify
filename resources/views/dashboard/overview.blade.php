{{-- Overview -------------------------------------------------------------------------------- --}}
<section class="ps-overview" :class="{'is-list': overviewView==='list'}" :style="{'--grid-columns': overviewColumns(), ...overviewGridStyle()}">
    <div class="ps-server-scroll" tabindex="0" role="region" aria-label="{{ $u('servers') }}">
        <div class="ps-server-stage" :class="{'is-list': overviewView==='list'}">
            {{-- Pinned announcements under the top bar, as wide as the servers (as in 0.7.0). --}}
            @livewire('simplify-core-announcement-bars', key('simplify-overview-bars'))
            {{-- Customers: a greeting with the links add-ons give them (such as a hoster's client area) and support. --}}
            <div class="ps-customer-head" x-show="boot.customer?.length" x-cloak>
                <div>
                    <h2 x-text="Simplify.fill(@js($u('customer_greeting')), { name: boot.user })"></h2>
                    <p x-text="Simplify.choice(@js($u('customer_servers')), boot.servers.length)"></p>
                </div>
                <div class="ps-customer-links">
                    <template x-for="link in boot.customer || []" :key="link.url">
                        <a class="ps-btn" :href="safeLink(link.url)" target="_blank" rel="noopener noreferrer"><span x-text="link.label"></span><svg aria-hidden="true"><use href="#ps-external"/></svg></a>
                    </template>
                </div>
            </div>
            <div class="ps-overview-toolbar" x-init="scrollHint($el)">
                {{-- One Filter (Khion, V52): who, game, node and the own server groups in one list; on a phone a sheet. --}}
                <div class="ps-overview-filters ps-filter" x-init="filterText = @js(collect(['filter_show', 'all_servers', 'my_servers', 'others', 'game', 'all_games', 'filter_node', 'all_nodes', 'filter_group', 'all_groups'])->mapWithKeys(fn ($key) => [$key => $u($key)]))" x-show="filterSections().length" x-on:click.outside="filterMenu=false" x-on:keydown.escape.stop="filterMenu=false; $refs.filterButton.focus()">
                    <button type="button" x-ref="filterButton" class="ps-chip ps-filter-button" :class="{'is-on': filterActive()}" aria-controls="ps-filter-menu" :aria-expanded="filterMenu" aria-haspopup="menu"
                            x-on:click="$refs.filterMenu.style.setProperty('--ps-room', Math.max(160, innerHeight - $el.getBoundingClientRect().bottom - 24) + 'px'); filterMenu=!filterMenu; appearanceMenu=false; groupsMenu=false; accountMenu=false; notifyMenu=false">
                        <svg aria-hidden="true"><use href="#ps-filter"/></svg><span x-text="filterLabel()">{{ $u('all_servers') }}</span><svg class="ps-filter-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg>
                    </button>
                    <div id="ps-filter-menu" x-ref="filterMenu" class="ps-dropdown ps-filter-menu" x-show="filterMenu" x-cloak x-transition.opacity role="menu" aria-label="{{ $u('filter') }}">
                        <strong class="ps-dropdown-title">{{ $u('filter') }}</strong>
                        <template x-for="section in filterSections()" :key="section.key">
                            <div class="ps-filter-section" role="group" :aria-label="section.label">
                                <span class="ps-label" x-text="section.label"></span>
                                <input type="search" class="ps-input ps-node-search" x-show="section.key==='node' && section.options.length > 4" x-model.debounce.200ms="nodeSearch" placeholder="{{ $u('search_nodes') }}" aria-label="{{ $u('search_nodes') }}">
                                <template x-for="option in section.options.filter((o) => section.key !== 'node' || o.value === 'all' || !nodeSearch || o.label.toLowerCase().includes(nodeSearch.toLowerCase()))" :key="section.key + '-' + option.value">
                                    <button type="button" class="ps-menu-item" role="menuitemradio" :aria-checked="filterValue(section.key) === option.value" x-on:click="setFilter(section.key, option.value)">
                                        <span x-text="option.label"></span><svg class="ps-filter-check" aria-hidden="true"><use href="#ps-check"/></svg>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
                {{-- Create buttons at the end of the filter row, unless they sit in the top bar with host health. --}}
                <div class="ps-create-buttons" x-show="healthPlace()!=='top-bar' && createButtons().length">
                    @include('simplify::dashboard.create-buttons')
                </div>
            </div>
            <div class="ps-server-grid" :class="{'is-list': overviewView==='list', 'is-compact': overviewView==='compact', 'has-motd': overviewPreferences.showMotd, 'no-metrics': !overviewMetrics().length, 'no-quick-actions': quickActionsMode()==='off', 'quick-actions-on': quickActionsMode()==='on'}">
                <template x-for="s in overviewItems()" :key="s.uuid">
                    <div class="ps-server-entry" :class="{'is-node-heading': !!s.isNodeHeader, 'is-suspended': !!(s.suspended || []).length, 'has-card-motd': overviewPreferences.showMotd && s.game==='minecraft' && !!s.motd, 'has-power': quickActionsMode()!=='off' && Object.values(s.permissions || {}).some(Boolean), 'power-shown': quickActionsMode()==='on', 'confirming-power': powerConfirmation?.uuid===s.uuid}" :style="accentStyle(s.uuid)">
                        <template x-if="s.isNodeHeader">
                            <h2 class="ps-node-heading" :class="{'has-color': !!s.color}"><svg aria-hidden="true"><use :href="'#ps-'+(s.icon || 'host')"/></svg><span x-text="s.name"></span><small x-text="Simplify.choice(@js($u('server_count')), s.count)"></small></h2>
                        </template>
                        <template x-if="!s.isNodeHeader">
                        <article class="ps-server-card">
                            <span class="ps-offline-artwork" x-show="s.status==='offline'" aria-label="{{ $u('server_offline') }}"><svg aria-hidden="true"><use href="#ps-offline"/></svg></span>
                            <img class="ps-card-background" x-show="s.background && s.status!=='offline'" :src="safeImage(s.background)" alt="" loading="lazy" x-on:error="$el.style.visibility='hidden'">
                            <button class="ps-server-open" x-on:click="s.url && (location.href=s.url)" :aria-disabled="!s.url" :data-tip="s.url ? null : Simplify.fill(@js($u('not_assigned')), { name: s.name })" :aria-label="Simplify.fill(@js($u('open_name')), { name: s.name })">
                                <div class="ps-card-head">
                                    {{-- Without an icon of its own the server shows its egg's icon large, and not again as the small one. --}}
                                    <span class="ps-card-icons" :class="{'egg-primary': overviewPreferences.largeIcon==='egg' && !!s.egg_icon && !!(s.server_icon || s.icon)}">
                                        <span class="ps-card-icon">
                                            <img x-show="serverIcon(s)" :src="serverIcon(s)" alt="{{ $u('server_icon') }}" loading="lazy" x-on:error="imageFailed($el.getAttribute('src'))">
                                            <svg x-show="!serverIcon(s)" aria-hidden="true"><use :href="kindIcon(s)"/></svg>
                                        </span>
                                        <span class="ps-card-egg-icon" x-show="s.egg_icon && (s.server_icon || s.icon)" data-tip-label="Server software" :data-tip="softwareTooltip(s)" :aria-label="softwareTooltip(s)">
                                            <img :src="safeImage(s.egg_icon)" alt="" loading="lazy" x-on:load="$el.style.visibility=''" x-on:error="$el.style.visibility='hidden'">
                                        </span>
                                    </span>
                                </div>
                                <div class="ps-card-title">
                                    <div class="ps-card-title-row" x-data="{observer:null,width:0,init(){this.observer=new ResizeObserver(()=>{const w=this.$el.clientWidth;if(w!==this.width){this.width=w;this.fitCardTitle(this.$el,s.name)}});this.observer.observe(this.$el)},destroy(){this.observer?.disconnect()}}"
                                         x-effect="const name=s.name; $nextTick(()=>fitCardTitle($el,name))">
                                        <h3 x-text="s.name"></h3>
                                    </div>
                                    <div class="ps-card-motd ps-motd-preview ps-motd-display" x-show="overviewPreferences.showMotd && s.game==='minecraft' && s.motd" x-data="{observer:null,init(){this.observer=new ResizeObserver(()=>this.fitMotd(this.$el));this.observer.observe(this.$el)},destroy(){this.observer?.disconnect()}}" x-effect="s.motd; overviewPreferences.showMotd; $nextTick(()=>fitMotd($el))"><template x-for="(part,index) in motdPreview(s.motd)" :key="index"><span :style="part.style" x-text="part.text"></span></template></div>
                                    {{-- Add-on badges: on cards the first (such as the version) joins game and status in the corner and the rest (such as the address) get the full width under the name; the list shows them all under the name. --}}
                                    <div class="ps-card-extras" x-show="(s.badges || []).length > (overviewView==='grid' ? 1 : 0)"><template x-for="badge in (s.badges || []).slice(overviewView==='grid' ? 1 : 0)" :key="badge"><span class="ps-badge" x-text="badge"></span></template></div>
                                </div>
                                <div class="ps-card-usage" x-show="overviewMetrics().length" :class="{'single-metric': overviewMetrics().length===1}">
                                    <template x-for="metric in overviewMetrics()" :key="metric.key">
                                        <div class="ps-card-mini-metric">
                                            <span class="ps-card-usage-label">
                                                <span class="ps-card-metric-name"><svg aria-hidden="true"><use :href="'#ps-'+metric.icon"/></svg><span x-text="metric.label"></span></span>
                                                <strong x-text="metric.key==='cpu' ? cpuLabel(s) : cardRamLabel(s)"></strong>
                                            </span>
                                            <svg class="ps-card-graph" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true">
                                                <path class="ps-card-graph-axis" d="M2 27H98"/>
                                                <path :d="cardGraph(s, metric.key)"/>
                                                <circle x-show="cardGraphPoint(s, metric.key)!==null" cx="98" :cy="cardGraphPoint(s, metric.key) || 0" r="1.4"/>
                                            </svg>
                                        </div>
                                    </template>
                                </div>
                                <div class="ps-card-badges ps-identity-badges">
                                    <span class="ps-badge is-strong ps-list-game"><svg aria-hidden="true"><use :href="s.game==='minecraft' ? '#ps-cube' : '#ps-gamepad'"/></svg><span x-text="s.game_label"></span></span>
                                    <span class="ps-badge ps-status" :class="s.status"><i></i><span x-text="Simplify.status(s.status)"></span></span>
                                    <span class="ps-badge ps-season-cake" x-show="serverBirthday(s)" :data-tip="serverBirthdayTip(s)" :aria-label="serverBirthdayTip(s)"><svg aria-hidden="true"><use href="#ps-cake"/></svg></span>
                                    <template x-for="badge in (s.badges || []).slice(0, overviewView==='grid' ? 1 : 0)" :key="badge"><span class="ps-badge" x-text="badge"></span></template>
                                </div>
                                <div class="ps-card-bottom" x-show="overviewView!=='list'">
                                    <span x-text="playerCount(s)"></span>
                                    <svg x-show="s.url" aria-hidden="true"><use href="#ps-chevron"/></svg>
                                </div>
                            </button>
                            <div class="ps-card-footer" x-show="overviewView==='list'">
                                <button type="button" class="ps-list-player-count" x-show="overviewView==='list'" x-on:click="s.url && (location.href=s.url)" :aria-disabled="!s.url" :aria-label="Simplify.fill(@js($u('open_name')), { name: s.name })">
                                    <span x-text="playerCount(s)"></span>
                                    <svg aria-hidden="true"><use href="#ps-chevron"/></svg>
                                </button>
                            </div>
                            <template x-if="easterEgg(s)">
                                <button type="button" class="ps-season-egg" aria-label="{{ $u('easter_egg') }}" x-on:click="findEasterEgg($event)"><svg aria-hidden="true"><use href="#ps-egg"/></svg></button>
                            </template>
                            {{-- Suspended by the hoster: the owner reads why and gets the add-ons' main links, such as Pay invoice from Simplify-Hosting. --}}
                            <div class="ps-card-suspended" x-show="(s.suspended || []).length" x-cloak>
                                <p>{{ $u('suspended_note') }}</p>
                                <template x-for="link in s.suspended || []" :key="link.url">
                                    <a class="ps-btn is-primary" :href="safeLink(link.url)" target="_blank" rel="noopener noreferrer"><span x-text="link.label"></span><svg aria-hidden="true"><use href="#ps-external"/></svg></a>
                                </template>
                            </div>
                            <div class="ps-overview-power-wrap" x-show="quickActionsMode()!=='off' && !(s.suspended || []).length">
                                <div class="ps-overview-power" :class="{'is-confirming': powerConfirmation?.uuid===s.uuid}" role="group" :aria-label="Simplify.fill(@js($u('power_controls_for')), { name: s.name })">
                                    <template x-for="action in [{key:'start',label:@js($u('start')),icon:'play'},{key:'stop',label:@js($u('stop')),icon:'stop'},{key:'restart',label:@js($u('restart')),icon:'restart'}]" :key="action.key">
                                        <button type="button" class="ps-btn is-icon" :data-power="action.key" :data-tip="action.label" :aria-label="action.label+' '+s.name" :disabled="!!overviewPowerBusy || !canOverviewPower(s, action.key)" x-on:click.stop="overviewPower(s, action.key)">
                                            <svg aria-hidden="true"><use :href="'#ps-'+action.icon"/></svg>
                                        </button>
                                    </template>
                                    <div class="ps-power-confirm" x-show="powerConfirmation?.uuid===s.uuid" x-cloak>
                                        <button type="button" class="ps-btn is-icon" data-tip="{{ $u('cancel') }}" aria-label="{{ $u('cancel_power_action') }}" x-on:click.stop="powerConfirmation=null"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
                                        <button type="button" class="ps-btn is-icon is-primary" :data-tip="Simplify.fill(@js($u('confirm_action')), { action: powerActionLabel(powerConfirmation?.action) })+(powerConfirmation?.action==='kill' ? ' — '+@js($u('unsaved_progress_lost')) : '')" :aria-label="Simplify.fill(@js($u('confirm_action')), { action: powerActionLabel(powerConfirmation?.action) })" x-on:click.stop="overviewPower(s, powerConfirmation.action, true)"><svg aria-hidden="true"><use href="#ps-check"/></svg></button>
                                    </div>
                                </div>
                            </div>
                        </article>
                        </template>
                    </div>
                </template>
            </div>
            <p x-show="!boot.servers.length" class="ps-empty">{{ $u('no_servers_yet') }}</p>
        </div>
    </div>

    {{-- Host health: the details under the button in the top bar, its only place (V43). --}}
    <section id="ps-health-panel" class="ps-health-panel" :style="accentStyle('overview')" x-on:click.outside="if (!$event.target.closest('.ps-health-pill')) { healthExpanded=false; healthOpenNodes=[]; healthPopover=false }" x-on:keydown.escape="if (healthPopover) { healthPopover=false; $root.querySelector('.ps-health-pill')?.focus() }" x-on:resize.window="healthPopover && placeHealthPopover()" x-on:scroll.window.passive="healthPopover && placeHealthPopover()" x-show="healthPlace() && healthPopover" :class="{'expanded': healthExpanded, 'is-popover': true}">
        <article class="ps-host-health" role="button" tabindex="0" aria-label="{{ $u('view_health_by_node') }}" :aria-expanded="healthExpanded" aria-controls="ps-health-details"
                 x-on:click="openHostHealth()" x-on:keydown.enter.prevent="openHostHealth()" x-on:keydown.space.prevent="openHostHealth()">
            <div class="ps-host-state">
                <span class="ps-host-emblem ps-health-heart" :class="boot.host_health?.state || 'unavailable'" role="img" :aria-label="Simplify.fill(@js($u('host_health_label')), { state: boot.host_health?.label || @js($u('unavailable')) })">
                    <svg class="ps-host-heart" aria-hidden="true"><use href="#ps-heart"/></svg>
                    <span class="ps-host-server-badge"><svg aria-hidden="true"><use href="#ps-host"/></svg></span>
                </span>
                <div>
                    <div class="ps-host-heading"><h3>{{ $u('host_health') }}</h3><button type="button" class="ps-health-refresh" :class="{'is-busy': healthRefreshing}" :disabled="healthRefreshing" x-on:click.stop="refreshHostHealth()" x-on:keydown.stop aria-label="{{ $u('refresh_host_health') }}" data-tip="{{ $u('refresh_now') }}"><svg aria-hidden="true"><use href="#ps-restart"/></svg></button></div>
                    <span class="ps-health-status" :class="boot.host_health?.state || 'unavailable'"><strong x-text="boot.host_health?.label || @js($u('unavailable'))"></strong><small x-text="Simplify.tc('dashboard.hosts_reporting', boot.host_health?.hosts ?? 0, { available: boot.host_health?.available ?? 0 })"></small></span>
                    <small class="ps-health-problem" x-show="boot.host_health?.problem" x-text="boot.host_health?.problem"></small>
                    <small class="ps-health-phone-summary" x-text="Simplify.fill(@js($u('cpu_ram')), { cpu: hostValue('cpu'), ram: hostValue('memory') })"></small>
                </div>
            </div>
            <template x-for="metric in hostMetrics" :key="metric.key">
                <div class="ps-host-metric">
                    {{-- One line per meter: label, a heart only when it needs attention, and the value; the bar below. Network shows its rate on the second line and its total as a tip. --}}
                    <div class="ps-host-heading">
                        <svg aria-hidden="true"><use :href="'#ps-'+metric.icon"/></svg><span x-text="metric.label" :data-tip="metric.key==='network' ? Simplify.fill(@js($u('used')), { amount: hostValue('network') }) : (metric.key==='cpu' && boot.host_health?.hosts>1 ? @js($u('average_across_reporting_hosts')) : null)"></span>
                        <span class="ps-health-heart" :class="hostMetricState(metric.key)" role="img" :aria-label="metric.label+': '+hostMetricLabel(metric.key)" :data-tip="hostMetricLabel(metric.key)"><svg aria-hidden="true"><use href="#ps-heart"/></svg></span>
                        <strong :class="{'ps-network-value': metric.key==='network'}" x-text="metric.key==='network' ? hostRate() : hostValue(metric.key)"></strong>
                    </div>
                    <span class="ps-meter" x-show="metric.key!=='network'"><span :style="{width: hostFill(metric.key)+'%'}"></span></span>
                </div>
            </template>
        </article>
        <div id="ps-health-details" class="ps-health-drawer" :inert="!healthExpanded" :aria-hidden="!healthExpanded">
            <div>
                <div class="ps-health-detail-toolbar">{{ $u('expand_a_node_to_view_its') }}</div>
                <div class="ps-node-health-list">
                    <template x-for="node in healthNodes()" :key="node.id">
                        <article class="ps-node-health" :class="{'expanded': healthNodeOpen(node)}">
                            <div class="ps-node-summary" x-on:click="if(!$event.target.closest('button')) toggleHealthNode(node)">
                                <header>
                                    <button type="button" class="ps-node-toggle" :aria-expanded="healthNodeOpen(node)" :aria-controls="'ps-node-servers-'+node.id" x-on:click="toggleHealthNode(node)">
                                        <svg class="ps-node-symbol" aria-hidden="true"><use href="#ps-host"/></svg>
                                        <strong class="ps-node-name" x-text="node.name"></strong>
                                        <span class="ps-badge ps-status" :class="node.state" x-text="node.label"></span>
                                        <svg class="ps-node-arrow" :class="{'expanded': healthNodeOpen(node)}" aria-hidden="true"><use href="#ps-chevron"/></svg>
                                    </button>
                                    <small class="ps-health-problem" x-show="node.problem" x-text="node.problem"></small>
                                </header>
                                <div class="ps-node-metrics">
                                    <template x-for="metric in hostMetrics" :key="metric.key">
                                        <div class="ps-host-metric">
                                            <div class="ps-host-heading"><svg aria-hidden="true"><use :href="'#ps-'+metric.icon"/></svg><span x-text="metric.label"></span></div>
                                            <strong :class="{'ps-network-value': metric.key==='network'}" x-text="metric.key==='network' ? hostRate(node) : hostValue(metric.key, node)"></strong>
                                            <span class="ps-meter" x-show="metric.key!=='network'"><span :style="{width: hostFill(metric.key, node)+'%'}"></span></span>
                                            <small x-show="metric.key==='network'" x-text="Simplify.fill(@js($u('used')), { amount: hostValue('network', node) })"></small>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div class="ps-node-server-drawer" :class="{'expanded': healthNodeOpen(node)}" :id="'ps-node-servers-'+node.id" :inert="!healthNodeOpen(node)" :aria-hidden="!healthNodeOpen(node)">
                                <div>
                                    <div class="ps-node-servers">
                                        <template x-for="item in nodeServers(node)" :key="item.uuid">
                                            <div class="ps-node-server">
                                                <div>
                                                    <a :href="item.url" x-text="item.name"></a>
                                                    <span class="ps-badge ps-status" :class="item.status" x-text="Simplify.status(item.status)"></span>
                                                </div>
                                                <span><small>{{ $u('cpu') }}</small><strong x-text="cpuLabel(item)"></strong></span>
                                                <span><small>{{ $u('ram') }}</small><strong x-text="memoryLabel(item)"></strong></span>
                                                <span><small>{{ $u('storage') }}</small><strong x-text="item.usage?.disk_bytes==null ? '—' : trafficBytes(item.usage.disk_bytes)"></strong></span>
                                                <span><small>{{ $u('network') }}</small><strong x-text="serverRate(item)"></strong><small x-text="Simplify.fill(@js($u('used')), { amount: serverTraffic(item) })"></small></span>
                                            </div>
                                        </template>
                                        <p x-show="!nodeServers(node).length" class="ps-help">{{ $u('no_visible_servers_on_this_node') }}</p>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </template>
                    <p x-show="!boot.host_health?.nodes?.length" class="ps-empty">{{ $u('no_nodes_reporting_yet') }}</p>
                </div>
            </div>
        </div>
        {{-- A tab under the card's bottom edge: the card folds open to show every node. --}}
        <button type="button" class="ps-health-tab" :class="{'expanded': healthExpanded}" x-show="boot.features?.host_health" x-on:click="openHostHealth()" :aria-expanded="healthExpanded" aria-controls="ps-health-details" aria-label="{{ $u('view_health_by_node') }}"><svg aria-hidden="true"><use href="#ps-chevron"/></svg></button>
    </section>
</section>

