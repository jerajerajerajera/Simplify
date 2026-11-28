{{-- An announcement as plain text, for mail apps without HTML: nothing here is HTML, so nothing is escaped. --}}
@php($t = 'simplify::announcements.mail.')
{!! $panel !!}

{!! $title !!}

@if ($body !== '')
{!! $body !!}

@endif
{!! $label !!}: {!! $button !!}

{!! __($t.'footer', ['panel' => $host]) !!}
{!! __($t.'stop') !!}: {!! $stop !!}
