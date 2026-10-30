// Screen audit of a panel with Simplify: logs in, opens every screen, tab, menu and overlay (Simplify's and Pelican's),
// on eight window sizes in dark (and two in light), and measures what a screenshot alone would not show: a page
// that scrolls, sideways overflow, text cut off, controls or popups off the screen, children sticking out of a
// clipped box, panes that scroll inside the content card, and the console's colours. Writes a screenshot per
// screen and result.json with every measurement and finding.
// node audit.cjs --base http://127.0.0.1:8001 --urls urls.json --out result [--only phone390,laag1366]
const fs = require('fs'), path = require('path');
const { chromium } = require('playwright');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base', 'http://127.0.0.1:8001'), out = arg('out', 'result'), urls = JSON.parse(fs.readFileSync(arg('urls'), 'utf8'));
const only = arg('only', '') ? arg('only').split(',') : null;
const section = arg('section', ''); // 'seasons', 'extras', 'layers' or 'first' runs only that part (quick diagnosis)
const panelDir = path.resolve(arg('panel', '../../../panel'));
const servermaker = arg('servermaker', 'main') !== 'none';
const uuidOf = (name) => { try { return (JSON.parse(fs.readFileSync(path.join(panelDir, 'servers.json'), 'utf8')).find((x) => x.name === name) || {}).uuid; } catch { return null; } };
fs.mkdirSync(out, { recursive: true });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const rel = (u) => u.replace(/^https?:\/\/[^/]+/, '');
const VIEWPORTS = {
  d1920: { width: 1920, height: 1080 }, d1440: { width: 1440, height: 900 }, laag1366: { width: 1366, height: 650 }, laag1100: { width: 1100, height: 700 }, d1100: { width: 1100, height: 800 },
  hoog1280: { width: 1280, height: 1500 }, tablet768: { width: 768, height: 1024 }, phone390: { width: 390, height: 844 }, phone360: { width: 360, height: 780 },
};
const LIGHT = ['d1440', 'phone390'];
const screens = [], problems = [];
let browser, page, stand = '', screen = '';

const { measure } = require('./measure.cjs');
const settle = async (p, ms = 600) => { await p.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {}); await sleep(ms); };
const alpine = (body) => page.evaluate((b) => { const el = document.querySelector('.ps'); return el && window.Alpine ? new Function('d', b)(window.Alpine.$data(el)) : null; }, body).catch(() => null);
// The theme is set before any page script runs (both Filament's and Simplify's stored choice) and the browser's own
// colour scheme follows it, so every page of the stand opens in that theme.
const setTheme = async (theme) => { await page.context().addInitScript((t) => { try { localStorage.setItem('pelicanSimplify.theme', t); localStorage.setItem('theme', t); } catch {} document.documentElement?.classList.toggle('dark', t === 'dark'); }, theme); await page.emulateMedia({ colorScheme: theme }); await page.evaluate((t) => { localStorage.setItem('pelicanSimplify.theme', t); localStorage.setItem('theme', t); window.SimplifyTheme?.set(t); document.documentElement.classList.toggle('dark', t === 'dark'); }, theme); };

// Simplify's own admin pages (by their path), so the measurement knows which admin pages must follow Simplify's style.
const mopts = () => ({ simplifyAdmin: (urls.admin || []).filter((p) => /^Simplify\\/.test(p.class)).map((p) => rel(p.url)) });
// Records the current state of the page (and its visible frames) under the given screen id.
async function record(id, title, extra = {}) {
  screen = id;
  const dir = path.join(out, stand); fs.mkdirSync(dir, { recursive: true });
  const entry = { id, title, stand, url: rel(page.url()), docTitle: await page.title().catch(() => null), ...extra };
  try {
    entry.main = await page.evaluate(measure, mopts());
    entry.frames = [];
    for (const f of page.frames()) {
      if (f === page.mainFrame()) continue;
      const handle = await f.frameElement().catch(() => null);
      if (!handle) continue;
      const box = await handle.boundingBox().catch(() => null);
      if (!box || box.width < 50) continue;
      const m = await f.evaluate(measure, mopts()).catch((e) => ({ error: e.message }));
      entry.frames.push({ box: `${Math.round(box.x)},${Math.round(box.y)} ${Math.round(box.width)}x${Math.round(box.height)}`, ...m });
    }
    await page.screenshot({ path: path.join(dir, id + '.png'), fullPage: false, timeout: 15000 });
    entry.shot = `${stand}/${id}.png`;
  } catch (e) { entry.error = e.message.slice(0, 200); problems.push(`${stand} ${id}: ${e.message.slice(0, 200)}`); }
  screens.push(entry);
  fs.writeFileSync(path.join(out, 'result.json'), JSON.stringify({ base, screens, problems }, null, 1));
}
// Admin tools outside host mode (1.4.5): a tile opens its page inside Simplify, in host mode's frame and title bar
// without the tree, with a close button back to the overview; from a server page through /simplify#admin:group/page.
const adminTiles = async (overview, serverUrl, openNav) => {
  const has = await page.locator('.ps-host-close').count().catch(() => 0);
  if (!has) return; // a kern without this feature
  const want = await alpine(`const s = d.hostSections(); const pick = (sk, f) => { const sec = s.find((x) => x.key === sk); const it = sec && sec.pages.find(f); return it ? [sk, it.key, it.label] : null; };
    return [pick('servers', (i) => i.key === 'serverresource'), pick('people', (i) => i.key === 'userresource'), pick('panel', (i) => i.settings), pick('pelican', (i) => i.settings), pick('health', (i) => i.key === 'health')].filter(Boolean);`) || [];
  const frameReady = async () => { await page.waitForSelector('.ps-host-frame iframe', { timeout: 10000 }).catch(() => {}); const f = page.frames().find((x) => x !== page.mainFrame() && /\/admin/.test(x.url())); if (f) await f.waitForLoadState('load').catch(() => {}); await sleep(1800); };
  for (const [sk, ik, label] of want) {
    await page.goto('about:blank').catch(() => {});
    await goto(`${overview}#admin:${encodeURIComponent(sk)}/${encodeURIComponent(ik)}`, 600); await frameReady();
    const st = await alpine('return { adminView: d.adminView, hostMode: d.hostMode, page: d.hostPage && d.hostPage.label }');
    if (!st || !st.adminView || st.hostMode) problems.push(`${stand} tile-${slug(label)}: did not open inside Simplify (${JSON.stringify(st)})`);
    await record(`tile-${sk}-${slug(label)}`, `Admin tools-tegel · ${label} (gewone modus, Simplify-frame)`);
  }
  // The real path once: the shield menu, a tile, then the close button.
  await goto(overview); await sleep(600); await openNav();
  if (await click('[x-ref="adminButton"]')) {
    if (!(await page.locator('.ps-admin-tile >> visible=true').count())) { await page.locator('.ps-admin-group-head >> visible=true').first().click().catch(() => {}); await sleep(600); } // phones: tiles fold out under a group
    const tile = page.locator('.ps-admin-tile >> visible=true').first();
    if (await tile.count()) { await tile.click().catch(() => {}); await frameReady(); await record('tile-click', 'Admin tools-tegel aangeklikt'); if (await click('.ps-host-close', 800)) { await record('tile-closed', 'Admin tools-pagina gesloten, terug op het overzicht'); const st = await alpine('return { adminView: d.adminView, hostPage: !!d.hostPage }'); if (st && (st.adminView || st.hostPage)) problems.push(`${stand} tile-closed: still open after close (${JSON.stringify(st)})`); } }
    else problems.push(`${stand} tile-click: no visible admin tile in the shield menu`);
  }
  // From a server page: the tile goes through the overview.
  await goto(serverUrl, 2500); await openNav();
  if (await click('[x-ref="adminButton"]')) {
    if (!(await page.locator('.ps-admin-tile >> visible=true').count())) { await page.locator('.ps-admin-group-head >> visible=true').first().click().catch(() => {}); await sleep(600); }
    const tile = page.locator('.ps-admin-tile >> visible=true').first();
    if (await tile.count()) { await tile.click().catch(() => {}); await page.waitForLoadState('domcontentloaded').catch(() => {}); await frameReady(); await record('tile-from-server', 'Admin tools-tegel vanaf een serverpagina'); const st = await alpine('return { adminView: d.adminView, hash: location.hash }'); if (!st || !st.adminView) problems.push(`${stand} tile-from-server: did not open inside Simplify (${JSON.stringify(st)}, url ${rel(page.url())})`); }
  }
  await goto(overview); await sleep(400);
};
// Host health card and its fold-open tab are one hover zone (1.4.5): pointing at either gives the same colours.
const healthHover = async (where) => {
  const card = page.locator('.ps-host-health >> visible=true').first(), tab = page.locator('.ps-health-tab >> visible=true').first();
  if (!(await card.count()) || !(await tab.count())) return;
  const look = () => page.evaluate(() => { const c = document.querySelector('.ps-host-health'), t = document.querySelector('.ps-health-tab'); const bg = (el) => getComputedStyle(el).backgroundColor; return { card: bg(c), tab: bg(t), tabTop: getComputedStyle(t, '::before').backgroundColor, tabInk: getComputedStyle(t).color }; });
  await page.mouse.move(5, 5); await sleep(300); const rest = await look();
  await card.hover({ position: { x: 40, y: 10 } }).catch(() => {}); await sleep(400); const onCard = await look();
  await record(`${where}-health-hover-card`, `Host health, wijzen naar de kaart`, { colours: onCard });
  await tab.hover().catch(() => {}); await sleep(400); const onTab = await look();
  await record(`${where}-health-hover-tab`, `Host health, wijzen naar het lipje`, { colours: onTab });
  await page.mouse.move(5, 5);
  const same = JSON.stringify(onCard) === JSON.stringify(onTab);
  const lit = onCard.card !== rest.card && onCard.tab !== rest.tab;
  if (!same || !lit) problems.push(`${stand} ${where}-health-hover: card and tab ${same ? 'same' : 'DIFFERENT'} on hover${lit ? '' : ', hover does NOT colour both'}: rest ${JSON.stringify(rest)} card ${JSON.stringify(onCard)} tab ${JSON.stringify(onTab)}`);
  else problems.push(`${stand} ${where}-health-hover: ok, card and tab light up together (${onCard.card})`);
};
const goto = async (url, ms = 900) => { for (let i = 0; i < 3; i++) { try { await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 }); break; } catch (e) { if (i === 2) throw e; await sleep(1500); } } await settle(page, ms); };
const click = async (sel, ms = 600) => { const l = page.locator(sel).first(); if (!(await l.count()) || !(await l.isVisible().catch(() => false))) { problems.push(`${stand} ${screen}: nothing to click for ${sel}`); return false; } await l.click({ timeout: 5000 }).catch((e) => problems.push(`${stand} ${screen}: click ${sel}: ${e.message.slice(0, 80)}`)); await sleep(ms); return true; };
const escape = async () => { await page.keyboard.press('Escape'); await sleep(300); };
const phone = () => VIEWPORTS[stand.split('-')[0]].width <= 600;

async function login(email, password) {
  await goto('/login');
  await page.fill('#form\\.login', email); await page.fill('#form\\.password', password);
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 30000 }).catch(() => {});
  await settle(page);
  if (page.url().includes('/login')) throw new Error('login failed for ' + email);
}

