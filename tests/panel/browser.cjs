// Browser smoke of a panel with Simplify (checks.yml, job browser): a real browser logs in and opens the overview, a
// server's console, a click in the server menu, Admin and Settings › Save, on a desktop (1440) and a phone (390), and
// checks what the HTTP smoke cannot see: the page never scrolls (P-7; on a phone the overview and the server page
// scroll as a whole, by design, so that check is for the desktop), a menu click keeps the page (P-11), the console
// is there, and axe-core finds no critical accessibility problem. Writes a screenshot per screen, axe.json and
// headers.json (the session cookie, for Lighthouse) to --out. The last line says "All steps passed" or lists the failures.
// node tests/panel/browser.cjs --base http://127.0.0.1:8001 --urls panel/urls.json --out result
const fs = require('fs'), path = require('path');
const { chromium } = require('playwright');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base', 'http://127.0.0.1:8001'), out = arg('out', 'result');
const urls = JSON.parse(fs.readFileSync(arg('urls'), 'utf8'));
const axeMax = Number(arg('axe-critical', '0')); // critical axe violations allowed per screen (0 after the first run)
const EMAIL = 'demo@example.com', PASSWORD = 'DemoPassword123!'; // the audit's seed.php
const SERVER = 'Survival SMP';
const SAVED = 'Settings saved'; // lang/en/settings.php 'saved'
fs.mkdirSync(out, { recursive: true });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const failures = [], axeReport = {};
let browser, page, stand;

const say = (step, ok, detail) => {
  console.log(`${ok ? 'PASS' : 'FAIL'} ${stand} ${step}${detail ? ' ' + JSON.stringify(detail) : ''}`);
  if (!ok) failures.push(`${stand} ${step}`);
};
const settle = async (ms = 600) => { await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {}); await sleep(ms); };
// Alpine on Simplify's root, so a slow load never swallows a click.
const ready = () => page.waitForFunction(() => { const el = document.querySelector('.ps'); return !!(el && window.Alpine && el._x_dataStack); }, null, { timeout: 15000 }).catch(() => null);
const goto = async (url) => { await page.goto(url.startsWith('http') ? url : base + url, { waitUntil: 'domcontentloaded' }); await settle(); await ready(); };
const shot = (name) => page.screenshot({ path: path.join(out, `${stand}-${name}.png`), fullPage: false }).catch(() => {});
const scrolls = () => page.evaluate(() => document.documentElement.scrollHeight - window.innerHeight);
const visible = (sel) => page.evaluate((s) => { const e = document.querySelector(s); return !!e && !!e.getClientRects().length && getComputedStyle(e).display !== 'none'; }, sel);
// Scrolls a menu to its end, clicks its last item and says where the menu stands once the page loaded (K-B80).
const menuKeeps = async (menu, links) => {
  const scroller = (root) => { const m = document.querySelector(root); return m ? [m, ...m.querySelectorAll('*')].find((x) => x.scrollHeight > x.clientHeight + 2 && /(auto|scroll)/.test(getComputedStyle(x).overflowY)) : null; };
  const box = () => page.evaluate(`(() => { const el = (${scroller})(${JSON.stringify(menu)}); return el ? { top: Math.round(el.scrollTop), room: el.scrollHeight - el.clientHeight } : { top: 0, room: 0 }; })()`).catch(() => ({ top: 0, room: 0 }));
  await page.evaluate(`(() => { const el = (${scroller})(${JSON.stringify(menu)}); if (el) el.scrollTop = el.scrollHeight; })()`).catch(() => {});
  const before = await box();
  const last = page.locator(links).last();
  const item = ((await last.innerText().catch(() => '')) || '').trim();
  await last.click().catch(() => {});
  // The click is no page load: wait until the menu marks the clicked page, so a slow panel is not measured halfway.
  await page.waitForFunction(([root, text]) => { const at = document.querySelector(root + ' [aria-current="page"]'); return !!at && at.textContent.replace(/\s+/g, ' ').includes(text); }, [menu, item.replace(/\s+/g, ' ')], { timeout: 15000 }).catch(() => {});
  await settle(1500);
  const after = await box();
  const inView = await page.evaluate(`(() => { const el = (${scroller})(${JSON.stringify(menu)}); const at = document.querySelector(${JSON.stringify(menu)} + ' [aria-current="page"]'); if (!el || !at) return false; const a = at.getBoundingClientRect(), b = el.getBoundingClientRect(); return a.top >= b.top - 1 && a.bottom <= b.bottom + 1; })()`).catch(() => false);
  return { item, before: before.top, room: before.room, after: after.top, inView };
};

