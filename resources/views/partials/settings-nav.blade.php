{{--
    One row of tabs over Simplify's settings (V51/V53), on the settings page and on every settings page it links to
    (Settings::nav(), SimplifyPlugin::settingsNav()). On the settings page itself a tab of the page switches the page's
    hidden Filament tabs in place; every other tab is a page of its own.
--}}
<nav class="s-settings-nav" aria-label="{{ __('simplify::settings.title') }}" x-data="{ current: @js($active) }"
    @if ($here) x-init="const tab = new URLSearchParams(location.search).get('tab'); if (@js(\Simplify\Core\Filament\Admin\Pages\Settings::TABS).includes(tab)) current = tab" @endif>
    @foreach ($items as $item)
        <a
            href="{{ $item['url'] }}"
            class="s-settings-nav-item"
            @unless ($item['tab'] && $here) wire:navigate @endunless
            :aria-current="current === @js($item['key']) ? 'page' : null"
            @if ($item['tab'] && $here)
                data-tab
                x-on:click="
                    const tabs = document.querySelector('.s-settings-tabs > .fi-tabs');
                    if (! tabs) return;
                    $event.preventDefault();
                    tabs.querySelectorAll('.fi-tabs-item')[{{ array_search($item['key'], \Simplify\Core\Filament\Admin\Pages\Settings::TABS, true) }}]?.click();
                    current = @js($item['key']);
                "
            @endif
        >
            <x-filament::icon :icon="$item['icon']" />
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
