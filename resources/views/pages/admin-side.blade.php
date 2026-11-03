@php($u = fn (string $key, array $values = []) => __('simplify::dashboard.ui.'.$key, $values))
{{-- The Admin page's menu (AdminSide, V52): the admin pages as a tree, or the servers per node, with health at the
     bottom. It keeps its place while its links load the page next to it (wire:navigate). On a phone it is a sheet that
     the bottom bar's Menu tab opens (simplify:admin-menu). --}}
<div class="ps server-active ps-admin-side" :class="{'menu-open': open, 'dark': dark}" x-data="{
        open: false,
        dark: window.SimplifyTheme?.dark() ?? document.documentElement.classList.contains('dark'),
        branches: {},
        here: '',
        path: '',
        pages: @js(collect($sections)->flatMap(fn ($section) => collect($section['pages'])->map(fn ($page) => [$section['key'], $page['key'] ?? '', $page['url'] ?? '']))->values()),
        locate() {
            this.path = location.pathname.replace(/\/$/, '');
            const at = location.pathname + location.search;
            let best = ['', '', ''], length = -1;
            for (const page of this.pages) {
                if (!page[2]) continue;
                const url = new URL(page[2], location.origin), path = url.pathname.replace(/\/$/, '');
                const full = path + url.search;
                const score = at === full || (url.search && at.startsWith(full)) ? full.length + 1000 : (at === path || at.startsWith(path + '/') || at.startsWith(path + '?')) ? path.length : -1;
                if (score > length) { best = page; length = score; }
            }
            this.here = length < 0 ? '' : best[0] + '/' + best[1];
            if (best[0]) this.branches[best[0]] = true;
            this.open = false;
        },
        all() { return @js(array_column($sections, 'key')).every((key) => this.branches[key]); },
        toggleAll() { const on = !this.all(); for (const key of @js(array_column($sections, 'key'))) this.branches[key] = on; },
     }"
     x-init="locate()" x-on:livewire:navigated.window="locate()" x-on:simplify:admin-menu.window="open = !open" x-on:simplify:theme.window="dark = $event.detail.dark" x-on:keydown.escape.window="open = false">
    <div class="ps-host-backdrop" x-show="open" x-cloak x-on:click="open = false"></div>
    <aside id="ps-admin-side" class="ps-host-side" aria-label="{{ $u('admin_sections') }}">
        <div class="ps-host-tabs" role="group" aria-label="{{ $u('menu') }}">
            <button type="button" aria-pressed="{{ $tab === 'admin' ? 'true' : 'false' }}" wire:click="$set('tab', 'admin')"><svg aria-hidden="true"><use href="#ps-shield"/></svg>{{ $u('admin') }}</button>
            <button type="button" aria-pressed="{{ $tab === 'servers' ? 'true' : 'false' }}" wire:click="$set('tab', 'servers')"><svg aria-hidden="true"><use href="#ps-host"/></svg>{{ $u('servers') }}<small>{{ $serverCount }}</small></button>
            <button type="button" class="ps-btn is-icon is-subtle ps-host-menu-close" x-on:click="open = false" aria-label="{{ $u('close_menu') }}"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
        </div>
        <div class="ps-host-side-scroll">
            @if ($tab === 'admin')
                {{-- A section with one page opens it; the others (and Other plugins, so a plugin shows its own name) fold open and shut. --}}
                <nav class="ps-host-tree" aria-label="{{ $u('admin_sections') }}">
                    <button type="button" class="ps-host-expand" x-on:click="toggleAll()" x-text="all() ? @js($u('collapse_all')) : @js($u('expand_all'))">{{ $u('expand_all') }}</button>
                    @foreach ($sections as $section)
                        <div class="ps-host-group" wire:key="section-{{ $section['key'] }}" :class="{'is-open': branches[@js($section['key'])]}" style="--ps-tone: {{ $section['color'] }}">
                            @if (count($section['pages']) === 1 && $section['key'] !== 'extensions')
                                <a class="ps-host-group-head" href="{{ $section['pages'][0]['url'] }}" wire:navigate :aria-current="here === @js($section['key'].'/'.($section['pages'][0]['key'] ?? '')) ? 'page' : null">
                                    <svg aria-hidden="true"><use href="#ps-{{ $section['icon'] }}"/></svg><span>{{ $section['label'] }}</span>
                                </a>
                            @else
                                <button type="button" class="ps-host-group-head" :aria-expanded="!!branches[@js($section['key'])]" x-on:click="branches[@js($section['key'])] = !branches[@js($section['key'])]">
                                    <svg aria-hidden="true"><use href="#ps-{{ $section['icon'] }}"/></svg><span>{{ $section['label'] }}</span>
                                    <svg class="ps-host-group-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg>
                                </button>
                                <div class="ps-host-branch" x-show="branches[@js($section['key'])]" x-cloak>
                                    @foreach ($section['pages'] as $page)
                                        <a class="ps-host-leaf" href="{{ $page['url'] }}" wire:navigate :aria-current="here === @js($section['key'].'/'.($page['key'] ?? '')) ? 'page' : null">
                                            <svg aria-hidden="true"><use href="#ps-{{ $page['icon'] ?? 'plugins' }}"/></svg><span>{{ $page['label'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </nav>
            @else
                {{-- Servers per node; a server opens its own page. --}}
                <div class="ps-host-server-list" wire:poll.visible.15s>
                    @forelse ($nodes as $node)
                        <div class="ps-host-node" wire:key="node-{{ $loop->index }}">
                            <span class="ps-label"><svg aria-hidden="true"><use href="#ps-host"/></svg><span>{{ $node['name'] }}</span><small>{{ trans_choice('simplify::dashboard.ui.server_count', count($node['servers']), ['count' => count($node['servers'])]) }}</small></span>
                            @foreach ($node['servers'] as $server)
                                <a class="ps-host-server" href="{{ $server['url'] }}" wire:key="server-{{ $server['uuid'] }}">
                                    <i class="ps-host-dot ps-status {{ $server['status'] }}"></i>
                                    <strong>{{ $server['name'] }}</strong>
                                    <small>{{ $server['line'] }}</small>
                                    <span class="ps-host-players"><svg aria-hidden="true"><use href="#ps-players"/></svg><span>{{ $server['players'] }}</span></span>
                                </a>
                            @endforeach
                        </div>
                    @empty
                        <p class="ps-empty">{{ $u('no_servers_yet') }}</p>
                    @endforelse
                </div>
            @endif
            <div wire:loading.delay wire:target="tab">
                @for ($i = 0; $i < 6; $i++)
                    <div class="ps-host-placeholder"></div>
                @endfor
            </div>
        </div>
        {{-- Health at the bottom: state, CPU and RAM (root admins), and one mark per health check of Pelican. --}}
        @if ($healthUrl !== null)
            <div class="ps-host-health-bar" :class="{'is-current': here.startsWith('health/')}">
                <a class="ps-host-health-open" href="{{ $healthUrl }}" wire:navigate>
                    <span class="ps-host-health-state"><svg class="ps-health-heart {{ $health['state'] ?? 'unavailable' }}" aria-hidden="true"><use href="#ps-heart"/></svg><strong>{{ $health['label'] ?? $u('health') }}</strong>@if ($health !== [])<small>{{ $u('hosts_available', ['available' => $health['available'] ?? 0, 'count' => $health['hosts'] ?? 0]) }}</small>@endif</span>
                    @if ($health !== [])
                        <span class="ps-host-health-meters">
                            @foreach (['cpu' => is_numeric($health['cpu_percent'] ?? null) ? (float) $health['cpu_percent'] : null, 'ram' => ($health['memory_total'] ?? 0) > 0 ? 100 * $health['memory_used'] / $health['memory_total'] : null] as $kind => $percent)
                                <span><span>{{ $u($kind) }} <b>{{ $percent === null ? '—' : number_format($percent, 1).'%' }}</b></span><span class="ps-meter"><span style="width: {{ min(100, max(0, (int) $percent)) }}%"></span></span></span>
                            @endforeach
                        </span>
                    @endif
                </a>
                @if ($checks !== [])
                    <div class="ps-health-grid" role="group" aria-label="{{ $u('current_health_checks') }}">
                        @foreach ($checks as $check)
                            <a class="ps-health-check is-{{ $check['status'] }}" href="{{ $healthUrl }}" wire:navigate data-tip="{{ $check['label'].($check['status'] === 'ok' ? '' : ': '.($check['message'] ?: $check['status'])) }}" aria-label="{{ $check['label'].': '.$check['status'] }}">
                                <svg aria-hidden="true"><use href="#ps-{{ $check['status'] === 'ok' ? 'check' : 'alert' }}"/></svg>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </aside>
    {{-- Phones: the bottom bar, as on the overview; Menu opens the Admin menu as a sheet. --}}
    <nav class="ps-tabbar" aria-label="{{ $u('phone_navigation') }}">
        <a class="ps-tab" href="{{ $overviewUrl }}"><svg aria-hidden="true"><use href="#ps-grid"/></svg><span>{{ $u('servers') }}</span></a>
        <a class="ps-tab is-active" href="{{ $adminUrl }}" aria-current="page"><svg aria-hidden="true"><use href="#ps-shield"/></svg><span>{{ $u('admin') }}</span></a>
        <button type="button" class="ps-tab" aria-controls="ps-admin-side" :aria-expanded="open" x-on:click.stop="open = !open"><svg aria-hidden="true"><use href="#ps-list"/></svg><span>{{ $u('menu') }}</span></button>
    </nav>
</div>
