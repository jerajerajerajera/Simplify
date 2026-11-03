{{-- Personal appearance preferences, opened from Customize in the account menu. --}}
<div class="ps-overview-appearance" x-data="{ adminTop: !!$el.closest('.ps-admin-top') }" x-on:click.outside="appearanceMenu=false" x-on:keydown.escape.stop="appearanceMenu=false; $el.closest('.ps-account')?.querySelector('.ps-account-button')?.focus()">
    <div id="ps-overview-appearance-menu" class="ps-dropdown is-end ps-overview-appearance-menu" x-show="appearanceMenu" x-cloak x-transition.opacity>
        <strong class="ps-dropdown-title">{{ $u('customize') }}</strong>
        <p class="ps-dropdown-hint">{{ $u('only_for_you_on_this_device') }}</p>
        {{-- The theme, on every page (Jeroen 3-11: in Customize Simplify instead of the account menu). --}}
        <div class="ps-customize ps-customize-theme">
            <div class="ps-customize-row is-first">
                <div><strong>{{ $u('theme') }}</strong><small>{{ $u('theme_hint') }}</small></div>
                <div class="ps-segmented" role="group" aria-label="{{ $u('theme') }}">
                    <template x-for="t in [{key:'light',label:@js($u('light')),icon:'sun'},{key:'dark',label:@js($u('dark')),icon:'moon'},{key:'system',label:@js($u('system')),icon:'monitor'}]" :key="t.key">
                        <button type="button" :aria-pressed="theme===t.key" x-on:click="setTheme(t.key)"><svg aria-hidden="true"><use :href="'#ps-'+t.icon"/></svg><span x-text="t.label"></span></button>
                    </template>
                </div>
            </div>
        </div>
        {{-- The Admin page and the account pages have only the theme. --}}
        <template x-if="!server && !adminTop">
            <div class="ps-customize">
                {{-- Server groups have their own window, opened from here (as Customize › Server groups on a phone). --}}
                <button type="button" class="ps-customize-switch is-first" x-on:click.stop="appearanceMenu=false; groupsMenu=true">
                    <span><strong>{{ $u('server_groups') }}</strong><small>{{ $u('saved_with_your_account_so_every') }}</small></span>
                    <svg class="ps-customize-go" aria-hidden="true"><use href="#ps-chevron"/></svg>
                </button>
                <button type="button" class="ps-customize-head is-toggle is-first" :aria-expanded="customizeOpen('overview.layout')" aria-controls="ps-customize-overview-layout" x-on:click="toggleCustomize('overview.layout')"><span>{{ $u('layout') }}</span><svg class="ps-customize-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                <div class="ps-customize-section" id="ps-customize-overview-layout" x-show="customizeOpen('overview.layout')">
                <div class="ps-customize-row">
                    <div><strong>{{ $u('layout') }}</strong><small>{{ $u('cards_small_cards_or_rows') }}</small></div>
                    <div class="ps-segmented" role="group" aria-label="{{ $u('layout') }}">
                        <template x-for="view in [{key:'grid',label:@js($u('cards')),icon:'grid'},{key:'compact',label:@js($u('compact')),icon:'grid-small'},{key:'list',label:@js($u('list')),icon:'list'}]" :key="view.key">
                            <button type="button" :aria-pressed="overviewView===view.key" x-on:click="setOverviewView(view.key)"><svg aria-hidden="true"><use :href="'#ps-'+view.icon"/></svg><span x-text="view.label"></span></button>
                        </template>
                    </div>
                </div>
                <div class="ps-customize-row" x-show="overviewView!=='list' && !overviewCompact">
                    <div><strong>{{ $u('columns') }}</strong><small>{{ $u('most_cards_per_row') }}</small></div>
                    <div class="ps-segmented ps-columns-choice" role="group" aria-label="{{ $u('columns') }}">
                        <template x-for="count in ['auto','2','3','4','5','6']" :key="count">
                            <button type="button" :aria-pressed="overviewPreferences.columns===count" x-on:click="setOverviewPreference('columns', count)" x-text="count==='auto' ? @js($u('auto')) : count"></button>
                        </template>
                    </div>
                </div>
                <div class="ps-customize-row" x-show="overviewView!=='list' && !overviewCompact">
                    <div><strong>{{ $u('card_width') }}</strong><small>{{ $u('size_of_each_card') }}</small></div>
                    <div class="ps-segmented" role="group" aria-label="{{ $u('card_width') }}">
                        <template x-for="size in [{key:'narrow',label:@js($u('narrow'))},{key:'normal',label:@js($u('normal'))},{key:'wide',label:@js($u('wide'))}]" :key="size.key">
                            <button type="button" :aria-pressed="overviewPreferences.cardWidth===size.key" x-on:click="setOverviewPreference('cardWidth', size.key)" x-text="size.label"></button>
                        </template>
                    </div>
                </div>
                <button type="button" class="ps-customize-switch" role="switch" :aria-checked="overviewPreferences.fullWidth" x-on:click="setOverviewPreference('fullWidth', !overviewPreferences.fullWidth)">
                    <span><strong>{{ $u('full_screen_width') }}</strong><small>{{ $u('use_the_whole_width_of_the_2') }}</small></span>
                    <span class="ps-switch" :class="{'is-on': overviewPreferences.fullWidth}" aria-hidden="true"></span>
                </button>
                </div>
                <button type="button" class="ps-customize-head is-toggle" :aria-expanded="customizeOpen('overview.cards')" aria-controls="ps-customize-overview-cards" x-on:click="toggleCustomize('overview.cards')"><span>{{ $u('cards') }}</span><svg class="ps-customize-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                <div class="ps-customize-section" id="ps-customize-overview-cards" x-show="customizeOpen('overview.cards')">
                <div class="ps-customize-row">
                    <div><strong>{{ $u('card_icon') }}</strong><small>{{ $u('the_big_picture_at_the_top') }}</small></div>
                    <div class="ps-segmented" role="group" aria-label="{{ $u('card_icon') }}">
                        <button type="button" :aria-pressed="overviewPreferences.largeIcon==='server'" x-on:click="setOverviewPreference('largeIcon','server')">{{ $u('server') }}</button>
                        <button type="button" :aria-pressed="overviewPreferences.largeIcon==='egg'" x-on:click="setOverviewPreference('largeIcon','egg')">{{ $u('egg') }}</button>
                    </div>
                </div>
                <span class="ps-customize-sub">{{ $u('show_on_each_card') }}</span>
                <template x-for="option in [{key:'showMotd',label:@js($u('message_of_the_day')),hint:@js($u('the_minecraft_motd_under_the_server'))},{key:'showCpu',label:@js($u('cpu_usage')),hint:@js($u('a_small_live_cpu_graph'))},{key:'showRam',label:@js($u('memory_usage')),hint:@js($u('a_small_live_ram_graph'))}]" :key="option.key">
                    <button type="button" class="ps-customize-switch" role="switch" :aria-checked="overviewPreferences[option.key]" x-on:click="setOverviewPreference(option.key, !overviewPreferences[option.key])">
                        <span><strong x-text="option.label"></strong><small x-text="option.hint"></small></span>
                        <span class="ps-switch" :class="{'is-on': overviewPreferences[option.key]}" aria-hidden="true"></span>
                    </button>
                </template>
                <div class="ps-customize-row">
                    <div><strong>{{ $u('quick_actions') }}</strong><small>{{ $u('start_stop_and_restart_buttons_on') }}</small></div>
                    <div class="ps-segmented" role="group" aria-label="{{ $u('quick_actions') }}">
                        <template x-for="mode in [{key:'off',label:@js($u('off'))},{key:'on',label:@js($u('on'))},{key:'hover',label:@js($u('hover'))}]" :key="mode.key">
                            <button type="button" :aria-pressed="overviewPreferences.quickActions===mode.key" x-on:click="setOverviewPreference('quickActions', mode.key)" x-text="mode.label"></button>
                        </template>
                    </div>
                </div>
                </div>
                <button type="button" class="ps-customize-head is-toggle" :aria-expanded="customizeOpen('overview.grouping')" aria-controls="ps-customize-overview-grouping" x-on:click="toggleCustomize('overview.grouping')"><span>{{ $u('grouping') }}</span><svg class="ps-customize-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                <div class="ps-customize-section" id="ps-customize-overview-grouping" x-show="customizeOpen('overview.grouping')">
                <div class="ps-customize-row">
                    <div><strong>{{ $u('group_by_node') }}</strong><small>{{ $u('auto_groups_servers_per_node_once') }}</small></div>
                    <div class="ps-segmented" role="group" aria-label="{{ $u('group_by_node') }}">
                        <template x-for="mode in [{key:'auto',label:@js($u('auto'))},{key:'always',label:@js($u('always'))},{key:'off',label:@js($u('off'))}]" :key="mode.key">
                            <button type="button" :aria-pressed="overviewPreferences.groupByNode===mode.key" x-on:click="setOverviewPreference('groupByNode', mode.key)" x-text="mode.label"></button>
                        </template>
                    </div>
                </div>
                </div>
                <button type="button" class="ps-customize-head is-toggle" x-show="(seasonsToday().length && boot.appearance?.seasonal !== false) || boot.settings_url" :aria-expanded="customizeOpen('overview.more')" aria-controls="ps-customize-overview-more" x-on:click="toggleCustomize('overview.more')"><span>{{ $u('more') }}</span><svg class="ps-customize-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                <div class="ps-customize-section" id="ps-customize-overview-more" x-show="customizeOpen('overview.more')">
                <button type="button" class="ps-customize-switch" role="switch" x-show="seasonsToday().length && boot.appearance?.seasonal !== false" :aria-checked="overviewPreferences.halloween" x-on:click="setOverviewPreference('halloween', !overviewPreferences.halloween)">
                    <span><strong>{{ $u('seasonal_touches') }}</strong><small x-text="Simplify.fill(@js($u('a_few_small_touches_for')), { name: seasonNames() })"></small></span>
                    <span class="ps-switch" :class="{'is-on': overviewPreferences.halloween}" aria-hidden="true"></span>
                </button>
                {{-- Server colours are a panel setting; point administrators at it from here. Group colours live with each group. --}}
                <div class="ps-customize-row" x-show="boot.settings_url">
                    <div><strong>{{ $u('server_colors') }}</strong><small>{{ $u('one_color_for_every_server_or') }}</small></div>
                    <a class="ps-btn is-sm" :href="boot.settings_url">{{ $u('open_settings') }}</a>
                </div>
                </div>
            </div>
        </template>
        <template x-if="server && !adminTop">
            <div class="ps-customize">
                <span class="ps-customize-sub">{{ $u('server_pages') }}</span>
                <button type="button" class="ps-customize-switch" role="switch" :aria-checked="overviewPreferences.serverBiggerConsole" x-on:click="setOverviewPreference('serverBiggerConsole', !overviewPreferences.serverBiggerConsole)">
                    <span><strong>{{ $u('bigger_console') }}</strong><small>{{ $u('open_server_pages_with_the_large') }}</small></span>
                    <span class="ps-switch" :class="{'is-on': overviewPreferences.serverBiggerConsole}" aria-hidden="true"></span>
                </button>
                <button type="button" class="ps-customize-switch" role="switch" :aria-checked="overviewPreferences.serverConsoleLog" x-on:click="setOverviewPreference('serverConsoleLog', !overviewPreferences.serverConsoleLog)">
                    <span><strong>{{ $u('log_in_the_mini_console') }}</strong><small>{{ $u('last_lines_on_other_pages') }}</small></span>
                    <span class="ps-switch" :class="{'is-on': overviewPreferences.serverConsoleLog}" aria-hidden="true"></span>
                </button>
                <button type="button" class="ps-customize-switch" role="switch" :aria-checked="overviewPreferences.fullWidth" x-on:click="setOverviewPreference('fullWidth', !overviewPreferences.fullWidth)">
                    <span><strong>{{ $u('full_screen_width') }}</strong><small>{{ $u('use_the_whole_width_of_the') }}</small></span>
                    <span class="ps-switch" :class="{'is-on': overviewPreferences.fullWidth}" aria-hidden="true"></span>
                </button>
            </div>
        </template>
    </div>
</div>
