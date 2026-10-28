<link rel="stylesheet" href="{{ \Simplify\Core\Support\Assets::url('simplify.css') }}">
<link rel="manifest" href="{{ route('simplify.manifest') }}">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ \Simplify\Core\WebApp\WebApp::LIGHT }}">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="{{ \Simplify\Core\WebApp\WebApp::DARK }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ \Simplify\Core\WebApp\WebApp::name() }}">
<link rel="apple-touch-icon" href="{{ \Simplify\Core\WebApp\WebApp::icon(180)[0] }}">
<script>
    // A page inside host mode's frame drops Pelican's own menus; it keeps that through every link it follows.
    try {
        if (window.frameElement?.dataset.psFrame) {
            document.documentElement.classList.add('ps-frame');
            document.documentElement.dataset.psFrame = window.frameElement.dataset.psFrame;
        }
    } catch {}
    if ('serviceWorker' in navigator && window.isSecureContext) {
        addEventListener('load', () => navigator.serviceWorker.register(@js(route('simplify.worker')), { scope: @js(\Simplify\Core\WebApp\WebApp::scope()) }).catch(() => {}));
    }
</script>
