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

// Everything measurable about the current document (also run inside frames).
const measure = () => {
  const root = document.documentElement, W = innerWidth, H = innerHeight;
  const r = { url: location.pathname + location.search, w: W, h: H, dark: root.classList.contains('dark'), fit: root.classList.contains('ps-fit'), problems: [], info: {} };
  const push = (kind, text) => r.problems.push({ kind, text });
  const name = (el) => (el.getAttribute('aria-label') || el.id || (typeof el.className === 'string' && el.className.trim().split(/\s+/).slice(0, 3).join('.')) || el.tagName.toLowerCase()).slice(0, 60);
  const text = (el) => (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 50);
  const visible = (el) => { if (!(el instanceof Element)) return false; const b = el.getBoundingClientRect(); if (b.width < 1 || b.height < 1) return false; const cs = getComputedStyle(el); if (cs.visibility === 'hidden' || cs.display === 'none' || cs.opacity === '0') return false; for (let a = el; a; a = a.parentElement) { const c = a === el ? cs : getComputedStyle(a); if (c.display === 'none' || c.visibility === 'hidden') return false; if (a.hasAttribute('inert') || a.getAttribute('aria-hidden') === 'true' || a.hidden) return false; } return true; };
  const scrollAncestor = (el, axis) => { for (let a = el.parentElement; a && a !== document.body; a = a.parentElement) { const cs = getComputedStyle(a); const o = axis === 'x' ? cs.overflowX : cs.overflowY; if (/(auto|scroll)/.test(o) && (axis === 'x' ? a.scrollWidth > a.clientWidth + 2 : a.scrollHeight > a.clientHeight + 2)) return a; } return null; };
  const all = [...document.querySelectorAll('body *')].filter(visible);
  // 1. The page itself.
  r.info.pageScrollH = root.scrollHeight; r.info.pageScrollW = root.scrollWidth;
  const bodyScroll = document.body.scrollHeight;
  if (root.scrollWidth > W + 2 || bodyScroll > H + 2 && root.scrollWidth > W + 2) push('hscroll', `page is wider than the window (${root.scrollWidth} > ${W})`);
  if (root.scrollHeight > H + 2) {
    const before = scrollY; scrollTo(0, 50); const moved = scrollY !== before; scrollTo(0, before);
    push(moved ? 'pagescroll' : 'tallnoscroll', `page is taller than the window (${root.scrollHeight} > ${H})${moved ? ', the page scrolls' : ', nothing scrolls'}`);
  }
  // 2. Text cut off: single-line text hidden without an ellipsis, and text shortened with an ellipsis (info).
  const cut = [], ell = [];
  for (const el of all) {
    const cs = getComputedStyle(el);
    if (el.matches('.sr-only, .fi-sr-only, script, style, svg, svg *')) continue;
    if (cs.overflowX === 'hidden' && el.scrollWidth > el.clientWidth + 2 && (el.textContent || '').trim() && !el.querySelector('svg, img, canvas')) {
      if (cs.textOverflow === 'ellipsis' && cs.whiteSpace === 'nowrap') ell.push(name(el) + ': "' + text(el) + '"');
      else if (cs.whiteSpace === 'nowrap' || el.childElementCount === 0) cut.push(name(el) + ': "' + text(el) + '" (' + el.scrollWidth + '>' + el.clientWidth + ')');
    }
  }
  if (cut.length) push('cut', 'text cut off without an ellipsis: ' + cut.slice(0, 6).join(' | ') + (cut.length > 6 ? ` (+${cut.length - 6})` : ''));
  if (ell.length) r.info.ellipsis = ell.slice(0, 12);
  // 3. Controls off the screen (not inside a pane that scrolls to them).
  const off = [];
  for (const el of all) {
    if (!el.matches('button, a, input, select, textarea, [role=button], [role=tab]')) continue;
    const b = el.getBoundingClientRect();
    if (b.width < 8 || b.height < 8) continue;
    const right = b.right > W + 2 && !scrollAncestor(el, 'x'), bottom = b.bottom > H + 2 && !scrollAncestor(el, 'y') && !(root.scrollHeight > H + 2), left = b.left < -2, top = b.top < -2;
    if ((right || bottom || left || top) && off.length < 8) off.push(`${name(el)} "${text(el)}" (${Math.round(b.left)},${Math.round(b.top)} ${Math.round(b.width)}x${Math.round(b.height)})`);
  }
  if (off.length) push('offscreen', 'control off the screen: ' + off.join(' | '));
  // 4. Popups, menus and dialogs must sit inside the window.
  for (const el of document.querySelectorAll('[role=dialog], [role=menu], .fi-dropdown-panel, .fi-modal-window, .ps-dropdown, .ps-modal, .ps-sheet, .ps-account-menu, .ps-nav-items, .fi-global-search-results-ctn, .ps-phone-more-menu')) {
    if (!visible(el)) continue;
    const b = el.getBoundingClientRect();
    const tall = el.scrollHeight > el.clientHeight + 2;
    r.info.popups = r.info.popups || []; r.info.popups.push(`${name(el)} ${Math.round(b.left)},${Math.round(b.top)} ${Math.round(b.width)}x${Math.round(b.height)}${tall ? ' scrolls' : ''}`);
    if (b.left < -2 || b.top < -2 || b.right > W + 2 || b.bottom > H + 2) push('popupoff', `popup partly off the screen: ${name(el)} (${Math.round(b.left)},${Math.round(b.top)} ${Math.round(b.width)}x${Math.round(b.height)} in ${W}x${H})`);
  }
  // 5. Children sticking out of a box that clips (overflow hidden/clip): the notifications list, the account menu buttons.
  const stick = []; const reported = new Set();
  for (const el of all) {
    const cs = getComputedStyle(el);
    if (!/(hidden|clip)/.test(cs.overflowX) && !/(hidden|clip)/.test(cs.overflowY)) continue;
    if (el.matches('svg, svg *, .xterm, .xterm *, canvas, [aria-hidden=true], .ps-spark, .ps-account-menu, .ps-dropdown, .fi-dropdown-panel, [x-transition], .ps-motd-preview, .ps-marquee, .ps-ticker, .ps-ticker *, .ps-bar-text, .ps-announcement-bar *')) continue;
    if ([...reported].some((p) => p.contains(el))) continue;
    const b = el.getBoundingClientRect();
    if (b.width < 20 || b.height < 20) continue;
    for (const c of el.querySelectorAll('button, a, input, select, textarea, img, li, p, h1, h2, h3, strong, span, div')) {
      if (!visible(c) || c.matches('svg *') ) continue;
      const cb = c.getBoundingClientRect();
      if (cb.width < 6 || cb.height < 6) continue;
      const dx = Math.max(cb.right - b.right, b.left - cb.left), dy = Math.max(cb.bottom - b.bottom, b.top - cb.top);
      const clipX = /(hidden|clip)/.test(cs.overflowX) && dx > 4, clipY = /(hidden|clip)/.test(cs.overflowY) && dy > 4;
      if ((clipX || clipY) && !c.matches('.sr-only, .fi-sr-only, [x-cloak]') && getComputedStyle(c).position !== 'absolute' && getComputedStyle(c).position !== 'fixed') {
        if (el.scrollHeight <= el.clientHeight + 2 && el.scrollWidth <= el.clientWidth + 2 && !clipX && cb.top >= b.top) continue;
        stick.push(`${name(c)} "${text(c)}" sticks ${Math.round(Math.max(dx, dy))}px out of ${name(el)}`); reported.add(el); break;
      }
    }
    if (stick.length >= 8) break;
  }
  if (stick.length) push('clipped', 'content clipped by its box: ' + stick.join(' | '));
  // 6. Panes that scroll inside the page (info), and the content card on Simplify pages.
  const panes = all.filter((el) => { const cs = getComputedStyle(el); return /(auto|scroll)/.test(cs.overflowY) && el.scrollHeight > el.clientHeight + 4 && !el.matches('.xterm-viewport'); }).map((el) => `${name(el)} ${el.clientHeight}/${el.scrollHeight}`);
  if (panes.length) r.info.panes = panes.slice(0, 10);
  const main = document.querySelector('.fi-main-ctn > .fi-main');
  if (main && visible(main)) { const b = main.getBoundingClientRect(); r.info.card = `${Math.round(b.top)}..${Math.round(b.bottom)} of ${H}` + (r.fit ? ' fit=' + root.style.getPropertyValue('--ps-fit') : ''); if (r.fit && b.bottom > H + 2) push('cardoff', `content card ends below the window (${Math.round(b.bottom)} > ${H})`); }
  // 7. The console: output area, its card and the command bar must share one colour; the last row must sit above the command bar.
  const term = document.getElementById('terminal');
  if (term && visible(term)) {
    const card = term.parentElement, bar = card.querySelector(':scope > div:has(#send-command), form:has(#send-command)');
    const bg = (el) => el && getComputedStyle(el).backgroundColor;
    r.info.console = { card: bg(card), terminal: bg(term), xterm: bg(term.querySelector('.xterm')), viewport: bg(term.querySelector('.xterm-viewport')), bar: bg(bar) };
    const rows = term.querySelector('.xterm-rows'), screenEl = term.querySelector('.xterm-screen');
    if (screenEl) { const sb = screenEl.getBoundingClientRect(), tb = term.getBoundingClientRect(), barB = bar?.getBoundingClientRect();
      r.info.consoleBox = `screen ${Math.round(sb.left)},${Math.round(sb.top)} ${Math.round(sb.width)}x${Math.round(sb.height)}; terminal ${Math.round(tb.left)},${Math.round(tb.top)} ${Math.round(tb.width)}x${Math.round(tb.height)}` + (barB ? `; bar top ${Math.round(barB.top)}` : '');
      if (sb.right > tb.right + 1 || sb.bottom > tb.bottom + 1) push('consoleover', `console rows run out of the output box (screen bottom ${Math.round(sb.bottom)} / right ${Math.round(sb.right)}, box bottom ${Math.round(tb.bottom)} / right ${Math.round(tb.right)})`);
      if (barB && sb.bottom > barB.top + 1) push('consoleover', `last console row sits behind the command bar (${Math.round(sb.bottom)} > ${Math.round(barB.top)})`);
    }
    const colours = new Set([r.info.console.card, r.info.console.bar].filter((c) => c && c !== 'rgba(0, 0, 0, 0)'));
    if (colours.size > 1) push('colour', `console card and command bar differ in colour: ${[...colours].join(' vs ')}`);
  }
  // 8. Elements wider than the window (any), as a hint where sideways overflow comes from.
  if (root.scrollWidth > W + 2) { const wide = all.filter((el) => el.getBoundingClientRect().right > W + 2 && !scrollAncestor(el, 'x')).slice(0, 5).map((el) => name(el)); if (wide.length) r.info.wide = wide; }
  return r;
};