async function login() {
  await goto('/login');
  await page.fill('#form\\.login', EMAIL); await page.fill('#form\\.password', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 30000 }).catch(() => {});
  await settle();
  if (page.url().includes('/login')) throw new Error('login failed');
}

// axe-core on the current page: critical violations fail the step; every violation goes to axe.json for the report.
async function axe(name) {
  await page.addScriptTag({ path: require.resolve('axe-core/axe.min.js') }).catch(() => {});
  const result = await page.evaluate(async () => {
    if (!window.axe) return null;
    const r = await window.axe.run(document, { resultTypes: ['violations'] });
    return r.violations.map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, help: v.help }));
  }).catch(() => null);
  axeReport[`${stand}-${name}`] = result;
  const critical = (result || []).filter((v) => v.impact === 'critical').reduce((n, v) => n + v.nodes, 0);
  say(`axe ${name}`, result !== null && critical <= axeMax, { violations: (result || []).length, critical });
}

async function run(viewport) {
  stand = viewport.name;
  const phone = stand === 'phone390';
  // The server's sections: a menu on a computer; on a phone a bottom bar whose Sections button opens them as a sheet.
  const MENU = phone ? '.ps-phone-sections' : '.ps-server-menu';
  const LINKS = phone ? '#ps-phone-more-menu a.ps-menu-item[href]' : '.ps-server-menu a[href]';
  const context = await browser.newContext({ viewport, colorScheme: 'dark', deviceScaleFactor: 1 });
  page = await context.newPage();
  page.on('pageerror', (e) => say('page error', false, { message: String(e.message).slice(0, 200) }));
  // Wings' console output as it reaches the page (the output is drawn with WebGL, so the page has no text to read).
  page.on('websocket', (ws) => ws.on('framereceived', (frame) => {
    if (String(frame.payload).includes('"console output"')) page.evaluate(() => { window.__psConsoleLines = (window.__psConsoleLines || 0) + 1; }).catch(() => {});
  }));
  await login();
  if (stand === 'd1440') {
    const cookies = await context.cookies(base);
    fs.writeFileSync(path.join(out, 'headers.json'), JSON.stringify({ Cookie: cookies.map((c) => `${c.name}=${c.value}`).join('; ') }));
  }

  // The overview: the servers show, the page itself does not scroll (only the content does).
  await goto('/');
  await shot('overview');
  say('overview shows the servers', await page.locator('.ps-server-card', { hasText: SERVER }).first().isVisible().catch(() => false));
  if (!phone) say('overview does not scroll', (await scrolls()) <= 1, { scroll: await scrolls() });
  await axe('overview');

  // The server page: title card, menu and Pelican's console (xterm) are there.
  const serverUrl = urls.servers && urls.servers[SERVER];
  say('the seed names the server', !!serverUrl, { server: SERVER });
  if (serverUrl) {
    await goto(serverUrl);
    await shot('server');
    say('server page shows the card and the menu', (await visible('.ps-server-info')) && (await visible(MENU)));
    say('server page shows the console', await page.locator('.xterm, #terminal').first().isVisible().catch(() => false));
    // Londikk 28-11: on the first visit to a server page the console connects and shows Wings' output, without a reload.
    const live = await page.waitForFunction(() => {
      const dot = document.querySelector('#terminal .ps-console-dot.connected'), screen = document.querySelector('#terminal .xterm-screen');
      return !!dot && !!screen && screen.getBoundingClientRect().height > 60 && (window.__psConsoleLines || 0) > 0;
    }, null, { timeout: 20000 }).then(() => true).catch(() => false);
    say('the console connects and shows output on the first visit (Londikk)', live, live ? null : await page.evaluate(() => ({
      dot: !!document.querySelector('#terminal .ps-console-dot.connected'), screen: Math.round(document.querySelector('#terminal .xterm-screen')?.getBoundingClientRect().height || 0),
      lines: window.__psConsoleLines || 0, xterm: !!window.Xterm, state: document.querySelector('#terminal .ps-console-state')?.textContent || null,
    })).catch(() => null));
    if (!phone) say('server page does not scroll', (await scrolls()) <= 1, { scroll: await scrolls() });
    await axe('server');

    // K-B70: each button of the console's title row takes its own click (Pelican rc.3 put its own Copy and Download
    // over that corner). K-B74: the console in its own window shows the console, filling the window.
    const tools = await page.evaluate(() => [...document.querySelectorAll('#terminal > .ps-console-title button')].filter((btn) => btn.getClientRects().length).map((btn) => {
      const box = btn.getBoundingClientRect(), hit = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
      return { label: btn.getAttribute('aria-label'), own: !!hit && btn.contains(hit) };
    })).catch(() => null);
    say("each button of the console's title row takes its own click", !!tools && tools.every((tool) => tool.own) && (phone || tools.length >= 2), tools);
    if (!phone) {
      const [popup] = await Promise.all([context.waitForEvent('page', { timeout: 8000 }).catch(() => null), page.locator('#terminal > .ps-console-title .ps-console-window').click({ timeout: 4000 }).catch(() => {})]);
      if (popup) { await popup.waitForSelector('#terminal .xterm', { state: 'attached', timeout: 15000 }).catch(() => {}); await sleep(2500); }
      const shown = popup ? await popup.evaluate(() => {
        const box = (el) => { const b = el ? el.getBoundingClientRect() : null; return b ? [Math.round(b.width), Math.round(b.height), Math.round(b.bottom)] : null; };
        return { width: innerWidth, height: innerHeight, terminal: box(document.getElementById('terminal')), command: box(document.getElementById('send-command')), scroll: document.documentElement.scrollHeight - innerHeight };
      }).catch(() => null) : null;
      if (popup) await popup.screenshot({ path: path.join(out, `${stand}-console-window.png`) }).catch(() => {});
      say('the console in its own window shows the console, without scrolling', !!shown && !!shown.terminal && shown.terminal[0] > 300 && shown.terminal[1] > 100 && shown.scroll <= 1 && (!shown.command || shown.command[2] <= shown.height), shown);
      if (popup) await popup.close().catch(() => {});
    }

    // K-B8: the name is edited in the title card itself. A click on the name opens a field with its text selected, a
    // click beside it keeps a change (no autosave, P-38), Esc puts the name back and Enter saves.
    const nameState = () => page.evaluate(() => {
      const vis = (e) => !!e && e.getClientRects().length > 0;
      const field = [...document.querySelectorAll('.ps-server-info .ps-name-input')].find(vis);
      const title = [...document.querySelectorAll('.ps-server-info .ps-game-title h2')].find(vis);
      return { field: !!field, value: field ? field.value : null, selected: field ? field.value.slice(field.selectionStart, field.selectionEnd) : null, focused: !!field && document.activeElement === field, title: title ? title.textContent : null, dialog: [...document.querySelectorAll('.ps-modal-layer')].some(vis) };
    }).catch(() => null);
    const fieldReady = () => page.waitForFunction(() => document.activeElement && document.activeElement.classList.contains('ps-name-input'), null, { timeout: 4000 }).catch(() => {});
    const original = ((await nameState()) || {}).title || '';
    await page.locator('.ps-server-info .ps-game-title h2 >> visible=true').first().click({ timeout: 4000 }).catch(() => {}); await fieldReady(); await sleep(300);
    const opened = await nameState();
    await shot('server-name-edit');
    say('name edit: a click on the name opens a field with the name selected', !!opened && opened.field && !opened.dialog && opened.focused && !!original && opened.selected === original, opened);
    await page.keyboard.type('Browser name'); await sleep(200);
    await page.locator('.ps-server-info .ps-info-meta >> visible=true').first().click({ timeout: 4000, position: { x: 2, y: 2 } }).catch(() => {}); await sleep(500);
    const beside = await nameState();
    say('name edit: a click beside the field keeps what was typed', !!beside && beside.field && beside.value === 'Browser name', beside);
    await page.keyboard.press('Escape'); await sleep(400);
    const back = await nameState();
    say('name edit: Esc puts the name back', !!back && !back.field && back.title === original, back);
    await page.locator('.ps-server-info .ps-game-title h2 >> visible=true').first().click({ timeout: 4000 }).catch(() => {}); await fieldReady(); await sleep(300);
    await page.keyboard.type(original + ' 2'); await page.keyboard.press('Enter'); await sleep(2000);
    const saved = await nameState();
    say('name edit: Enter saves', !!saved && !saved.field && saved.title === original + ' 2', saved);
    // K-B79: the browser tab follows the new name at once, not only after the next click in the menu.
    const tab = await page.title();
    say('name edit: the browser tab carries the new name (K-B79)', tab === original + ' 2 - Simplify', { tab });
    // The seeded name back for the rest of the run.
    await page.locator('.ps-server-info .ps-game-title h2 >> visible=true').first().click({ timeout: 4000 }).catch(() => {}); await fieldReady(); await sleep(300);
    await page.keyboard.type(original); await page.keyboard.press('Enter'); await sleep(2000);

    // A click in the server menu loads only the content: the page is not reloaded and the menu is the same element (P-11).
    await page.evaluate((sel) => { window.__psMark = 1; const m = document.querySelector(sel); if (m) m.__psKeep = 1; }, MENU);
    if (phone) { await page.locator('.ps-phone-more > .ps-phone-section').first().click().catch(() => {}); await sleep(700); }
    const link = page.locator(`${LINKS}:not([aria-current="page"]) >> visible=true`).first();
    const target = await link.getAttribute('href').catch(() => null);
    await link.click().catch(() => {});
    await settle(1500);
    const after = await page.evaluate((sel) => ({ reloaded: window.__psMark !== 1, menuKept: !!document.querySelector(sel) && document.querySelector(sel).__psKeep === 1, path: location.pathname }), MENU);
    say('a menu click loads only the content', !!target && !after.reloaded && after.menuKept, { target, ...after });
    await shot('server-menu-click');
    // K-B80: in a low window the menu scrolls. A click on its last item may not jump the menu back to the top, where
    // that item is out of sight.
    if (!phone) {
      await page.setViewportSize({ width: viewport.width, height: 560 }); await sleep(700);
      await page.evaluate(() => { const more = document.querySelector('.ps-server-menu-more'); if (more) more.open = true; });
      const kept = await menuKeeps('.ps-server-menu', '.ps-server-menu a[href]:not([target]) >> visible=true');
      say('the server menu keeps its place after a click (K-B80)', kept.before > 0 && kept.after > 0 && kept.inView, kept);
      await page.setViewportSize({ width: viewport.width, height: viewport.height }); await sleep(500);
    }

    // Files: a file's Delete is in its menu, on top and ready for a click; no row has a red button of its own.
    await goto(serverUrl.replace(/\/console$/, '') + '/files');
    await page.waitForSelector('.fi-ta-row', { timeout: 15000 }).catch(() => {}); await sleep(800);
    const loose = await page.evaluate(() => [...document.querySelectorAll('.fi-ta-row .fi-ta-actions :is(a, button)')].filter((btn) => btn.getClientRects().length && !btn.closest('.fi-dropdown-panel') && btn.classList.contains('fi-color-danger')).length).catch(() => -1);
    await page.locator('.fi-ta-row >> visible=true').first().locator('.fi-ta-actions .fi-dropdown-trigger').first().click({ timeout: 4000 }).catch(() => {}); await sleep(800);
    const remove = await page.evaluate(() => {
      const item = [...document.querySelectorAll('.fi-dropdown-panel .fi-dropdown-list-item.fi-color-danger')].find((btn) => btn.getClientRects().length);
      if (!item) return null;
      const box = item.getBoundingClientRect(), hit = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
      return { own: !!hit && item.contains(hit) };
    }).catch(() => null);
    await shot('files-menu');
    say("Files: Delete is in a file's menu and takes its own click", loose === 0 && !!remove && remove.own, { loose, remove });
    await page.keyboard.press('Escape').catch(() => {}); await sleep(300);

    // Londikk 28-11: Files reached through the server menu, as people do. Pelican's "Drop files to upload" layer and its
    // upload dialog stay hidden: on arrival, after a file is dragged over the page and back out, and after a drag
    // inside the page (a link) that ends without a drop. Then a text file opens in the editor, which takes typing and
    // opens its own Find with Ctrl+F.
    if (!phone) {
      await goto(serverUrl);
      const filesLink = page.locator('.ps-server-menu a[href$="/files"] >> visible=true').first();
      await filesLink.click({ timeout: 4000 }).catch(() => {});
      await page.waitForURL((url) => url.pathname.endsWith('/files'), { timeout: 15000 }).catch(() => {});
      await page.waitForSelector('.fi-ta-row', { timeout: 15000 }).catch(() => {}); await settle(800);
      const layers = () => page.evaluate(() => {
        const shown = (el) => !!el && el.getClientRects().length > 0 && getComputedStyle(el).display !== 'none' && getComputedStyle(el).visibility !== 'hidden' && Number(getComputedStyle(el).opacity) > 0.05;
        const drop = document.querySelector('[x-data^="fileDropZone"] > div');
        const dialog = [...document.querySelectorAll('[x-data^="fileUploader"] > div')];
        return { path: location.pathname, dropZone: !!document.querySelector('[x-data^="fileDropZone"]'), drop: shown(drop), dialog: dialog.some(shown), uploaders: document.querySelectorAll('[x-data^="fileUploader"]').length };
      }).catch(() => null);
      const arrived = await layers();
      await shot('files-through-menu');
      say('Files through the menu: no upload layer or upload dialog in view (Londikk)', !!arrived && arrived.path.endsWith('/files') && !arrived.drop && !arrived.dialog && arrived.uploaders <= 1, arrived);
      // A file from the computer dragged over the page and back out: dragenter/dragleave as Chrome fires them, per element.
      await page.evaluate(() => {
        const fire = (el, type) => { const dt = new DataTransfer(); dt.items.add(new File(['x'], 'x.txt', { type: 'text/plain' })); el.dispatchEvent(new DragEvent(type, { bubbles: true, cancelable: true, dataTransfer: dt })); };
        const row = document.querySelector('.fi-ta-row') || document.body;
        fire(document.body, 'dragenter'); fire(row, 'dragenter'); fire(document.body, 'dragleave'); fire(row, 'dragleave');
      }).catch(() => {});
      await sleep(700);
      const outside = await layers();
      say('Files: a file dragged over the page and back out leaves no upload layer (Londikk)', !!outside && !outside.drop && !outside.dialog, outside);
      // A link in the page dragged a little and let go without a drop.
      const rowLink = page.locator('.fi-ta-row a[href] >> visible=true').first();
      const box = await rowLink.boundingBox().catch(() => null);
      if (box) {
        await page.mouse.move(box.x + 10, box.y + box.height / 2); await page.mouse.down();
        await page.mouse.move(box.x + 60, box.y + box.height / 2 + 40, { steps: 8 }); await page.mouse.move(box.x + 200, box.y + 160, { steps: 8 });
        await page.mouse.up(); await sleep(700);
      }
      const inside = await layers();
      await shot('files-after-drag');
      say('Files: a drag inside the page leaves no upload layer (Londikk)', !!inside && !inside.drop && !inside.dialog, inside);

      // The editor, opened from the list as people do (server.properties from the seed's Wings).
      // The row's link goes to files/edit/<path> (Pelican's recordUrl), the path encoded or not.
      const editHref = await page.evaluate(() => [...document.querySelectorAll('.fi-ta-row a[href]')].map((a) => a.getAttribute('href')).find((href) => /\/edit\//.test(href) && /server\.properties/.test(decodeURIComponent(href))) || null).catch(() => null);
      if (editHref) await page.locator(`.fi-ta-row a[href="${editHref}"] >> visible=true`).first().click({ timeout: 4000 }).catch(() => {});
      // The audit's Wings calls every file a .jar, so Pelican offers no edit link there: then straight to the editor.
      if (!editHref) await goto(serverUrl.replace(/\/console$/, '') + '/files/edit/server.properties');
      await page.waitForSelector('.monaco-editor .view-lines', { timeout: 20000 }).catch(() => {}); await settle(1000);
      await page.locator('.monaco-editor .view-lines').first().click({ timeout: 4000, position: { x: 40, y: 10 } }).catch(() => {});
      await page.keyboard.press('Control+End').catch(() => {});
      await page.keyboard.type('simplifytyped', { delay: 20 }).catch(() => {}); await sleep(500);
      const typed = await page.evaluate(() => ({
        text: (document.querySelector('.monaco-editor .view-lines')?.innerText || '').includes('simplifytyped'),
        focus: document.activeElement ? (document.activeElement.className || document.activeElement.tagName).toString().slice(0, 80) : null,
        inEditor: !!document.activeElement?.closest('.monaco-editor'),
      })).catch(() => null);
      await shot('file-editor');
      say('the file editor takes typing (Londikk)', !!typed && typed.text, { fromList: !!editHref, ...typed });
      await page.keyboard.press('Control+f').catch(() => {}); await sleep(700);
      const find = await page.evaluate(() => {
        const widget = document.querySelector('.monaco-editor .find-widget');
        return { visible: !!widget && widget.classList.contains('visible'), focus: !!document.activeElement?.closest('.find-widget'), focusOn: document.activeElement ? (document.activeElement.className || document.activeElement.tagName).toString().slice(0, 80) : null };
      }).catch(() => null);
      say("the file editor opens its own Find with Ctrl+F (Londikk)", !!find && find.visible && find.focus, find);
      await page.keyboard.press('Escape').catch(() => {});
      // The text as it was, so leaving the page asks nothing.
      for (let i = 0; i < 'simplifytyped'.length; i++) await page.keyboard.press('Backspace').catch(() => {});
      await sleep(300);
    }
  }

  // Admin: on a desktop the shield in the top bar switches without a full load; on a phone the Admin tab in the bottom bar.
  await goto('/');
  await page.evaluate(() => { window.__psMark = 1; });
  const admin = page.locator((phone ? '.ps-tabbar a.ps-tab:has(use[href="#ps-shield"])' : 'a.ps-nav-admin') + ' >> visible=true').first();
  await admin.click().catch(() => {});
  // The click is no page load, so "network idle" is there at once: wait for the address itself (a slow panel took
  // longer than the pause that followed, and the step failed on the overview).
  await page.waitForURL((url) => url.pathname.includes('/admin'), { timeout: 15000 }).catch(() => {});
  await settle(1500);
  const onAdmin = await page.evaluate(() => ({ reloaded: window.__psMark !== 1, path: location.pathname }));
  say('Admin opens without a full page load', onAdmin.path.includes('/admin') && !onAdmin.reloaded, onAdmin);
  say('Admin does not scroll', (await scrolls()) <= 1, { scroll: await scrolls() });
  await shot('admin');
  await axe('admin');
  // K-B80 for the Admin menu: with every group open it scrolls, and a click on its last page keeps it there.
  if (!phone) {
    await page.setViewportSize({ width: viewport.width, height: 560 }); await sleep(700);
    const expand = page.locator('#ps-admin-side .ps-host-expand >> visible=true').first();
    if (await expand.isVisible().catch(() => false)) { await expand.click().catch(() => {}); await sleep(600); }
    const kept = await menuKeeps('#ps-admin-side', '#ps-admin-side a.ps-host-leaf >> visible=true');
    say('the Admin menu keeps its place after a click (K-B80)', kept.before > 0 && kept.after > 0 && kept.inView, kept);
    await page.setViewportSize({ width: viewport.width, height: viewport.height }); await sleep(500);
  }

  // K-B92: the Plugins list on a phone. Its import buttons end the row of the filter and the search has room to type
  // in; they stood halfway between the two rows of the tools, beside a search of a few letters.
  if (phone) {
    await goto('/admin/plugins');
    await page.waitForSelector('.fi-ta-header-ctn', { timeout: 15000 }).catch(() => {}); await sleep(600);
    const tools = await page.evaluate(() => {
      const top = (el) => (el && el.getClientRects().length ? Math.round(el.getBoundingClientRect().top) : null);
      const search = document.querySelector('.fi-ta-header-ctn .fi-ta-search-field input');
      return { filter: top(document.querySelector('.fi-ta-header-ctn .ps-filter-button')), buttons: [...document.querySelectorAll('.fi-ta-header-ctn > .fi-ta-header .fi-icon-btn')].map(top).filter((y) => y !== null), search: search ? Math.round(search.getBoundingClientRect().width) : 0 };
    }).catch(() => null);
    await shot('admin-plugins');
    say('the Plugins list on a phone: the import buttons stand in the row of the filter, the search has room (K-B92)', !!tools && tools.filter !== null && tools.buttons.length > 0 && tools.buttons.every((y) => Math.abs(y - tools.filter) <= 6) && tools.search >= 120, tools);
  }

  // Settings: the Save button in the title bar saves ("Settings saved" follows as a notification).
  await goto('/admin/simplify');
  await shot('settings');
  const save = page.locator('.s-save-button >> visible=true').first();
  const hasSave = await save.isVisible().catch(() => false);
  if (hasSave) await save.click().catch(() => {});
  const saved = hasSave && !!(await page.getByText(SAVED).first().waitFor({ state: 'visible', timeout: 8000 }).then(() => true).catch(() => false));
  say('Settings saves from the title bar', saved, { hasSave });
  say('Settings does not scroll', (await scrolls()) <= 1, { scroll: await scrolls() });

  if (phone) say('phone shows the bottom bar', await visible('.ps-tabbar'));
  await context.close();
}

// Holidays (seasons.js) on a phone: the server page and the overview on an ordinary day, during Diwali and at
// Christmas, with the browser's clock set to that day. A holiday may not move the page (layout shift) nor keep the
// browser busier while the page sits still: Lighthouse found both during Diwali (K-B67).
const DAYS = { ordinary: '2026-11-13T10:00:00', diwali: '2026-11-09T10:00:00', christmas: '2026-12-10T10:00:00' };
async function holidays() {
  stand = 'holiday412';
  const serverUrl = urls.servers && urls.servers[SERVER];
  const measured = {};
  for (const [day, time] of Object.entries(DAYS)) {
    const context = await browser.newContext({ viewport: { width: 412, height: 823 }, deviceScaleFactor: 1 });
    page = await context.newPage();
    await page.clock.install({ time: new Date(time) });
    await page.addInitScript(() => {
      window.__psShift = 0;
      window.__psShifts = [];
      // What moved, so a red run names the element: its classes, where it was and where it went, and when.
      const name = (n) => (n && n.nodeType === 1 ? n.tagName.toLowerCase() + (n.id ? '#' + n.id : '') + (n.className && typeof n.className === 'string' ? '.' + n.className.trim().split(/\s+/).slice(0, 3).join('.') : '') : String(n && n.nodeName));
      const box = (r) => [Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height)].join(',');
      try {
        new PerformanceObserver((list) => {
          for (const e of list.getEntries()) {
            if (e.hadRecentInput) continue;
            window.__psShift += e.value;
            window.__psShifts.push({ at: Math.round(e.startTime), value: Number(e.value.toFixed(4)), sources: (e.sources || []).map((x) => `${name(x.node)} ${box(x.previousRect)} -> ${box(x.currentRect)}`) });
          }
        }).observe({ type: 'layout-shift', buffered: true });
      } catch {}
    });
    await login();
    const cdp = await context.newCDPSession(page);
    await cdp.send('Performance.enable');
    const metrics = async () => Object.fromEntries((await cdp.send('Performance.getMetrics')).metrics.map((m) => [m.name, m.value]));
    for (const [name, url] of [['overview', '/'], ['server', serverUrl]]) {
      if (!url) continue;
      await goto(url);
      await sleep(2500);
      const before = await metrics();
      await sleep(5000);
      const after = await metrics();
      measured[`${day}-${name}`] = {
        shift: Number((await page.evaluate(() => window.__psShift)).toFixed(3)),
        shifts: await page.evaluate(() => window.__psShifts.slice(0, 8)),
        busy: Number((after.TaskDuration - before.TaskDuration).toFixed(3)),
        styles: after.RecalcStyleCount - before.RecalcStyleCount,
        layouts: after.LayoutCount - before.LayoutCount,
        season: await page.evaluate(() => document.querySelector('.ps')?.getAttribute('data-season') || ''),
      };
      if (day !== 'ordinary') await shot(`${day}-${name}`);
    }
    await context.close();
  }
  for (const name of ['overview', 'server']) {
    const plain = measured[`ordinary-${name}`];
    // K-B67: Pelican's footer stood at the bottom of the window until the servers pushed it down (0.07).
    if (plain) say(`ordinary ${name}: the page does not move while it loads`, plain.shift <= 0.02, plain);
    for (const day of ['diwali', 'christmas']) {
      const m = measured[`${day}-${name}`];
      if (!plain || !m) continue;
      say(`${day} ${name}: the holiday is on`, m.season.includes(day), m);
      say(`${day} ${name}: the page does not move more than on an ordinary day`, m.shift <= Math.max(0.05, plain.shift + 0.02), { holiday: m, ordinary: plain });
      say(`${day} ${name}: the browser is not busier while the page sits still`, m.busy <= plain.busy * 1.5 + 0.2 && m.layouts <= plain.layouts + 10, { holiday: m, ordinary: plain });
    }
  }
  fs.writeFileSync(path.join(out, 'holidays.json'), JSON.stringify(measured, null, 2));
}

(async () => {
  browser = await chromium.launch({ args: ['--no-sandbox'] });
  for (const viewport of [{ name: 'd1440', width: 1440, height: 900 }, { name: 'phone390', width: 390, height: 844 }]) {
    try { await run(viewport); } catch (e) { say('setup', false, { error: String(e.message).slice(0, 300) }); }
  }
  try { await holidays(); } catch (e) { stand = 'holiday412'; say('setup', false, { error: String(e.message).slice(0, 300) }); }
  await browser.close();
  fs.writeFileSync(path.join(out, 'axe.json'), JSON.stringify(axeReport, null, 2));
  if (failures.length) { console.log(`FAILED: ${failures.join('; ')}`); process.exit(1); }
  console.log('All steps passed');
})();
