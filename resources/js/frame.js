/* A Pelican page in host mode's frame (head.blade.php): its search field, its header and table buttons move into
   host mode's title bar, next to the section and page name, as in PelicanSimplify 0.7.0. The page's own toolbar
   hides while they are there (simplify.css, data-ps-head-tools); a phone shows them in a slim row above the page. */
(() => {
  const root = document.documentElement;
  let head;
  try {
    head = window.frameElement.closest('.ps-host-main').querySelector(':scope > .ps-host-head.is-page');
  } catch {}
  if (!head) return;
  const parent = head.ownerDocument;
  // The header's buttons: its actions and what an add-on puts in the header-actions render hook (Explain on Logs), as in
  // server.js. A hook's own modal stays in the hidden header and still opens (simplify.css).
  const HEADER_ACTIONS = ['.fi-ac > :is(button, a)', '> :is(button, a):is(.fi-btn, .fi-icon-btn, .fi-link)', '> :not(.fi-ac) :is(button, a):is(.fi-btn, .fi-icon-btn, .fi-link)'].map((part) => '.fi-header-actions-ctn ' + part).join(', ');
  const tooltip = (el) => el.getAttribute('x-tooltip')?.match(/content:\s*'((?:[^'\\]|\\.)*)'/)?.[1]?.replace(/\\'/g, "'") || '';
  const label = (el) => el.getAttribute('aria-label') || el.getAttribute('title') || tooltip(el) || el.textContent.trim();
  // The page's own table, not one inside a form, tab or relation manager: those keep their toolbar.
  const mainTable = () => [...document.querySelectorAll('.fi-ta-ctn')].find((el) => !el.parentElement.closest('.fi-resource-relation-managers, .fi-resource-relation-manager, .fi-sc-tabs, .fi-section, .fi-modal, .fi-ta-ctn'));
  const button = (source, text, primary, fallback) => {
    const btn = parent.createElement('button');
    btn.type = 'button';
    btn.className = 'ps-btn is-sm' + (primary ? ' is-primary' : ' is-icon') + (source.classList.contains('fi-color-danger') ? ' is-danger' : '');
    btn.setAttribute('aria-label', text);
    if (!primary) btn.dataset.tip = tooltip(source) || text;
    btn.disabled = source.disabled || source.getAttribute('aria-disabled') === 'true';
    const icon = source.querySelector('svg');
    if (icon) btn.append(parent.importNode(icon, true));
    else if (fallback) btn.insertAdjacentHTML('beforeend', `<svg aria-hidden="true"><use href="#ps-${fallback}"/></svg>`);
    if (primary) btn.append(parent.createTextNode(text));
    btn.addEventListener('click', () => createServer(source) || source.click());
    return btn;
  };
  // Admin → Servers → Create opens Simplify's Create server popup (Server Maker), as everywhere else.
  const createServer = (source) => {
    if (!/\/servers\/create(\?|$)/.test(new URL(source.getAttribute('href') || '', location.href).pathname)) return false;
    const shell = parent.querySelector('[data-simplify-root]');
    const data = shell && parent.defaultView.Alpine?.$data(shell);
    return !!data?.openCreate?.(null, data.createButtons()[0]);
  };
  document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[href]');
    if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && createServer(link)) {
      event.preventDefault();
      event.stopPropagation();
    }
  }, true);
  const bar = parent.createElement('div');
  bar.className = 'ps-frame-tools';
  let signature = null;
  const build = () => {
    // Shown by host mode (x-show) in a visible host block; a phone shows the row only once it has tools (dashboard.css).
    const visible = head.style.display !== 'none' && head.parentElement.getClientRects().length > 0;
    const table = visible ? mainTable() : null;
    // Simplify's settings save with the button under the form; in the tree their header (Run setup again) stays hidden.
    const settings = document.querySelector('.s-settings > .s-bar button[type="submit"]');
    const header = document.querySelector('.s-settings') && root.dataset.psTree ? [] : [...document.querySelectorAll(HEADER_ACTIONS)].filter((el) => !el.closest('.fi-modal'));
    const sources = visible ? [...header, ...(table?.querySelectorAll('.fi-ta-header-toolbar > .fi-ta-actions > :is(button, a)') || []), ...(settings ? [settings] : [])].filter((el) => !el.closest('.fi-dropdown')) : [];
    // Simplify's own pages (People & roles) have one toolbar: tabs, search and the add button.
    const own = visible ? document.querySelector('.fi-main .s-toolbar') : null;
    const segments = own ? [...own.querySelectorAll('.s-segments > button')] : [];
    if (own) sources.push(...own.querySelectorAll('.s-bar > button'));
    const search = table?.querySelector('.fi-ta-search-field input') || own?.querySelector('input[type="search"]') || null;
    const filters = table?.querySelector('.fi-ta-filters-dropdown .fi-dropdown-trigger > :is(button, a)') || null;
    // The list's "Sort by" menu (sort.js): its columns and the sorted one are part of what the bar shows.
    const sorting = table && window.SimplifySort ? window.SimplifySort.state(table) : '';
    const next = JSON.stringify([sorting, !!search, !!filters, segments.map((el) => [label(el), el.classList.contains('is-active')]), sources.map((el) => [label(el), el.disabled || el.getAttribute('aria-disabled')])]);
    if (next === signature) return;
    signature = next;
    bar.replaceChildren();
    const bridged = !!(sources.length || search || filters || segments.length || sorting.includes('"'));
    root.toggleAttribute('data-ps-head-tools', bridged);
    if (!bridged) return bar.remove();
    if (!bar.isConnected) head.append(bar);
    if (segments.length) {
      const group = parent.createElement('div');
      group.className = 'ps-segmented ps-frame-segments';
      group.setAttribute('role', 'group');
      for (const source of segments) {
        const btn = parent.createElement('button');
        btn.type = 'button';
        btn.setAttribute('aria-pressed', String(source.classList.contains('is-active')));
        const icon = source.querySelector('svg');
        if (icon) btn.append(parent.importNode(icon, true));
        btn.append(parent.createTextNode(label(source)));
        btn.addEventListener('click', () => source.click());
        group.append(btn);
      }
      bar.append(group);
    }
    const sort = table && window.SimplifySort ? window.SimplifySort.control(table, parent) : null;
    if (sort) bar.append(sort);
    if (search) {
      const field = parent.createElement('label');
      field.className = 'ps-frame-search';
      field.innerHTML = '<svg aria-hidden="true"><use href="#ps-search"/></svg>';
      const input = Object.assign(parent.createElement('input'), { type: 'search', placeholder: search.placeholder, value: search.value });
      input.setAttribute('aria-label', search.placeholder);
      input.addEventListener('input', () => {
        search.value = input.value;
        search.dispatchEvent(new Event('input', { bubbles: true }));
      });
      field.append(input);
      bar.append(field);
    }
    // Create (a link to /create) is the one button with its name; the rest are icon buttons before it.
    const primaries = [];
    const seen = new Set();
    for (const source of sources) {
      const text = label(source);
      if (seen.has(text)) continue;
      seen.add(text);
      if (/\/create(\?|$)/.test(source.getAttribute('href') || '') || source.classList.contains('s-btn-primary')) primaries.push(button(source, text, true));
      else bar.append(button(source, text, false, source === settings ? 'save' : null));
    }
    if (filters) {
      const btn = button(filters, label(filters), false);
      btn.querySelectorAll('.fi-icon-btn-badge-ctn').forEach((el) => el.remove());
      bar.append(btn);
    }
    bar.append(...primaries);
  };
  let queued = 0;
  const queue = () => (queued ||= requestAnimationFrame(() => {
    queued = 0;
    build();
  }));
  const observer = new MutationObserver(queue);
  const start = () => {
    signature = null;
    build();
    observer.disconnect();
    observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['aria-disabled', 'disabled'] });
  };
  if (document.body) start();
  else addEventListener('DOMContentLoaded', start, { once: true });
  parent.defaultView.addEventListener('resize', queue);
  // The title bar can show only after this page was built (host mode opening): build again when it does.
  const shown = new MutationObserver(queue);
  for (let el = head; el && el !== parent.body; el = el.parentElement) shown.observe(el, { attributes: true, attributeFilter: ['style', 'class'] });
  // Pelican's links (New egg, Edit) change the page without a reload: the bar follows the new page.
  document.addEventListener('livewire:navigated', start);
  addEventListener('pagehide', () => {
    parent.defaultView.removeEventListener('resize', queue);
    shown.disconnect();
    bar.remove();
  });
})();
