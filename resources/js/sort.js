/* Pelican's lists inside Simplify are rows without column titles (G1, simplify.css), so the columns a list can be sorted
   by come back as a "Sort by" menu in the list's toolbar, with a button for the direction. Both press the column's own
   sort button, which stays in the hidden title row: Filament keeps doing the sorting. Host mode's title bar takes the
   same menu (frame.js, SimplifySort.control). The legacy panel keeps its column titles and gets no menu. */
(() => {
  if (window.SimplifySort) return;
  const text = Object.assign({ by: 'Sort by', ascending: 'Ascending', descending: 'Descending' }, window.simplifySortText || {});
  const root = document.documentElement;
  const inSimplify = () => root.matches('.ps-frame, .ps-shell') || !!document.querySelector('.ps-server-top, .ps-admin-top');
  // A list's own sortable columns, in their order, with the direction Filament reports for the sorted one.
  const columns = (table) => [...table.querySelectorAll(':scope .fi-ta-table > thead th')]
    .map((th) => ({ th, button: th.querySelector('.fi-ta-header-cell-sort-btn') }))
    .filter(({ button }) => button)
    .map(({ th, button }) => ({ button, label: button.textContent.trim(), dir: ['ascending', 'descending'].includes(th.getAttribute('aria-sort')) ? th.getAttribute('aria-sort') : null }));
  const state = (table) => JSON.stringify(columns(table).map((c) => [c.label, c.dir]));
  // The menu, built in `doc` (this page, or host mode's page around the frame).
  const control = (table, doc = document) => {
    const list = columns(table);
    if (!list.length) return null;
    const active = list.findIndex((c) => c.dir);
    const wrap = doc.createElement('div');
    wrap.className = 's-sort';
    const select = doc.createElement('select');
    select.className = 'ps-input s-sort-select';
    select.setAttribute('aria-label', text.by);
    const placeholder = doc.createElement('option');
    placeholder.value = '';
    placeholder.textContent = text.by;
    placeholder.disabled = active >= 0;
    select.append(placeholder);
    list.forEach((column, index) => {
      const option = doc.createElement('option');
      option.value = String(index);
      option.textContent = column.label;
      select.append(option);
    });
    select.value = active >= 0 ? String(active) : '';
    select.addEventListener('change', () => list[Number(select.value)]?.button.click());
    wrap.append(select);
    if (active >= 0) {
      const dir = list[active].dir;
      const button = doc.createElement('button');
      button.type = 'button';
      button.className = 'ps-btn is-sm is-icon s-sort-dir';
      button.textContent = dir === 'ascending' ? '↑' : '↓';
      button.setAttribute('aria-label', text[dir]);
      button.dataset.tip = text[dir];
      button.addEventListener('click', () => list[active].button.click());
      wrap.append(button);
    }
    return wrap;
  };
  // Every list on the page keeps its menu: Livewire redraws the toolbar, so it is put back after each change.
  const seen = new WeakMap();
  const place = () => {
    if (!inSimplify()) return;
    for (const table of document.querySelectorAll('.fi-ta-ctn')) {
      const toolbar = table.querySelector(':scope .fi-ta-header-toolbar');
      if (!toolbar) continue;
      const current = toolbar.querySelector(':scope > .s-sort, :scope > * > .s-sort');
      const next = state(table);
      if (current && seen.get(table) === next) continue;
      current?.remove();
      seen.set(table, next);
      const menu = control(table);
      if (!menu) continue;
      const search = toolbar.querySelector('.fi-ta-search-field')?.closest('.fi-ta-header-toolbar > *');
      if (search) search.before(menu);
      else toolbar.append(menu);
    }
  };
  let queued = 0;
  const queue = () => (queued ||= requestAnimationFrame(() => { queued = 0; place(); }));
  window.SimplifySort = { control, state };
  const start = () => {
    place();
    new MutationObserver(queue).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['aria-sort'] });
  };
  if (document.body) start();
  else document.addEventListener('DOMContentLoaded', start, { once: true });
})();
