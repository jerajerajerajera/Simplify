{{-- On Pelican's own pages: a button in the top bar back to Simplify's overview, as in PelicanSimplify 0.7.0. --}}
<x-filament::button tag="a" color="gray" size="sm" icon="tabler-layout-grid" class="s-return" :href="\Simplify\Core\Filament\App\Pages\Overview::getUrl(panel: 'app')">
    {{ __('simplify::settings.return') }}
</x-filament::button>
