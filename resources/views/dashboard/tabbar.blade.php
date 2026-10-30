{{-- Phone tab bar (overview only): servers, create and the panel menu within thumb reach. --}}
<nav class="ps-tabbar" x-show="!hostMode && !adminView" aria-label="{{ $u('phone_navigation') }}" x-on:click.outside="createMenu=false" x-on:keydown.escape.stop="createMenu=false">
    <button type="button" class="ps-tab is-active" aria-current="page" x-on:click="createMenu=false; navMenu=false; scrollTo({top: 0, behavior: 'smooth'})">
        <svg aria-hidden="true"><use href="#ps-host"/></svg><span>{{ $u('servers') }}</span>
    </button>
    <div class="ps-tab-create" x-show="createButtons().length">
        <button type="button" class="ps-tab" aria-haspopup="dialog" :aria-expanded="createMenu" x-on:click="navMenu=false; createButtons().length===1 ? (openCreate(null, createButtons()[0]) || (location.href=createButtons()[0].url)) : (createMenu=!createMenu)">
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
    <button type="button" class="ps-tab" aria-controls="ps-navigation-items" :aria-expanded="navMenu" x-on:click.stop="createMenu=false; accountMenu=false; navMenu=!navMenu">
        <svg aria-hidden="true"><use href="#ps-menu"/></svg><span>{{ $u('menu') }}</span>
    </button>
</nav>
