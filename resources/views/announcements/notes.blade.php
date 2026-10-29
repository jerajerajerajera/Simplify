@php
    use Simplify\Core\Announcements\Support\Rules;
@endphp
{{-- The bars as short rows in the server card, beside the name: two fit, +n on the second shows the rest. --}}
<div class="ps-info-notes" x-data="{ open: false }" x-on:click.outside="open=false" x-on:keydown.escape.stop="open=false">
    @if ($announcements->isNotEmpty())
        <div class="ps-notes" :class="{ 'is-open': open }">
            @foreach ($announcements as $index => $announcement)
                <div class="ps-note is-{{ Rules::color($announcement->category) }}" wire:key="simplify-note-{{ $announcement->id }}">
                    <x-filament::icon class="ps-note-icon" :icon="Rules::icon($announcement->category)" />
                    <p class="ps-note-text" title="{{ $announcement->title }}{{ filled($announcement->body) ? ' · '.$announcement->body : '' }}"><strong>{{ $announcement->title }}</strong>@if (filled($announcement->body)) <span>{{ $announcement->body }}</span>@endif</p>
                    @if ($announcement->button_url)
                        <a class="ps-btn is-sm is-subtle ps-note-link" href="{{ $announcement->button_url }}" @if (! str_starts_with($announcement->button_url, '/')) target="_blank" rel="noopener" @endif>{{ $announcement->button_label ?: __('simplify::announcements.bars.open') }}</a>
                    @endif
                    @if ($index === 1 && $announcements->count() > 2)
                        <button type="button" class="ps-btn is-sm is-subtle ps-notes-more" x-on:click="open=!open" :aria-expanded="open" aria-label="{{ __('simplify::dashboard.ui.more') }}">+{{ $announcements->count() - 2 }}</button>
                    @endif
                    <button type="button" class="ps-btn is-icon is-sm is-subtle ps-note-pin" wire:click="unpin({{ $announcement->id }})" aria-pressed="true" aria-label="{{ __('simplify::announcements.bars.unpin') }}" data-tip="{{ __('simplify::announcements.bars.unpin') }}">
                        <x-filament::icon icon="tabler-pinned-filled" />
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>
