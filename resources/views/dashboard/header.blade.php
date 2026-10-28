{{-- Header: the panel name on the left, the overview's buttons and the panel menu on the right. --}}
<header class="ps-top">
    <div class="ps-heading">
        <div class="ps-identity">
            <a :href="boot.list_url"><h1 class="ps-brand" :style="brandStyle()"><template x-if="brand().logo_url"><img class="ps-brand-logo" :src="brand().logo_url" alt="" :style="{ height: (Number(brand().logo_height) || 32) + 'px' }"></template><span class="ps-panel-name" x-show="!brand().logo_url" :style="Simplify.brandTextStyle(brand(), null, boot.appearance?.accent_color)" x-text="boot.panel_name"></span><span class="ps-title-pelican">{{ $u('pelican') }}</span><span class="ps-visually-hidden" x-show="brand().logo_url" x-text="boot.panel_name"></span><template x-if="halloween()"><svg class="ps-hw-lantern" viewBox="0 0 24 24" aria-hidden="true"><path class="ps-hw-body" d="M12 6.5c-1.6-.9-3.6-1-5.3-.2C3.8 7.6 2.5 10.7 3.3 14c.8 3.4 3.6 5.6 6.4 5.4.8 0 1.6-.3 2.3-.7.7.4 1.5.7 2.3.7 2.8.2 5.6-2 6.4-5.4.8-3.3-.5-6.4-3.4-7.7-1.7-.8-3.7-.7-5.3.2z"/><path class="ps-hw-ribs" d="M8.2 7.2c-1.4 3-1.4 7.5.6 11.6M15.8 7.2c1.4 3 1.4 7.5-.6 11.6"/><path class="ps-hw-stem" d="M12 6.5c0-1.6.6-2.8 2-3.6"/><g class="ps-hw-face"><path d="M7.4 11.6 9.6 10l.9 2.4z"/><path d="M16.6 11.6 14.4 10l-.9 2.4z"/><path d="M7 14.6c1.4 2.2 8.6 2.2 10 0-.9.5-1.6.6-2.2.6l-.5-.9-.7 1.1c-.8.1-1.4.1-2.2 0l-.7-1.1-.5.9c-.7 0-1.3-.1-2.2-.6z"/></g></svg></template></h1></a>
        </div>
    </div>
    <nav class="ps-top-actions" aria-label="{{ $u('panel_navigation') }}" :class="{'nav-open': navMenu}"
         x-on:click.outside="navMenu=false; accountMenu=false" x-on:keydown.escape.stop="navMenu=false; accountMenu=false">
        {{-- Health in the top bar: the button opens the details below it; the create buttons sit next to it. --}}
        <div class="ps-top-overview" x-show="healthPlace()==='top-bar'" x-cloak>
            <button type="button" class="ps-btn ps-health-pill" :class="boot.host_health?.state || 'unavailable'" x-on:click="healthPopover=!healthPopover; healthExpanded=false; healthOpenNodes=[]; placeHealthPopover()" :aria-expanded="healthPopover" aria-controls="ps-health-panel" :aria-label="Simplify.fill(@js($u('host_health_label')), { state: boot.host_health?.label || @js($u('unavailable')) })">
                <svg aria-hidden="true"><use href="#ps-heart"/></svg><span x-text="boot.host_health?.label || @js($u('unavailable'))"></span>
            </button>
            @include('pelican-simplify::dashboard.create-buttons')
        </div>
        <button type="button" class="ps-btn is-icon is-subtle ps-mobile-menu" aria-label="{{ $u('panel_navigation') }}" aria-controls="ps-navigation-items" :aria-expanded="navMenu"
                x-on:click="navMenu=!navMenu; accountMenu=false; appearanceMenu=false; groupsMenu=false; notifyMenu=false">
            <svg aria-hidden="true"><use href="#ps-menu"/></svg>
        </button>
        <div class="ps-nav-items" id="ps-navigation-items">
            {{-- Notifications: Pelican's own (backups, installs, subusers) and host health alerts in one dropdown. --}}
            <div class="ps-notify" x-on:click.outside="notifyMenu=false" x-on:keydown.escape.stop="notifyMenu=false; $refs.notifyButton.focus()">
                <button type="button" x-ref="notifyButton" class="ps-btn is-icon is-subtle" data-tip="{{ $u('notifications') }}" aria-label="{{ $u('notifications') }}" aria-controls="ps-notify-menu" :aria-expanded="notifyMenu" x-on:click="toggleNotifications()">
                    <svg aria-hidden="true"><use href="#ps-bell"/></svg><span class="ps-mobile-label">{{ $u('notifications') }}</span>
                    <span class="ps-notify-count" x-show="unreadNotifications()" x-text="unreadNotifications()"></span>
                </button>
                <div id="ps-notify-menu" class="ps-dropdown is-end ps-notify-menu" x-show="notifyMenu" x-cloak x-transition.opacity>
                    <div class="ps-notify-head">
                        <strong class="ps-dropdown-title">{{ $u('notifications') }}</strong>
                        <button type="button" class="ps-btn is-sm is-subtle" x-show="unreadNotifications()" x-on:click="markNotifications([], true)">{{ $u('mark_all_read') }}</button>
                        <a class="ps-btn is-sm is-icon is-subtle" x-show="boot.notifications_url" :href="boot.notifications_url" data-tip="{{ $u('notifications_on_this_device') }}" aria-label="{{ $u('notifications_on_this_device') }}"><svg aria-hidden="true"><use href="#ps-settings"/></svg></a>
                    </div>
                    <div class="ps-notify-list">
                        <template x-for="item in notifications()" :key="item.id">
                            <div class="ps-notify-item" :class="['is-'+item.tone, item.unread === false ? 'is-read' : '']">
                                <svg aria-hidden="true"><use :href="'#ps-'+(item.tone==='ok'?'check':item.tone==='info'?'info':'alert')"/></svg>
                                <strong x-text="item.title"></strong>
                                <small x-text="item.text" x-show="item.text"></small>
                                <small class="ps-notify-time" x-show="item.time" x-text="item.time"></small>
                                <span class="ps-notify-actions" x-show="item.action || item.pelican">
                                    <template x-if="item.action">
                                        <a class="ps-btn is-sm" :href="item.action.url" x-on:click="item.pelican && item.unread && markNotifications([item.key], true)" x-text="item.action.label"></a>
                                    </template>
                                    <button type="button" class="ps-btn is-sm is-subtle" x-show="item.pelican" x-on:click="markNotifications([item.key], !item.unread)" x-text="item.unread ? @js($u('mark_read')) : @js($u('mark_as_unread'))"></button>
                                </span>
                            </div>
                        </template>
                        <p class="ps-empty" x-show="!notifications().length">{{ $u('you_are_all_caught_up') }}</p>
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
            @include('pelican-simplify::dashboard.groups-menu')
            @include('pelican-simplify::dashboard.customize-menu')
            <button type="button" x-show="installable" x-cloak x-on:click="installApp()" class="ps-btn is-icon is-subtle ps-nav-view" data-tip="{{ $u('install_app') }}" aria-label="{{ $u('install_app') }}">
                <svg aria-hidden="true"><use href="#ps-download"/></svg><span class="ps-mobile-label">{{ $u('install_app') }}</span>
            </button>
            {{-- Phones list these buttons as a menu: first what everyone uses, then the admin buttons under their own heading. --}}
            <span class="ps-label ps-nav-heading" x-show="adminPages().length">{{ $u('panel') }}</span>
            {{-- Admin tools: Pelican's admin pages in coloured sections; each opens Pelican's own page. --}}
            <div class="ps-notify ps-nav-admin" x-show="adminPages().length" x-on:click.outside="adminMenu=false" x-on:keydown.escape.stop="adminMenu=false; $refs.adminButton.focus()">
                <button type="button" x-ref="adminButton" class="ps-btn is-icon is-subtle" data-tip="{{ $u('admin_tools') }}" aria-label="{{ $u('admin_tools') }}" aria-controls="ps-admin-menu" :aria-expanded="adminMenu"
                        x-on:click="adminMenu=!adminMenu; notifyMenu=false; appearanceMenu=false; groupsMenu=false; accountMenu=false">
                    <svg aria-hidden="true"><use href="#ps-shield"/></svg><span class="ps-mobile-label">{{ $u('admin_tools') }}</span>
                </button>
                <div id="ps-admin-menu" class="ps-dropdown is-end ps-admin-menu" x-show="adminMenu" x-cloak x-transition.opacity>
                    <strong class="ps-dropdown-title">{{ $u('admin_tools') }}</strong>
                    <div class="ps-admin-groups">
                        <template x-for="section in hostSections()" :key="section.key">
                            <div class="ps-admin-group" :style="{'--ps-tone': section.color}">
                                <span class="ps-admin-group-head"><span class="ps-host-rail-icon"><svg aria-hidden="true"><use :href="'#ps-'+section.icon"/></svg></span><span x-text="section.label"></span></span>
                                <template x-for="item in section.pages" :key="item.key">
                                    <a class="ps-admin-item" :href="item.url"><svg aria-hidden="true"><use :href="'#ps-'+item.icon"/></svg><span x-text="item.label"></span></a>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <a x-show="boot.admin_url" :href="boot.admin_url" class="ps-btn is-icon is-subtle ps-nav-admin" data-tip="{{ $u('legacy_panel') }}" aria-label="{{ $u('legacy_panel') }}">
                <svg aria-hidden="true"><use href="#ps-legacy"/></svg><span class="ps-mobile-label">{{ $u('legacy_panel') }}</span>
            </a>
            @include('pelican-simplify::dashboard.account')
        </div>
    </nav>
</header>