// One round of every screen on the current stand.
async function round(theme) {
  const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url || '/';
  const mainServer = 'Survival SMP', serverUrl = urls.servers[mainServer];
  const ready = () => page.waitForSelector('.ps-server-card, .ps-compact-row, .ps-list-row', { timeout: 15000 }).catch(() => {});
  const onPhone = phone();

  // Pelican's health checks run when its Health page opens; the overview's menus and host mode show their results.
  await goto('/admin/health', 2500);
  // Overview and its menus.
  await goto(overview); await setTheme(theme); await goto(overview); await ready(); await alpine('d.setHostMode(false)'); await sleep(500);
  for (const view of ['grid', 'compact', 'list']) { await alpine(`d.setOverviewView('${view}')`); await sleep(700); await record('overview-' + view, 'Overzicht, ' + view); }
  // Full width (an overview preference): the scroller as wide as the grid, its scrollbar beside the cards (1.5.0).
  if (!phone() && await alpine('return typeof d.setOverviewPreference === "function"')) {
    await alpine(`d.setOverviewPreference('fullWidth', true)`); await sleep(700);
    for (const view of ['grid', 'list']) { await alpine(`d.setOverviewView('${view}')`); await sleep(700); await record('overview-full-' + view, 'Overzicht, volle breedte, ' + view); }
    await alpine(`d.setOverviewPreference('fullWidth', false)`); await sleep(500);
  }
  await alpine(`d.setOverviewView('grid')`); await sleep(500);
  // Phones: the panel menu opens from the tab bar at the bottom (the account button sits inside it).
  const openNav = async () => { if (!onPhone) return; if (!(await click('.ps-tabbar button[aria-controls="ps-navigation-items"]', 500))) await click('.ps-mobile-menu'); };
  if (onPhone) { await openNav(); await record('overview-phone-menu', 'Overzicht, telefoonmenu open'); }
  await click('.ps-account-button'); await record('overview-account', 'Accountmenu open');
  await click('#ps-account-actions button[aria-label]:nth-child(2)'); await record('overview-account-theme', 'Accountmenu, themakeuze open'); await escape();
  await openNav();
  await click('[x-ref="notifyButton"]'); await record('overview-bell', 'Bel (aankondigingen) open'); await escape();
  await openNav();
  await click('[x-ref="adminButton"]'); await record('overview-admin', 'Beheermenu (schild) open');
  // Admin tools (1.4.4, variant D): the second group open, its pages as tiles (on a phone they fold out under the group).
  if (await click('.ps-admin-group-head:not(.is-open)', 700)) await record('overview-admin-group2', 'Beheermenu, tweede groep open'); await escape();
  await openNav();
  await click('[x-ref="notifyButton"]');
  // The bell's expand button (1.4.4): a window with every notification.
  if (await click('.ps-notify-all, [x-ref="notifyButton"] ~ .ps-dropdown button[aria-label*="all" i], .ps-dropdown button[aria-label*="expand" i]', 1200)) await record('overview-bell-all', 'Meldingenvenster (alle meldingen) open'); await escape(); await escape();
  await openNav();
  await click('[x-ref="overviewAppearance"]'); await record('overview-customize', 'Weergave aanpassen open'); await escape();
  await openNav();
  await click('[x-ref="overviewGroupsButton"]'); await record('overview-groups', 'Servergroepen open'); await escape();
  if (await click('.ps-health-pill')) await record('overview-health', 'Gezondheid van de host open'); await escape();
  // Host health card's tab under its bottom edge (1.4.4): the nodes fold open.
  if (await click('.ps-health-tab', 900)) { await record('overview-health-nodes', 'Host health, nodes uitgeklapt'); await click('.ps-health-tab', 600); }
  await healthHover('overview');
  await adminTiles(overview, serverUrl, openNav);
  // The menus under the top bar once more with one announcement bar and with none: their height must follow the bars.
  const unpinBar = async () => { const b = page.locator('button[aria-label="Unpin"], .ps-announcement-unpin, [wire\\:click^="unpin"]').first(); if (!(await b.count())) return false; await b.click().catch(() => {}); await sleep(1500); return true; };
  for (const [tag, label] of [['1bar', 'één aankondigingsbalk'], ['0bar', 'geen aankondigingsbalk']]) {
    if (!(await unpinBar())) { problems.push(`${stand}: no announcement bar to unpin for ${tag}`); break; }
    await openNav(); await click('[x-ref="notifyButton"]'); await record('overview-bell-' + tag, `Bel open, ${label}`); await escape();
    await openNav(); await click('[x-ref="adminButton"]'); await record('overview-admin-' + tag, `Beheermenu open, ${label}`); await escape();
    await openNav(); await click('[x-ref="overviewAppearance"]'); await record('overview-customize-' + tag, `Weergave aanpassen open, ${label}`); await escape();
  }
  // Pin both bars again for the next stand (the Livewire component keeps the choice per user).
  await page.evaluate(() => { const c = window.Livewire?.all().find((x) => /announcement/i.test(x.name)); if (c) { c.call('pin', 1); c.call('pin', 2); } }).catch(() => {}); await sleep(1500);
  await click('.ps-server-card .ps-card-menu-button, .ps-server-card button[aria-label*="options" i], .ps-server-card button[aria-label*="menu" i]', 500) && await record('overview-card-menu', 'Serverkaart ⋯-menu open'); await escape();
  for (const p of urls.app.filter((x) => x.url !== overview)) { await goto(p.url); await record('app-' + slug(p.title), p.title); }
  if (urls.profile) { await goto(urls.profile); await record('profile', 'Profiel'); }

  // The server page: console (live, through the fake Wings' websocket), its menus, every section and add-on tab.
  await goto(serverUrl, 3500); await record('server-console', `Server · Console (${mainServer})`);
  await page.fill('#send-command', 'say a command typed in the bar').catch(() => {}); await page.keyboard.press('Enter').catch(() => {}); await sleep(800);
  await record('server-console-typed', 'Server · Console na een commando');
  if (await click('.ps-console-grow', 800)) { await record('server-console-big', 'Server · Console vergroot'); await click('.ps-console-grow', 800); }
  await click('.ps-info-more-button'); await record('server-more', 'Server · ⋯-menu open'); await escape();
  const colour = page.locator('.ps-info-more-button').first();
  if (await colour.count()) { await colour.click().catch(() => {}); await sleep(400); if (await click('.ps-menu-item:has-text("colour"), .ps-menu-item:has-text("Colour"), .ps-menu-item:has-text("color")', 500)) await record('server-colour', 'Server · kleurkiezer open'); await escape(); }
  if (await click('.ps-info-motd, .ps-motd-preview', 700)) { await record('server-motd', 'Server · MOTD-editor open'); await escape(); }
  if (onPhone) { if (await click('.ps-phone-more button', 500)) await record('server-phone-more', 'Server · telefoon: Meer-menu open'); await escape(); }
  // Server menu (1.5.0): main items plus "Advanced" (a <details>) with Delete server at the bottom; no "Open in Admin".
  const more = page.locator('.ps-server-menu-more > summary >> visible=true').first();
  if (await more.count()) {
    await more.click().catch(() => {}); await sleep(500); await record('server-menu-advanced', 'Server · menu, Advanced open');
    const del = await page.evaluate(() => { const b = [...document.querySelectorAll('.ps-server-menu-more button, .ps-server-menu button')].find((x) => /delete/i.test(x.textContent)); return b ? { disabled: b.disabled || b.getAttribute('aria-disabled') === 'true', text: b.textContent.trim() } : null; });
    problems.push(`${stand} server-menu: Delete server ${del ? (del.disabled ? 'disabled (running server: ok)' : 'ENABLED on a running server') : 'NOT FOUND'}`);
    await more.click().catch(() => {}); await sleep(300);
  }
  const labels = await page.locator('.ps-server-menu a, .ps-phone-sections a, .ps-phone-more-menu a').evaluateAll((as) => as.map((a) => a.textContent.trim()));
  if (labels.some((t) => /open in admin/i.test(t))) problems.push(`${stand} server-menu: "Open in Admin" is still in the menu`);
  const menu = await page.locator('.ps-server-menu a, .ps-phone-sections a, .ps-phone-more-menu a').evaluateAll((as) => [...new Map(as.map((a) => [a.getAttribute('href'), a.textContent.trim()])).entries()]);
  for (const [href, label] of menu) {
    if (!href || href === serverUrl || /\/server\/[^/]+\/?$/.test(href) && rel(href) === rel(serverUrl)) continue;
    await goto(href, 1500); await record('server-' + slug(label), `Server · ${label}`);
    // Backups & tasks: tabs on top of the backup and schedule pages.
    if (await page.locator('.ps-page-tabs >> visible=true').count()) { const tabs = page.locator('.ps-page-tabs a >> visible=true'); const n = await tabs.count(); for (let t = 0; t < n; t++) { const tl = (await tabs.nth(t).innerText().catch(() => '')).trim(); const th = await tabs.nth(t).getAttribute('href'); if (!th || rel(th) === rel(page.url()) || screens.some((x) => x.stand === stand && x.id === 'server-tab-' + slug(tl))) continue; await goto(th, 1500); await record('server-tab-' + slug(tl), `Server · ${label} › ${tl}`); if (!(await page.locator('.ps-page-tabs >> visible=true').count())) problems.push(`${stand} server-tab-${slug(tl)}: no page tabs on this page`); await goto(href, 1200); } }
    // Inside each section: the first row's actions or the first tab, so dialogs and dropdowns there are seen too.
    if (/schedule/i.test(label) && urls.schedules?.[mainServer]) { await goto(urls.schedules[mainServer], 1500); await record('server-schedule-edit', 'Server · Schedule bewerken (3 taken)'); }
    if (/file/i.test(label)) { if (await click('.fi-ta-row .fi-ta-actions button, .fi-ta-row .fi-dropdown-trigger button', 600)) await record('server-files-rowmenu', 'Server · Files, rijmenu open'); await escape(); if (await click('.fi-ta-row a:has-text("plugins"), .fi-ta-row a:has-text("mods")', 1500)) await record('server-files-folder', 'Server · Files, map open'); }
    if (/backup|database|user|schedule|webhook/i.test(label)) { if (await click('.fi-header-actions button, .fi-ac-btn-action, .fi-page-header-main-ctn button', 900)) await record('server-' + slug(label) + '-new', `Server · ${label}, nieuw-dialoog open`); await escape(); }
    if (/startup|setting/i.test(label)) { const tabs = page.locator('.fi-tabs [role=tab], .fi-sc-tabs-tab'); const n = Math.min(await tabs.count(), 5); for (let i = 1; i < n; i++) { await tabs.nth(i).click().catch(() => {}); await sleep(600); await record(`server-${slug(label)}-tab${i}`, `Server · ${label}, tabblad ${i + 1}`); } }
  }
  // Add-on tabs that the main server does not have (Explain, Egg Updates, Server Maker ...), on the first server that opens them.
  const seen = new Set(menu.map(([h]) => rel(h)));
  for (const [name, pages] of Object.entries(urls.server)) for (const p of pages) { if (!p.url || seen.has(rel(p.url)) || screens.some((s) => s.stand === stand && s.id === 'server-' + slug(p.title))) continue; const r = await page.request.get(rel(p.url)).catch(() => null); if (!r || r.status() !== 200) continue; seen.add(rel(p.url)); await goto(p.url, 1500); await record('server-' + slug(p.title), `Server · ${p.title} (${name})`); }
  // A second server with another game, console only.
  const other = Object.entries(urls.servers).find(([n]) => n === 'Valheim Vikings' || n === 'Rust Weekend');
  if (other) { await goto(other[1], 3000); await record('server2-console', `Server · Console (${other[0]})`); }
  // And the server with its own icon set in Pelican (seed): its header must show that icon with the egg badge small in the corner.
  if (urls.servers['Skyblock']) { await goto(urls.servers['Skyblock'], 3000); await record('server3-console', 'Server · Console (Skyblock, eigen icoon)'); }
  if (urls.servers['Creative Builds']) { await goto(urls.servers['Creative Builds'], 3000); await record('server4-console', 'Server · Console (Creative Builds, icoon laadt niet)');
    // The browser tab is "<server name> - Simplify" on every server page (1.4.5), here and on a tab below it.
    const files = page.locator('a[href*="/files"] >> visible=true').first();
    if (await files.count()) { await files.click().catch(() => {}); await settle(page, 2500); await record('server4-files', 'Server · Files (Creative Builds)'); }
    for (const sid of ['server4-console', 'server4-files']) { const e = screens.filter((x) => x.id === sid && x.stand === stand).pop(); if (e) problems.push(`${stand} ${sid}: tab title ${/^Creative Builds - Simplify$/.test(e.docTitle || '') ? 'ok' : 'WRONG'}: "${e.docTitle}"`); }
  }

  // The admin side as Pelican shows it (sidebar, topbar, notifications, user menu, search) and Simplify's admin pages.
  const admin = urls.admin;
  await goto('/admin', 1200); await record('admin-dashboard', 'Beheer · Dashboard');
  if (await click('.fi-topbar-database-notifications-btn, button[aria-label*="notification" i], .fi-topbar button:has(.fi-topbar-database-notifications-badge)', 1200)) { await record('admin-notifications', 'Beheer · Pelican-meldingen open'); if (await click('.fi-modal-window button:has-text("Show more"), .fi-dropdown-panel button:has-text("Show more"), button:has-text("Show more")', 1200)) await record('admin-notifications-more', 'Beheer · Pelican-meldingen, Show more'); } else problems.push(`${stand}: no Pelican notifications button found`);
  await escape(); await goto('/admin', 1000);
  if (await click('.fi-user-menu button, .fi-user-menu .fi-dropdown-trigger, .fi-topbar .fi-avatar', 800)) await record('admin-usermenu', 'Beheer · gebruikersmenu open'); else problems.push(`${stand}: no user menu found`);
  await escape();
  if (await click('.fi-global-search-field input, .fi-global-search input, .fi-topbar input[type=search]', 300)) { await page.keyboard.type('surv'); await sleep(1500); await record('admin-search', 'Beheer · zoeken, resultaten open'); } else if (await click('.fi-global-search-btn, .fi-topbar button[aria-label*="search" i]', 500)) { await page.keyboard.type('surv'); await sleep(1500); await record('admin-search', 'Beheer · zoeken, resultaten open'); } else problems.push(`${stand}: no global search found`);
  await escape(); await goto('/admin', 800);
  if (onPhone || VIEWPORTS[stand.split('-')[0]].width < 1024) { if (await click('.fi-topbar-open-sidebar-btn, button[aria-label*="sidebar" i], .fi-topbar-sidebar-toggle-btn, .fi-layout-sidebar-toggle-btn', 800)) await record('admin-sidebar-open', 'Beheer · zijbalk open (klein venster)'); await escape(); }
  else { if (await click('.fi-topbar-collapse-sidebar-btn, .fi-sidebar-collapse-btn, button[aria-label*="collapse" i]', 800)) { await record('admin-sidebar-collapsed', 'Beheer · zijbalk ingeklapt'); await click('.fi-topbar-expand-sidebar-btn, .fi-sidebar-expand-btn, button[aria-label*="expand" i], .fi-topbar-open-sidebar-btn', 800); } }
  // Every admin page, with the first tab row and the first row's edit page for the big resources.
  for (const p of admin) {
    if (/Setup$|ViewLogs$|Dashboard$/.test(p.class)) continue;
    await goto(p.url, 1200); await record('admin-' + slug(p.title), 'Beheer · ' + p.title);
    const tabs = page.locator('main [role=tab], .fi-sc-tabs-tab, .s-settings-tabs [role=tab], .s-segment');
    const n = await tabs.count();
    for (let i = 1; i < Math.min(n, 9); i++) { await tabs.nth(i).click().catch(() => {}); await sleep(900); await record(`admin-${slug(p.title)}-tab${i}`, `Beheer · ${p.title}, tabblad ${i + 1}`); }
    if (/RoleResource|UserResource|ServerResource|NodeResource|EggResource/.test(p.class)) { const edit = page.locator('.fi-ta-row a[href*="/edit"], .fi-ta-row a[href*="/admin/"]').first(); if (await edit.count()) { const h = await edit.getAttribute('href'); if (h) { await goto(h, 1500); await record('admin-' + slug(p.title) + '-edit', 'Beheer · ' + p.title + ', bewerken'); const t2 = page.locator('main [role=tab], .fi-sc-tabs-tab'); const m = await t2.count(); for (let i = 1; i < Math.min(m, 7); i++) { await t2.nth(i).click().catch(() => {}); await sleep(800); await record(`admin-${slug(p.title)}-edit-tab${i}`, `Beheer · ${p.title}, bewerken, tabblad ${i + 1}`); } } } }
    if (/People$/.test(p.class)) { if (await click('main button:has-text("Add person"), main button:has-text("Add")', 1000)) await record('admin-people-add', 'Beheer · People, persoon toevoegen'); await escape(); }
    if (/AnnouncementResource$|Announcements$/.test(p.class)) { if (await click('main a:has-text("New announcement"), main button:has-text("New announcement")', 1200)) await record('admin-announcements-new', 'Beheer · nieuwe aankondiging'); await escape(); }
  }

  // Host mode: the tree with every section and page, the server list and a server in the frame.
  await goto(overview); await ready();
  await alpine('d.setHostMode(true)'); await sleep(2500);
  await record('host', 'Hostmodus');
  if (await click('.ps-health-tab', 900)) { await record('host-health-nodes', 'Hostmodus · host health, nodes uitgeklapt'); await click('.ps-health-tab', 600); }
  await healthHover('host');
  if (onPhone) { if (await click('.ps-host-menu-open, button[aria-controls="ps-host-menu"], .ps-mobile-menu', 600)) await record('host-phone-menu', 'Hostmodus · telefoonmenu open'); await escape(); }
  const heads = page.locator('.ps-host-group-head'); const hn = await heads.count();
  for (let i = 0; i < hn; i++) { const h = heads.nth(i); const label = (await h.innerText().catch(() => 'sectie ' + i)).trim().split('\n')[0]; await h.click().catch(() => {}); await sleep(2500); await record('host-' + slug(label), 'Hostmodus · ' + label);
    // Only this group's own pages (the tree keeps other groups open), all of them.
    const leaves = page.locator('.ps-host-group').nth(i).locator('.ps-host-leaf >> visible=true'); const ln = await leaves.count();
    for (let j = 0; j < Math.min(ln, 40); j++) { const l = leaves.nth(j); const t = (await l.innerText().catch(() => 'pagina ' + j)).trim(); if (screens.some((s) => s.stand === stand && s.id === 'host-' + slug(label) + '-' + slug(t))) continue; await l.click().catch(() => {}); await sleep(2500); await record('host-' + slug(label) + '-' + slug(t), `Hostmodus · ${label} · ${t}`); } }
  if (await click('.ps-host-tabs button:nth-child(2)', 1200)) { await record('host-servers', 'Hostmodus · serverlijst'); if (await click('.ps-host-server strong, .ps-host-server', 3000)) { await record('host-server', 'Hostmodus · server open (console in frame)'); await click('.ps-host-server-head .ps-info-more-button, .ps-info-more-button', 500) && await record('host-server-more', 'Hostmodus · server ⋯-menu'); await escape(); } }
  await alpine('d.setHostMode(false)'); await sleep(800);
  if (['d1440', 'tablet768', 'phone390'].includes(stand.split('-')[0])) await seasons(theme);
  if (['d1440', 'phone390'].includes(stand.split('-')[0])) await createFlow();
  if (['d1440', 'laag1366', 'laag1100', 'phone390'].includes(stand.split('-')[0])) await extrasFlow(theme);
  // Last, because it changes what the account has read and archived.
  if (theme === 'dark' && ['d1440', 'phone390'].includes(stand.split('-')[0])) await notifyFlow();
}

