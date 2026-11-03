<link rel="stylesheet" href="{{ \Simplify\Core\Support\Assets::url('simplify.css') }}">
<link rel="manifest" href="{{ route('simplify.manifest') }}">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ \Simplify\Core\WebApp\WebApp::LIGHT }}">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="{{ \Simplify\Core\WebApp\WebApp::DARK }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ \Simplify\Core\WebApp\WebApp::name() }}">
<link rel="apple-touch-icon" href="{{ \Simplify\Core\WebApp\WebApp::icon(180)[0] }}">
<script src="{{ \Simplify\Core\Support\Assets::url('pull.js') }}" defer></script>
<script src="{{ \Simplify\Core\Support\Assets::url('sheet.js') }}" defer></script>
<script>window.simplifyUnsavedText = @js([...__('simplify::js.unsaved'), 'cancel' => __('simplify::js.common.cancel')]);</script>
<script src="{{ \Simplify\Core\Support\Assets::url('unsaved.js') }}" defer></script>
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
    // The Admin page and the account pages (V52): Simplify's header, the admin menu beside, Pelican's page as a card
    // (simplify.css, html.ps-shell). The legacy panel (?simplify_legacy=1) shows them as Pelican does.
    @if (\Simplify\Core\Filament\Admin\AdminTop::here())
    if (!window.frameElement) document.documentElement.classList.add('ps-shell'@if (\Simplify\Core\Filament\Admin\AdminTop::menu()), 'ps-shell-menu'@endif);
    @endif
    // wire:navigate keeps <html> as it was: the page that came in says by its header and menu whether it is one.
    if (!window.simplifyShellWatch) {
        window.simplifyShellWatch = true;
        document.addEventListener('livewire:navigated', () => {
            if (window.frameElement) return;
            const root = document.documentElement;
            root.classList.toggle('ps-shell', !!document.querySelector('[x-persist="simplify-admin-top"]'));
            root.classList.toggle('ps-shell-menu', !!document.querySelector('[x-persist="simplify-admin-side"]'));
        });
    }
    if ('serviceWorker' in navigator && window.isSecureContext) {
        addEventListener('load', () => navigator.serviceWorker.register(@js(route('simplify.worker')), { scope: @js(\Simplify\Core\WebApp\WebApp::scope()) }).catch(() => {}));
    }
    // Simplify's own admin pages (settings, Health, People & roles, the form pages, the end of the setup) get the height the window leaves them, so the page never scrolls:
    // the card scrolls inside (simplify.css). In host mode's frame the frame itself already has the window's height.
    // The end of the setup is one card in host mode too, so it fits there as well. The settings on a phone scroll with the page.
    {
        const fitCards = document.documentElement.matches('.ps-frame, .ps-shell') ? '.s-done' : '.s-settings-tabs.fi-vertical, .s-health, .s-people, .s-fit, .s-done';
        let queued = 0;
        const fit = () => {
            queued = 0;
            for (const card of document.querySelectorAll(fitCards)) {
                card.style.removeProperty('--s-fit');
                // On a phone the settings' menu sits above the settings, so a fitted card would leave them a few lines: the page scrolls.
                if (innerWidth <= 600 && card.matches('.s-settings-tabs')) continue;
                const box = card.getBoundingClientRect();
                // In the Admin page's card (html.ps-shell) the card scrolls, not the page: fit what is left of the card (on a
                // low window that can be less than the page's usual minimum).
                const shell = card.closest('html.ps-shell:not(.ps-frame) .fi-layout > .fi-main-ctn');
                if (shell) {
                    const room = shell.getBoundingClientRect();
                    const tall = Math.max(160, Math.floor(room.bottom - box.top - (shell.scrollHeight - shell.scrollTop - (box.bottom - room.top))));
                    let fitted = tall;
                    card.style.setProperty('--s-fit', fitted + 'px');
                    // Whatever still grows with the card (its paddings, a page's minimum height) is taken off again.
                    for (let i = 0; i < 3; i++) {
                        const over = shell.scrollHeight - shell.clientHeight;
                        if (over <= 0 || fitted <= 160) break;
                        fitted = Math.max(160, fitted - over);
                        card.style.setProperty('--s-fit', fitted + 'px');
                    }
                    continue;
                }
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
        // Whatever comes later on a first visit with nothing cached (fonts, Livewire starting, Pelican's footer) changes
        // what is left under the card, without the page telling: measure again when they are there, and a few times
        // after the page loaded.
        document.addEventListener('livewire:initialized', queue);
        document.fonts?.ready.then(queue);
        addEventListener('load', () => [0, 300, 1000, 2500].forEach((ms) => setTimeout(queue, ms)));
    }
</script>
