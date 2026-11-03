@php($u = fn (string $key, array $values = []) => __('simplify::dashboard.ui.'.$key, $values))
{{-- The Admin page's menu (AdminSide, V52): the admin pages as a tree. It keeps its place while its links load the
     page next to it (wire:navigate), and its breadcrumb (All servers › group › page) is the page's title (Jeroen 3-11).
     On a computer the tree sits left of the page, both straight on the background as on the overview. On a phone the
     bottom bar's Admin opens it as a page of its own with Health at the bottom; the group in the breadcrumb, Admin
     again or a swipe from the left edge bring it back. --}}
<div class="ps server-active ps-admin-side" :class="{'menu-open': open, 'dark': dark}" x-data="{
        open: false,
        dark: window.SimplifyTheme?.dark() ?? document.documentElement.classList.contains('dark'),
        branches: {},
        here: '',
        path: '',
        crumb: null,
        pages: @js(collect($sections)->flatMap(fn ($section) => collect($section['pages'])->map(fn ($page) => [$section['key'], $page['key'] ?? '', $page['url'] ?? '', $section['label'], count($section['pages']) > 1 || $section['key'] === 'extensions' ? $page['label'] : '', $section['icon'], $section['color']]))->values()),
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
            // The breadcrumb: the group, its page, and on a page under it (an edit page) Pelican's own title.
            const title = document.querySelector('.fi-main .fi-header-heading')?.textContent.trim() ?? '';
            const exact = length >= 1000 || (best[2] && at.replace(/\/$/, '') === new URL(best[2], location.origin).pathname.replace(/\/$/, ''));
            this.crumb = length < 0 ? (title ? { key: '', group: title, icon: 'shield', color: 'currentColor', page: '', sub: '' } : null)
                : { key: best[0], group: best[3], page: best[4], icon: best[5], color: best[6], sub: exact || title === best[4] || title === best[3] ? '' : title };
            // Admin in the bottom bar comes with #menu: the menu as a page (phones). Livewire also sends navigated on the first load.
            this.open = location.hash === '#menu' && this.phone();
        },
        phone() { return matchMedia('(max-width: 600px)').matches; },
        // The panel's accent, as the header (AdminTop) has it, for New in the bottom bar and the menu.
        accent() { this.$nextTick(() => { const top = document.querySelector('.ps-admin-top'); if (!top) return; const css = getComputedStyle(top); for (const key of ['--ps-accent', '--ps-on-accent']) { const value = css.getPropertyValue(key).trim(); if (value) this.$root.style.setProperty(key, value); } }); },
        // A swipe from the left edge to the right brings the menu back (phones; the menu is beside the page elsewhere).
        swipe() {
            let start = null;
            addEventListener('touchstart', (e) => { const t = e.touches[0]; start = !this.open && this.phone() && e.touches.length === 1 && t.clientX <= 24 ? [t.clientX, t.clientY] : null; }, { passive: true });
            addEventListener('touchend', (e) => { const t = e.changedTouches[0]; if (start && t.clientX - start[0] > 70 && Math.abs(t.clientY - start[1]) < t.clientX - start[0]) this.open = true; start = null; }, { passive: true });
        },
        // The breadcrumb's width, so the page's own buttons on its row (simplify.css) start after it.
        crumbWidth() { const crumb = this.$root.querySelector('.ps-admin-crumb'); if (crumb) new ResizeObserver(() => document.documentElement.style.setProperty('--ps-crumb-w', crumb.offsetWidth + 'px')).observe(crumb); },
        // The group in the breadcrumb: on a phone back to the menu, on a computer its branch in the tree beside the page.
        group() { if (this.phone()) this.open = true; else if (this.crumb?.key) this.branches[this.crumb.key] = true; },
        all() { return @js(array_column($sections, 'key')).every((key) => this.branches[key]); },
        toggleAll() { const on = !this.all(); for (const key of @js(array_column($sections, 'key'))) this.branches[key] = on; },
     }"
     x-init="locate(); swipe(); accent(); crumbWidth()" x-on:livewire:navigated.window="locate()" x-on:simplify:admin-menu.window="open = !open" x-on:simplify:theme.window="dark = $event.detail.dark; accent()" x-on:keydown.escape.window="open = false">
    <div class="ps-host-backdrop" x-show="open" x-cloak x-on:click="open = false"></div>
    {{-- Where you are, as the page's title: All servers (computers; on a phone the bottom bar has it) › group › page. --}}
    <nav class="ps-admin-crumb" aria-label="{{ $u('admin_sections') }}" x-show="crumb && !open" x-cloak>
        <a class="ps-admin-crumb-home" href="{{ $overviewUrl }}"><svg aria-hidden="true"><use href="#ps-grid"/></svg><span>{{ $u('all_servers') }}</span></a>
        <svg class="ps-admin-crumb-sep ps-admin-crumb-home" aria-hidden="true"><use href="#ps-chevron"/></svg>
        <button type="button" aria-controls="ps-admin-side" :aria-expanded="open" x-on:click.stop="group()" :class="{'is-last': !crumb?.page && !crumb?.sub}" :style="'--ps-tone: ' + (crumb?.color ?? 'currentColor')"><svg aria-hidden="true"><use :href="'#ps-' + (crumb?.icon ?? 'shield')"/></svg><span x-text="crumb?.group"></span></button>
        <template x-for="part in [crumb?.page, crumb?.sub].filter(Boolean)" :key="part">
            <span class="ps-admin-crumb-part"><svg class="ps-admin-crumb-sep" aria-hidden="true"><use href="#ps-chevron"/></svg><strong x-text="part"></strong></span>
        </template>
    </nav>
    <aside id="ps-admin-side" class="ps-host-side" aria-label="{{ $u('admin_sections') }}">
        <div class="ps-host-side-scroll">
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
    {{-- Phones: the bottom bar, as on the overview; Admin opens the Admin menu as a page. --}}
    <nav class="ps-tabbar" aria-label="{{ $u('phone_navigation') }}">
        <a class="ps-tab" href="{{ $overviewUrl }}"><svg aria-hidden="true"><use href="#ps-grid"/></svg><span>{{ $u('servers') }}</span></a>
        @if ($create)
            <button type="button" class="ps-tab" x-on:click="open = false; $dispatch('simplify:create')"><span class="ps-tab-plus"><svg aria-hidden="true"><use href="#ps-plus"/></svg></span><span>{{ $u('new') }}</span></button>
        @endif
        <button type="button" class="ps-tab is-active" aria-current="page" aria-controls="ps-admin-side" :aria-expanded="open" x-on:click.stop="open = true"><svg aria-hidden="true"><use href="#ps-shield"/></svg><span>{{ $u('admin') }}</span></button>
    </nav>
</div>
