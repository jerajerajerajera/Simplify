{{-- Host mode (admins, large hosts): one frame with a menu on the left (admin pages as a tree, or the servers per node)
     and the page or server on the right; each side scrolls on its own. Phones open the menu as a sheet. --}}
<section class="ps-host" :class="{'menu-open': hostMenuOpen}" x-show="hostMode" x-cloak :style="accentStyle('overview')" x-on:keydown.escape="hostMenuOpen=false">
    <button type="button" class="ps-btn ps-host-menu-toggle" x-on:click="hostMenuOpen=true" aria-controls="ps-host-side" :aria-expanded="hostMenuOpen">
        <svg aria-hidden="true"><use href="#ps-list"/></svg><span x-text="hostServer ? cardNames(hostServer).large : (hostPage && hostSection?.pages.length > 1 ? hostSection.label+' · '+hostPage.label : hostSection?.label || @js($u('admin')))"></span><svg class="ps-host-menu-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg>
    </button>
    <div class="ps-host-backdrop" x-show="hostMenuOpen" x-cloak x-on:click="hostMenuOpen=false"></div>
    <aside id="ps-host-side" class="ps-host-side" aria-label="{{ $u('host_menu') }}">
        <div class="ps-host-tabs" role="group" aria-label="{{ $u('menu') }}">
            <button type="button" :aria-pressed="hostTab==='admin'" x-on:click="hostTab='admin'"><svg aria-hidden="true"><use href="#ps-shield"/></svg>{{ $u('admin') }}</button>
            <button type="button" :aria-pressed="hostTab==='servers'" x-on:click="hostTab='servers'"><svg aria-hidden="true"><use href="#ps-host"/></svg>{{ $u('servers') }}<small x-text="(boot.servers||[]).length"></small></button>
            <button type="button" class="ps-btn is-icon is-subtle ps-host-menu-close" x-on:click="hostMenuOpen=false" aria-label="{{ $u('close_menu') }}"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
        </div>
        <div class="ps-host-side-scroll">
            {{-- Admin pages as a tree: every section with its pages hung underneath; a section with one page opens it directly. --}}
            <nav class="ps-host-tree" x-show="hostTab==='admin'" aria-label="{{ $u('admin_sections') }}">
                <template x-for="section in hostSections().filter((x) => x.key !== 'health')" :key="section.key">
                    <div class="ps-host-group" :style="{'--ps-tone': section.color}">
                        <button type="button" class="ps-host-group-head" :aria-current="hostSection?.key===section.key && !hostServer && section.pages.length === 1 ? 'page' : null" x-on:click="openHostSection(section)">
                            <svg aria-hidden="true"><use :href="'#ps-'+section.icon"/></svg><span x-text="section.label"></span>
                        </button>
                        <div class="ps-host-branch" x-show="hostBranchOpen(section)">
                            <template x-for="item in section.pages" :key="item.key">
                                <button type="button" class="ps-host-leaf" :aria-current="hostSection?.key===section.key && hostPage?.key===item.key && !hostServer ? 'page' : null" x-on:click="openHostLeaf(section, item)">
                                    <svg aria-hidden="true"><use :href="'#ps-'+item.icon"/></svg><span x-text="item.label"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </nav>
            {{-- Servers per node; a server opens on the right, ctrl/cmd-click opens its full page. --}}
            <div class="ps-host-server-list" x-show="hostTab==='servers'">
                <template x-for="node in hostNodes()" :key="node.id">
                    <div class="ps-host-node">
                        <span class="ps-label"><svg aria-hidden="true"><use href="#ps-host"/></svg><span x-text="node.name"></span><small x-text="Simplify.choice(@js($u('server_count')), node.servers.length)"></small></span>
                        <template x-for="s in node.servers" :key="s.uuid">
                            <a class="ps-host-server" :href="s.linked ? s.url : null" :style="accentStyle(s.uuid)" :aria-disabled="!s.linked" :aria-current="hostServer?.uuid===s.uuid ? 'page' : null" x-on:click="if (s.linked && !$event.ctrlKey && !$event.metaKey && !$event.shiftKey) { $event.preventDefault(); openHostServer(s); }">
                                <i class="ps-host-dot ps-status" :class="s.status" :aria-label="Simplify.status(s.status)"></i>
                                <strong x-text="cardNames(s).large"></strong>
                                <small x-text="hostServerVersion(s)"></small>
                                <span class="ps-host-players"><svg aria-hidden="true"><use href="#ps-players"/></svg><span x-text="playerCount(s)"></span></span>
                            </a>
                        </template>
                    </div>
                </template>
                <p class="ps-empty" x-show="!(boot.servers||[]).length">{{ $u('no_servers_yet') }}</p>
            </div>
        </div>
        {{-- Health at the bottom of the menu: state, CPU and RAM, and one mark per health check. --}}
        <template x-for="section in hostSections().filter((x) => x.key === 'health')" :key="section.key">
            <div class="ps-host-health-bar" :class="{'is-current': hostSection?.key==='health' && !hostServer}">
                <button type="button" class="ps-host-health-open" x-on:click="openHostSection(section)">
                    <span class="ps-host-health-state"><svg class="ps-health-heart" :class="boot.host_health?.state || 'unavailable'" aria-hidden="true"><use href="#ps-heart"/></svg><strong x-text="boot.features.host_health ? (boot.host_health?.label || @js($u('unavailable'))) : @js($u('health'))"></strong><small x-show="boot.features.host_health" x-text="Simplify.fill(@js($u('hosts_available')), { available: boot.host_health?.available ?? 0, count: boot.host_health?.hosts ?? 0 })"></small></span>
                    <span class="ps-host-health-meters" x-show="boot.features.host_health">
                        <template x-for="kind in ['cpu', 'memory']" :key="kind">
                            <span><span><span x-text="kind==='cpu' ? @js($u('cpu')) : @js($u('ram'))"></span> <b x-text="hostValue(kind)"></b></span><span class="ps-meter"><span :style="{width: hostFill(kind)+'%'}"></span></span></span>
                        </template>
                    </span>
                </button>
                <div class="ps-health-grid" x-show="healthChecks().length" role="group" aria-label="{{ $u('current_health_checks') }}">
                    @include('pelican-simplify::dashboard.health-checks', ['open' => 'openHostSection(section)'])
                </div>
            </div>
        </template>
    </aside>
    <div class="ps-host-main">
        <header class="ps-host-head" x-show="hostServer" x-cloak :style="accentStyle(hostServer?.uuid || 'overview')">
            <i class="ps-host-dot ps-status" :class="hostServer?.status"></i>
            <h2 class="ps-host-server-name" x-text="hostServer ? cardNames(hostServer).large : ''"></h2>
            <small x-text="hostServer ? hostServerVersion(hostServer) : ''"></small>
            <span class="ps-host-head-actions">
                <button type="button" class="ps-btn is-sm is-icon" x-on:click="closeHostServer()" data-tip="{{ $u('close') }}" aria-label="{{ $u('close_server') }}"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
            </span>
        </header>
        <header class="ps-host-head is-page" x-show="!hostServer && hostSection" :style="{'--ps-tone': hostSection?.color}">
            <svg class="ps-host-head-icon" aria-hidden="true"><use :href="'#ps-'+(hostSection?.icon||'host')"/></svg>
            <h2 x-text="hostSection?.label"></h2>
            <small x-show="hostSection?.pages.length > 1" x-text="hostPage?.label"></small>
        </header>
        <div class="ps-host-frame is-server" x-show="hostServer" :style="accentStyle(hostServer?.uuid || 'overview')">
            <template x-for="s in hostServer ? [hostServer] : []" :key="s.uuid">
                <iframe data-ps-frame="server" :src="frameUrl(s.url)" :title="s.name"></iframe>
            </template>
        </div>
        <div class="ps-host-frame" x-show="!hostServer">
            <template x-if="hostMode && hostPage">
                <iframe data-ps-frame="native" :data-ps-tree="hostPage.settings ? '1' : null" :src="frameUrl(hostPage.url)" :title="hostPage.label"></iframe>
            </template>
        </div>
    </div>
</section>
