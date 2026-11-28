{{-- Under "Email" while the panel has no mail server (Pelican's Settings › Mail): say so, and where to set one up. --}}
@php($t = 'simplify::announcements.form.')
<div class="s-zone-hint">
    <x-filament::icon icon="tabler-alert-triangle" class="s-zone-hint-icon" />
    <span><strong>{{ __($t.'email_missing') }}</strong> {{ __($t.'email_missing_help') }}
        @if ($url)
            <a href="{{ $url }}">{{ __($t.'email_missing_link') }}</a>
        @endif
    </span>
</div>
