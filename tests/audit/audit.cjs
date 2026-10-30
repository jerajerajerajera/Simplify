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
const section = arg('section', ''); // 'seasons' runs only the seasonal round (quick diagnosis)
fs.mkdirSync(out, { recursive: true });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const rel = (u) => u.replace(/^https?:\/\/[^/]+/, '');
const VIEWPORTS = {
  d1920: { width: 1920, height: 1080 }, d1440: { width: 1440, height: 900 }, laag1366: { width: 1366, height: 650 }, d1100: { width: 1100, height: 800 },
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
  const entry = { id, title, stand, url: rel(page.url()), ...extra };
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
  const menu = await page.locator('.ps-server-menu a, .ps-phone-sections a, .ps-phone-more-menu a').evaluateAll((as) => [...new Map(as.map((a) => [a.getAttribute('href'), a.textContent.trim()])).entries()]);
  for (const [href, label] of menu) {
    if (!href || href === serverUrl || /\/server\/[^/]+\/?$/.test(href) && rel(href) === rel(serverUrl)) continue;
    await goto(href, 1500); await record('server-' + slug(label), `Server · ${label}`);
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
  if (onPhone) { if (await click('.ps-host-menu-open, button[aria-controls="ps-host-menu"], .ps-mobile-menu', 600)) await record('host-phone-menu', 'Hostmodus · telefoonmenu open'); await escape(); }
  const heads = page.locator('.ps-host-group-head'); const hn = await heads.count();
  for (let i = 0; i < hn; i++) { const h = heads.nth(i); const label = (await h.innerText().catch(() => 'sectie ' + i)).trim().split('\n')[0]; await h.click().catch(() => {}); await sleep(2500); await record('host-' + slug(label), 'Hostmodus · ' + label);
    // Only this group's own pages (the tree keeps other groups open), all of them.
    const leaves = page.locator('.ps-host-group').nth(i).locator('.ps-host-leaf >> visible=true'); const ln = await leaves.count();
    for (let j = 0; j < Math.min(ln, 40); j++) { const l = leaves.nth(j); const t = (await l.innerText().catch(() => 'pagina ' + j)).trim(); if (screens.some((s) => s.stand === stand && s.id === 'host-' + slug(label) + '-' + slug(t))) continue; await l.click().catch(() => {}); await sleep(2500); await record('host-' + slug(label) + '-' + slug(t), `Hostmodus · ${label} · ${t}`); } }
  if (await click('.ps-host-tabs button:nth-child(2)', 1200)) { await record('host-servers', 'Hostmodus · serverlijst'); if (await click('.ps-host-server strong, .ps-host-server', 3000)) { await record('host-server', 'Hostmodus · server open (console in frame)'); await click('.ps-host-server-head .ps-info-more-button, .ps-info-more-button', 500) && await record('host-server-more', 'Hostmodus · server ⋯-menu'); await escape(); } }
  await alpine('d.setHostMode(false)'); await sleep(800);
  if (['d1440', 'tablet768', 'phone390'].includes(stand.split('-')[0])) await seasons(theme);
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
const slug = (s) => String(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 40) || 'x';

(async () => {
  browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: VIEWPORTS.d1440, baseURL: base, deviceScaleFactor: 1 });
  page = await context.newPage(); page.setDefaultTimeout(8000);
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
      if (section === 'seasons') { if (!['d1440', 'tablet768', 'phone390'].includes(name)) continue; try { await setTheme(theme); await seasons(theme); } catch (e) { problems.push(`${stand} seasons: ${e.message.slice(0, 200)}`); } continue; }
      try { await round(theme); } catch (e) { problems.push(`${stand} ${screen}: round stopped: ${e.message.slice(0, 200)}`); console.log('  ! ' + e.message.slice(0, 200)); if (page.isClosed()) { page = await context.newPage(); await login('demo@example.com', 'DemoPassword123!'); } }
    }
  }
  // The customer's view, two stands.
  const customer = await browser.newContext({ viewport: VIEWPORTS.d1440, baseURL: base });
  const cp = await customer.newPage(); const admin = page; page = cp; page.setDefaultTimeout(8000);
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
