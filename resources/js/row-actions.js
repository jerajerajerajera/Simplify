/* Phones: a row of a list keeps one button and ⋯ (Jeroen 5-11). With many buttons (Egg Updates gives an egg Test
   first and Update next to Pelican's Edit, Export and Duplicate) a row was wider than the screen, so the page slid
   sideways. On a phone a row with more than two buttons shows only its main one (a coloured one, such as Update) and
   ⋯; ⋯ opens a sheet with every button by its name, and a tap there presses the row's own button. Computers keep the
   row as it is. Only in Simplify's lists (the Admin page, a server's pages, host mode's frame). */
(() => {
  if (window.SimplifyRowActions) return;
  window.SimplifyRowActions = true;
  const phone = matchMedia('(max-width: 600px)');
  const MAIN = /\bfi-color-(success|warning|danger|info)\b/;
  const words = () => window.simplifyRowText || {};
  const ours = () => document.documentElement.classList.contains('ps-frame') || !!document.querySelector('.ps-server-top, .ps-admin-top');

  const tooltip = (el) => el.getAttribute('x-tooltip')?.match(/content:\s*'((?:[^'\\]|\\.)*)'/)?.[1]?.replace(/\\'/g, "'") || '';
  const label = (el) => (el.getAttribute('aria-label') || el.getAttribute('title') || tooltip(el) || el.textContent || '').replace(/\s+/g, ' ').trim();
  /** A row's own buttons: links and buttons in its actions, not those inside a dropdown or a modal, nor ⋯ itself. */
  const actions = (box) => [...box.querySelectorAll(':scope :is(a, button)')]
    .filter((el) => !el.closest('.fi-dropdown-panel, .fi-modal') && !el.classList.contains('ps-row-more') && el.closest('.fi-ta-actions') === box);

  const closeSheet = (layer) => {
    if (!layer?.isConnected) return;
    const sheet = layer.querySelector('.ps-dropdown');
    sheet.classList.add('ps-t-out', 'ps-t-from');
    setTimeout(() => layer.remove(), 220);
  };
  const openSheet = (box, more) => {
    document.querySelectorAll('.ps-row-sheet').forEach((old) => old.remove());
    const layer = document.createElement('div');
    layer.className = 'ps ps-row-sheet' + (document.documentElement.classList.contains('dark') ? ' dark' : '');
    const sheet = document.createElement('div');
    sheet.className = 'ps-dropdown ps-row-sheet-menu ps-t-in ps-t-from';
    sheet.setAttribute('role', 'menu');
    const row = box.closest('.fi-ta-row');
    const title = row?.querySelector('.fi-ta-cell .fi-ta-text, .fi-ta-cell')?.textContent.replace(/\s+/g, ' ').trim();
    if (title) {
      const head = document.createElement('strong');
      head.className = 'ps-dropdown-title';
      head.textContent = title;
      sheet.append(head);
    }
    for (const action of actions(box)) {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'ps-menu-item';
      item.setAttribute('role', 'menuitem');
      item.disabled = action.disabled || action.getAttribute('aria-disabled') === 'true';
      const icon = action.querySelector('svg');
      if (icon) item.append(icon.cloneNode(true));
      const text = document.createElement('span');
      text.textContent = label(action);
      item.append(text);
      // The sheet goes at once: while it is open, sheet.js would take the button's press for a tap beside it.
      item.addEventListener('click', () => {
        layer.remove();
        action.click();
      });
      sheet.append(item);
    }
    // Escape (also what sheet.js sends for a tap beside the sheet or a swipe down) closes it; focus goes back to ⋯.
    sheet.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      closeSheet(layer);
      more.focus();
    });
    layer.append(sheet);
    document.body.append(layer);
    requestAnimationFrame(() => requestAnimationFrame(() => sheet.classList.remove('ps-t-from')));
    sheet.querySelector('.ps-menu-item')?.focus();
  };

  const moreButton = (box) => {
    const more = document.createElement('button');
    more.type = 'button';
    more.className = 'fi-icon-btn ps-row-more';
    more.setAttribute('aria-label', words().more || 'More actions');
    more.setAttribute('aria-haspopup', 'menu');
    more.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M11 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M18 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/></svg>';
    more.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      openSheet(box, more);
    });
    return more;
  };

  const fold = () => {
    if (!ours()) return;
    for (const box of document.querySelectorAll('.fi-ta-row .fi-ta-actions')) {
      const own = actions(box);
      const many = phone.matches && own.length > 2;
      box.classList.toggle('ps-row-folded', many);
      own.forEach((el) => el.classList.remove('ps-row-main'));
      if (!many) continue;
      const main = own.find((el) => MAIN.test(el.className) && !el.disabled);
      main?.classList.add('ps-row-main');
      if (!box.querySelector(':scope > .ps-row-more, :scope .ps-row-more')) box.append(moreButton(box));
    }
  };
  let queued = 0;
  const queue = () => (queued ||= requestAnimationFrame(() => { queued = 0; fold(); }));
  const start = () => {
    fold();
    new MutationObserver(queue).observe(document.body, { childList: true, subtree: true });
  };
  phone.addEventListener?.('change', queue);
  document.addEventListener('livewire:navigated', queue);
  if (document.readyState === 'loading') addEventListener('DOMContentLoaded', start);
  else start();
})();