// Create server (1.5.0): without Server Maker, Pelican's create form opens inside Simplify like an Admin tools page
// (title "Servers › Create server", close button); with Server Maker, Server Maker opens. Desktop button, phone tab bar.
// Edit schedule (section=schedule, old plugin issue #236): the Tasks block under the form must be reachable. The content
// box scrolls to its end, and the last task's actions are clickable there (not covered, not cut off).
async function scheduleFlow() {
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  await goto(urls.servers['Survival SMP'].replace(/\/console$/, '') + '/schedules', 3000);
  const row = page.locator('.fi-ta-row:has-text("Nightly restart") >> visible=true').first();
  if (!(await row.count())) return say('schedule-edit', false, { note: 'no Nightly restart row', url: rel(page.url()) });
  await row.click().catch(() => {}); await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {}); await sleep(2000);
  const reach = () => page.evaluate(() => {
    const box = document.querySelector('.fi-page-main') || document.scrollingElement;
    box.scrollTop = box.scrollHeight;
    const rows = [...document.querySelectorAll('.fi-ta-row')].filter((r) => r.getClientRects().length);
    const last = rows[rows.length - 1];
    const action = last && [...last.querySelectorAll('button, a')].filter((b) => b.getClientRects().length).pop();
    const r = action && action.getBoundingClientRect();
    const hit = r && document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
    return { url: location.pathname, tasks: rows.length, scrollable: box.scrollHeight > box.clientHeight + 2, scrolled: Math.round(box.scrollTop),
      pageScroll: document.documentElement.scrollHeight - innerHeight, action: action ? (action.getAttribute('aria-label') || action.textContent.trim()).slice(0, 40) : null,
      inView: !!r && r.top >= 0 && r.bottom <= innerHeight, clickable: !!hit && (hit === action || action.contains(hit)) };
  });
  await sleep(500);
  const st = await reach();
  await record('schedule-edit', 'Schedules · taak bewerken (onderaan)');
  say('schedule-edit', st.tasks >= 3 && st.inView && st.clickable && st.pageScroll <= 1, st);
}

// Server Maker (section=maker, with servermaker): every wizard step on a real screen, for its own docs/screenshots:
// Minecraft › Plugins, another game's Server step, Resources and Review. Each step must move on and show its own form.
async function makerFlow() {
  if (!servermaker) return;
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  const state = () => page.evaluate(() => {
    const v = (e) => !!e && !!e.getClientRects().length && getComputedStyle(e).display !== 'none';
    const step = document.querySelector('.fi-sc-wizard-step.fi-active');
    return { step: step ? step.id || step.getAttribute('x-ref') || null : null, types: v(document.querySelector('.smk-types')), games: v(document.querySelector('.smk-games-grid')),
      errors: [...document.querySelectorAll('.fi-fo-field-wrp-error-message')].filter(v).map((e) => e.textContent.trim()).slice(0, 3), url: location.pathname };
  });
  const click = async (selector) => { const el = page.locator(`${selector} >> visible=true`).first(); if (!(await el.count())) return false; await el.click().catch(() => {}); await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {}); await sleep(1500); return true; };
  await goto('/server-maker', 3000);
  const picked = await click('button.smk-minecraft-type:has-text("Plugins")');
  await sleep(1500);
  let st = await state();
  await record('maker-minecraft', 'Server Maker · Minecraft (Plugins)');
  say('maker-minecraft', picked && st.types && !st.games, { picked, ...st });
  await click('.smk-back');
  const other = await click('button.smk-game:has-text("Valheim")');
  await click('.smk-next');
  st = await state();
  await record('maker-server-other', 'Server Maker · Server (Valheim)');
  say('maker-server-other', other && !st.games && !st.types && !st.errors.length, { other, ...st });
  const before = st.step;
  await click('.smk-next');
  st = await state();
  await record('maker-resources', 'Server Maker · Resources');
  say('maker-resources', st.step !== before && !st.errors.length, st);
  const resources = st.step;
  await click('.smk-next');
  st = await state();
  await record('maker-review', 'Server Maker · Review');
  say('maker-review', st.step !== resources && !st.errors.length, st);
}

