@php
    use Simplify\Core\Announcements\Support\Rules;
@endphp

<div @class(['flex flex-col gap-3', 'mt-3' => $announcements->isNotEmpty()])>
    @foreach ($announcements as $announcement)
        <x-filament::callout
            wire:key="simplify-announcement-{{ $announcement->id }}"
            :icon="Rules::icon($announcement->category)"
            :color="Rules::color($announcement->category)"
        >
            <x-slot name="heading">{{ $announcement->title }}</x-slot>

            @if (filled($announcement->body) || $announcement->ends_at)
                <x-slot name="description">
                    @if (filled($announcement->body))
                        <span class="whitespace-pre-line">{{ $announcement->body }}</span>
                    @endif
                    @if ($announcement->ends_at)
                        {{-- Shown in the browser's own time zone. --}}
                        <span class="block text-xs opacity-75">
                            {{ __('pelican-simplify::announcements.bars.until') }}
                            <time
                                datetime="{{ $announcement->ends_at->toIso8601String() }}"
                                x-data
                                x-text="new Date($el.getAttribute('datetime')).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })"
                            >{{ $announcement->ends_at->toDayDateTimeString() }} UTC</time>
                        </span>
                    @endif
                </x-slot>
            @endif

            @if ($announcement->button_url)
                <x-slot name="footer">
                    <x-filament::button
                        tag="a"
                        :href="$announcement->button_url"
                        :target="str_starts_with($announcement->button_url, '/') ? null : '_blank'"
                        :color="Rules::color($announcement->category)"
                        size="sm"
                    >
                        {{ $announcement->button_label ?: __('pelican-simplify::announcements.bars.open') }}
                    </x-filament::button>
                </x-slot>
            @endif

            <x-slot name="controls">
                <x-filament::icon-button
                    icon="tabler-pinned-filled"
                    color="gray"
                    :label="__('pelican-simplify::announcements.bars.unpin')"
                    :tooltip="__('pelican-simplify::announcements.bars.unpin')"
                    aria-pressed="true"
                    wire:click="unpin({{ $announcement->id }})"
                />
            </x-slot>
        </x-filament::callout>
    @endforeach
</div>
