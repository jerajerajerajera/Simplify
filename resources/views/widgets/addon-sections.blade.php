<x-filament-widgets::widget>
    <div class="s-addon-sections">
        @foreach ($this->sections() as $component)
            @livewire($component, ['server' => $server], key($component.'-'.$server->id))
        @endforeach
    </div>
</x-filament-widgets::widget>