async function createFlow() {
  const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url || '/';
  const onPhone = phone();
  // Create server opens Simplify's popup with Server Maker (or Pelican's form without it) on every page, with only the
  // form inside: no second top bar or side menu.
  const check = async (id, label) => {
    await page.waitForSelector('.ps-create-popup .ps-create-frame', { timeout: 8000 }).catch(() => {}); await sleep(3000);
    const pop = await page.evaluate(() => { const l = document.querySelector('.ps-create-popup'); const f = l && l.querySelector('iframe'); return { open: !!l && !!l.getClientRects().length && getComputedStyle(l).display !== 'none', title: (l && l.querySelector('h2') || {}).innerText || null, src: f ? f.getAttribute('src') : null }; }).catch(() => null);
    const frame = page.frames().find((f) => f !== page.mainFrame() && pop && pop.src && f.url().includes(pop.src.replace(/^https?:\/\/[^/]+/, '').split('?')[0]));
    const chrome = frame ? await frame.evaluate(() => ({ bars: [...document.querySelectorAll('.fi-topbar, .fi-sidebar, .ps-topbar, .ps-top')].filter((e) => e.getClientRects().length && getComputedStyle(e).display !== 'none' && e.getBoundingClientRect().height > 10).map((e) => String(e.className).split(' ')[0]), title: ((document.querySelector('h1') || {}).innerText || '').trim().slice(0, 40) })).catch(() => null) : null;
    await record(id, label, { create: { pop, url: rel(page.url()), frame: frame ? rel(frame.url()) : null, chrome } });
    const maker = !!frame && /maker/i.test(frame.url());
    const ok = pop && pop.open && !!frame && chrome && !chrome.bars.length && (servermaker ? maker : true);
    problems.push(`${stand} ${id}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify({ pop, frame: frame ? rel(frame.url()) : null, maker, chrome, page: rel(page.url()) })}`);
    if (pop && pop.open) { await page.keyboard.press('Escape'); await sleep(500); }
  };
  const press = async (where) => {
    const btn = page.locator(':is(a, button):has-text("Create server") >> visible=true').first();
    if (!(await btn.count())) return false;
    await btn.click().catch(() => {}); await sleep(800);
    const choice = page.locator('a.ps-create-button >> visible=true').first(); // a menu of choices when there is more than one
    if (await choice.count()) { await record(`create-menu-${where}`, `Create server · keuzemenu (${where})`); await choice.click().catch(() => {}); }
    return true;
  };
  if (!onPhone) {
    await goto(overview); await sleep(600);
    if (await press('overview')) await check('create-desktop', 'Create server (overzicht)'); else problems.push(`${stand} create-desktop: WRONG no Create server button on the overview`);
    await goto(urls.servers['Survival SMP'], 3000);
    if (await press('server')) await check('create-server-page', 'Create server (serverpagina)'); else problems.push(`${stand} create-server-page: WRONG no Create server button on a server page`);
    // Host mode: Admin → Servers → Create, inside the frame.
    await goto(overview); await alpine('d.setHostMode(true)'); await sleep(1200);
    await alpine(`const sec = d.hostSections().find((x) => x.key === 'servers'); const it = sec && sec.pages.find((p) => p.key === 'serverresource'); if (it) d.openHostLeaf(sec, it); return !!it;`);
    await page.waitForSelector('.ps-host-frame iframe', { timeout: 8000 }).catch(() => {}); await sleep(2500);
    const list = page.frames().find((x) => x !== page.mainFrame() && /\/admin\/servers/.test(x.url()));
    const link = list && list.locator('a[href*="/servers/create"] >> visible=true').first();
    if (link && (await link.count())) { await link.click().catch(() => {}); await sleep(800); await check('create-host-admin', 'Create server (hostmodus, Admin → Servers → Create)'); }
    else problems.push(`${stand} create-host-admin: WRONG no Create in Admin → Servers (${list ? rel(list.url()) : 'no frame'})`);
    await alpine('d.setHostMode(false)'); await sleep(500);
  } else {
    await goto(overview); await sleep(600);
    const tab = page.locator('.ps-tabbar .ps-tab[aria-haspopup="dialog"] >> visible=true').first();
    if (!(await tab.count())) { problems.push(`${stand} create: no + in the tab bar`); return; }
    await tab.click().catch(() => {}); await sleep(900);
    const item = page.locator('.ps-menu-item[role="menuitem"] >> visible=true').first();
    if (await item.count()) { await record('create-tab-menu', 'Create server · tabbalk-menu'); await item.click().catch(() => {}); await check('create-phone-menu', 'Create server via tabbalk-menu'); }
    else await check('create-phone-tab', 'Create server via tabbalk (+)');
  }
  await goto(overview);
}

