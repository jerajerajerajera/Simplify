{{-- Header: the panel name on the left, the overview's buttons and the panel menu on the right. --}}
<header class="ps-top" @if (($notes ?? null) === 'host') data-ps-notes="host" @endif>
    <div class="ps-heading">
        <div class="ps-identity">
            <a :href="boot.list_url"><h1 class="ps-brand" :style="brandStyle()"><template x-if="brand().logo_url"><img class="ps-brand-logo" :src="brand().logo_url" alt="" :style="{ height: (Number(brand().logo_height) || 32) + 'px' }"></template><span class="ps-panel-name" :class="brand().name_class" x-show="!brand().logo_url" x-text="boot.panel_name"></span><span class="ps-title-pelican" x-show="boot.appearance?.pelican_suffix !== false">{{ $u('pelican') }}</span><span class="ps-visually-hidden" x-show="brand().logo_url" x-text="boot.panel_name"></span></h1></a>
            {{-- Seasonal touches: an icon for the holiday of the day (resources/js/seasons.js). Pointing at it, or tapping it, tells what the day is. --}}
            <span class="ps-season-badges" x-show="seasonBadges().length" x-cloak>
                <template x-for="badge in seasonBadges()" :key="badge.key">
                    <button type="button" class="ps-season-badge" :class="'is-'+badge.key" :style="{ color: badge.color }" :data-tip-label="badge.label" :data-tip="badge.text" :aria-label="badge.label" x-on:click="$el.blur(); $el.focus()">
                        <template x-if="badge.key==='halloween'"><svg class="ps-hw-lantern" viewBox="0 0 24 24" aria-hidden="true"><path class="ps-hw-body" d="M12 6.5c-1.6-.9-3.6-1-5.3-.2C3.8 7.6 2.5 10.7 3.3 14c.8 3.4 3.6 5.6 6.4 5.4.8 0 1.6-.3 2.3-.7.7.4 1.5.7 2.3.7 2.8.2 5.6-2 6.4-5.4.8-3.3-.5-6.4-3.4-7.7-1.7-.8-3.7-.7-5.3.2z"/><path class="ps-hw-ribs" d="M8.2 7.2c-1.4 3-1.4 7.5.6 11.6M15.8 7.2c1.4 3 1.4 7.5-.6 11.6"/><path class="ps-hw-stem" d="M12 6.5c0-1.6.6-2.8 2-3.6"/><g class="ps-hw-face"><path d="M7.4 11.6 9.6 10l.9 2.4z"/><path d="M16.6 11.6 14.4 10l-.9 2.4z"/><path d="M7 14.6c1.4 2.2 8.6 2.2 10 0-.9.5-1.6.6-2.2.6l-.5-.9-.7 1.1c-.8.1-1.4.1-2.2 0l-.7-1.1-.5.9c-.7 0-1.3-.1-2.2-.6z"/></g></svg></template>
                        <template x-if="badge.key!=='halloween'"><svg aria-hidden="true"><use :href="'#ps-'+badge.icon"/></svg></template>
                        <template x-if="badge.key==='hanukkah'"><span class="ps-season-nights" aria-hidden="true"><template x-for="n in 8" :key="n"><i :class="{'is-lit': n <= badge.day}"></i></template></span></template>
                        <template x-if="badge.key==='diwali'"><span class="ps-season-lights" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span></template>
                    </button>
                </template>
                <span class="ps-season-countdown" x-show="newYearCountdown()" x-text="newYearCountdown()"></span>
            </span>
        </div>
        <a x-show="server" x-cloak :href="boot.list_url" class="ps-btn ps-back-overview">
            <svg aria-hidden="true"><use href="#ps-back"/></svg><span>{{ $u('all_servers') }}</span>
        </a>
    </div>
    @if (! empty($notes))
        {{-- A server's announcements, and in host mode the panel's, as short rows in the bar (announcements/notes.blade.php). --}}
        @livewire('simplify-core-announcement-bars', ['compact' => true], key('simplify-server-notes'))
    @endif
    <nav class="ps-top-actions" aria-label="{{ $u('panel_navigation') }}" :class="{'nav-open': navMenu}"
         x-on:click.outside="navMenu=false; accountMenu=false" x-on:keydown.escape.stop="navMenu=false; accountMenu=false">
        {{-- Health in the top bar: the button opens the details below it; the create buttons sit next to it. --}}
        <div class="ps-top-overview" x-show="!server && !hostMode && healthPlace()==='top-bar'" x-cloak>
            <button type="button" class="ps-btn ps-health-pill" :class="boot.host_health?.state || 'unavailable'" x-on:click="healthPopover=!healthPopover; healthExpanded=false; healthOpenNodes=[]; placeHealthPopover()" :aria-expanded="healthPopover" aria-controls="ps-health-panel" :aria-label="Simplify.fill(@js($u('host_health_label')), { state: boot.host_health?.label || @js($u('unavailable')) })">
                <svg aria-hidden="true"><use href="#ps-heart"/></svg><span x-text="boot.host_health?.label || @js($u('unavailable'))"></span>
            </button>
            @include('simplify::dashboard.create-buttons')
        </div>
        <button type="button" class="ps-btn is-icon is-subtle ps-mobile-menu" aria-label="{{ $u('panel_navigation') }}" aria-controls="ps-navigation-items" :aria-expanded="navMenu"
                x-on:click="navMenu=!navMenu; accountMenu=false; appearanceMenu=false; groupsMenu=false; notifyMenu=false">
            <svg aria-hidden="true"><use href="#ps-menu"/></svg>
        </button>
        <div class="ps-nav-items" id="ps-navigation-items">
            {{-- Notifications: Pelican's own (backups, installs, subusers) and host health alerts in one dropdown. --}}
            <div class="ps-notify" x-on:click.outside="notifyMenu=false" x-on:keydown.escape.stop="notifyMenu=false; $refs.notifyButton.focus()">
                {{-- A new notification drops down from the bell and slides back up after a few seconds. --}}
                <div class="ps-incoming" x-show="incomingShown" x-cloak role="status" aria-live="polite"
                     x-transition:enter="ps-incoming-move" x-transition:enter-start="is-up" x-transition:leave="ps-incoming-move" x-transition:leave-end="is-up"
                     x-on:mouseenter="clearTimeout(incomingTimer)" x-on:mouseleave="holdIncomingNotice(2500)">
                    <template x-if="incomingNotice">
                        <div class="ps-notify-item" :class="'is-'+(incomingNotice.tone || 'info')">
                            <svg aria-hidden="true"><use :href="'#ps-'+(incomingNotice.tone==='ok'?'check':(incomingNotice.tone||'info')==='info'?'info':'alert')"/></svg>
                            <strong x-text="incomingNotice.title"></strong>
                            <small x-text="incomingNotice.text" x-show="incomingNotice.text"></small>
                            <span class="ps-notify-actions">
                                <template x-if="incomingNotice.action">
                                    <a class="ps-btn is-sm" :href="incomingNotice.action.url" x-on:click="markNotifications([incomingNotice.key], true)" x-text="incomingNotice.action.label"></a>
                                </template>
                                <button type="button" class="ps-btn is-sm is-subtle" x-on:click="hideIncomingNotice()">{{ $u('dismiss') }}</button>
                            </span>
                        </div>
                    </template>
                </div>
                <button type="button" x-ref="notifyButton" class="ps-btn is-icon is-subtle" data-tip="{{ $u('notifications') }}" aria-label="{{ $u('notifications') }}" aria-controls="ps-notify-menu" :aria-expanded="notifyMenu" x-on:click="toggleNotifications()">
                    <svg aria-hidden="true"><use href="#ps-bell"/></svg><span class="ps-mobile-label">{{ $u('notifications') }}</span>
                    <span class="ps-notify-count" x-show="unreadNotifications()" x-text="unreadNotifications()"></span>
                </button>
                <div id="ps-notify-menu" class="ps-dropdown is-end ps-notify-menu" x-show="notifyMenu" x-cloak x-transition.opacity>
                    <div class="ps-notify-head">
                        <strong class="ps-dropdown-title">{{ $u('notifications') }}</strong>
                        <button type="button" class="ps-btn is-sm is-subtle" x-show="unreadNotifications()" x-on:click="markNotifications([], true)">{{ $u('mark_all_read') }}</button>
                        <button type="button" class="ps-btn is-sm is-icon is-subtle" x-on:click="openAllNotifications()" data-tip="{{ $u('all_notifications') }}" aria-label="{{ $u('all_notifications') }}"><svg aria-hidden="true"><use href="#ps-expand"/></svg></button>
                    </div>
                    <div class="ps-notify-list">
                        <template x-for="item in notifications()" :key="item.id">
                            @include('simplify::dashboard.notify-item')
                        </template>
                        <p class="ps-empty" x-show="!notifications().length">{{ $u('you_are_all_caught_up') }}</p>
                    </div>
                    <div class="ps-notify-list" x-show="pushOffer">
                        <div class="ps-push-offer">
                            <svg aria-hidden="true"><use href="#ps-bell"/></svg>
                            <span>{{ $u('get_announcements_as_notifications_on_this') }}</span>
                            <button type="button" class="ps-btn is-sm" x-on:click="openAllNotifications()">{{ $u('turn_on') }}</button>
                            <button type="button" class="ps-btn is-sm is-icon is-subtle" data-tip="{{ $u('not_now') }}" aria-label="{{ $u('not_now') }}" x-on:click="hidePushOffer()"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
                        </div>
                    </div>
                    <button type="button" class="ps-btn is-sm is-subtle ps-notify-more" x-show="feed.more" x-on:click="loadNotificationPage()" :disabled="feed.loading" x-text="feed.loading ? @js($u('loading')) : @js($u('show_more'))"></button>
                    <div class="ps-dropdown-section" x-show="noticeHistory.length">
                        <span class="ps-label">{{ $u('recent_messages') }}</span>
                        <div class="ps-notify-list">
                            <template x-for="entry in noticeHistory" :key="entry.id">
                                <div class="ps-notify-item" :class="'is-'+entry.tone">
                                    <svg aria-hidden="true"><use href="#ps-info"/></svg><strong x-text="entry.text"></strong><small x-text="entry.time"></small>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            @include('simplify::dashboard.groups-menu')
            @include('simplify::dashboard.customize-menu')
            <button type="button" x-show="installable" x-cloak x-on:click="installApp()" class="ps-btn is-icon is-subtle ps-nav-view" data-tip="{{ $u('install_app') }}" aria-label="{{ $u('install_app') }}">
                <svg aria-hidden="true"><use href="#ps-download"/></svg><span class="ps-mobile-label">{{ $u('install_app') }}</span>
            </button>
            {{-- Phones list these buttons as a menu: first what everyone uses, then the admin buttons under their own heading. --}}
            <span class="ps-label ps-nav-heading" x-show="adminPages().length">{{ $u('panel') }}</span>
            {{-- Admin tools: Pelican's admin pages in coloured sections; each opens Pelican's own page. --}}
            <div class="ps-notify ps-nav-admin" x-show="adminPages().length && !(hostMode && !server)" x-on:click.outside="adminMenu=false" x-on:keydown.escape.stop="adminMenu=false; $refs.adminButton.focus()">
                <button type="button" x-ref="adminButton" class="ps-btn is-icon is-subtle" data-tip="{{ $u('admin_tools') }}" aria-label="{{ $u('admin_tools') }}" aria-controls="ps-admin-menu" :aria-expanded="adminMenu"
                        x-on:click="adminMenu=!adminMenu; notifyMenu=false; appearanceMenu=false; groupsMenu=false; accountMenu=false">
                    <svg aria-hidden="true"><use href="#ps-shield"/></svg><span class="ps-mobile-label">{{ $u('admin_tools') }}</span>
                </button>
                <div id="ps-admin-menu" class="ps-dropdown is-end ps-admin-menu" x-show="adminMenu" x-cloak x-transition.opacity>
                    <strong class="ps-dropdown-title">{{ $u('admin_tools') }}</strong>
                    {{-- Groups on the left, one open at a time; its pages as tiles on the right. In the phone menu the tiles fold out under their group. --}}
                    <div class="ps-admin-split">
                        <div class="ps-admin-nav">
                            <template x-for="section in adminSections()" :key="section.key">
                                <div class="ps-admin-group" :class="{'is-open': adminGroup() === section.key}" :style="{'--ps-tone': section.color}">
                                    <button type="button" class="ps-admin-group-head" :aria-expanded="adminGroup() === section.key" x-on:click="pickAdminGroup(section.key)">
                                        <span class="ps-host-rail-icon"><svg aria-hidden="true"><use :href="'#ps-'+section.icon"/></svg></span><span class="ps-admin-group-name" x-text="section.label"></span>
                                        <small class="ps-admin-group-count" x-text="section.pages.length"></small><svg class="ps-admin-group-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg>
                                    </button>
                                    <div class="ps-admin-tiles is-inline" x-show="adminGroup() === section.key">
                                        <template x-for="item in section.pages" :key="item.key">
                                            <a class="ps-admin-tile" :href="item.url"><span class="ps-host-rail-icon"><svg aria-hidden="true"><use :href="'#ps-'+item.icon"/></svg></span><span x-text="item.label"></span></a>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="ps-admin-pane">
                            <template x-if="adminSection()">
                                <div :style="{'--ps-tone': adminSection().color}">
                                    <div class="ps-admin-pane-head"><span x-text="adminSection().label"></span><small x-text="adminSection().pages.length"></small></div>
                                    <div class="ps-admin-tiles">
                                        <template x-for="item in adminSection().pages" :key="item.key">
                                            <a class="ps-admin-tile" :href="item.url"><span class="ps-host-rail-icon"><svg aria-hidden="true"><use :href="'#ps-'+item.icon"/></svg></span><span x-text="item.label"></span></a>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <template x-if="adminHealthPage()">
                        <div class="ps-admin-health">
                            <a class="ps-admin-health-title" :href="adminHealthPage().url"><svg aria-hidden="true"><use href="#ps-heart"/></svg><span>{{ $u('host_health_checks') }}</span></a>
                            <div class="ps-health-grid">
                                @include('simplify::dashboard.health-checks', ['open' => 'location.href = adminHealthPage().url'])
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <a x-show="boot.admin_url" :href="boot.admin_url" class="ps-btn is-icon is-subtle ps-nav-admin" data-tip="{{ $u('legacy_panel') }}" aria-label="{{ $u('legacy_panel') }}">
                <svg aria-hidden="true"><use href="#ps-legacy"/></svg><span class="ps-mobile-label">{{ $u('legacy_panel') }}</span>
            </a>
            @include('simplify::dashboard.account')
        </div>
    </nav>
    {{-- Every notification in one window, with what this account gets on its devices above the list. --}}
    <template x-teleport="body">
        <div class="ps-modal-layer ps-ui ps-notify-all" :class="{'dark': themeDark}" x-show="notifyAll" x-cloak :data-open="notifyAll" x-on:click.self="notifyAll=false" x-on:keydown.escape.window="notifyAll && (notifyAll=false)" :style="accentStyle('overview')">
            <section class="ps-modal" role="dialog" aria-modal="true" aria-labelledby="ps-notify-all-title">
                <header class="ps-modal-head">
                    <h2 id="ps-notify-all-title">{{ $u('all_notifications') }}</h2>
                    <button type="button" class="ps-btn is-sm is-subtle" x-show="unreadNotifications()" x-on:click="markNotifications([], true)">{{ $u('mark_all_read') }}</button>
                    <button type="button" class="ps-btn is-icon is-subtle" x-on:click="notifyAll=false" aria-label="{{ $u('close') }}" data-tip="{{ $u('close') }}"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
                </header>
                <div class="ps-modal-body">
                    <div class="ps-notify-settings" x-show="push.enabled">
                        <span class="ps-label">{{ $u('customize_notifications') }}</span>
                        <div class="ps-notify-setting">
                            <svg aria-hidden="true"><use href="#ps-monitor"/></svg>
                            <span><strong>{{ __('simplify::push.this_device') }}</strong>
                                <small x-text="{ on: @js(__('simplify::push.this_on')), off: @js(__('simplify::push.this_off')), blocked: @js(__('simplify::push.blocked')), install: @js(__('simplify::push.install')), unsupported: @js(__('simplify::push.unsupported')) }[push.state]"></small></span>
                            <span class="ps-notify-setting-actions">
                                <button type="button" class="ps-btn is-sm" x-show="push.state === 'on'" :disabled="push.busy" x-on:click="pushTest()">{{ __('simplify::push.test') }}</button>
                                <button type="button" class="ps-btn is-sm" x-show="push.state === 'on'" :disabled="push.busy" x-on:click="pushOff()">{{ __('simplify::push.turn_off') }}</button>
                                <button type="button" class="ps-btn is-sm is-primary" x-show="push.state === 'off' || push.state === 'blocked'" :disabled="push.busy" x-on:click="pushOn()">{{ __('simplify::push.turn_on') }}</button>
                            </span>
                        </div>
                        <label class="ps-notify-setting">
                            <svg aria-hidden="true"><use href="#ps-bell"/></svg>
                            <span><strong>{{ __('simplify::push.messages') }}</strong><small>{{ __('simplify::push.messages_help') }}</small></span>
                            <input type="checkbox" class="ps-switch" :checked="push.messages" x-on:change="pushChoose('messages', $event.target.checked)">
                        </label>
                        <label class="ps-notify-setting" x-show="push.updates !== null">
                            <svg aria-hidden="true"><use href="#ps-download"/></svg>
                            <span><strong>{{ __('simplify::push.updates') }}</strong><small>{{ __('simplify::push.updates_help') }}</small></span>
                            <input type="checkbox" class="ps-switch" :checked="push.updates" x-on:change="pushChoose('updates', $event.target.checked)">
                        </label>
                    </div>
                    <span class="ps-label">{{ $u('notifications') }}</span>
                    <div class="ps-notify-list">
                        <template x-for="item in notifications()" :key="item.id">
                            @include('simplify::dashboard.notify-item')
                        </template>
                        <p class="ps-empty" x-show="!notifications().length">{{ $u('you_are_all_caught_up') }}</p>
                    </div>
                    <button type="button" class="ps-btn is-sm is-subtle ps-notify-more" x-show="feed.more" x-on:click="loadNotificationPage()" :disabled="feed.loading" x-text="feed.loading ? @js($u('loading')) : @js($u('show_more'))"></button>
                </div>
            </section>
        </div>
    </template>
</header>
