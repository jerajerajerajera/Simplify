{{-- Phone tab bar (overview only), within thumb reach: Customize (server groups and the overview's look), New and Admin tools. Each opens a sheet from the bottom. --}}
<nav class="ps-tabbar" x-show="!hostMode && !adminView" aria-label="{{ $u('phone_navigation') }}" x-on:click.outside="createMenu=false; customizeMenu=false" x-on:keydown.escape.stop="createMenu=false; customizeMenu=false">
    <div class="ps-tab-customize">
        <button type="button" class="ps-tab" aria-controls="ps-tab-customize-menu" :aria-expanded="customizeMenu" x-on:click="createMenu=false; customizeMenu=!customizeMenu">
            <svg aria-hidden="true"><use href="#ps-wand"/></svg><span>{{ $u('customize') }}</span>
        </button>
        <div id="ps-tab-customize-menu" class="ps-dropdown" x-show="customizeMenu" x-cloak x-transition.opacity role="menu">
            <strong class="ps-dropdown-title">{{ $u('customize') }}</strong>
            <button type="button" class="ps-menu-item" role="menuitem" x-on:click.stop="customizeMenu=false; groupsMenu=true"><svg aria-hidden="true"><use href="#ps-files"/></svg><span>{{ $u('server_groups') }}</span></button>
            <button type="button" class="ps-menu-item" role="menuitem" x-on:click.stop="customizeMenu=false; appearanceMenu=true"><svg aria-hidden="true"><use href="#ps-wand"/></svg><span>{{ $u('customize_overview') }}</span></button>
        </div>
    </div>
    <div class="ps-tab-create" x-show="createButtons().length">
        <button type="button" class="ps-tab" aria-haspopup="dialog" :aria-expanded="createMenu" x-on:click="customizeMenu=false; createButtons().length===1 ? (openCreate(null, createButtons()[0]) || (location.href=createButtons()[0].url)) : (createMenu=!createMenu)">
            <span class="ps-tab-plus" :class="{'is-pumpkin': halloween()}"><svg aria-hidden="true" x-show="!halloween()"><use href="#ps-plus"/></svg><svg class="ps-hw-tab" viewBox="0 3 24 18" aria-hidden="true" x-show="halloween()" x-cloak><path class="ps-hw-body" d="M12 6.5c-1.6-.9-3.6-1-5.3-.2C3.8 7.6 2.5 10.7 3.3 14c.8 3.4 3.6 5.6 6.4 5.4.8 0 1.6-.3 2.3-.7.7.4 1.5.7 2.3.7 2.8.2 5.6-2 6.4-5.4.8-3.3-.5-6.4-3.4-7.7-1.7-.8-3.7-.7-5.3.2z"/><path class="ps-hw-ribs" d="M8.2 7.2c-1.4 3-1.4 7.5.6 11.6M15.8 7.2c1.4 3 1.4 7.5-.6 11.6"/><path class="ps-hw-stem" d="M12 6.5c0-1.6.6-2.8 2-3.6"/><path class="ps-hw-carve" d="M12 9.6v6.4M8.8 12.8h6.4"/></svg></span><span>{{ $u('new') }}</span>
        </button>
        <div class="ps-dropdown ps-tab-create-menu" x-show="createMenu" x-cloak x-transition.opacity role="menu">
            <template x-for="choice in createButtons()" :key="choice.url">
                <a class="ps-menu-item" role="menuitem" :href="choice.url" x-on:click="openCreate($event, choice)">
                    <svg aria-hidden="true"><use :href="'#ps-'+(choice.primary ? 'plus' : 'gamepad')"/></svg><span x-text="choice.label"></span>
                </a>
            </template>
        </div>
    </div>
    <button type="button" class="ps-tab" x-show="adminPages().length" aria-controls="ps-admin-menu" :aria-expanded="adminMenu" x-on:click.stop="createMenu=false; customizeMenu=false; adminMenu=!adminMenu">
        <svg aria-hidden="true"><use href="#ps-shield"/></svg><span>{{ $u('admin_tools') }}</span>
    </button>
</nav>
