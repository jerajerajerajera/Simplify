{{-- Account: avatar with inline profile, theme, notifications and sign-out actions. --}}
<div class="ps-account" :class="{'account-open': accountMenu}" x-on:click.outside="accountMenu=false" x-on:keydown.escape.stop="accountMenu=false">
    <button type="button" x-on:click="accountMenu=!accountMenu; appearanceMenu=false; groupsMenu=false; notifyMenu=false" class="ps-btn ps-account-button" aria-label="{{ $u('profile_options') }}" aria-controls="ps-account-actions" :aria-expanded="accountMenu">
        <span class="ps-account-avatar" x-data="{failed:false}">
            <img x-show="boot.avatar && !failed" :src="boot.avatar" alt="" x-on:error="failed=true">
            <svg x-show="!boot.avatar || failed" aria-hidden="true"><use href="#ps-account"/></svg>
        </span>
        <span class="ps-account-username" x-text="boot.user"></span>
    </button>
    <div id="ps-account-actions" class="ps-account-menu" :inert="!accountMenu" :aria-hidden="!accountMenu">
        <div class="ps-account-actions-inner">
            <a class="ps-btn is-icon" :href="boot.profile_url" data-tip="{{ $u('profile_settings') }}" aria-label="{{ $u('profile_settings') }}"><svg aria-hidden="true"><use href="#ps-account"/></svg></a>
            {{-- Connections: the add-ons' outside accounts (Steam, CurseForge), only when an add-on gives a row. --}}
            <a class="ps-btn is-icon" x-show="boot.connections_url" x-cloak :href="boot.connections_url" wire:navigate data-tip="{{ $u('connections') }}" aria-label="{{ $u('connections') }}"><svg aria-hidden="true"><use href="#ps-link"/></svg></a>
            {{-- Customize Simplify: the theme, the look of the overview and of the server pages, and the server groups. --}}
            <button type="button" class="ps-btn is-icon" x-on:click.stop="accountMenu=false; appearanceMenu=true" data-tip="{{ $u('customize') }}" aria-label="{{ $u('customize') }}"><svg aria-hidden="true"><use href="#ps-wand"/></svg></button>
            {{-- Add-on pages (such as Server Maker's Steam account): Pelican's account panel inside Simplify (AdminTop). --}}
            <template x-for="page in boot.account_pages || []" :key="page.key">
                <a class="ps-btn is-icon" :href="page.url" wire:navigate :data-tip="page.label" :aria-label="page.label" x-html="page.icon"></a>
            </template>
            <template x-for="link in boot.account_links" :key="link.url">
                <a class="ps-btn is-icon" :href="link.url" target="_blank" rel="noopener" :data-tip="link.label" :aria-label="Simplify.fill(@js($u('opens_in_new_tab')), { label: link.label })" x-html="link.icon"></a>
            </template>
            <a class="ps-btn is-icon" x-show="boot.support_url" x-cloak :href="boot.support_url" target="_blank" rel="noopener noreferrer" data-tip="{{ $u('support') }}" aria-label="{{ $u('opens_in_new_tab', ['label' => $u('support')]) }}"><svg aria-hidden="true"><use href="#ps-question"/></svg></a>
            {{-- Install app (phones; the top bar has it elsewhere) and the legacy panel. --}}
            <button type="button" class="ps-btn is-icon ps-phone-only" x-show="installable" x-cloak x-on:click="accountMenu=false; installApp()" data-tip="{{ $u('install_app') }}" aria-label="{{ $u('install_app') }}"><svg aria-hidden="true"><use href="#ps-download"/></svg></button>
            <a class="ps-btn is-icon" x-show="boot.legacy_url" :href="boot.legacy_url" data-tip="{{ $u('legacy_panel') }}" aria-label="{{ $u('legacy_panel') }}"><svg aria-hidden="true"><use href="#ps-legacy"/></svg></a>
            <form method="post" :action="boot.logout_url">@csrf
                <button type="submit" class="ps-btn is-icon" data-tip="{{ $u('sign_out') }}" aria-label="{{ $u('sign_out') }}"><svg aria-hidden="true"><use href="#ps-logout"/></svg></button>
            </form>
        </div>
        <p class="ps-account-foot ps-phone-only">{{ __('simplify::js.dashboard.enhanced_by') }} Simplify · {{ __('simplify::js.dashboard.build', ['version' => \Simplify\Core\Support\Assets::version()]) }}@if (str_contains(\Simplify\Core\Support\Assets::version(), '-'))<span data-ps-env></span>@endif</p>
    </div>
    {{-- Customize Simplify opens from the account menu, so it hangs under the account button (K-B19). --}}
    @include('simplify::dashboard.customize-menu')
</div>
