{{-- Under the times of an announcement: when this browser's clock is in another time zone than the profile, say so and
     link to the profile, where the time zone of the whole panel is set (Khion 6-11). Nothing is converted silently. --}}
<div class="s-zone-hint" x-cloak x-data="{
        browser: null,
        init() {
            const offset = (zone) => {
                try {
                    const now = new Date();
                    return Math.round((new Date(now.toLocaleString('en-US', { timeZone: zone })) - new Date(now.toLocaleString('en-US', { timeZone: 'UTC' }))) / 60000);
                } catch { return null }
            };
            let here = null;
            try { here = Intl.DateTimeFormat().resolvedOptions().timeZone } catch {}
            if (here && offset(here) !== null && offset(here) !== offset(@js($zone))) this.browser = here;
        },
    }" x-show="browser">
    <x-filament::icon icon="tabler-alert-triangle" class="s-zone-hint-icon" />
    <span><span x-text="@js(__('simplify::announcements.form.zone_hint', ['zone' => $zone])).replace(':browser', browser)"></span>
        <a href="{{ $profile }}">{{ __('simplify::announcements.form.zone_hint_link') }}</a></span>
</div>
