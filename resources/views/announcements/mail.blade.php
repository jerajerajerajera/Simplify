{{-- An announcement as an email (Announcements › Email). Inline styles only: mail apps drop style sheets. --}}
@php($t = 'simplify::announcements.mail.')
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title }}</title></head>
<body style="margin:0;padding:24px 12px;background:#f4f4f5;font-family:Inter,ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;color:#18181b">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;margin:0 auto">
        <tr><td style="background:#ffffff;border:1px solid #e4e4e7;border-radius:12px;padding:24px">
            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                <td style="padding-right:10px"><img src="{{ $icon }}" width="32" height="32" alt="" style="display:block;border-radius:8px"></td>
                <td style="font-weight:700;font-size:15px">{{ $panel }}</td>
            </tr></table>
            <h1 style="margin:18px 0 8px;font-size:19px;line-height:1.3">{{ $title }}</h1>
            @if ($body !== '')
                <p style="margin:0 0 18px;font-size:14px;line-height:1.55;white-space:pre-line">{{ $body }}</p>
            @endif
            <a href="{{ $button }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;border-radius:8px;padding:9px 16px">{{ $label }}</a>
        </td></tr>
        <tr><td style="padding:14px 8px;text-align:center;font-size:12px;line-height:1.5;color:#71717a">
            {{ __($t.'footer', ['panel' => $host]) }} <a href="{{ $stop }}" style="color:#2563eb;text-decoration:none">{{ __($t.'stop') }}</a>
        </td></tr>
    </table>
</body>
</html>
