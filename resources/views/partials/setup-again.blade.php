{{-- Simplify settings → General → Setup: what the setup does, beside a real button to run it again (Jeroen 5-11). --}}
<div class="s-action-row s-settings-setup">
    <p class="s-muted">{{ __('simplify::settings.general.setup_help') }}</p>
    <a class="s-btn" href="{{ \Simplify\Core\Filament\Admin\Pages\Setup::getUrl(panel: 'admin') }}">
        <x-filament::icon icon="tabler-wand" />
        <span>{{ __('simplify::setup.again') }}</span>
    </a>
</div>
