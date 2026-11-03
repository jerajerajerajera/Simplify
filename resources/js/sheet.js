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

  // A swipe down follows the thumb and closes the sheet past 80px; a shorter one springs back. Popups that fill the
  // screen (.ps-modal, such as all notifications) close the same way (K-B22). Only from the top of what scrolls under
  // the finger, and never on a sideways swipe (that one goes back).
  const DRAG = SHEETS + ', .ps-modal-layer[data-open="true"] > .ps-modal';
  const still = matchMedia('(prefers-reduced-motion: reduce)');
  const atTop = (el, sheet) => {
    for (; el && el !== sheet.parentElement; el = el.parentElement) if (el.scrollTop > 0) return false;
    return true;
  };
  const settle = (sheet, to, then) => {
    if (still.matches) return then();
    sheet.style.transition = 'translate .2s ' + (to ? 'ease-in' : 'ease-out');
    sheet.style.translate = to;
    setTimeout(then, 200);
  };
  const reset = (sheet) => { sheet.style.transition = ''; sheet.style.translate = ''; };
  let drag = null;
  document.addEventListener('touchstart', (event) => {
    const sheets = phone.matches && event.touches.length === 1 ? [...document.querySelectorAll(DRAG)].filter((el) => el.getClientRects().length > 0) : [];
    const sheet = sheets.reverse().find((el) => el.contains(event.target));
    drag = sheet && atTop(event.target, sheet) ? { sheet, x: event.touches[0].clientX, y: event.touches[0].clientY, moved: 0, way: null } : null;
  }, { passive: true });
  document.addEventListener('touchmove', (event) => {
    if (!drag) return;
    const dx = event.touches[0].clientX - drag.x, dy = event.touches[0].clientY - drag.y;
    if (!drag.way && Math.hypot(dx, dy) > 8) drag.way = Math.abs(dx) > Math.abs(dy) ? 'side' : 'down';
    if (drag.way !== 'down') return;
    drag.moved = Math.max(0, dy);
    drag.sheet.style.transition = 'none';
    drag.sheet.style.translate = '0 ' + drag.moved + 'px';
  }, { passive: true });
  document.addEventListener('touchend', () => {
    if (!drag) return;
    const { sheet, moved } = drag;
    drag = null;
    if (moved > 80) settle(sheet, '0 100%', () => { close(sheet); setTimeout(() => reset(sheet), 300); });
    else if (moved > 0) settle(sheet, '', () => reset(sheet));
  });
  document.addEventListener('touchcancel', () => { if (drag) reset(drag.sheet); drag = null; });
})();