const settle = async (p, ms = 600) => { await p.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {}); await sleep(ms); };
const alpine = (body) => page.evaluate((b) => { const el = document.querySelector('.ps'); return el && window.Alpine ? new Function('d', b)(window.Alpine.$data(el)) : null; }, body).catch(() => null);
const setTheme = async (theme) => page.evaluate((t) => { localStorage.setItem('pelicanSimplify.theme', t); localStorage.setItem('theme', t); document.documentElement.classList.toggle('dark', t === 'dark'); }, theme);

// Records the current state of the page (and its visible frames) under the given screen id.
async function record(id, title, extra = {}) {
  screen = id;
  const dir = path.join(out, stand); fs.mkdirSync(dir, { recursive: true });
  const entry = { id, title, stand, url: rel(page.url()), ...extra };
  try {
    entry.main = await page.evaluate(measure);
    entry.frames = [];
    for (const f of page.frames()) {
      if (f === page.mainFrame()) continue;
      const handle = await f.frameElement().catch(() => null);
      if (!handle) continue;
      const box = await handle.boundingBox().catch(() => null);
      if (!box || box.width < 50) continue;
      const m = await f.evaluate(measure).catch((e) => ({ error: e.message }));
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

  // Overview and its menus.
  await goto(overview); await setTheme(theme); await goto(overview); await ready(); await alpine('d.setHostMode(false)'); await sleep(500);
  for (const view of ['grid', 'compact', 'list']) { await alpine(`d.setOverviewView('${view}')`); await sleep(700); await record('overview-' + view, 'Overzicht, ' + view); }
  await alpine(`d.setOverviewView('grid')`); await sleep(500);
  if (onPhone) { await click('.ps-mobile-menu'); await record('overview-phone-menu', 'Overzicht, telefoonmenu open'); }
  await click('.ps-account-button'); await record('overview-account', 'Accountmenu open');
  await click('#ps-account-actions button[aria-label]:nth-child(2)'); await record('overview-account-theme', 'Accountmenu, themakeuze open'); await escape();
  if (onPhone) await click('.ps-mobile-menu');
  await click('[x-ref="notifyButton"]'); await record('overview-bell', 'Bel (aankondigingen) open'); await escape();
  if (onPhone) await click('.ps-mobile-menu');
  await click('[x-ref="adminButton"]'); await record('overview-admin', 'Beheermenu (schild) open'); await escape();
  if (onPhone) await click('.ps-mobile-menu');
  await click('[x-ref="overviewAppearance"]'); await record('overview-customize', 'Weergave aanpassen open'); await escape();
  if (onPhone) await click('.ps-mobile-menu');
  await click('[x-ref="overviewGroupsButton"]'); await record('overview-groups', 'Servergroepen open'); await escape();
  if (await click('.ps-health-pill')) await record('overview-health', 'Gezondheid van de host open'); await escape();
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
  if (onPhone) { if (await click('.ps-host-menu-open, button[aria-controls="ps-host-menu"], .ps-mobile-menu', 600)) await record('host-phone-menu', 'Hostmodus · telefoonmenu open'); await escape(); }
  const heads = page.locator('.ps-host-group-head'); const hn = await heads.count();
  for (let i = 0; i < hn; i++) { const h = heads.nth(i); const label = (await h.innerText().catch(() => 'sectie ' + i)).trim().split('\n')[0]; await h.click().catch(() => {}); await sleep(2500); await record('host-' + slug(label), 'Hostmodus · ' + label);
    const leaves = page.locator('.ps-host-leaf >> visible=true'); const ln = await leaves.count();
    for (let j = 0; j < Math.min(ln, 14); j++) { const l = leaves.nth(j); const t = (await l.innerText().catch(() => 'pagina ' + j)).trim(); if (screens.some((s) => s.stand === stand && s.id === 'host-' + slug(label) + '-' + slug(t))) continue; await l.click().catch(() => {}); await sleep(2500); await record('host-' + slug(label) + '-' + slug(t), `Hostmodus · ${label} · ${t}`); } }
  if (await click('.ps-host-tabs button:nth-child(2)', 1200)) { await record('host-servers', 'Hostmodus · serverlijst'); if (await click('.ps-host-server strong, .ps-host-server', 3000)) { await record('host-server', 'Hostmodus · server open (console in frame)'); await click('.ps-host-server-head .ps-info-more-button, .ps-info-more-button', 500) && await record('host-server-more', 'Hostmodus · server ⋯-menu'); await escape(); } }
  await alpine('d.setHostMode(false)'); await sleep(800);
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
