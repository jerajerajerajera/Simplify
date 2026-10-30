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
            // Host mode lists the settings tabs in its tree, so the page leaves out its own tab menu.
            if (window.frameElement.dataset.psTree) document.documentElement.dataset.psTree = '1';
            // A page's search and buttons (Save on the settings too) go into host mode's title bar (frame.js).
            if (window.frameElement.dataset.psFrame === 'native') document.head.append(Object.assign(document.createElement('script'), { src: @js(\Simplify\Core\Support\Assets::url('frame.js')), defer: true }));
            // A Pelican settings tab inside Simplify's settings grows with its form: one scrollbar for the whole page.
            if (window.frameElement.dataset.psTab) {
                addEventListener('DOMContentLoaded', () => new ResizeObserver(() => (window.frameElement.style.height = Math.ceil(document.body.getBoundingClientRect().height) + 'px')).observe(document.body));
            }
        }
    } catch {}
    if ('serviceWorker' in navigator && window.isSecureContext) {
        addEventListener('load', () => navigator.serviceWorker.register(@js(route('simplify.worker')), { scope: @js(\Simplify\Core\WebApp\WebApp::scope()) }).catch(() => {}));
    }
</script>