// Layers (1.5.2): on the console page the server card's menus, the top bar's menus and the tooltips lie on top of the
// console (xterm has its own layers); what the pointer hits at the middle of each item must be that item.
async function layersFlow() {
  const serverUrl = urls.servers['Survival SMP'], onPhone = phone();
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  // Only points the user can see count: inside the window and inside every scrolling box around the item (a long
  // menu scrolls, and what is scrolled out of view is not covered, just hidden).
  const hits = (menu) => page.evaluate((sel) => { const box = document.querySelector(sel); if (!box || !box.getClientRects().length) return null; const clip = (e) => { let r = { l: 0, t: 0, r: innerWidth, b: innerHeight }; for (let p = e.parentElement; p; p = p.parentElement) { const cs = getComputedStyle(p); if (/(auto|scroll|hidden|clip)/.test(cs.overflowX + cs.overflowY)) { const q = p.getBoundingClientRect(); r = { l: Math.max(r.l, q.left), t: Math.max(r.t, q.top), r: Math.min(r.r, q.right), b: Math.min(r.b, q.bottom) }; } } return r; }; return [...box.querySelectorAll('button, a')].filter((e) => e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden').map((e) => { const r = e.getBoundingClientRect(), x = r.left + r.width / 2, y = r.top + r.height / 2, c = clip(e); const top = document.elementFromPoint(x, y); const inWin = x > c.l && x < c.r && y > c.t && y < c.b; return { item: e.textContent.trim().replace(/\s+/g, ' ').slice(0, 30), ok: !!top && (top === e || e.contains(top)), on: top ? (top.className && String(top.className).slice(0, 40)) || top.tagName : null, inWin }; }); }, menu);
  await goto(serverUrl, 3500);
  await click('.ps-info-more-button', 700);
  const more = await hits('#ps-info-menu'); await record('layers-server-more', 'Lagen · ⋯-menu boven de console');
  say('layers-server-more', more && more.length && more.every((h) => h.ok), { bad: more && more.filter((h) => !h.ok), items: more && more.map((h) => h.item) });
  await escape(); await page.mouse.move(5, 5);
  // Tooltips over the card and the console.
  for (const sel of ['.ps-info-address[data-tip]', '.ps-info-meta-item[data-tip]']) {
    const el = page.locator(`${sel} >> visible=true`).first(); if (!(await el.count())) { say(`layers-tip-${slug(sel)}`, false, { note: 'nothing to hover' }); continue; }
    await el.hover().catch(() => {});
    // The tooltip shows after a short delay and fades in; it lets the pointer through, so the hit test turns that off.
    await page.waitForSelector('.ps-tooltip.is-visible', { timeout: 2500 }).catch(() => {}); await sleep(400);
    const tip = await page.evaluate(() => { const t = document.querySelector('.ps-tooltip.is-visible'); if (!t || !t.getClientRects().length) return null; const r = t.getBoundingClientRect(); t.style.pointerEvents = 'auto'; const top = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2); t.style.pointerEvents = ''; return { text: t.textContent.trim(), ok: !!top && (top === t || t.contains(top)), on: top && String(top.className).slice(0, 40), opacity: getComputedStyle(t).opacity, rect: [r.left, r.top, r.width, r.height].map(Math.round).join(' ') }; });
    await record(`layers-tip-${slug(sel)}`, `Lagen · tooltip ${sel}`);
    say(`layers-tip-${slug(sel)}`, !!tip && tip.ok, tip); await page.mouse.move(5, 5); await sleep(300);
  }
  // The top bar's menus over the server card.
  const openNav = async () => { if (onPhone) await page.locator('.ps-tabbar button[aria-controls="ps-navigation-items"]').first().click().catch(() => {}); await sleep(500); };
  if (!onPhone) for (const [btn, menu, id] of [['[x-ref="notifyButton"]', '#ps-notify-menu', 'bell'], ['[x-ref="adminButton"]', '#ps-admin-menu', 'admin'], ['.ps-account-button', '#ps-account-actions', 'account']]) {
    await goto(serverUrl, 2500); await openNav();
    if (!(await click(btn, 800))) continue;
    const h = await hits(menu); await record(`layers-${id}`, `Lagen · ${id}-menu boven de serverkaart`);
    const seen = h && h.filter((x) => x.inWin);
    say(`layers-${id}`, seen && seen.length && seen.every((x) => x.ok), { bad: seen && seen.filter((x) => !x.ok).slice(0, 5), n: seen && seen.length });
    await escape();
  }
  await scrollbars(serverUrl);
  await otherPlugins();
  await createFlow();
}

// First start (claude/first-start): a server that has not run yet says "Ready for its first start" in its card and has
// First start instead of Start, without Restart. A Minecraft server with its EULA still open has the EULA box (with the
// link) before First start, which stays off until the box is ticked; Start from elsewhere makes the box light up.
// Ticking it and First start writes eula.txt and starts (in the last theme, on a server of its own per stand).
async function firstFlow(theme) {
  const { execFileSync } = require('child_process');
  const last = theme === 'light';
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  const card = () => page.evaluate(() => {
    const v = (e) => !!e && !!e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden';
    const badge = document.querySelector('.ps-first-ready'), box = document.querySelector('.ps-eula-check'), main = document.querySelector('.ps-power-main'), restart = document.querySelector('.ps-power-restart'), old = document.querySelector('.ps-ready');
    const link = box && box.querySelector('a[href*="minecraft.net/eula"]');
    const before = v(box) && v(main) ? !!(box.compareDocumentPosition(main) & Node.DOCUMENT_POSITION_FOLLOWING) && (box.getBoundingClientRect().right <= main.getBoundingClientRect().left + 1 || box.getBoundingClientRect().bottom <= main.getBoundingClientRect().top + 1) : null;
    return { badge: v(badge) ? badge.innerText.trim() : null, box: v(box), boxPulse: !!box && box.classList.contains('is-pulse'), link: !!link, ticked: !!box && !!box.querySelector('input:checked'), before, main: v(main) ? main.innerText.trim() : null, mainOff: !!main && main.disabled, restart: v(restart), oldBanner: v(old) };
  });
  const open = async (name) => { await goto(urls.servers[name], 4000); await sleep(1500); return card(); };
  // (1) The EULA still open: eula=false (Fresh Start) and no eula.txt at all (No EULA).
  for (const name of ['Fresh Start', 'No EULA']) {
    if (!urls.servers[name]) { say(`first-eula-${slug(name)}`, false, { note: 'server not seeded' }); continue; }
    const st = await open(name); await record(`first-eula-${slug(name)}`, `First start · EULA open · ${name}`);
    const wlog = path.join(panelDir, 'wings.log'); const asked = fs.existsSync(wlog) ? fs.readFileSync(wlog, 'utf8').split('\n').filter((l) => l.includes(uuidOf(name) || '~') && /eula|contents/.test(l)).slice(-3).map((l) => l.slice(25, 140)) : [];
    st.wings = asked;
    say(`first-eula-${slug(name)}`, st.badge === 'Ready for its first start' && st.box && st.link && st.before && st.main === 'First start' && st.mainOff && !st.restart && !st.oldBanner, st);
    // Start from the keyboard or the console goes through power('start'): the box lights up, nothing starts.
    await page.evaluate(() => { const root = [...document.querySelectorAll('[x-data]')].find((e) => window.Alpine && typeof window.Alpine.$data(e).power === 'function'); if (root) window.Alpine.$data(root).power('start'); });
    await sleep(250); const pulse = await card(); await record(`first-eula-${slug(name)}-pulse`, `First start · Start terwijl de EULA open is · ${name}`);
    say(`first-eula-${slug(name)}-pulse`, pulse.boxPulse, { boxPulse: pulse.boxPulse });
  }
  // (2) Tick and First start: eula.txt is written, the server starts, the note and the box go away.
  const mine = stand.startsWith('d1440') ? 'Fresh Start' : 'No EULA';
  if (last && urls.servers[mine]) {
    await open(mine);
    const log = path.join(panelDir, 'wings.log'), size = fs.existsSync(log) ? fs.statSync(log).size : 0;
    await page.locator('.ps-eula-check input >> visible=true').first().check().catch(() => {}); await sleep(300);
    const ticked = await card(); await record('first-eula-ticked', `First start · vinkje aan · ${mine}`);
    await page.locator('.ps-power-main >> visible=true').first().click().catch(() => {}); await sleep(3000);
    const after = await card(); await record('first-eula-started', `First start · na First start · ${mine}`);
    const calls = fs.existsSync(log) ? fs.readFileSync(log, 'utf8').slice(size) : '';
    const wrote = /files\/write\?file=%2F?eula\.txt|files\/write\?file=\/?eula\.txt/.test(calls), started = /\/power/.test(calls);
    say('first-eula-start', !ticked.mainOff && wrote && started && !after.badge && !after.box, { mainOnAfterTick: !ticked.mainOff, wrote, started, after, calls: calls.split('\n').filter((l) => /write|power/.test(l)).map((l) => l.slice(25, 120)) });
  }
  // (3) Just installed (EULA already accepted): New World goes from installing to installed while its page is open.
  if (urls.servers['New World']) {
    const tinker = (code) => { try { return execFileSync('php', ['artisan', 'tinker', '--execute', code], { cwd: panelDir, encoding: 'utf8', timeout: 60000 }); } catch (e) { return 'tinker failed: ' + e.message.slice(0, 120); } };
    await open('New World');
    const t = tinker("\\App\\Models\\Server::where('name', 'New World')->update(['status' => null, 'installed_at' => now()]); echo 'done';");
    let st = null; for (let i = 0; i < 12; i++) { await sleep(2000); st = await card(); if (st.badge || st.main === 'First start') break; }
    await record('first-installed', 'First start · net geïnstalleerd, EULA al geaccepteerd (New World)');
    say('first-installed', st.badge === 'Ready for its first start' && st.main === 'First start' && !st.mainOff && !st.box && !st.restart && !st.oldBanner, { ...st, tinker: t.trim().slice(-40) });
    tinker("\\App\\Models\\Server::where('name', 'New World')->update(['status' => 'installing', 'installed_at' => null]); echo 'back';");
  }
}

// Server menu without reloads (1.5.2, wire:navigate + @persist): Console → Files → Backups & tasks → Schedules → Console →
// Advanced › Settings → Console. Each step: no full page load, the server top and the menu are the same elements and never
// gone for a frame, aria-current on the right item, on the console "Connected to Wings", live numbers and exactly one open
// websocket; elsewhere the log strip when it is on and no menu height left over; the page never scrolls.
async function navFlow() {
  const serverUrl = urls.servers['Survival SMP'];
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  const sockets = new Set();
  const onWs = (w) => { sockets.add(w); w.on('close', () => sockets.delete(w)); };
  page.on('websocket', onWs);
  await goto(serverUrl, 4000);
  await page.evaluate(() => {
    window.__psMark = 1; window.__psFlash = 0;
    const keep = () => { const c = document.querySelector('.ps-server-info'), m = document.querySelector('.ps-server-menu'); if (c) c.__psKeep = 1; if (m) m.__psKeep = 1; };
    keep();
    const tick = () => { const c = document.querySelector('.ps-server-info'), m = document.querySelector('.ps-server-menu'); if (!c || !c.getClientRects().length || c.getBoundingClientRect().height < 20 || !m || !m.getClientRects().length || c.querySelector(':scope > [x-cloak]') || c.hasAttribute('x-cloak')) window.__psFlash++; requestAnimationFrame(tick); };
    requestAnimationFrame(tick);
  });
  const state = () => page.evaluate(() => {
    const v = (e) => !!e && !!e.getClientRects().length && getComputedStyle(e).display !== 'none';
    const c = document.querySelector('.ps-server-info'), m = document.querySelector('.ps-server-menu'), more = document.querySelector('.ps-server-menu-more');
    const root = [...document.querySelectorAll('[x-data]')].map((e) => window.Alpine && window.Alpine.$data(e)).find((d) => d && 'onConsole' in d);
    const strip = document.querySelector('.ps-log-strip');
    return { reloaded: window.__psMark !== 1, cardKept: !!c && c.__psKeep === 1, menuKept: !!m && m.__psKeep === 1, flashes: window.__psFlash,
      current: [...document.querySelectorAll('.ps-server-menu [aria-current="page"]')].map((a) => a.textContent.trim().replace(/\s+/g, ' ')), advancedOpen: !!more && more.open,
      tab: [...document.querySelectorAll('a[aria-current="page"]')].filter((a) => !a.closest('.ps-server-menu, .ps-phone-sections')).map((a) => a.textContent.trim()).slice(0, 2),
      onConsole: root ? root.onConsole : null, wings: (document.querySelector('.ps-console-state') || {}).textContent || null,
      stats: (document.querySelector('.ps-server-stats') || {}).innerText || '', logPref: root ? !!(root.overviewPreferences || {}).serverConsoleLog : null, logLines: root ? (root.logLines || []).length : null, strip: v(strip),
      menuStyle: m ? [m.style.height, m.style.maxHeight, m.style.minHeight].filter(Boolean).join(' ') : null, menuH: m ? Math.round(m.getBoundingClientRect().height) : null,
      scroll: document.documentElement.scrollHeight - innerHeight, url: location.pathname };
  });
  const clickIn = async (text, scope) => page.evaluate(([t, sc]) => { const box = sc ? document.querySelector(sc) : document; const a = [...box.querySelectorAll('a, summary')].find((e) => e.textContent.trim().replace(/\s+/g, ' ') === t && e.getClientRects().length && !(sc ? false : e.closest('.ps-server-menu, .ps-phone-sections'))); if (!a) return false; a.click(); return true; }, [text, scope]);
  const steps = [
    ['Files', () => clickIn('Files', '.ps-server-menu'), 'Files'],
    ['Backups & tasks', () => clickIn('Backups & tasks', '.ps-server-menu'), 'Backups & tasks'],
    ['Schedules', () => clickIn('Schedules', null), 'Backups & tasks'],
    ['Console', () => clickIn('Console', '.ps-server-menu'), 'Console'],
    ['Settings', async () => { await page.evaluate(() => { const d = document.querySelector('.ps-server-menu-more'); if (d && !d.open) d.querySelector('summary').click(); }); await sleep(300); return clickIn('Settings', '.ps-server-menu-more'); }, 'Settings'],
    ['Console', () => clickIn('Console', '.ps-server-menu'), 'Console'],
  ];
  let i = 0;
  for (const [label, act, want] of steps) {
    i++;
    await page.evaluate(() => { window.__psFlash = 0; });
    const clicked = await act();
    await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {}); await sleep(2500);
    const st = await state();
    const consoleStep = want === 'Console';
    let live = null;
    if (consoleStep) { await sleep(2500); const again = await state(); live = again.stats !== st.stats; }
    await record(`nav-${i}-${slug(label)}`, `Navigatie · ${i}. ${label}`);
    const ok = clicked && !st.reloaded && st.cardKept && st.menuKept && st.flashes === 0 && st.current.includes(want) && (want !== 'Settings' || st.advancedOpen) && st.scroll <= 1
      && (consoleStep ? st.onConsole === true && /Connected to Wings/.test(st.wings || '') && live && sockets.size === 1 : st.onConsole === false && (!st.logPref || !st.logLines || st.strip) && !st.menuStyle);
    say(`nav-${i}-${slug(label)}`, ok, { clicked, sockets: sockets.size, live, ...st, stats: undefined });
  }
  page.off('websocket', onWs);
}

// Scrollbars (1.5.2): 11px wide with a 4px thumb, 14px short of both ends, in a tint of the background: white 16% in
// dark, black 16% in light, and white on the console in both. Checked where something scrolls: the overview, the
// settings, a page in the frame and the console.
async function scrollbars(serverUrl) {
  const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url || '/';
  const theme = stand.endsWith('light') ? 'light' : 'dark';
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  const want = (where) => (where === 'console' || theme === 'dark') ? 'rgba(255, 255, 255, 0.16)' : 'rgba(0, 0, 0, 0.16)';
  // The scrolling boxes of a document, scrolled to their middle so the thumb is in view, with what their bar is.
  const look = (doc, console) => doc.evaluate((onlyConsole) => {
    const rule = [...document.styleSheets].flatMap((sh) => { try { return [...sh.cssRules]; } catch { return []; } }).map((r) => r.cssText);
    const bar = rule.find((t) => /^html ::-webkit-scrollbar \{/.test(t)) || null, thumb = rule.find((t) => /^html ::-webkit-scrollbar-thumb \{/.test(t)) || null, track = rule.find((t) => /^html ::-webkit-scrollbar-track/.test(t)) || null;
    const els = onlyConsole ? [document.querySelector('.ps-console')].filter(Boolean) : [document.scrollingElement, ...document.querySelectorAll('*')].filter((e) => { if (!e || !e.getClientRects().length) return false; const cs = getComputedStyle(e); return (e === document.scrollingElement || /(auto|scroll)/.test(cs.overflowY)) && e.scrollHeight > e.clientHeight + 4 && e.clientHeight > 60; });
    const out = els.slice(0, 4).map((e) => { if (!onlyConsole) e.scrollTop = (e.scrollHeight - e.clientHeight) / 2; const cs = getComputedStyle(e); const r = e.getBoundingClientRect(); return { el: (e.className && String(e.className).trim().split(/\s+/).slice(0, 2).join('.')) || e.tagName.toLowerCase(), color: getComputedStyle(e).getPropertyValue('--s-scroll').trim(), bg: onlyConsole ? cs.backgroundColor : null, width: onlyConsole ? Math.round(r.width) : null, h: Math.round(r.height) }; });
    return { bar, thumb, track, boxes: out };
  }, !!console).catch((e) => ({ error: e.message.slice(0, 100) }));
  const norm = (c) => (c || '').replace(/\s+/g, ' ').replace(/^rgb\((\d+) (\d+) (\d+) \/ \.?(\d+)\)$/, (m, a, b, c2, d) => `rgba(${a}, ${b}, ${c2}, 0.${d})`);
  const judge = (where, info, place) => {
    const css = info && info.bar && /width: 11px/.test(info.bar) && info.thumb && /border-inline-end-width: 4px/.test(info.thumb) && info.track && /margin: 14px/.test(info.track);
    const boxes = (info && info.boxes) || [];
    const colors = boxes.map((b) => norm(b.color));
    say(`scroll-${where}`, css && boxes.length && colors.every((c) => c === want(where)), { want: want(where), got: colors, boxes: boxes.map((b) => b.el + ' ' + b.h + (b.width ? ' w' + b.width : '')), css: !!css, place });
  };
  await goto(overview, 1500); let info = await look(page); await sleep(300); await record('scroll-overview', 'Scrollbalk · overzicht'); judge('overview', info);
  await goto('/admin/simplify', 2000); info = await look(page); await sleep(300); await record('scroll-settings', 'Scrollbalk · instellingen'); judge('settings', info, rel(page.url()));
  await goto(overview); await alpine('d.setHostMode(true)'); await sleep(1200);
  await alpine(`const sec = d.hostSections().find((x) => x.key === 'servers'); const it = sec && sec.pages[0]; if (it) d.openHostLeaf(sec, it); return !!it;`);
  await page.waitForSelector('.ps-host-frame iframe', { timeout: 8000 }).catch(() => {}); await sleep(2500);
  const frame = page.frames().find((x) => x !== page.mainFrame() && /\/admin/.test(x.url()));
  info = frame ? await look(frame) : null; await sleep(300); await record('scroll-frame', 'Scrollbalk · pagina in het frame (hostmodus)'); judge('frame', info, frame ? rel(frame.url()) : 'no frame');
  await alpine('d.setHostMode(false)'); await sleep(500);
  await goto(serverUrl, 3500);
  await page.evaluate(() => { const t = document.querySelector('.ps-console .xterm-scrollable-element'); if (t) t.dispatchEvent(new WheelEvent('wheel', { deltaY: -400, bubbles: true })); }).catch(() => {});
  await sleep(400); info = await look(page, true); await record('scroll-console', 'Scrollbalk · console'); judge('console', info);
}

// Other plugins (1.5.2): always a group that folds open, and a plugin's page shows "Other plugins · <page>" in the
// head. CI has no plugin outside Simplify, so when the group is empty the check puts a page of Pelican's in it.
async function otherPlugins() {
  const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url || '/';
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  await goto(overview, 1200); await alpine('d.setHostMode(true)'); await sleep(1200);
  const real = await alpine(`const s = d.hostSections().find((x) => x.key === 'extensions'); return s ? s.pages.map((p) => p.label) : null;`);
  if (!real) {
    // A stand-in: the first Admin tools page that is not in a group, or the server list, as one plugin's page.
    await alpine(`const orig = d.hostSections.bind(d); const any = orig().flatMap((s) => s.pages).find((p) => p.url && !p.settings); d.hostSections = () => { const list = orig(); const at = list.findIndex((s) => s.key === 'health'); const ext = { key: 'extensions', label: 'Other plugins', icon: 'plugins', color: '#2dd4bf', pages: [{ ...any, key: 'test-plugin', label: 'Server directory' }] }; list.splice(at < 0 ? list.length : at, 0, ext); return list; };`);
    await sleep(300);
  }
  if (phone()) { await alpine('d.hostMenuOpen = true'); await sleep(500); }
  const head = page.locator('.ps-host-group:has(.ps-host-group-head:has-text("Other plugins")) .ps-host-group-head').first();
  if (!(await head.count())) { say('other-plugins', false, { note: 'no Other plugins group in the tree', real }); await alpine('d.setHostMode(false)'); return; }
  const before = await head.getAttribute('aria-expanded');
  await head.click().catch(() => {}); await sleep(600);
  const after = await head.getAttribute('aria-expanded');
  const leafs = await page.locator('.ps-host-group:has(.ps-host-group-head:has-text("Other plugins")) .ps-host-branch :is(a, button) >> visible=true').count();
  await record('other-plugins-open', 'Other plugins · uitgeklapt' + (real ? '' : ' (stand-in)'));
  await page.locator('.ps-host-group:has(.ps-host-group-head:has-text("Other plugins")) .ps-host-branch :is(a, button) >> visible=true').first().click().catch(() => {}); await sleep(2500);
  const top = await page.evaluate(() => { const h = document.querySelector('.ps-host-head.is-page'); const t = document.querySelector('.ps-host-menu-toggle'); return { head: h ? h.innerText.replace(/\s+/g, ' ').trim() : null, toggle: t ? t.innerText.replace(/\s+/g, ' ').trim() : null }; });
  await record('other-plugins-page', 'Other plugins · pagina van een plugin' + (real ? '' : ' (stand-in)'));
  const name = real ? real[0] : 'Server directory';
  say('other-plugins', before === 'false' && after === 'true' && leafs >= 1 && top.head && top.head.includes('Other plugins') && top.head.includes(name), { real: !!real, before, after, leafs, ...top });
  if (phone()) say('other-plugins-toggle', top.toggle && top.toggle.includes('Other plugins · ' + name), top);
  await alpine('d.setHostMode(false)'); await sleep(500);
}

// 1.5.0 extras: the end of the setup in low windows (in and outside host mode), the setup's Look step (#86), the
// install screen and the EULA bar (#89) and an announcement pinned in the bell (#92). Each check writes ok or WRONG.
async function extrasFlow(theme) {
  const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url || '/';
  const onPhone = phone(), last = !LIGHT.includes(stand.split('-')[0]) || theme === 'light';
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  const fits = (doc) => doc.evaluate(() => ({ scroll: document.documentElement.scrollHeight, window: innerHeight })).catch(() => null);
  // Setup done: the page itself never scrolls; the add-on list scrolls inside its card.
  await goto('/admin/simplify-setup-done', 1500); await record('setup-done', 'Setup klaar (gewone beheerpagina)');
  let f = await fits(page); say('setup-done', f && f.scroll <= f.window, f);
  await goto(overview); await alpine('d.setHostMode(true)'); await sleep(1500);
  const leaf = await alpine(`const sec = d.hostSections().find((x) => x.key === 'panel'); const it = sec && sec.pages.find((i) => i.settings); if (!it) return null; d.openHostLeaf(sec, it); return it.label;`);
  await page.waitForSelector('.ps-host-frame iframe', { timeout: 8000 }).catch(() => {}); await sleep(2500);
  const frame = page.frames().find((x) => x !== page.mainFrame() && /\/admin/.test(x.url()));
  if (leaf && frame) {
    await frame.goto(new URL('/admin/simplify-setup-done?simplify_embed=1', base).href, { waitUntil: 'load' }).catch(() => {}); await sleep(2000);
    await record('setup-done-host', 'Setup klaar (hostmodus, in het frame)');
    const ff = await fits(frame), pf = await fits(page);
    say('setup-done-host', ff && pf && ff.scroll <= ff.window && pf.scroll <= pf.window, { frame: ff, page: pf });
  } else say('setup-done-host', false, { note: 'no Simplify settings page in host mode', leaf, frames: page.frames().map((x) => rel(x.url())) });
  await alpine('d.setHostMode(false)'); await sleep(500);

  // The setup's Look step (#86): logo, text or both; effect; colour = accent. The preview follows every choice.
  if (stand.split('-')[0] !== 'laag1100') {
    await goto('/admin/simplify-setup', 1500);
    const step = page.locator('.fi-sc-wizard-header :is(button, a):has-text("Look") >> visible=true').first();
    if (await step.count()) { await step.click().catch(() => {}); await sleep(1200); }
    else { for (let i = 0; i < 2; i++) { await page.locator('.s-setup button:has-text("Next") >> visible=true').first().click().catch(() => {}); await sleep(1200); } }
    if (!(await page.locator('.s-look >> visible=true').count())) say('wizard-look', false, { note: 'Look step did not open', url: rel(page.url()) });
    else {
      await record('wizard-look', 'Setup · Look-stap');
      const uploads = []; page.on('response', (r) => { if (/livewire/.test(r.url())) uploads.push(`${r.status()} ${rel(r.url()).split('?')[0]}`); });
      const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAABSElEQVR4nO2bwQ3CQAwECaIb6IVGoBoagV7oBx7oPpEQxLf2nHQ7bxKvJ75wEWE5Xu6v3cTs6QA0FkAHoLEAOgCNBdABaCyADkBjAXQAmkN1weft/PMzp+ujIMmHpeJh6J+mv5EtI1VAT+NrskSkCFA2vkYtQn4TzGw+4/xSAdnNZ9SRCahqXl1PIqC6eWXdbgFU86r6XQLo5hs9OabfCocFjHL1G9E8noDIQaNd/UYkV9nTYGQLWyHaS2DrAaOOf2NrPk8AHYDGAugANBZAB6CxADoAzWYBlT9aRNiab/oJKHsYGnULHZqAUZdBJNf0SyAsYLQpiObxBPQcPMoU9OTongBaQm99yRKgJCjqyu4B1RJU9aQ3wSoJyjryb4FsCerz+x0hvyVW/J+hKd8THBlvhekANBZAB6CxADoAjQXQAWgsgA5A8waE/mJlGzhiUQAAAABJRU5ErkJggg==', 'base64');
      await page.locator('.s-look-logo input[type=file]').first().setInputFiles({ name: 'logo.png', mimeType: 'image/png', buffer: png }).catch((e) => say('wizard-look-logo', false, { note: e.message.slice(0, 120) }));
      await page.waitForSelector('.s-look-preview .s-preview-bar img', { timeout: 15000 }).catch(() => {}); await sleep(1500);
      await record('wizard-look-logo', 'Setup · Look, logo geüpload');
      // What Livewire holds after the upload (a temporary file, or nothing when the upload failed).
      const held = await page.evaluate(() => { const el = document.querySelector('.s-setup')?.closest('[wire\\:id]'); const c = el && window.Livewire?.find(el.getAttribute('wire:id')); return c ? { logo: JSON.stringify(c.get('data.brand_logo')).slice(0, 160), show: c.get('data.brand_show') } : null; }).catch((e) => ({ error: e.message.slice(0, 120) }));
      problems.push(`${stand} wizard-look-upload: held ${JSON.stringify(held)}, uploads ${JSON.stringify(uploads)}`);
      // Does the choice come after one more round trip (a re-render the upload itself should have done)?
      const chipsNow = await page.locator('.s-chips[role=radiogroup]:has(input[value="both"])').count();
      if (!chipsNow) {
        await page.evaluate(() => { const el = document.querySelector('.s-setup')?.closest('[wire\\:id]'); window.Livewire?.find(el.getAttribute('wire:id'))?.$refresh(); }).catch(() => {}); await sleep(2500);
        const after = await page.locator('.s-chips[role=radiogroup]:has(input[value="both"])').count();
        problems.push(`${stand} wizard-look-refresh: choice ${after ? 'appears only after another round trip' : 'still missing after a round trip'}; preview img ${await page.locator('.s-look-preview .s-preview-bar img').count()}; requests ${JSON.stringify(uploads)}`);
        if (after) await record('wizard-look-logo-refresh', 'Setup · Look, logo na een extra verversing');
      }
      const preview = () => page.evaluate(() => { const bar = document.querySelector('.s-look-preview .s-preview-bar'); if (!bar) return null; const img = bar.querySelector('img'), name = bar.querySelector('.s-preview-name'), inner = name && name.firstElementChild; const vis = (e) => !!e && e.getClientRects().length > 0 && getComputedStyle(e).display !== 'none'; const cs = inner ? getComputedStyle(inner) : null; return { img: vis(img), text: vis(name), shadow: cs && cs.textShadow, filter: cs && cs.filter, color: cs && cs.color, fill: cs && cs.webkitTextFillColor, background: cs && cs.backgroundImage }; });
      const chips = page.locator('.s-chips[role=radiogroup]:has(input[value="both"])').first();
      if (!(await chips.count())) say('wizard-look-show', false, { note: 'no logo/text/both choice after the upload', preview: await preview() });
      else for (const [v, img, text] of [['logo', true, false], ['text', false, true], ['both', true, true]]) {
        await chips.locator(`label:has(input[value="${v}"])`).click().catch(() => {}); await sleep(1500);
        await record(`wizard-look-show-${v}`, `Setup · Look, toont ${v}`);
        const pv = await preview(); say(`wizard-look-show-${v}`, pv && pv.img === img && pv.text === text, pv);
      }
      // Effect and colour sit under "fine-tune" in the name style.
      if (await page.locator('.s-fine-toggle >> visible=true').count()) {
        const toggle = page.locator('.s-fine-toggle').first();
        if ((await toggle.getAttribute('aria-expanded')) !== 'true') { await toggle.click().catch(() => {}); await sleep(600); }
        const row = async (name) => { const head = page.locator(`.s-fine-head:has-text("${name}") >> visible=true`).first(); if (await head.count() && (await head.getAttribute('aria-expanded')) !== 'true') { await head.click().catch(() => {}); await sleep(600); } };
        const before = await preview();
        await row('Effect');
        for (const e of ['Glow', 'Shadow']) {
          await page.locator(`.s-chip:has-text("${e}") >> visible=true`).first().click().catch(() => {}); await sleep(900);
          await record(`wizard-look-effect-${slug(e)}`, `Setup · Look, effect ${e}`);
          const pv = await preview(); // A gradient name gets its effect as a drop-shadow filter, a plain one as text-shadow.
          const fx = (v) => v && [v.shadow, v.filter].filter((x) => x && x !== 'none').join(' | ');
          say(`wizard-look-effect-${slug(e)}`, pv && fx(pv) && fx(pv) !== fx(before), { effect: pv && fx(pv) });
        }
        await row('Colo');
        const c0 = await preview();
        await page.locator('.s-chip:has-text("Accent") >> visible=true').first().click().catch(() => {}); await sleep(900);
        await record('wizard-look-colour-accent', 'Setup · Look, kleur = accentkleur');
        const c1 = await preview(); say('wizard-look-colour-accent', !!c1, { before: c0 && { color: c0.color, fill: c0.fill, background: c0.background }, after: c1 && { color: c1.color, fill: c1.fill, background: c1.background } });
      } else say('wizard-look-fine', false, { note: 'no fine-tune toggle for the name style' });
    }
  }

  // Install screen and the EULA bar (#89): a server that installs, one whose installation failed, one with the EULA open.
  for (const [name, id, sel, label] of [['New World', 'server-installing', '.ps-install', 'installeert'], ['Broken Install', 'server-install-failed', '.ps-install', 'installatie mislukt'], ['Fresh Start', 'server-eula', '.ps-ready.is-eula', 'EULA nog open']]) {
    if (!urls.servers[name]) { say(id, false, { note: 'server not seeded: ' + name }); continue; }
    await goto(urls.servers[name], 3500); await record(id, `Server · ${name} (${label})`);
    const st = await page.evaluate((s) => { const el = document.querySelector(s); const vis = !!el && el.getClientRects().length > 0; return { shown: vis, text: vis ? el.innerText.replace(/\s+/g, ' ').slice(0, 220) : null, eulaLink: !!(el && el.querySelector('a[href="https://www.minecraft.net/eula"]')), start: !!(el && [...el.querySelectorAll('button')].some((b) => /accept and start|try again/i.test(b.textContent) && b.getClientRects().length)) }; }, sel);
    say(id, st.shown && (id !== 'server-eula' || (st.eulaLink && st.start)) && (id !== 'server-install-failed' || st.start), st);
    if (id === 'server-eula' && st.shown && last) {
      await page.locator('.ps-ready-start >> visible=true').first().click().catch(() => {}); await sleep(3500);
      await record('server-eula-accepted', 'Server · EULA geaccepteerd en gestart');
      const after = await page.evaluate(() => { const el = document.querySelector('.ps-ready.is-eula'); return { bar: !!el && el.getClientRects().length > 0, status: window.Alpine && Alpine.$data(document.querySelector('.ps'))?.status }; }).catch(() => null);
      say('server-eula-accept', after && !after.bar, after);
    }
  }

  // An announcement pinned to everyone's bell (#92): on top under "Pinned", without ✕.
  await goto(overview); await sleep(600);
  if (onPhone) { await page.locator('.ps-tabbar button[aria-controls="ps-navigation-items"]').first().click().catch(() => {}); await sleep(500); }
  await page.locator('[x-ref="notifyButton"]').first().click().catch(() => {}); await sleep(1200);
  const pin = await page.evaluate(() => { const box = document.querySelector('#ps-notify-menu .ps-notify-pinned'); const vis = !!box && box.getClientRects().length > 0; const items = vis ? [...box.querySelectorAll('.ps-notify-item')].filter((e) => e.getClientRects().length) : []; return { shown: vis, items: items.map((e) => e.innerText.split('\n')[0]), close: items.some((e) => { const c = e.querySelector('.ps-notify-close'); return c && c.getClientRects().length > 0; }), first: !!box && box === box.parentElement.querySelector(':scope > :not(template)') }; }).catch(() => null);
  await record('notify-pinned', 'Meldingen · vastgezette aankondiging bovenaan');
  say('notify-pinned', pin && pin.shown && pin.items.some((t) => /Maintenance on Saturday/.test(t)) && !pin.close, pin);
  await escape(); if (onPhone) await escape();
  await goto(overview);
}

// Notifications (1.5.0, #90): Mark read on one, Clear read, ✕ to Archived, and Archived at the bottom of the window with
// Show more and Mark as unread. Each step checks that the counts move the right way.
async function notifyFlow() {
  const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url || '/';
  const onPhone = phone();
  const openBell = async () => { await goto(overview); await sleep(600); if (onPhone) { if (!(await page.locator('.ps-tabbar button[aria-controls="ps-navigation-items"]').count())) await page.locator('.ps-mobile-menu').first().click().catch(() => {}); else await page.locator('.ps-tabbar button[aria-controls="ps-navigation-items"]').first().click().catch(() => {}); await sleep(500); } await page.locator('[x-ref="notifyButton"]').first().click().catch(() => {}); await sleep(900); };
  const count = (scope) => page.evaluate((sc) => { const root = document.querySelector(sc); if (!root) return null; const vis = (e) => e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden'; const items = [...root.querySelectorAll('.ps-notify-item')].filter((e) => vis(e) && !e.closest('.ps-notify-pinned')); const inArchive = (e) => !!e.closest('#ps-notify-archived'); return { items: items.filter((e) => !inArchive(e)).length, unread: items.filter((e) => !inArchive(e) && !e.classList.contains('is-read')).length, archived: items.filter(inArchive).length }; }, scope).catch(() => null);
  const say = (step, ok, detail) => problems.push(`${stand} notify-${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  const bell = '#ps-notify-menu';
  await openBell();
  const c0 = await count(bell); await record('notify-bell', 'Meldingen · bel open', { counts: c0 });
  if (!c0 || !c0.items) { say('bell', false, { c0, note: 'no notifications under the bell' }); return; }
  // 1. Mark read on one unread notification.
  const mark = page.locator(`${bell} .ps-notify-item:not(.is-read):not(.ps-notify-pinned .ps-notify-item) .ps-notify-actions button.is-subtle >> visible=true`).first();
  if (await mark.count()) { await mark.click().catch(() => {}); await sleep(1500); const c1 = await count(bell); await record('notify-mark-read', 'Meldingen · één melding gelezen', { counts: c1 }); say('mark-read', c1 && c1.unread === c0.unread - 1, { before: c0, after: c1 }); }
  else say('mark-read', false, { note: 'no unread notification with a Mark read button' });
  // 2. Clear read under the bell: read notifications go to Archived.
  const c2a = await count(bell);
  const clear = page.locator(`${bell} button:has-text("Clear read") >> visible=true`).first();
  if (await clear.count()) { await clear.click().catch(() => {}); await sleep(1500); const c2 = await count(bell); await record('notify-clear-read', 'Meldingen · Clear read (bel)', { counts: c2 }); say('clear-read', c2 && c2.items < c2a.items && c2.items === c2a.unread, { before: c2a, after: c2 }); }
  else say('clear-read', false, { note: 'no Clear read button under the bell', counts: c2a });
  // 3. ✕ on one notification: it goes to Archived.
  const c3a = await count(bell);
  const close = page.locator(`${bell} .ps-notify-close >> visible=true`).first();
  if (await close.count()) { await close.click().catch(() => {}); await sleep(1500); const c3 = await count(bell); await record('notify-close', 'Meldingen · ✕ op één melding', { counts: c3 }); say('close', c3 && c3.items === c3a.items - 1, { before: c3a, after: c3 }); }
  else say('close', false, { note: 'no ✕ on a notification', counts: c3a });
  // 4. The window: Archived at the bottom, closed; open it, Show more, Mark as unread.
  await openBell();
  await page.locator('.ps-notify-all-button, .ps-notify-all, [x-ref="notifyButton"] ~ .ps-dropdown button[aria-label*="all" i], .ps-dropdown button[aria-label*="expand" i]').first().click().catch(() => {}); await sleep(1500);
  const win = '.ps-notify-all .ps-modal';
  const w0 = await count(win); await record('notify-window', 'Meldingen · venster', { counts: w0 });
  const toggle = page.locator(`${win} .ps-notify-archive-toggle >> visible=true`).first();
  if (!(await toggle.count())) { say('archived', false, { note: 'no Archived section in the window', counts: w0 }); await escape(); return; }
  await toggle.click().catch(() => {}); await sleep(1800);
  const w1 = await count(win); await record('notify-archived', 'Meldingen · Archived open', { counts: w1 }); say('archived', w1 && w1.archived > 0, { counts: w1 });
  const more = page.locator(`${win} .ps-notify-more >> visible=true`).first();
  if (await more.count()) { await more.click().catch(() => {}); await sleep(1800); const w2 = await count(win); await record('notify-archived-more', 'Meldingen · Archived, Show more', { counts: w2 }); say('show-more', w2 && w2.archived > w1.archived, { before: w1, after: w2 }); }
  else problems.push(`${stand} notify-show-more: no Show more under Archived (${w1 && w1.archived} archived)`);
  const w3a = await count(win);
  const unarch = page.locator(`${win} #ps-notify-archived .ps-notify-actions button.is-subtle >> visible=true`).first();
  if (await unarch.count()) { await unarch.click().catch(() => {}); await sleep(1800); const w3 = await count(win); await record('notify-unarchived', 'Meldingen · Mark as unread vanuit Archived', { counts: w3 }); say('mark-unread', w3 && w3.archived === w3a.archived - 1 && w3.unread === w3a.unread + 1, { before: w3a, after: w3 }); }
  else say('mark-unread', false, { note: 'no Mark as unread in Archived' });
  await escape(); await escape();
}
// Seasonal touches (1.4.4): the holiday badge next to the panel name and its tooltip, on other dates through the
// browser's clock, with "× Pelican" off and with a logo, and the panel setting that turns everything off.
async function seasons(theme) {
  const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url || '/';
  const serverUrl = urls.servers['Survival SMP'], onPhone = phone(), size = VIEWPORTS[stand.split('-')[0]];
  const context = page.context(), main = page;
  const LOGO = "data:image/svg+xml;utf8," + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="180" height="40" viewBox="0 0 180 40"><rect width="180" height="40" rx="8" fill="#2b6cb0"/><text x="90" y="26" text-anchor="middle" font-family="sans-serif" font-size="18" font-weight="700" fill="#fff">Northwind</text></svg>');
  const DATES = [['halloween', '2026-10-30T12:00:00', 'Halloween (30 okt)'], ['december', '2026-12-07T12:00:00', 'Chanoeka + Kerst (7 dec)'], ['valentine', '2026-02-14T12:00:00', 'Valentijn (14 feb)'], ['easter', '2026-04-05T12:00:00', 'Pasen (5 apr)'], ['newyear', '2026-12-31T23:20:00', 'Oudjaar 23:20']];
  const fresh = async (time) => { const p = await context.newPage(); p.setDefaultTimeout(8000); await p.setViewportSize(size); await p.emulateMedia({ colorScheme: theme }); if (time) await p.clock.install({ time: new Date(time) }); return p; };
  // What the tooltip looks like to the browser: where it is, what paints on top of it, and how it is styled.
  const tipDiag = async () => page.evaluate(() => {
    const t = document.querySelector('.ps-tooltip'); if (!t) return 'no .ps-tooltip element';
    const r = t.getBoundingClientRect(), cs = getComputedStyle(t);
    t.style.pointerEvents = 'auto'; const at = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2); t.style.pointerEvents = '';
    const chain = []; for (let e = t.parentElement; e && chain.length < 6; e = e.parentElement) chain.push(e.tagName.toLowerCase() + (e.className ? '.' + String(e.className).trim().split(/\s+/).join('.') : ''));
    return { visible: t.classList.contains('is-visible'), rect: [r.left, r.top, r.width, r.height].map(Math.round).join(' '), opacity: cs.opacity, display: cs.display, visibility: cs.visibility, zIndex: cs.zIndex, position: cs.position, transform: cs.transform, text: t.textContent.slice(0, 60), onTop: at ? at.tagName.toLowerCase() + (at.className ? '.' + String(at.className).trim().split(/\s+/).slice(0, 3).join('.') : '') : null, parents: chain.join(' < '), innerH: innerHeight, scrollY };
  }).catch((e) => 'diag failed: ' + e.message.slice(0, 80));
  const tipRound = async (tag, label, where) => {
    const badge = page.locator('.ps-season-badge >> visible=true').first();
    if (!(await badge.count())) { problems.push(`${stand} season-${tag}: no seasonal badge on ${where}`); return; }
    await badge.hover().catch(() => {}); await sleep(600); await record(`season-${tag}-${where}-tip`, `Seizoen · ${label} · ${where}, tooltip (hover)`, { tip: await tipDiag() });
    if (onPhone) { await badge.click().catch(() => {}); await sleep(600); await record(`season-${tag}-${where}-tap`, `Seizoen · ${label} · ${where}, tooltip (tik)`, { tip: await tipDiag() }); await escape(); }
    await page.mouse.move(5, 5);
  };
  const look = async (tag, label, prep) => {
    await goto(overview); await page.waitForSelector('.ps-server-card, .ps-compact-row, .ps-list-row', { timeout: 15000 }).catch(() => {}); if (prep) await prep(); await sleep(900);
    await record(`season-${tag}`, `Seizoen · ${label} · overzicht`);
    await tipRound(tag, label, 'overzicht');
    if (onPhone) { await record(`season-${tag}-tabbar`, `Seizoen · ${label} · telefoon tabbalk`); }
    await goto(serverUrl, 2500); if (prep) await prep(); await sleep(600);
    await record(`season-${tag}-server`, `Seizoen · ${label} · serverpagina`);
    await tipRound(tag, label, 'server');
  };
  for (const [tag, time, label] of DATES) {
    page = await fresh(time);
    try { await look(tag, label); } catch (e) { problems.push(`${stand} season-${tag}: ${e.message.slice(0, 160)}`); }
    await page.close().catch(() => {});
  }
  // Halloween with "× Pelican" off, and with a logo instead of the name (the boot data, as the settings would set it).
  page = await fresh('2026-10-30T12:00:00');
  try {
    await look('nosuffix', 'Halloween zonder "× Pelican"', () => alpine('d.boot.appearance.pelican_suffix=false'));
    await look('logo', 'Halloween met logo', () => alpine(`d.boot.appearance.pelican_suffix=true; d.boot.appearance.brand={mode:'logo', logo_url:${JSON.stringify(LOGO)}, logo_height:32}`));
  } catch (e) { problems.push(`${stand} season-logo: ${e.message.slice(0, 160)}`); }
  await page.close().catch(() => {});
  // The panel setting "Seasonal touches" off (Settings → Appearance, saved for real): nothing seasonal may remain.
  page = await fresh('2026-10-30T12:00:00');
  const settings = urls.admin.find((p) => /Simplify\\.*Settings$/.test(p.class))?.url;
  const setSeasonal = async (on) => {
    if (!settings) { problems.push(`${stand}: no Simplify settings page in urls`); return false; }
    await goto(settings, 1500);
    await click('.s-settings-tabs [role=tab]:has-text("Appearance"), .s-settings-tabs button:has-text("Appearance")', 900);
    const sw = page.locator('.fi-fo-field:has-text("Seasonal") button[role=switch], label:has-text("Seasonal") ~ * button[role=switch], .fi-fo-toggle:has-text("Seasonal") button, button[role=switch][id*="seasonal"]').first();
    if (!(await sw.count())) { problems.push(`${stand}: no "Seasonal touches" switch found`); return false; }
    const state = (await sw.getAttribute('aria-checked')) === 'true';
    if (state !== on) await sw.click().catch(() => {});
    await sleep(300);
    const save = page.locator('.fi-header button[aria-label*="Save" i], .fi-header button:has-text("Save"), .fi-page-header-main-ctn button[aria-label*="Save" i], .s-settings .s-bar button[type=submit]').first();
    if (!(await save.count())) { problems.push(`${stand}: no Save button for the settings`); return false; }
    await save.click().catch(() => {}); await sleep(2500);
    return true;
  };
  try {
    if (await setSeasonal(false)) {
      await record('season-off-settings', 'Seizoen · schakelaar uit, instellingen opgeslagen');
      await look('off', 'Seizoensverrassingen uit');
      const left = await page.evaluate(() => [...document.querySelectorAll('.ps-season-badges, .ps-season-badge, .ps-season-countdown, .is-pumpkin, .ps-hw-lantern, .ps-hw-tab, .ps-season-snow, [class*="ps-season"]')].filter((el) => { const b = el.getBoundingClientRect(); const cs = getComputedStyle(el); return b.width > 0 && b.height > 0 && cs.display !== 'none' && cs.visibility !== 'hidden'; }).map((el) => el.className.toString().slice(0, 60)));
      if (left.length) problems.push(`${stand} SEASON OFF: seasonal element(s) still shown on the server page: ${left.slice(0, 5).join(' | ')}`);
      await goto(overview); await sleep(1200);
      const left2 = await page.evaluate(() => [...document.querySelectorAll('.ps-season-badges, .ps-season-badge, .ps-season-countdown, .is-pumpkin, .ps-hw-lantern, .ps-hw-tab, [class*="ps-season"]')].filter((el) => { const b = el.getBoundingClientRect(); const cs = getComputedStyle(el); return b.width > 0 && b.height > 0 && cs.display !== 'none' && cs.visibility !== 'hidden'; }).map((el) => el.className.toString().slice(0, 60)));
      if (left2.length) problems.push(`${stand} SEASON OFF: seasonal element(s) still shown on the overview: ${left2.slice(0, 5).join(' | ')}`);
      else problems.push(`${stand} SEASON OFF: ok, nothing seasonal left on overview and server page`);
      await setSeasonal(true);
    }
  } catch (e) { problems.push(`${stand} season-off: ${e.message.slice(0, 160)}`); try { await setSeasonal(true); } catch {} }
  await page.close().catch(() => {});
  page = main;
}
// Screenshots never show the seasonal touches (they stay quiet); only seasons() looks at them, on pages of its own.
const quiet = (p) => p.addInitScript(() => { let s; Object.defineProperty(window, 'SimplifySeasons', { configurable: true, get: () => s, set: (v) => { s = v && { ...v, active: () => [] }; } }); });
const slug = (s) => String(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 40) || 'x';

(async () => {
  browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: VIEWPORTS.d1440, baseURL: base, deviceScaleFactor: 1 });
  page = await context.newPage(); page.setDefaultTimeout(8000);
  await quiet(page);
  page.on('pageerror', (e) => problems.push(`${stand} ${screen} page error: ${e.message.slice(0, 160)}`));
  page.on('console', (m) => { if (m.type() === 'error' && !/favicon|net::ERR|status of 4|WebSocket/.test(m.text())) problems.push(`${stand} ${screen} console: ${m.text().slice(0, 160)}`); });
  page.on('response', (r) => { if (r.status() >= 500) problems.push(`${stand} ${screen} HTTP ${r.status()} ${rel(r.url())}`); });
  page.on('dialog', (d) => d.dismiss().catch(() => {}));
  await login('demo@example.com', 'DemoPassword123!');
  for (const [name, size] of Object.entries(VIEWPORTS)) {
    if (only && !only.includes(name)) continue;
    for (const theme of LIGHT.includes(name) ? ['dark', 'light'] : ['dark']) {
      stand = `${name}-${theme}`;
      console.log(`[${new Date().toISOString().slice(11, 19)}] ${stand} (${screens.length} screens, ${problems.length} problems so far)`);
      await page.setViewportSize(size);
      if (section) {
        // One or more sections (comma separated), each on its own stands.
        const parts = { first: [['d1440', 'phone390'], () => firstFlow(theme)], layers: [['d1440', 'laag1366', 'laag1100', 'phone390'], () => layersFlow()], nav: [['d1440', 'laag1366'], () => navFlow()], create: [['d1440', 'laag1366', 'phone390'], () => createFlow()],
          maker: [['d1440', 'phone390'], () => makerFlow()],
          schedule: [['d1440', 'laag1366', 'laag1100', 'phone390'], () => scheduleFlow()],
          extras: [['d1440', 'laag1366', 'laag1100', 'phone390'], () => extrasFlow(theme)], seasons: [['d1440', 'tablet768', 'phone390'], () => seasons(theme)] };
        for (const part of section.split(',')) { const [where, run] = parts[part] || [[], null]; if (!run || !where.includes(name)) continue; try { await setTheme(theme); await run(); } catch (e) { problems.push(`${stand} ${part}: ${e.message.slice(0, 200)}`); } }
        continue;
      }
      try { await round(theme); } catch (e) { problems.push(`${stand} ${screen}: round stopped: ${e.message.slice(0, 200)}`); console.log('  ! ' + e.message.slice(0, 200)); if (page.isClosed()) { page = await context.newPage(); await quiet(page); await login('demo@example.com', 'DemoPassword123!'); } }
    }
  }
  // The customer's view, two stands.
  const customer = await browser.newContext({ viewport: VIEWPORTS.d1440, baseURL: base });
  const cp = await customer.newPage(); const admin = page; page = cp; page.setDefaultTimeout(8000); await quiet(page);
  await login('sam@example.com', 'DemoPassword123!');
  for (const name of ['d1440', 'phone390']) {
    if (only && !only.includes(name)) continue;
    stand = `klant-${name}-dark`; await page.setViewportSize(VIEWPORTS[name]);
    try {
      const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url || '/';
      await goto(overview); await setTheme('dark'); await goto(overview); await sleep(800); await record('overview', 'Klant · overzicht');
      await click('.ps-account-button'); await record('account', 'Klant · accountmenu'); await escape();
      await goto(urls.servers['Survival SMP'], 3000); await record('server-console', 'Klant · console');
      await click('.ps-info-more-button'); await record('server-more', 'Klant · ⋯-menu'); await escape();
    } catch (e) { problems.push(`${stand}: ${e.message.slice(0, 200)}`); }
  }
  page = admin;
  fs.writeFileSync(path.join(out, 'result.json'), JSON.stringify({ base, screens, problems }, null, 1));
  const flagged = screens.filter((s) => (s.main?.problems?.length || 0) + (s.frames || []).reduce((n, f) => n + (f.problems?.length || 0), 0));
  console.log(`${screens.length} screens, ${flagged.length} with findings, ${problems.length} problems`);
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
