/* Pull to refresh in the installed app (1.5.3). The page itself never scrolls and an installed app has no reload button,
   so a pull down from the top of whatever scrolls under the finger reloads the app. Also inside host mode's frame:
   then the whole app reloads. Only with the app installed; a browser tab has its own reload. */
(() => {
  let top;
  try { top = window.top; top.document; } catch { return; }
  const standalone = top.matchMedia('(display-mode: standalone)').matches || top.navigator.standalone === true;
  if (!standalone || window.SimplifyPull) return;
  window.SimplifyPull = true;
  const PULL = 70;
  let start = null, pulled = 0, mark = null;
  // Something under the finger that scrolls and is not at its top, a console, or a field takes the gesture itself.
  const blocked = (el) => {
    for (; el && el.nodeType === 1; el = el.parentElement) {
      if (el.matches('input, textarea, select, [contenteditable], #terminal, .xterm, [role=dialog], .fi-modal-window')) return true;
      const style = getComputedStyle(el);
      if (/(auto|scroll)/.test(style.overflowY) && el.scrollHeight > el.clientHeight + 1 && el.scrollTop > 0) return true;
    }
    return (document.scrollingElement?.scrollTop || 0) > 0;
  };
  const show = (distance) => {
    if (!mark) {
      mark = document.createElement('div');
      mark.className = 's-pull';
      mark.setAttribute('aria-hidden', 'true');
      mark.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/></svg>';
      document.body.append(mark);
    }
    mark.style.setProperty('--s-pull', Math.min(distance, PULL * 1.3) + 'px');
    mark.style.setProperty('--s-turn', Math.round(distance * 4) + 'deg');
    mark.classList.toggle('is-ready', distance >= PULL);
  };
  const hide = () => { mark?.remove(); mark = null; };
  addEventListener('touchstart', (e) => {
    start = e.touches.length === 1 && !blocked(e.target) ? { x: e.touches[0].clientX, y: e.touches[0].clientY } : null;
    pulled = 0;
  }, { passive: true });
  addEventListener('touchmove', (e) => {
    if (!start) return;
    const dy = e.touches[0].clientY - start.y, dx = Math.abs(e.touches[0].clientX - start.x);
    if (dy < 0 || dx > dy) { start = null; hide(); return; }
    pulled = dy * 0.5;
    if (pulled > 8) show(pulled);
  }, { passive: true });
  addEventListener('touchend', () => {
    if (start && pulled >= PULL) {
      mark?.classList.add('is-busy');
      top.location.reload();
    } else hide();
    start = null;
  }, { passive: true });
  addEventListener('touchcancel', () => { start = null; hide(); }, { passive: true });
})();
