{{-- In the legacy panel (Pelican's own pages): a button in the top bar back to Simplify's overview, which ends the legacy panel. --}}
<x-filament::button tag="a" color="gray" size="sm" icon="tabler-layout-grid" class="s-return" :href="\Simplify\Core\Support\Legacy::url(\Simplify\Core\Filament\App\Pages\Overview::getUrl(panel: 'app'), false)">
    {{ __('simplify::settings.return') }}
</x-filament::button>
