/* Phones (1.5.4): every menu is a sheet from the bottom (dashboard.css). A tap beside an open sheet only closes it,
   instead of also pressing what lies under the dimmed page, and a swipe down on a sheet closes it too. Closing sends
   Escape to the sheet: every menu already closes on Escape. The Admin menu on a phone is a page of its own, not a
   sheet (K-B24): its bottom bar and header keep their taps. */
(() => {
  if (window.SimplifySheet) return;
  window.SimplifySheet = true;
  const phone = matchMedia('(max-width: 600px)');
  const SHEETS = '.ps .ps-dropdown, .ps .account-open > .ps-account-menu';
  const open = () => (phone.matches ? [...document.querySelectorAll(SHEETS)].filter((el) => el.getClientRects().length > 0) : []);
  const close = (sheet) => sheet.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

  document.addEventListener('click', (event) => {
    const sheets = open();
    if (!sheets.length || sheets.some((sheet) => sheet.contains(event.target))) return;
    event.preventDefault();
    event.stopPropagation();
    sheets.forEach(close);
  }, true);

  // Escape from anywhere else (the keyboard's focus outside the sheet) closes it as well.
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    open().filter((sheet) => !sheet.contains(event.target)).forEach(close);
  });

  // A swipe down follows the thumb and lightens the backdrop as it goes (--ps-drag). Past 30% of the sheet, or with a
  // quick flick, it slides away and closes; otherwise it springs back. Popups that fill the screen (.ps-modal, such as
  // all notifications) close the same way (K-B22). Only from the top of what scrolls under the finger, and never on a
  // sideways swipe (that one goes back) or a swipe up (that one scrolls). Opening and closing themselves slide through
  // the ps-t-* classes (components.css, dashboard.css).
  const DRAG = SHEETS + ', .ps-modal-layer[data-open="true"] > .ps-modal';
  const atTop = (el, sheet) => {
    for (; el && el !== sheet.parentElement; el = el.parentElement) if (el.scrollTop > 0) return false;
    return true;
  };
  const seconds = (el, name) => parseFloat(getComputedStyle(el).getPropertyValue(name)) * 1000 || 0;
  const style = (sheet, transition, translate, drag) => {
    sheet.style.transition = transition;
    sheet.style.translate = translate;
    sheet.parentElement.style.transition = transition && transition.replace('translate', 'background-color');
    sheet.parentElement.style.setProperty('--ps-drag', drag);
  };
  const reset = (sheet) => style(sheet, '', '', '');
  let drag = null;
  document.addEventListener('touchstart', (event) => {
    const sheets = phone.matches && event.touches.length === 1 ? [...document.querySelectorAll(DRAG)].filter((el) => el.getClientRects().length > 0) : [];
    const sheet = sheets.reverse().find((el) => el.contains(event.target));
    const { clientX: x, clientY: y } = event.touches[0] || {};
    drag = sheet && atTop(event.target, sheet) ? { sheet, x, y, moved: 0, speed: 0, at: event.timeStamp, way: null } : null;
  }, { passive: true });
  document.addEventListener('touchmove', (event) => {
    if (!drag) return;
    const dx = event.touches[0].clientX - drag.x, dy = event.touches[0].clientY - drag.y;
    if (!drag.way && Math.hypot(dx, dy) > 8) drag.way = Math.abs(dx) > Math.abs(dy) ? 'side' : dy > 0 ? 'down' : 'up';
    if (drag.way !== 'down') return;
    const moved = Math.max(0, dy);
    drag.speed = (moved - drag.moved) / Math.max(event.timeStamp - drag.at, 1);
    drag.at = event.timeStamp;
    drag.moved = moved;
    style(drag.sheet, 'none', '0 ' + moved + 'px', Math.min(100, (moved / drag.sheet.offsetHeight) * 100) + '%');
  }, { passive: true });
  document.addEventListener('touchend', () => {
    if (!drag) return;
    const { sheet, moved, speed } = drag;
    drag = null;
    if (!moved) return;
    if (moved > sheet.offsetHeight * 0.3 || (speed > 0.5 && moved > 24)) {
      const out = seconds(sheet, '--ps-sheet-out');
      style(sheet, out ? `translate ${out}ms var(--ps-ease-out), box-shadow ${out}ms ease` : 'none', '0 100%', '100%');
      setTimeout(() => { close(sheet); setTimeout(() => reset(sheet), out + 200); }, out);
    } else {
      const back = seconds(sheet, '--ps-sheet-in');
      style(sheet, back ? `translate ${back}ms cubic-bezier(.3, 1.3, .5, 1), box-shadow ${back}ms ease` : 'none', '', '');
      setTimeout(() => reset(sheet), back);
    }
  });
  document.addEventListener('touchcancel', () => { if (drag) reset(drag.sheet); drag = null; });

  // The Admin menu on a phone (AdminSide) is a page that slides like an app: away to the left over the page a tap in
  // it opened, and back in from the left (dashboard.css, html[data-ps-admin-slide]). change() switches the menu and
  // resolves once the page shows it. On a computer, with reduced motion or without view transitions it switches at once.
  window.simplifyAdminSlide = (on, change) => {
    const root = document.documentElement;
    if (!phone.matches || !document.startViewTransition || matchMedia('(prefers-reduced-motion: reduce)').matches) return void change();
    root.dataset.psAdminSlide = on ? 'in' : 'out';
    const slide = document.startViewTransition(change);
    slide.finished.finally(() => delete root.dataset.psAdminSlide);
    return slide;
  };
})();
