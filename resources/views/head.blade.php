<link rel="stylesheet" href="{{ \Simplify\Core\Support\Assets::url('simplify.css') }}">
<link rel="manifest" href="{{ route('simplify.manifest') }}">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ \Simplify\Core\WebApp\WebApp::LIGHT }}">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="{{ \Simplify\Core\WebApp\WebApp::DARK }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ \Simplify\Core\WebApp\WebApp::name() }}">
<link rel="apple-touch-icon" href="{{ \Simplify\Core\WebApp\WebApp::icon(180)[0] }}">
<script src="{{ \Simplify\Core\Support\Assets::url('pull.js') }}" defer></script>
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
    // Simplify's own admin pages (settings, Health, People & roles, the form pages, the end of the setup) get the height the window leaves them, so the page never scrolls:
    // the card scrolls inside (simplify.css). In host mode's frame the frame itself already has the window's height.
    // The end of the setup is one card in host mode too, so it fits there as well. Not on a phone: there the page scrolls.
    {
        const fitCards = document.documentElement.classList.contains('ps-frame') ? '.s-done' : '.s-settings-tabs.fi-vertical, .s-health, .s-people, .s-fit, .s-done';
        let queued = 0;
        const fit = () => {
            queued = 0;
            for (const card of document.querySelectorAll(fitCards)) {
                card.style.removeProperty('--s-fit');
                // A phone scrolls the page, like the server page there: a fitted card would leave its content a few lines.
                if (innerWidth <= 600) continue;
                const box = card.getBoundingClientRect();
                const below = document.documentElement.scrollHeight - (box.bottom + scrollY);
                const height = Math.max(320, Math.floor(innerHeight - (box.top + scrollY) - below));
                card.style.setProperty('--s-fit', height + 'px');
                // Whatever still grows with the page (a layout's minimum height, a footer pushed down) is taken off again.
                const over = document.documentElement.scrollHeight - innerHeight;
                if (over > 0) card.style.setProperty('--s-fit', Math.max(320, height - over) + 'px');
            }
        };
        const queue = () => (queued ||= requestAnimationFrame(fit));
        addEventListener('resize', queue);
        document.addEventListener('livewire:navigated', queue);
        addEventListener('DOMContentLoaded', () => {
            queue();
            // A new card, or Livewire redrawing one without its height, measures again.
            const lost = (change) => change.type === 'attributes'
                ? change.target.matches(fitCards) && !change.target.style.getPropertyValue('--s-fit')
                : [...change.addedNodes].some((node) => node.nodeType === 1 && (node.matches(fitCards) || node.querySelector(fitCards)));
            new MutationObserver((changes) => changes.some(lost) && queue())
                .observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] });
        });
    }
</script>
