{{-- Account: avatar with inline profile, theme, notifications and sign-out actions. --}}
<div class="ps-account" x-data="{ themeOpen: false }" x-effect="accountMenu || (themeOpen = false)" :class="{'account-open': accountMenu}" x-on:click.outside="accountMenu=false" x-on:keydown.escape.stop="accountMenu=false">
    <button type="button" x-on:click="accountMenu=!accountMenu; appearanceMenu=false; groupsMenu=false; notifyMenu=false" class="ps-btn ps-account-button" aria-label="{{ $u('profile_options') }}" aria-controls="ps-account-actions" :aria-expanded="accountMenu">
        <span class="ps-account-avatar" x-data="{failed:false}">
            <img x-show="boot.avatar && !failed" :src="boot.avatar" alt="" x-on:error="failed=true">
            <svg x-show="!boot.avatar || failed" aria-hidden="true"><use href="#ps-account"/></svg>
        </span>
        <span class="ps-account-username" x-text="boot.user"></span>
    </button>
    <div id="ps-account-actions" class="ps-account-menu" :style="{'--ps-account-count': (adminPages().length && !server ? 4 : 3) + (boot.notifications_url ? 1 : 0) + (boot.support_url ? 1 : 0) + (themeOpen ? 2 : 0)}" :inert="!accountMenu" :aria-hidden="!accountMenu">
        <div class="ps-account-actions-inner">
            <a class="ps-btn is-icon" :href="boot.profile_url" data-tip="{{ $u('profile_settings') }}" aria-label="{{ $u('profile_settings') }}"><svg aria-hidden="true"><use href="#ps-account"/></svg></a>
            {{-- Theme: the current choice; clicking it unfolds Light, Dark and System. --}}
            <button type="button" class="ps-btn is-icon" x-show="!themeOpen" x-on:click="themeOpen=true" :data-tip="@js($u('theme_prefix'))+' '+(theme==='light' ? @js($u('light')) : theme==='dark' ? @js($u('dark')) : @js($u('system')))" aria-label="{{ $u('choose_theme') }}" :aria-expanded="themeOpen">
                <svg aria-hidden="true"><use :href="theme==='light' ? '#ps-sun' : theme==='dark' ? '#ps-moon' : '#ps-monitor'"/></svg>
            </button>
            <template x-for="t in ['light','dark','system']" :key="t">
                <button type="button" class="ps-btn is-icon ps-theme-option" x-show="themeOpen" :aria-pressed="theme===t" x-on:click="setTheme(t); themeOpen=false" :data-tip="{ light: @js($u('light')), dark: @js($u('dark')), system: @js($u('system')) }[t]" :aria-label="Simplify.fill(@js($u('theme_option')), { theme: { light: @js($u('light')), dark: @js($u('dark')), system: @js($u('system')) }[t] })">
                    <svg aria-hidden="true"><use :href="t==='light' ? '#ps-sun' : t==='dark' ? '#ps-moon' : '#ps-monitor'"/></svg>
                </button>
            </template>
            <a class="ps-btn is-icon" x-show="boot.notifications_url" :href="boot.notifications_url" data-tip="{{ $u('notifications_on_this_device') }}" aria-label="{{ $u('notifications_on_this_device') }}"><svg aria-hidden="true"><use href="#ps-bell"/></svg></a>
            <button type="button" class="ps-btn is-icon" x-show="adminPages().length && !server" x-on:click="setHostMode(!hostMode); accountMenu=false" :data-tip="hostMode ? @js($u('switch_to_simple_mode')) : @js($u('switch_to_host_mode'))" :aria-label="hostMode ? @js($u('switch_to_simple_mode')) : @js($u('switch_to_host_mode'))">
                <svg aria-hidden="true"><use :href="hostMode ? '#ps-grid' : '#ps-host'"/></svg>
            </button>
            <a class="ps-btn is-icon" x-show="boot.support_url" x-cloak :href="boot.support_url" target="_blank" rel="noopener noreferrer" data-tip="{{ $u('support') }}" aria-label="{{ $u('opens_in_new_tab', ['label' => $u('support')]) }}"><svg aria-hidden="true"><use href="#ps-question"/></svg></a>
            <form method="post" :action="boot.logout_url">@csrf
                <button type="submit" class="ps-btn is-icon" data-tip="{{ $u('sign_out') }}" aria-label="{{ $u('sign_out') }}"><svg aria-hidden="true"><use href="#ps-logout"/></svg></button>
            </form>
        </div>
    </div>
</div>
