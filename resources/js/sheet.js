/* Phones (1.5.4): every menu is a sheet from the bottom (dashboard.css). A tap beside an open sheet only closes it,
   instead of also pressing what lies under the dimmed page, and a swipe down on a sheet closes it too. Closing sends
   Escape to the sheet: every menu already closes on Escape. */
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

  let drag = null;
  document.addEventListener('touchstart', (event) => {
    const sheet = open().find((el) => el.contains(event.target));
    drag = sheet && sheet.scrollTop <= 0 ? { sheet, y: event.touches[0].clientY, moved: 0 } : null;
  }, { passive: true });
  document.addEventListener('touchmove', (event) => {
    if (!drag) return;
    drag.moved = Math.max(0, event.touches[0].clientY - drag.y);
    drag.sheet.style.translate = '0 ' + drag.moved + 'px';
  }, { passive: true });
  document.addEventListener('touchend', () => {
    if (!drag) return;
    drag.sheet.style.translate = '';
    if (drag.moved > 80) close(drag.sheet);
    drag = null;
  });
})();
