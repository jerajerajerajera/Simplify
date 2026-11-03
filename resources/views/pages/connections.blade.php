<x-filament-panels::page>
    {{-- One row per add-on connection; each row saves on its own. --}}
    <div class="s-connections">
        @foreach ($rows as $row)
            @livewire($row, key('connection-'.$loop->index))
        @endforeach
    </div>
</x-filament-panels::page>
