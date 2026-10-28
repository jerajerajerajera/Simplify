@props(['label', 'value'])
<div class="s-copy" x-data="{ copied: false }">
    <span class="s-muted">{{ $label }}</span>
    <code class="s-copy-value">{{ $value }}</code>
    <button
        type="button"
        class="s-btn s-btn-icon s-btn-small"
        x-on:click="navigator.clipboard.writeText(@js($value)); copied = true; setTimeout(() => copied = false, 1500)"
        aria-label="{{ __('pelican-simplify::server.copy', ['what' => $label]) }}"
    >
        <x-filament::icon icon="tabler-copy" x-show="! copied" />
        <x-filament::icon icon="tabler-check" x-show="copied" x-cloak />
    </button>
</div>
