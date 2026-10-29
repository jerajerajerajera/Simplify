<x-filament-panels::page>
    {{-- Polls while the page waits for something outside the panel (Cloudflare), so the status changes without a reload. --}}
    <form wire:submit="save" @if ($this->waiting()) wire:poll.3s @endif>
        {{ $this->form }}
    </form>
</x-filament-panels::page>
