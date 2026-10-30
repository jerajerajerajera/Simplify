{{-- Backups & tasks (ServerMenu): Backups and Schedules as two tabs above their pages. --}}
<nav class="ps-segmented ps-page-tabs" aria-label="{{ __('simplify::dashboard.ui.backups_and_tasks') }}">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['url'] }}" @if ($tab['active']) aria-current="page" @endif>{{ $tab['label'] }}</a>
    @endforeach
</nav>
