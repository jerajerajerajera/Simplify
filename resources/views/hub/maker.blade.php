<div class="s-hub-by">
    <span class="s-hub-avatar" style="--s-hue: {{ crc32($name) % 360 }}">{{ mb_strtoupper(mb_substr($name ?: '?', 0, 1)) }}</span>
    <span><small>{{ __('simplify::settings.hub.made_by') }}</small><b>{{ $name ?: '?' }}</b></span>
</div>
