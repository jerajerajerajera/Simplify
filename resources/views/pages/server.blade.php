@php($u = fn (string $key, array $values = []) => __('simplify::dashboard.ui.'.$key, $values))
<div @class(['ps server-active ps-server-top', 'is-console' => $console]) data-simplify-root x-cloak wire:ignore.self
     x-data="simplifyServer($wire, @js($this->initial()))"
     x-on:resize.window.debounce.150ms="applyScale()"
     :class="{'dark': themeDark, 'is-server-full-width': overviewPreferences.fullWidth, 'ps-halloween': halloween()}"
     :data-season="seasonKeys()"
     :style="accentStyle(server.uuid)">
    @include('simplify::dashboard.assets')
    @include('simplify::dashboard.icons')
    @include('simplify::dashboard.halloween')
    @include('simplify::dashboard.seasons')
    {{-- Status bar: slides in once the server card scrolls out of view, so status and power stay in reach. --}}
    <div class="ps-statusbar" :class="{'is-visible': statusBarVisible}" :inert="!statusBarVisible" role="region" aria-label="{{ $u('server_status') }}">
        <span class="ps-statusbar-name" x-text="server.name"></span>
        <span class="ps-badge ps-status" :class="status"><i></i><span x-text="Simplify.status(status)"></span></span>
        <button type="button" class="ps-statusbar-item ps-statusbar-address" x-show="server.address" x-on:click="copyText(server.address)" data-tip="{{ $u('copy_server_address') }}"><svg aria-hidden="true"><use href="#ps-link"/></svg><span x-text="server.address"></span></button>
        <span class="ps-statusbar-item" x-show="server.players != null" data-tip="{{ $u('players_online') }}"><svg aria-hidden="true"><use href="#ps-players"/></svg><span x-text="playerCount(server)"></span></span>
        <div class="ps-statusbar-power">
            <button type="button" class="ps-btn is-sm is-primary" x-on:click="power('start')" :disabled="busy || !server.permissions['control.start'] || status==='running'" aria-label="{{ $u('start_server') }}" data-tip="{{ $u('start') }}"><svg aria-hidden="true"><use href="#ps-play"/></svg></button>
            <button type="button" class="ps-btn is-sm" x-on:click="power('restart')" :disabled="busy || !server.permissions['control.restart'] || !canRestart()" aria-label="{{ $u('restart_server') }}" data-tip="{{ $u('restart') }}"><svg aria-hidden="true"><use href="#ps-restart"/></svg></button>
            <button type="button" class="ps-btn is-sm" x-on:click="power('stop')" :disabled="busy || !server.permissions['control.stop'] || status==='offline'" aria-label="{{ $u('stop_server') }}" data-tip="{{ $u('stop') }}"><svg aria-hidden="true"><use href="#ps-stop"/></svg></button>
            <button type="button" class="ps-btn is-sm is-danger" x-on:click="power('kill')" :disabled="busy || !server.permissions['control.stop'] || status==='offline'" aria-label="{{ $u('force_stop_server') }}" data-tip="{{ $u('kill') }}"><svg aria-hidden="true"><use href="#ps-kill"/></svg></button>
        </div>
    </div>
    <div class="ps-main">
        @include('simplify::dashboard.header', ['notes' => true])
        <article class="ps-server-info" aria-label="{{ $u('server_information') }}" x-init="watchServerInfo($el)">
            {{-- Artwork has its own clipping layer, so it stays inside the card while the ⋯ menu lets the card overflow. --}}
            <span class="ps-info-decor">
                <span class="ps-offline-artwork" x-show="status==='offline'" aria-label="{{ $u('server_offline') }}"><svg aria-hidden="true"><use href="#ps-offline"/></svg></span>
                <img class="ps-server-banner" x-show="server.background && status!=='offline'" :src="safeImage(server.background)" alt="" x-on:error="$el.style.visibility='hidden'">
            </span>
            <span class="ps-info-icons">
                {{-- The same order as the server's card on the overview: the game's own icon, then the icon set in Pelican. --}}
                <span class="ps-info-icon ps-custom-server-icon" x-show="server.server_icon || server.icon"><img :src="safeImage(server.server_icon || server.icon)" alt="" x-on:error="$el.style.visibility='hidden'"></span>
                <span class="ps-info-icon ps-software-icon" x-show="server.egg_icon || !(server.server_icon || server.icon)" :class="{'is-badge': server.server_icon || server.icon}" :data-tip="server.software || @js($u('server_software'))">
                    <img x-show="server.egg_icon && !brokenImages[safeImage(server.egg_icon)]" :src="safeImage(server.egg_icon)" alt="" x-on:error="imageFailed($el.getAttribute('src'))">
                    <svg x-show="!server.egg_icon || brokenImages[safeImage(server.egg_icon)]" aria-hidden="true"><use :href="server.minecraft ? '#ps-cube' : '#ps-gamepad'"/></svg>
                </span>
            </span>
            <div class="ps-info-content">
                <div class="ps-game-title">
                    <h2 x-text="server.name"></h2>
                    <button type="button" class="ps-btn is-icon is-sm is-subtle ps-alias-button" x-show="server.can_edit" x-on:click="saveName(@js($u('server_name')), @js($u('rename')))" data-tip="{{ $u('rename') }}" aria-label="{{ $u('rename') }}" aria-haspopup="dialog"><svg aria-hidden="true"><use href="#ps-edit"/></svg></button>
                </div>
                {{-- One line: status, software and version, what add-ons add, the address. --}}
                <div class="ps-info-meta">
                    <span class="ps-badge ps-status" :class="status"><i></i><span x-text="Simplify.status(status)"></span></span>
                    <span class="ps-info-meta-item" :data-tip="server.software || @js($u('not_reported'))"><svg aria-hidden="true"><use :href="server.minecraft ? '#ps-cube' : '#ps-gamepad'"/></svg><span x-text="softwareLabel()"></span></span>
                    <template x-for="badge in metaBadges()" :key="badge"><span class="ps-info-meta-item" x-text="badge"></span></template>
                    <button type="button" class="ps-address ps-info-address" x-on:click="copyText(server.address)" :disabled="!server.address" data-tip="{{ $u('copy_server_address') }}">
                        <svg aria-hidden="true"><use href="#ps-link"/></svg><span x-text="server.address || @js($u('no_address_assigned'))"></span><svg aria-hidden="true"><use href="#ps-copy"/></svg>
                    </button>
                </div>
            </div>
            <div class="ps-motd-preview ps-motd-display ps-info-motd" x-show="server.motd" x-effect="server.motd; $nextTick(() => fitMotd($el))" aria-label="{{ $u('message_of_the_day') }}"><template x-for="(part, index) in motdPreview(server.motd || '')" :key="index"><span :style="part.style" x-text="part.text"></span></template></div>
            {{-- Power: one button that follows the state (Start, Stop, or Force stop while starting or stopping), Restart, and the rest under ⋯. --}}
            <div class="ps-info-power" x-on:click.outside="colorMenu=false; infoMenu=false" x-on:keydown.escape.stop="colorMenu=false; infoMenu=false">
                <button type="button" class="ps-btn ps-power-main" :class="{'is-primary': mainPower().action==='start', 'is-danger': mainPower().action==='kill'}" x-on:click="power(mainPower().action)" :disabled="busy || !server.permissions[mainPower().permission] || (mainPower().action==='start' && !canRestart())" :aria-label="Simplify.fill(@js($u('action_server')), { action: mainPower().label })"><svg aria-hidden="true"><use :href="'#ps-'+mainPower().icon"/></svg><span x-text="mainPower().label"></span></button>
                <button type="button" class="ps-btn ps-power-restart" x-on:click="power('restart')" :disabled="busy || !server.permissions['control.restart'] || !canRestart()" aria-label="{{ $u('restart_server') }}"><svg aria-hidden="true"><use href="#ps-restart"/></svg><span>{{ $u('restart') }}</span></button>
                <button type="button" class="ps-btn is-icon ps-info-more-button" aria-label="{{ $u('more_server_options') }}" aria-controls="ps-info-menu" :aria-expanded="infoMenu" x-on:click="infoMenu=!infoMenu; colorMenu=false"><svg aria-hidden="true"><use href="#ps-dots"/></svg></button>
                <div id="ps-info-menu" class="ps-dropdown is-end ps-info-menu" x-show="infoMenu" x-cloak x-transition.opacity role="menu">
                    <span class="ps-label ps-menu-heading" x-show="server.can_edit">{{ $u('look') }}</span>
                    <button type="button" class="ps-menu-item" role="menuitem" x-show="server.can_edit" :disabled="busy" x-on:click="openColorMenu()"><span class="ps-color-dot" aria-hidden="true"></span><span>{{ $u('server_color') }}</span></button>
                    <label class="ps-menu-item" role="menuitem" x-show="server.can_edit"><svg aria-hidden="true"><use href="#ps-image"/></svg><span>{{ $u('server_background') }}</span><input type="file" accept="image/png,image/jpeg,image/webp" hidden x-on:change="infoMenu=false; $event.target.files[0] && saveLook(server.color, $event.target.files[0]); $event.target.value=''"></label>
                    <button type="button" class="ps-menu-item" role="menuitem" x-show="server.can_edit && server.background_id==='upload'" :disabled="busy" x-on:click="infoMenu=false; saveLook(server.color, null, true)"><svg aria-hidden="true"><use href="#ps-close"/></svg><span>{{ $u('no_background') }}</span></button>
                    <button type="button" class="ps-menu-item" role="menuitem" x-show="server.can_edit" x-on:click="infoMenu=false; saveName(@js($u('server_name')), @js($u('rename')))"><svg aria-hidden="true"><use href="#ps-edit"/></svg><span>{{ $u('rename') }}</span></button>
                    <template x-if="server.links?.length"><hr></template>
                    <template x-for="link in server.links || []" :key="link.url">
                        <a class="ps-menu-item" role="menuitem" :href="safeLink(link.url)" target="_blank" rel="noopener noreferrer" x-on:click="infoMenu=false"><svg aria-hidden="true"><use href="#ps-arrow-right"/></svg><span x-text="link.label"></span><svg class="ps-menu-item-out" aria-hidden="true"><use href="#ps-external"/></svg></a>
                    </template>
                    <hr>
                    <button type="button" class="ps-menu-item is-danger" role="menuitem" :disabled="busy || !server.permissions['control.stop'] || status==='offline'" x-on:click="infoMenu=false; power('kill')"><svg aria-hidden="true"><use href="#ps-kill"/></svg><span>{{ $u('force_stop') }}</span></button>
                </div>
                <div class="ps-dropdown is-end ps-color-dropdown" x-show="colorMenu" x-cloak x-on:click.stop>
                    <span class="ps-label">{{ $u('server_color') }}</span>
                    <div class="ps-swatches ps-color-presets">
                        <template x-for="(name, color) in COLORS" :key="color">
                            <button type="button" class="ps-swatch" :style="{'background-color': color}" :data-tip="name" :aria-label="Simplify.fill(@js($u('select_color')), { color: name })" :aria-pressed="colorDraft===color" :disabled="busy" x-on:click="colorDraft=color"></button>
                        </template>
                    </div>
                    <label class="ps-check ps-custom-color"><span>{{ $u('custom_color') }}</span><input type="color" class="ps-color-input" x-model="colorDraft" :disabled="busy" aria-label="{{ $u('choose_custom_server_color') }}"></label>
                    <div class="ps-dropdown-actions">
                        <button type="button" class="ps-btn is-sm is-subtle" x-on:click="saveLook(null)" :disabled="busy || !server.color">{{ $u('automatic') }}</button>
                        <button type="button" class="ps-btn is-sm" x-on:click="colorMenu=false" :disabled="busy">{{ $u('cancel') }}</button>
                        <button type="button" class="ps-btn is-sm is-primary" x-on:click="saveLook(colorDraft)" :disabled="busy">{{ $u('save') }}</button>
                    </div>
                </div>
            </div>
        </article>

        {{-- Live numbers in one row: players and the resource metrics. --}}
        <section class="ps-server-stats" aria-label="{{ $u('server_status') }}">
            <article class="ps-card ps-stat ps-players-stat" x-show="server.players != null || server.minecraft">
                <div class="ps-stat-top"><span>{{ $u('players') }}</span><svg aria-hidden="true"><use href="#ps-players"/></svg></div>
                <strong><span x-text="server.players ?? '—'"></span><span class="ps-stat-total" x-text="server.max_players ? ' / '+server.max_players : ''"></span></strong>
                <small class="ps-player-empty" x-text="server.players>0 ? @js($u('names_not_available')) : @js($u('no_players_online'))"></small>
            </article>
            <template x-for="metric in metrics" :key="metric.key">
                <article class="ps-card ps-stat">
                    <div class="ps-stat-top"><span x-text="metric.label"></span><svg aria-hidden="true"><use :href="'#ps-'+metric.icon"/></svg></div>
                    <strong x-text="metricValue(metric.key)"></strong>
                    {{-- A long line (traffic "since server start") keeps its first part; the whole line is the tooltip. --}}
                    <small x-text="metricLimit(metric.key).split(' · ')[0]" :data-tip="metricLimit(metric.key).includes(' · ') ? metricLimit(metric.key) : null"></small>
                    <svg class="ps-spark" viewBox="0 0 200 36" preserveAspectRatio="none" aria-hidden="true"><path :d="spark(metric.key)"/></svg>
                </article>
            </template>
        </section>
    </div>
    {{-- Pelican's server menu, add-on pages included, next to Pelican's page. --}}
    <nav class="ps-server-menu" aria-label="{{ $u('server_sections') }}" wire:ignore>
        @foreach ($sections as $item)
            <a class="ps-widget" href="{{ $item->getUrl() }}" @if ($item->isActive()) aria-current="page" @endif @if ($item->shouldOpenUrlInNewTab()) target="_blank" rel="noopener" @endif>
                <span class="ps-widget-icon">@if ($item->getIcon())<x-filament::icon :icon="$item->getIcon()" />@endif</span>
                <span><strong>{{ $item->getLabel() }}</strong></span>
            </a>
        @endforeach
    </nav>
    {{-- Phones: four tabs that always fit. The first three sections, the rest under More. --}}
    <nav class="ps-phone-sections" aria-label="{{ $u('server_sections') }}" x-data="{ more: false }" x-on:click.outside="more=false" x-on:keydown.escape.stop="more=false" wire:ignore>
        @foreach (array_slice($sections, 0, 3) as $item)
            <a class="ps-phone-section" href="{{ $item->getUrl() }}" @if ($item->isActive()) aria-current="page" @endif>@if ($item->getIcon())<x-filament::icon class="ps-phone-section-icon" :icon="$item->getIcon()" />@endif<span>{{ $item->getLabel() }}</span></a>
        @endforeach
        @if (count($sections) > 3)
            <div class="ps-phone-more">
                <button type="button" class="ps-phone-section" aria-controls="ps-phone-more-menu" :aria-expanded="more" x-on:click="more=!more"><svg class="ps-phone-section-icon" aria-hidden="true"><use href="#ps-dots"/></svg><span>{{ $u('more') }}</span></button>
                <div id="ps-phone-more-menu" class="ps-dropdown is-end ps-phone-more-menu" x-show="more" x-cloak x-transition.opacity>
                    @foreach (array_slice($sections, 3) as $item)
                        <a class="ps-menu-item" href="{{ $item->getUrl() }}" @if ($item->isActive()) aria-current="page" @endif>@if ($item->getIcon())<x-filament::icon :icon="$item->getIcon()" />@endif<span>{{ $item->getLabel() }}</span></a>
                    @endforeach
                </div>
            </div>
        @endif
    </nav>
    {{-- The title row of Pelican's console, put above its output by server.js. --}}
    <template x-ref="consoleBar">
        <div class="ps-console-title ps-ui" data-quick="{{ $u('quick_commands') }}">
            <span class="ps-terminal-icon" aria-hidden="true">›_</span>
            <strong>{{ $u('live_console') }}</strong>
            <span class="ps-console-dot"></span>
            <span class="ps-live-bars" aria-hidden="true" hidden><i></i><i></i><i></i><i></i></span>
            <span class="ps-console-state" data-connecting="{{ $u('connecting_to_wings') }}" data-connected="{{ $u('connected_to_wings') }}">{{ $u('connecting_to_wings') }}</span>
            <button type="button" class="ps-console-clear ps-console-tool ps-console-grow" data-normal="{{ $u('normal_size') }}" data-bigger="{{ $u('bigger_console') }}" aria-label="{{ $u('bigger_console') }}" aria-pressed="false"><svg aria-hidden="true"><use href="#ps-expand"/></svg></button>
            <button type="button" class="ps-console-clear ps-console-tool ps-console-window" aria-label="{{ $u('open_the_console_in_a_new') }}" data-tip="{{ $u('open_in_a_new_window') }}"><svg aria-hidden="true"><use href="#ps-external"/></svg></button>
        </div>
    </template>
</div>
