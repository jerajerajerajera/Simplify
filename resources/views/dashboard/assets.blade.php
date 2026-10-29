{{-- Styles, texts and scripts of the overview and the server page, loaded once per page. --}}
@assets
    @foreach (['tokens.css', 'components.css', 'dashboard.css'] as $file)
        <link rel="stylesheet" href="{{ \Simplify\Core\Support\Assets::url($file) }}">
    @endforeach
    {{-- Base64, because Livewire copies this block into the page in a way that drops one level of backslashes. --}}
    <script>window.simplifyLang = JSON.parse(new TextDecoder().decode(Uint8Array.from(atob('{{ base64_encode(json_encode(__('pelican-simplify::js'))) }}'), (c) => c.charCodeAt(0))));</script>
    @if (\Simplify\Core\Support\PanelTheme::active())
        <script>document.documentElement.classList.add('ps-panel-theme')</script>
    @endif
    @foreach (['core.js', 'motd.js', 'dashboard.js', 'server.js'] as $file)
        <script src="{{ \Simplify\Core\Support\Assets::url($file) }}"></script>
    @endforeach
@endassets
