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
const themeOnly = arg('theme', ''); // 'dark' or 'light': one theme per job, so the two run side by side
// The parts of a round, so a run after a fix or for a small change does only the screens it can touch (parts.py says which).
const PARTS = ['overview', 'server', 'admin', 'host', 'seasons', 'create', 'extras', 'notify', 'klant'];
const parts = arg('parts', '') ? arg('parts').split(',').filter(Boolean) : null;
const want = (part) => !parts || parts.includes(part);
for (const part of parts || []) if (!PARTS.includes(part)) { console.error(`unknown part ${part}; parts are ${PARTS.join(', ')}`); process.exit(2); }
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
// Waits (up to 10 s) for Alpine on Simplify's root: a slow page load must not skip a step or swallow a click.
const ready = () => page.waitForFunction(() => { const el = document.querySelector('.ps'); return !!(el && window.Alpine && el._x_dataStack); }, null, { timeout: 10000 }).catch(() => null);
const alpine = async (body) => { await ready(); return page.evaluate((b) => { const el = document.querySelector('.ps'); return el && window.Alpine ? new Function('d', b)(window.Alpine.$data(el)) : null; }, body).catch(() => null) };
// The theme is set before any page script runs (both Filament's and Simplify's stored choice) and the browser's own
// colour scheme follows it, so every page of the stand opens in that theme.
let theme_ = null;
const setTheme = async (theme) => { theme_ = theme; await page.context().addInitScript((t) => { try { localStorage.setItem('pelicanSimplify.theme', t); localStorage.setItem('theme', t); } catch {} document.documentElement?.classList.toggle('dark', t === 'dark'); }, theme); await page.emulateMedia({ colorScheme: theme }); await page.evaluate((t) => { localStorage.setItem('pelicanSimplify.theme', t); localStorage.setItem('theme', t); window.SimplifyTheme?.set(t); document.documentElement.classList.toggle('dark', t === 'dark'); }, theme); };

// Simplify's own admin pages (by their path), so the measurement knows which admin pages must follow Simplify's style.
const mopts = () => ({ simplifyAdmin: (urls.admin || []).filter((p) => /^Simplify\\/.test(p.class)).map((p) => rel(p.url)) });
// Records the current state of the page (and its visible frames) under the given screen id.
async function record(id, title, extra = {}) {
  screen = id;
  const dir = path.join(out, stand); fs.mkdirSync(dir, { recursive: true });
  const entry = { id, title, stand, url: rel(page.url()), docTitle: await page.title().catch(() => null), ...extra };
  try {
    entry.main = await page.evaluate(measure, mopts());
    // K-B9: on the Admin and account pages Pelican's copyright line is the last thing in the card, under the whole page,
    // never halfway over it where the card's first screen ended (every screen, so every settings tab, phone and computer).
    const foot = await page.evaluate(() => {
      const card = document.querySelector('html.ps-shell:not(.ps-frame) .fi-layout > .fi-main-ctn'), main = card?.querySelector(':scope > .fi-main'), footer = card?.querySelector(':scope > footer, :scope > [data-ps-footer]');
      if (!main || !footer || !footer.getClientRects().length) return null;
      // The page itself holds everything on it (what scrolls inside its own box, a popup or Monaco's helpers aside).
      const top = card.getBoundingClientRect().top - card.scrollTop, f = footer.getBoundingClientRect();
      return { footerTop: Math.round(f.top - top), footerBottom: Math.round(f.bottom - top), page: Math.round(main.getBoundingClientRect().bottom - top), overflow: main.scrollHeight - main.clientHeight, scroll: card.scrollHeight };
    }).catch(() => null);
    // Every screen in a dark folder is dark and every screen in a light folder light (a partial run once shot its dark folder light).
    const dark = await page.evaluate(() => document.documentElement.classList.contains('dark')).catch(() => null);
    if (theme_ && dark !== null && dark !== (theme_ === 'dark')) problems.push(`${stand} ${id} theme: WRONG the page is ${dark ? 'dark' : 'light'} in a ${theme_} round`);
    if (foot && (foot.overflow > 2 || foot.footerTop < foot.page - 1 || foot.footerBottom < foot.scroll - 2)) problems.push(`${stand} ${id} footer-end: WRONG the copyright line is not at the end of the page ${JSON.stringify(foot)}`);
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
// The Admin page (V52): the shield opens Pelican's admin pages inside Simplify without a frame: Simplify's header, the
// Admin menu on the left (on a phone a page of its own that the bottom bar's Admin opens) and the page beside it. The menu stays
// while its links change the page (wire:navigate). The legacy panel shows the same pages as Pelican draws them.
const adminState = () => page.evaluate(() => {
  const side = document.querySelector('.ps-admin-side'), card = document.querySelector('.fi-layout > .fi-main-ctn');
  const shown = (s) => [...document.querySelectorAll(s)].some((e) => e.getClientRects().length && getComputedStyle(e).display !== 'none' && getComputedStyle(e).visibility !== 'hidden');
  return { shell: document.documentElement.classList.contains('ps-shell-menu'), side: !!side, kept: !!side && side.dataset.auditMark === '1', /* a page in a frame; the embed's own preview is content */ frames: document.querySelectorAll('.fi-main-ctn iframe[data-ps-frame]:not(.ps-create-frame)').length,
    header: shown('.ps-admin-top .ps-top'), pelicanBars: shown('.fi-sidebar, .fi-topbar'), pageScroll: document.documentElement.scrollHeight - innerHeight, card: card ? getComputedStyle(card).overflowY : null, path: location.pathname,
    // Computers: the page's own buttons sit on the breadcrumb's row (its title bar), not on a row of their own below it.
    offRow: (() => { const crumb = document.querySelector('.ps-admin-crumb'); if (!crumb || innerWidth <= 600 || !crumb.getClientRects().length) return 0; const r = crumb.getBoundingClientRect(); return [...document.querySelectorAll('.fi-main .fi-header .fi-header-actions-ctn :is(button, a)')].filter((e) => e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden').filter((e) => { const b = e.getBoundingClientRect(), y = b.top + b.height / 2; return y < r.top || y > r.bottom; }).length; })(),
    // The page's box ends inside the window (its content scrolls in it); when not, where its height comes from.
    tall: (() => { const ctn = document.querySelector('.fi-layout > .fi-main-ctn'), lay = document.querySelector('.fi-layout'); if (!ctn || innerWidth <= 600 || ctn.getBoundingClientRect().bottom <= innerHeight + 2) return null; const cs = getComputedStyle(ctn), ls = getComputedStyle(lay), r = (e) => Math.round(e.getBoundingClientRect().top) + '..' + Math.round(e.getBoundingClientRect().bottom); return { ctn: r(ctn) + ' h ' + cs.height + ' max ' + cs.maxHeight + ' row ' + cs.gridRow + ' as ' + cs.alignSelf, layout: r(lay) + ' h ' + ls.height + ' rows ' + ls.gridTemplateRows + ' ov ' + ls.overflow + ' ' + ls.display, kids: [...lay.children].map((k) => (k.getAttribute('x-persist') || k.className.split(' ')[0]) + ' ' + r(k) + ' ' + getComputedStyle(k).gridRow + ' ' + getComputedStyle(k).display), body: r(document.body) + ' ' + getComputedStyle(document.body).height }; })(),
    // Nothing of the page starts under the breadcrumb's row.
    underCrumb: (() => { const crumb = document.querySelector('.ps-admin-crumb'), body = document.querySelector('.fi-main .fi-page-main'), ctn = document.querySelector('.fi-layout > .fi-main-ctn'); if (!crumb || !body || innerWidth <= 600 || !ctn || ctn.scrollTop > 0) return false; if (body.getBoundingClientRect().top >= crumb.getBoundingClientRect().bottom - 1) return false; const main = document.querySelector('.fi-main'), head = document.querySelector('.fi-main .fi-header'), r = (e) => e ? Math.round(e.getBoundingClientRect().top) + '..' + Math.round(e.getBoundingClientRect().bottom) : null; return { crumb: r(crumb), ctn: r(ctn), main: r(main), pad: main && getComputedStyle(main).paddingTop, head: head && getComputedStyle(head).display + ' ' + r(head) + ' mt ' + getComputedStyle(head).marginTop, body: r(body), parents: [...(function* (e) { while (e && e !== main) { yield e.className.split(' ')[0] + ' ' + r(e) + ' ' + getComputedStyle(e).display + ' pt ' + getComputedStyle(e).paddingTop + ' mt ' + getComputedStyle(e).marginTop; e = e.parentElement; } })(body)], doc: document.scrollingElement.scrollTop }; })() };
}).catch(() => null);
const markAdminSide = () => page.evaluate(() => { const side = document.querySelector('.ps-admin-side'); if (side) side.dataset.auditMark = '1'; }).catch(() => {});
const openAdminMenu = async () => { if (phone() && !(await page.locator('.ps-admin-side.menu-open').count())) { await page.locator('.ps-admin-side .ps-tabbar button[aria-controls="ps-admin-side"]').first().click().catch(() => {}); await sleep(700); } };
const shieldTo = async (overview) => {
  await goto(overview, 800);
  const shield = page.locator((phone() ? '.ps-tabbar a.ps-tab:has(use[href="#ps-shield"])' : 'a.ps-nav-admin') + ' >> visible=true').first();
  // A narrow window (tablet) keeps the top bar's buttons behind its menu button.
  if (!(await shield.count()) && await page.locator('.ps-top .ps-mobile-menu >> visible=true').count()) { await page.locator('.ps-top .ps-mobile-menu >> visible=true').first().click().catch(() => {}); await sleep(500); }
  if (!(await shield.count())) return false;
  // The switch to Admin changes only the content (wire:navigate), as in an app: no whole page, the top bar's buttons stay.
  switched = { before: await topButtons() };
  await page.evaluate(() => { window.__psSwitch = 1; }).catch(() => {});
  await shield.click().catch(() => {}); await page.waitForURL(/\/admin\//, { timeout: 15000 }).catch(() => {}); await page.waitForLoadState('domcontentloaded').catch(() => {}); await settle(page, 1500);
  switched.reloaded = await page.evaluate(() => window.__psSwitch !== 1).catch(() => true);
  switched.after = await topButtons();
  // Healthy keeps its state on the Admin page (asked there too), instead of a grey "Health".
  switched.health = await page.waitForFunction(() => { const p = [...document.querySelectorAll('.ps-top .ps-health-pill')].find((e) => e.getClientRects().length); return !p || !p.classList.contains('unavailable') ? (p ? p.className : 'none') : false; }, null, { timeout: 8000 }).then((h) => h.jsonValue()).catch(() => 'unavailable');
  return true;
};
let switched = null;
// Back from Admin to the overview: the shield on a computer, Servers in the bottom bar on a phone.
const shieldBack = async () => {
  const back = page.locator((phone() ? '.ps-admin-side .ps-tabbar a.ps-tab' : '.ps-admin-top a.ps-nav-admin') + ' >> visible=true').first();
  if (!(await back.count()) && await page.locator('.ps-top .ps-mobile-menu >> visible=true').count()) { await page.locator('.ps-top .ps-mobile-menu >> visible=true').first().click().catch(() => {}); await sleep(500); }
  await back.click().catch(() => {}); await page.waitForURL((u) => !/\/admin\//.test(u.pathname), { timeout: 15000 }).catch(() => {}); await settle(page, 1500);
};
// Where the top bar's buttons are (the bottom bar's on a phone): the same place on the overview and on Admin.
const topButtons = () => page.evaluate(() => [...document.querySelectorAll('.ps-top :is(.ps-health-pill, .ps-nav-admin, .ps-notify-button, .ps-account-button), .ps-tabbar .ps-tab')]
  .filter((e) => e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden')
  .map((e) => { const b = e.getBoundingClientRect(); return `${(e.className.baseVal ?? e.className).split(' ').find((c) => /^ps-(health-pill|nav-admin|notify-button|account-button|tab)$/.test(c))}@${Math.round(b.left)},${Math.round(b.top)}`; })).catch(() => []);
const adminPage = async (overview) => {
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  if (!(await shieldTo(overview))) return say('admin-page', false, { note: 'no shield link on the overview' });
  // Overview → Admin and back like an app (Jeroen 3-11): only the content changes, the top bar's buttons (Health too, on
  // a phone) stay where they are; the bottom bar keeps its tabs.
  const there = switched;
  await shieldBack();
  const back = { reloaded: await page.evaluate(() => window.__psSwitch !== 1).catch(() => true), path: await page.evaluate(() => location.pathname).catch(() => null), after: await topButtons() };
  // A narrow window keeps the top bar's buttons behind its menu button: there only the switch itself counts.
  const folded = (await page.locator('.ps-top .ps-mobile-menu >> visible=true').count()) > 0;
  const same = (a, b) => folded || !!a && !!b && a.filter((x) => !/^ps-tab@/.test(x)).join() === b.filter((x) => !/^ps-tab@/.test(x)).join();
  say('admin-switch', !!there && !there.reloaded && there.health !== 'unavailable' && same(there.before, there.after) && (folded || there.before.some((x) => x.startsWith('ps-health-pill'))) && !back.reloaded && !/\/admin\//.test(back.path || '') && same(there.after, back.after), { there, back });
  if (!(await shieldTo(overview))) return say('admin-page', false, { note: 'no shield link on the overview' });
  const st = await adminState(); await record('admin-page', 'Admin-pagina (schild)');
  say('admin-page', !!st && st.shell && st.side && st.header && !st.frames && !st.pelicanBars && st.pageScroll <= 1 && (phone() || /auto|scroll/.test(st.card || '')), st);
  // A click in the Admin menu changes only the page: the same page looks the same after Servers → Eggs → Servers
  // (wire:navigate) as after a full load: card, menu, title and footer (Jeroen 3-11, video).
  if (!phone()) {
    const look = () => page.evaluate(() => {
      const css = (sel, props) => { const e = [...document.querySelectorAll(sel)].find((x) => x.getClientRects().length); if (!e) return null; const s = getComputedStyle(e); return props.map((p) => s.getPropertyValue(p)).join(' | '); };
      return {
        card: css('.fi-layout > .fi-main-ctn', ['background-color', 'border-top-width', 'border-top-color', 'padding-top']),
        main: css('.fi-layout > .fi-main-ctn > .fi-main', ['padding-top', 'padding-left']),
        leaf: css('.ps-admin-side .ps-host-leaf', ['font-family', 'font-size', 'font-weight', 'letter-spacing']),
        head: css('.ps-admin-side .ps-host-group-head, .ps-admin-side .ps-host-group > button', ['font-family', 'font-size', 'font-weight']),
        title: css('.fi-main .fi-header-heading', ['font-family', 'font-size', 'font-weight']),
        body: css('body', ['font-family', 'background-color']),
        busy: document.documentElement.classList.contains('nprogress-busy'),
        footer: (document.querySelector('.fi-main-ctn [data-ps-footer], .fi-main-ctn footer')?.innerText || '').replace(/\s+/g, ' ').trim(),
        sheets: [...document.head.querySelectorAll('link[rel="stylesheet"], style')].map((e) => (e.getAttribute('href') || 'style:' + e.textContent.length).replace(/^https?:\/\/[^/]+/, '').replace(/\?.*$/, '')),
      };
    }).catch(() => null);
    const leaf = (re) => page.locator('.ps-admin-side a.ps-host-leaf >> visible=true').filter({ hasText: re }).first();
    await goto(new URL('/admin/servers', base).href, 2000);
    const first = await look();
    let loads = 0; const onLoad = () => loads++; page.on('load', onLoad);
    if (!(await leaf(/^\s*Eggs\s*$/).count())) { await page.locator('.ps-admin-side .ps-host-group > button >> visible=true').filter({ hasText: /Servers/ }).first().click().catch(() => {}); await sleep(400); }
    // Measure only once wire:navigate is done: while it runs (html.nprogress-busy) the page is still changing.
    const arrived = (re) => page.waitForFunction((src) => new RegExp(src).test(location.pathname) && !document.documentElement.classList.contains('nprogress-busy'), re.source, { timeout: 10000 }).catch(() => {});
    await leaf(/^\s*Eggs\s*$/).click({ timeout: 4000 }).catch(() => {}); await arrived(/\/admin\/eggs$/); await settle(page, 1500);
    await leaf(/^\s*Servers\s*$/).click({ timeout: 4000 }).catch(() => {}); await arrived(/\/admin\/servers$/); await settle(page, 1500);
    page.off('load', onLoad);
    const again = await look(); await record('admin-nav-look', 'Admin-pagina · zelfde stijl na menuklikken');
    const diff = first && again ? Object.keys(first).filter((k) => JSON.stringify(first[k]) !== JSON.stringify(again[k])) : ['missing'];
    say('admin-nav-look', !!first && !!first.card && diff.length === 0 && loads === 0 && /\/admin\/servers/.test(page.url()), { diff, loads, first, again });
  }
  // The other way out of an admin page: the end of the setup's "Go to the panel" opens the overview with wire:navigate.
  // It looks as after a full load (Jeroen 3-11, beta.3: the background turned lighter grey), and so does the Admin page
  // the shield opens from there.
  if (!phone()) {
    const pageLook = () => page.evaluate(() => {
      const css = (el, props) => { if (!el) return null; const s = getComputedStyle(el); return props.map((p) => s.getPropertyValue(p).trim()).join(' | '); };
      const root = document.documentElement;
      return {
        html: [...root.classList].filter((c) => !/^nprogress/.test(c)).sort().join(' '),
        busy: root.classList.contains('nprogress-busy'),
        tokens: css(root, ['--gray-950', '--gray-900', '--ps-bg', '--ps-surface', '--s-bg', '--s-surface']),
        body: css(document.body, ['background-color', 'color']),
        layout: css(document.querySelector('.fi-layout'), ['background-color']),
        top: css([...document.querySelectorAll('.ps-top')].find((e) => e.getClientRects().length), ['background-color']),
        sheets: [...document.head.querySelectorAll('link[rel="stylesheet"], style')].map((e) => (e.getAttribute('href') || 'style:' + e.textContent.length).replace(/^https?:\/\/[^/]+/, '').replace(/\?.*$/, '')),
      };
    }).catch(() => null);
    const same = (a, b, keys) => (a && b ? keys.filter((k) => JSON.stringify(a[k]) !== JSON.stringify(b[k])) : ['missing']);
    const keys = ['html', 'busy', 'tokens', 'body', 'layout', 'top'];
    const arrivedAt = (test) => page.waitForFunction((src) => new RegExp(src).test(location.pathname) && !document.documentElement.classList.contains('nprogress-busy'), test.source, { timeout: 10000 }).catch(() => {});
    const overviewPath = new URL(overview, base).pathname;
    await goto(new URL(overview, base).href, 2000);
    const fullOverview = await pageLook();
    await goto(new URL('/admin/simplify/setup-done', base).href, 2000);
    const fullAdmin = await pageLook();
    const go = page.locator('.fi-header a >> visible=true').filter({ hasText: /Go to the panel/ }).first();
    const found = (await go.count()) > 0;
    await go.click({ timeout: 4000 }).catch(() => {}); await arrivedAt(new RegExp('^' + overviewPath.replace(/\/$/, '') + '/?$')); await settle(page, 1500);
    const navOverview = await pageLook(); await record('setup-done-to-overview', 'Setup klaar › Go to the panel: overzicht');
    const d1 = same(fullOverview, navOverview, keys);
    say('setup-done-to-overview', found && d1.length === 0, { found, diff: d1, full: fullOverview, after: navOverview });
    const shield = page.locator('a.ps-nav-admin >> visible=true').first();
    await shield.click({ timeout: 4000 }).catch(() => {}); await arrivedAt(/^\/admin/); await settle(page, 1500);
    const navAdmin = await pageLook();
    const d2 = same(fullAdmin, navAdmin, keys);
    say('overview-to-admin-look', d2.length === 0, { diff: d2, full: fullAdmin, after: navAdmin, path: new URL(page.url()).pathname });
    // The overview and the Admin page share one background, so going between them changes no colour (K-B28).
    const bg = (look) => look?.body?.split(' | ')[0] || null;
    say('admin-overview-bg', !!bg(fullOverview) && bg(fullOverview) === bg(fullAdmin) && bg(fullAdmin) === bg(navAdmin), { overview: bg(fullOverview), admin: bg(fullAdmin), adminAfterShield: bg(navAdmin) });
    await shieldTo(overview);
  }
  // Computers (Jeroen 3-11): the Admin page belongs to the overview instead of feeling like a separate site. The same
  // header (Healthy, Create server), the menu and the page together as wide as it, both without a card and without a
  // Servers tab; the breadcrumb (All servers › …) is the title, the page's buttons sit on its row, and every crumb and
  // button is itself under its middle. The shield and All servers lead back to the overview, whose header is as wide.
  if (!phone()) {
    const desk = await page.evaluate(() => {
      const shown = (e) => !!e && e.getClientRects().length > 0 && getComputedStyle(e).display !== 'none' && getComputedStyle(e).visibility !== 'hidden';
      const box = (e) => { const cs = e && getComputedStyle(e); return !!cs && (cs.borderTopWidth !== '0px' || cs.boxShadow !== 'none' || !/rgba\(0, 0, 0, 0\)|transparent/.test(cs.backgroundColor)); };
      const top = document.querySelector('.ps-admin-top .ps-top'), side = document.querySelector('.ps-admin-side .ps-host-side'), card = document.querySelector('.fi-layout > .fi-main-ctn'), crumb = document.querySelector('.ps-admin-crumb');
      if (!top || !side || !card) return null;
      const t = top.getBoundingClientRect(), s = side.getBoundingClientRect(), c = card.getBoundingClientRect(), r = shown(crumb) ? crumb.getBoundingClientRect() : null;
      const hit = (e) => { const b = e.getBoundingClientRect(), at = document.elementFromPoint(b.left + b.width / 2, b.top + b.height / 2); return !!at && (at === e || e.contains(at)); };
      const actions = [...document.querySelectorAll('.fi-main .fi-header .fi-header-actions-ctn :is(button, a)')].filter(shown);
      const parts = crumb ? [...crumb.querySelectorAll('a, button')].filter(shown) : [];
      return { top: [Math.round(t.left), Math.round(t.right)], menu: Math.round(s.left), page: Math.round(c.right), crumb: r ? crumb.innerText.replace(/\s+/g, ' ').trim() : null,
        health: shown(top.querySelector('.ps-health-pill')), create: [...top.querySelectorAll('.ps-create-button')].some(shown), tabs: !!document.querySelector('.ps-admin-side .ps-host-tabs'),
        menuBox: box(side), pageBox: box(card), heading: [...document.querySelectorAll('.fi-main .fi-header-heading')].some(shown),
        onRow: !r || actions.every((a) => { const b = a.getBoundingClientRect(); return b.top + b.height / 2 >= r.top && b.top + b.height / 2 <= r.bottom; }), actions: actions.length,
        blocked: [...parts, ...actions].filter((e) => !hit(e)).map((e) => e.innerText.trim().slice(0, 30)), shield: top.querySelector('.ps-nav-admin')?.getAttribute('aria-current') };
    }).catch(() => null);
    say('admin-desktop', !!desk && Math.abs(desk.top[0] - desk.menu) <= 2 && Math.abs(desk.top[1] - desk.page) <= 2 && desk.health && desk.create && !desk.tabs && !desk.menuBox && !desk.pageBox
      && !!desk.crumb && /^All servers/.test(desk.crumb) && !desk.heading && desk.onRow && !desk.blocked.length && desk.shield === 'page', desk);
    await page.locator('.ps-admin-crumb a.ps-admin-crumb-home').first().click().catch(() => {}); await page.waitForLoadState('domcontentloaded').catch(() => {}); await settle(page, 1200);
    const back = await page.evaluate(() => { const t = document.querySelector('.ps-top')?.getBoundingClientRect(); return { path: location.pathname, shell: document.documentElement.classList.contains('ps-shell'), top: t ? [Math.round(t.left), Math.round(t.right)] : null }; }).catch(() => null);
    say('admin-desktop-back', !!back && !!desk && !back.shell && !!back.top && Math.abs(back.top[0] - desk.top[0]) <= 2 && Math.abs(back.top[1] - desk.top[1]) <= 2, { back, admin: desk?.top });
    if (!(await shieldTo(overview))) return say('admin-page', false, { note: 'no shield link on the overview' });
  }
  // The legacy panel: the same page as Pelican draws it, and its Back to Simplify button clears that again.
  if (!(await page.locator('.ps-admin-top .ps-account-button >> visible=true').count())) { await page.locator('.ps-admin-top .ps-mobile-menu >> visible=true').first().click().catch(() => {}); await sleep(500); }
  let accountError = null;
  await page.locator('.ps-admin-top .ps-account-button >> visible=true').first().click({ timeout: 5000 }).catch((e) => { accountError = e.message.split('\n').slice(0, 3).join(' ').slice(0, 300); }); await sleep(700);
  const legacy = page.locator('#ps-account-actions a[href*="simplify_legacy=1"] >> visible=true').first();
  if (await legacy.count()) {
    await legacy.click().catch(() => {}); await page.waitForLoadState('domcontentloaded').catch(() => {}); await settle(page, 1500);
    const lg = await adminState(); await record('admin-legacy', 'Legacy-paneel (Pelicans eigen pagina)');
    say('admin-legacy', !!lg && !lg.shell && !lg.side && !lg.header && !/simplify_legacy/.test(page.url()), lg);
    // The way back is the first item of Pelican's sidebar; on a small window that sidebar opens from its button first.
    // (A closed off-canvas sidebar still counts as visible to Playwright: look whether the link is on the screen.)
    const onScreen = () => page.evaluate(() => [...document.querySelectorAll('a[href*="simplify_legacy=0"]')].some((a) => { const b = a.getBoundingClientRect(); return b.width > 0 && b.left >= 0 && b.right <= innerWidth && b.top >= 0 && b.bottom <= innerHeight; })).catch(() => false);
    if (!(await onScreen())) {
      const opener = page.locator('.fi-topbar-open-sidebar-btn, .fi-layout-sidebar-toggle-btn-ctn button, button[x-on\\:click*="sidebar.open"] >> visible=true').first();
      if (await opener.count()) await opener.click().catch(() => {}); else await page.evaluate(() => window.Alpine?.store('sidebar')?.open?.()).catch(() => {});
      await sleep(1200); await record('admin-legacy-sidebar', 'Legacy-paneel, zijbalk open');
    }
    const back = page.locator('a[href*="simplify_legacy=0"] >> visible=true').first();
    if (await back.count()) {
      let clickError = null;
      await Promise.all([page.waitForNavigation({ timeout: 10000 }).catch(() => {}), back.click({ timeout: 5000 }).catch((e) => { clickError = e.message.slice(0, 120); })]); await settle(page, 1200);
      await goto('/admin/health', 1500); const again = await adminState();
      say('admin-legacy-back', !!again && again.shell && again.side, { ...again, clickError });
    } else say('admin-legacy-back', false, { note: 'no Back to Simplify in the legacy panel' });
    // The legacy panel is kept for the session, but Simplify's own way in (the shield, Admin in the bottom bar) always
    // opens Simplify's Admin page, never the legacy panel (Jeroen 3-11: "Admin goes to legacy again").
    await goto('/admin/health?simplify_legacy=1', 1200);
    if (await shieldTo(overview)) {
      const via = await adminState();
      say('admin-legacy-shield', !!via && via.shell && via.side, { ...via, url: page.url() });
    } else say('admin-legacy-shield', false, { note: 'no shield link on the overview' });
  } else {
    // What the account button and its menu were doing, and a picture of it, so the cause is in the result.
    const why = await page.evaluate(() => {
      const button = [...document.querySelectorAll('.ps-admin-top .ps-account-button')].find((b) => b.getClientRects().length), account = button?.closest('.ps-account'), menu = account?.querySelector('#ps-account-actions'), link = menu?.querySelector('a[href*="simplify_legacy=1"]');
      const r = button?.getBoundingClientRect(), hit = r ? document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2) : null;
      const cs = menu ? getComputedStyle(menu) : null, ls = link ? getComputedStyle(link) : null;
      return { buttons: document.querySelectorAll('.ps-admin-top .ps-account-button').length, open: account?.classList.contains('account-open') ?? null, hit: hit ? `${hit.tagName.toLowerCase()}.${[...hit.classList].join('.')}` : null, hitInside: !!(hit && button?.contains(hit)), menu: cs && { display: cs.display, visibility: cs.visibility, position: cs.position }, link: link ? { href: link.getAttribute('href'), display: ls.display, rect: link.getClientRects().length } : null, url: location.pathname + location.hash };
    }).catch((e) => ({ error: e.message.slice(0, 120) }));
    await record('admin-legacy-missing', 'Accountmenu op de Admin-pagina: geen Legacy-link');
    say('admin-legacy', false, { note: 'no Legacy panel link in the account menu', accountError, ...why });
  }
  // Always end inside Simplify, so a failed step above does not leave the rest of the round in the legacy panel.
  await goto(overview + (overview.includes('?') ? '&' : '?') + 'simplify_legacy=0', 600);
};
// The filters: on a desktop a dropdown per filter (Egg always, then Show, Node and Group when they have a choice); on a
// phone one Filter with them all as a sheet. Group only when the account has server groups. A choice narrows the servers and names itself on the chip; All takes it back.
const filterCheck = async () => {
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  const want = await alpine('return { sections: d.filterSections().map((x) => x.key), groups: d.overviewGroups.length, servers: d.visibleServers().length }');
  // K-B15: the egg filter is always there while the overview has servers (it vanished with one game, one node and only own servers).
  if (!want || want.sections[0] !== 'egg') return say('overview-filter', false, { note: 'no egg filter', want });
  if (!(await click('.ps-filter-button >> visible=true', 700))) return say('overview-filter', false, { note: 'no Filter button' });
  const shown = await page.evaluate(() => [...document.querySelectorAll('#ps-filter-menu .ps-filter-section')].filter((e) => e.getClientRects().length).map((e) => e.querySelector('.ps-label').textContent.trim())).catch(() => null);
  // A desktop dropdown holds its own filter; the phone's one Filter holds them all.
  const all = await alpine("return d.filterMenu === 'all'");
  await record('overview-filter', 'Overzicht · Filter open');
  const pick = page.locator('#ps-filter-menu .ps-filter-section .ps-menu-item[aria-checked="false"] >> visible=true').first();
  const label = (await pick.locator('span').first().innerText().catch(() => '')).trim(); // the option's name, without its count
  await pick.click().catch(() => {}); await sleep(700);
  const after = await alpine('return { servers: d.visibleServers().length, label: d.filterLabel(), open: d.filterMenu }');
  await record('overview-filtered', 'Overzicht · gefilterd');
  await alpine(`for (const x of d.filterSections()) d.setFilter(x.key, 'all'); return true;`); await sleep(500);
  const back = await alpine('return d.visibleServers().length');
  say('overview-filter', Array.isArray(shown) && shown.length === (all ? want.sections.length : 1) && (want.groups > 0) === want.sections.includes('group') && !!after && !after.open && after.label.includes(label) && after.servers <= want.servers && back === want.servers, { want, shown, all, label, after, back });
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
// Opened, everything around the nodes folds the card shut again, so all of it lights up together; a node keeps its own
// smaller hover and click (K-B30, Jeroen 3-11).
const healthSurround = async () => {
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  if (!(await page.locator('.ps-health-panel >> visible=true').count()) && !(await page.locator('.ps-health-pill >> visible=true').count())) return;
  if (!(await page.locator('.ps-health-panel >> visible=true').count())) { await page.locator('.ps-health-pill >> visible=true').first().click().catch(() => {}); await sleep(600); }
  if (!(await page.locator('.ps-health-panel >> visible=true').count())) return say('overview-health-surround', false, { panel: 'not open' });
  if (!(await alpine('return d.healthExpanded'))) { await page.locator('.ps-health-tab').first().click().catch(() => {}); await sleep(900); }
  const look = () => page.evaluate(() => { const p = document.querySelector('.ps-health-panel'), n = p.querySelector('.ps-node-health'); const bg = (el, x) => el ? getComputedStyle(el, x).backgroundColor : null; return { panel: bg(p), card: bg(p.querySelector('.ps-host-health')), tab: bg(p.querySelector('.ps-health-tab')), tabTop: bg(p.querySelector('.ps-health-tab'), '::before'), node: bg(n), cursor: getComputedStyle(p.querySelector('.ps-health-detail-toolbar')).cursor }; });
  const hoverAt = async (sel, pos) => { const l = page.locator(sel).first(); if (!(await l.count())) return null; await l.hover({ position: pos, force: true }).catch(() => {}); await sleep(400); return look(); };
  const onCard = await hoverAt('.ps-host-health', { x: 40, y: 10 });
  const onToolbar = await hoverAt('.ps-health-detail-toolbar', { x: 4, y: 4 });
  await record('overview-health-surround', 'Host health open, wijzen naast de nodes', { colours: onToolbar });
  const onNode = await hoverAt('.ps-node-health', { x: 6, y: 6 });
  const lit = (l) => !!l && l.panel === onCard.card && l.card === onCard.card && l.tab === onCard.card && l.tabTop === onCard.card;
  const nodeOwn = !onNode || (onNode.panel !== onCard.card && onNode.card !== onCard.card);
  const box = await page.locator('.ps-health-detail-toolbar').first().boundingBox().catch(() => null);
  if (box) await page.mouse.click(box.x + 4, box.y + 4); await sleep(700);
  const closed = !(await alpine('return d.healthExpanded'));
  await page.mouse.move(5, 5);
  say('overview-health-surround', !!onCard && lit(onToolbar) && onToolbar.cursor === 'pointer' && nodeOwn && closed, { onCard, onToolbar, onNode, closed });
  await escape();
};
const goto = async (url, ms = 900) => { for (let i = 0; i < 3; i++) { try { await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 }); break; } catch (e) { if (i === 2) throw e; await sleep(1500); } } await settle(page, ms); };
const click = async (sel, ms = 600) => { const l = page.locator(sel).first(); if (!(await l.count()) || !(await l.isVisible().catch(() => false))) { problems.push(`${stand} ${screen}: nothing to click for ${sel}`); return false; } await l.click({ timeout: 5000 }).catch((e) => problems.push(`${stand} ${screen}: click ${sel}: ${e.message.slice(0, 80)}`)); await sleep(ms); return true; };
const escape = async () => { await page.keyboard.press('Escape'); await sleep(300); };
const phone = () => VIEWPORTS[stand.split('-')[0]].width <= 600;
// Phones (1.5.4) reach the overview's menus from the bottom bar: server groups and the overview's look under Customize. Elsewhere, and on wider screens, the button in the top bar.
// Customize (the look of the overview and the server pages) is in the account menu on every screen (Jeroen 3-11);
// server groups open from inside Customize.
const CUSTOMIZE = ['.ps-top .ps-account-button', '#ps-account-actions button:has(use[href="#ps-wand"])'];
const PHONE_ROUTE = { overviewAppearance: CUSTOMIZE, overviewGroupsButton: [...CUSTOMIZE, '#ps-overview-appearance-menu [data-tab="groups"]', '#ps-overview-appearance-menu .ps-customize-edit'] };
const DESK_ROUTE = PHONE_ROUTE;
const openRef = async (ref, ms) => { if (!phone() && DESK_ROUTE[ref]) { for (const sel of DESK_ROUTE[ref]) if (!(await click(sel, ms))) return false; return true; } if (phone() && PHONE_ROUTE[ref] && (await page.locator('.ps-tabbar >> visible=true').count())) { for (const sel of PHONE_ROUTE[ref]) if (!(await click(sel, ms))) return false; return true; } return click(`[x-ref="${ref}"]`, ms); };

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
  // Every round sets its theme first: a run of only some parts (parts=admin) skips the overview, and its dark folder
  // must still be dark.
  await setTheme(theme); await goto('/admin/health', 1500);
  if (want('overview')) {
  // Overview and its menus.
  await goto(overview); await setTheme(theme); await goto(overview); await ready(); await sleep(500);
  for (const view of ['grid', 'compact', 'list']) { await alpine(`d.setOverviewView('${view}')`); await sleep(700); await record('overview-' + view, 'Overzicht, ' + view); }
  // Full width (an overview preference): the scroller as wide as the grid, its scrollbar beside the cards (1.5.0).
  if (!phone() && await alpine('return typeof d.setOverviewPreference === "function"')) {
    await alpine(`d.setOverviewPreference('fullWidth', true)`); await sleep(700);
    for (const view of ['grid', 'list']) { await alpine(`d.setOverviewView('${view}')`); await sleep(700); await record('overview-full-' + view, 'Overzicht, volle breedte, ' + view); }
    await alpine(`d.setOverviewPreference('fullWidth', false)`); await sleep(500);
  }
  await alpine(`d.setOverviewView('grid')`); await sleep(500);
  // Phones: the panel menu opens from the tab bar at the bottom (the account button sits inside it).
  // Phones have no panel menu any more (1.5.4): the bell and the account sit in the top bar, the rest in the bottom bar.
  const openNav = async () => {};
  await click('.ps-account-button'); await record('overview-account', 'Accountmenu open');
  await escape(); await alpine('d.accountMenu=false; d.appearanceMenu=true'); await sleep(600); await record('overview-customize-theme', 'Customize Simplify, thema bovenaan');
  // K-B19: Customize Simplify opens from the account menu, so its arrow points at the account button and its box ends
  // where the account menu ends, not at the place Customize had in the top bar before.
  if (!phone()) {
    const at = await page.evaluate(() => {
      const button = [...document.querySelectorAll('.ps-top .ps-account-button')].find((e) => e.getClientRects().length);
      const menu = document.querySelector('#ps-overview-appearance-menu');
      const wrap = menu?.parentElement;
      if (!button || !menu || !menu.getClientRects().length || !wrap) return null;
      const b = button.getBoundingClientRect(), m = menu.getBoundingClientRect(), w = wrap.getBoundingClientRect();
      const arrow = getComputedStyle(wrap, '::after');
      const x = w.right - parseFloat(arrow.insetInlineEnd || arrow.right || '0') - 6;
      return { arrowX: Math.round(x), button: [Math.round(b.left), Math.round(b.right)], menuRight: Math.round(m.right), arrowShown: arrow.content !== 'none' };
    }).catch(() => null);
    problems.push(`${stand} customize-anchor: ${at && at.arrowShown && at.arrowX >= at.button[0] && at.arrowX <= at.button[1] && Math.abs(at.menuRight - at.button[1]) <= 2 ? 'ok' : 'WRONG'} ${JSON.stringify(at)}`);
  }
  // Wide Customize (Jeroen 3-11): every section shows its contents straight away, inside the window; on a computer the
  // window is wide with the sections beside their contents, on a phone the sections are chips above them.
  {
    const tabs = await page.evaluate(() => [...document.querySelectorAll('#ps-overview-appearance-menu [data-tab]')].filter((b) => b.getClientRects().length).map((b) => b.dataset.tab)).catch(() => []);
    const seen = [];
    for (const key of tabs) {
      await page.locator(`#ps-overview-appearance-menu [data-tab="${key}"]`).first().click().catch(() => {}); await sleep(350);
      const at = await page.evaluate(() => {
        const menu = document.querySelector('#ps-overview-appearance-menu');
        const pane = menu?.querySelector('.ps-customize-pane');
        const side = menu?.querySelector('.ps-customize-side');
        if (!menu || !pane || !side) return null;
        const m = menu.getBoundingClientRect(), p = pane.getBoundingClientRect(), d = side.getBoundingClientRect();
        const rows = [...pane.querySelectorAll('.ps-customize-row, .ps-customize-switch, .ps-customize-edit, .ps-customize-lead')].filter((e) => e.getClientRects().length).length;
        return { width: Math.round(m.width), inside: m.top >= 0 && m.bottom <= innerHeight + 1 && m.left >= 0 && m.right <= innerWidth + 1, beside: p.left >= d.right - 1, rows };
      }).catch(() => null);
      await record('overview-customize-' + key, 'Customize Simplify · ' + key);
      seen.push({ key, ...at });
    }
    const wide = (x) => x && x.rows > 0 && x.inside && (phone() || (x.width >= 640 && x.beside));
    problems.push(`${stand} customize-wide: ${tabs.length >= 5 && seen.every(wide) ? 'ok' : 'WRONG'} ${JSON.stringify(seen)}`);
  }
  await alpine('d.appearanceMenu=false'); await sleep(300);
  await openNav();
  await click('[x-ref="notifyButton"]'); await record('overview-bell', 'Bel (aankondigingen) open'); await escape();
  await openNav();
  await click('[x-ref="notifyButton"]');
  // The bell's expand button (1.4.4): a window with every notification.
  if (await click('.ps-notify-all, [x-ref="notifyButton"] ~ .ps-dropdown button[aria-label*="all" i], .ps-dropdown button[aria-label*="expand" i]', 1200)) await record('overview-bell-all', 'Meldingenvenster (alle meldingen) open'); await escape(); await escape();
  await openNav();
  await openRef('overviewAppearance'); await record('overview-customize', 'Weergave aanpassen open'); await escape();
  await openNav();
  await openRef('overviewGroupsButton'); await record('overview-groups', 'Servergroepen open'); await escape();
  await filterCheck();
  if (await click('.ps-health-pill')) await record('overview-health', 'Gezondheid van de host open'); await escape();
  // Host health card's tab under its bottom edge (1.4.4): the nodes fold open.
  if (await click('.ps-health-tab', 900)) { await record('overview-health-nodes', 'Host health, nodes uitgeklapt'); await click('.ps-health-tab', 600); }
  await healthHover('overview');
  await healthSurround();
  await adminPage(overview);
  // The menus under the top bar once more with one announcement bar and with none: their height must follow the bars.
  const unpinBar = async () => { const b = page.locator('button[aria-label="Unpin"], .ps-announcement-unpin, [wire\\:click^="unpin"]').first(); if (!(await b.count())) return false; await b.click().catch(() => {}); await sleep(1500); return true; };
  for (const [tag, label] of [['1bar', 'één aankondigingsbalk'], ['0bar', 'geen aankondigingsbalk']]) {
    if (!(await unpinBar())) { problems.push(`${stand}: no announcement bar to unpin for ${tag}`); break; }
    await openNav(); await click('[x-ref="notifyButton"]'); await record('overview-bell-' + tag, `Bel open, ${label}`); await escape();
    await openNav(); await openRef('overviewAppearance'); await record('overview-customize-' + tag, `Weergave aanpassen open, ${label}`); await escape();
  }
  // Pin both bars again for the next stand (the Livewire component keeps the choice per user).
  await page.evaluate(() => { const c = window.Livewire?.all().find((x) => /announcement/i.test(x.name)); if (c) { c.call('pin', 1); c.call('pin', 2); } }).catch(() => {}); await sleep(1500);
  await click('.ps-server-card .ps-card-menu-button, .ps-server-card button[aria-label*="options" i], .ps-server-card button[aria-label*="menu" i]', 500) && await record('overview-card-menu', 'Serverkaart ⋯-menu open'); await escape();
  for (const p of urls.app.filter((x) => x.url !== overview)) { await goto(p.url); await record('app-' + slug(p.title), p.title); }
  if (urls.profile) { await goto(urls.profile); await record('profile', 'Profiel'); }
  }

  if (want('server')) {
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
  // An add-on's form on a server tab (Toolkit's Minecraft settings) uses the core's unsaved changes (P-38): a change
  // shows the bar, a menu click asks first, Cancel stays. Only checked once the add-on's form opts in.
  const mc = (urls.server[mainServer] || []).find((p) => p.url && /minecraft/i.test(p.title));
  if (mc) {
    await goto(mc.url, 2500);
    const field = page.locator('[data-simplify-unsaved] input[type="number"] >> visible=true').first();
    if (await field.count()) {
      const shown = (sel) => page.evaluate((s) => [...document.querySelectorAll(s)].some((e) => e.getClientRects().length > 0), sel).catch(() => null);
      const st = { bar: null, asked: null, stayed: null };
      const before = await field.inputValue().catch(() => '');
      await field.fill(String((Number(before) || 20) + 1)).catch(() => {});
      await page.waitForSelector('.s-unsaved:not([hidden])', { timeout: 4000 }).catch(() => {});
      st.bar = await shown('.s-unsaved');
      await record('server-addon-unsaved', 'Server · add-on-formulier, Changes made');
      const link = page.locator('a[wire\\:navigate][href*="/server/"] >> visible=true').first();
      if (await link.count()) await link.click().catch(() => {});
      else await page.evaluate(() => { const a = Object.assign(document.createElement('a'), { href: location.pathname.replace(/[^/]+$/, '') }); a.setAttribute('wire:navigate', ''); document.body.append(a); a.click(); }).catch(() => {});
      await page.waitForSelector('.s-unsaved-dialog', { timeout: 3000 }).catch(() => {});
      st.asked = await shown('.s-unsaved-dialog');
      await page.locator('.s-unsaved-dialog .s-unsaved-btn').first().click().catch(() => {});
      await sleep(400);
      st.stayed = rel(page.url()) === rel(mc.url) && !(await shown('.s-unsaved-dialog'));
      st.url = rel(page.url());
      // Typed back to what it was: no change any more, the bar goes and leaving asks nothing.
      await field.fill(before).catch(() => {});
      await page.waitForSelector('.s-unsaved', { state: 'hidden', timeout: 3000 }).catch(() => {});
      st.gone = !(await shown('.s-unsaved'));
      const good = st.bar === true && st.asked === true && st.stayed === true && st.gone === true;
      problems.push(`${stand} server-addon-unsaved: ${good ? 'ok' : 'WRONG'} ${JSON.stringify(st)}`);
      // Whatever is left, the rest of the round goes on (the browser's own question would stop the next page).
      page.once('dialog', (dialog) => dialog.accept().catch(() => {}));
    }
  }
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
  }

  if (want('admin')) {
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
    const tabs = page.locator('main [role=tab]:visible, .fi-sc-tabs-tab:visible, .s-settings-nav a[data-tab], .s-segment');
    const n = await tabs.count();
    for (let i = 1; i < Math.min(n, 9); i++) { await tabs.nth(i).click().catch(() => {}); await sleep(900); await record(`admin-${slug(p.title)}-tab${i}`, `Beheer · ${p.title}, tabblad ${i + 1}`); }
    if (/RoleResource|UserResource|ServerResource|NodeResource|EggResource/.test(p.class)) { const edit = page.locator('.fi-ta-row a[href*="/edit"], .fi-ta-row a[href*="/admin/"]').first(); if (await edit.count()) { const h = await edit.getAttribute('href'); if (h) { await goto(h, 1500); await record('admin-' + slug(p.title) + '-edit', 'Beheer · ' + p.title + ', bewerken'); const t2 = page.locator('main [role=tab], .fi-sc-tabs-tab'); const m = await t2.count(); for (let i = 1; i < Math.min(m, 7); i++) { await t2.nth(i).click().catch(() => {}); await sleep(800); await record(`admin-${slug(p.title)}-edit-tab${i}`, `Beheer · ${p.title}, bewerken, tabblad ${i + 1}`); } } } }
    if (/People$/.test(p.class)) { if (await click('main button:has-text("Add person"), main button:has-text("Add")', 1000)) await record('admin-people-add', 'Beheer · People, persoon toevoegen'); await escape(); }
    if (/AnnouncementResource$|Announcements$/.test(p.class)) { if (await click('main a:has-text("New announcement"), main button:has-text("New announcement")', 1200)) await record('admin-announcements-new', 'Beheer · nieuwe aankondiging'); await escape(); }
  }
  }

  if (want('host')) {
  // The Admin page: every section and page of its menu, and the menu kept beside each page.
  if (await shieldTo(overview)) {
    await record('host', 'Admin-pagina');
    if (onPhone) { await openAdminMenu(); await record('host-phone-menu', 'Admin-pagina · telefoon, menu als pagina'); await escape(); }
    const lost = [], visited = [], offRow = [], underCrumb = [], tall = [];
    const go = async (locator, id, title) => {
      await markAdminSide(); await locator.click().catch(() => {}); await settle(page, 1800);
      // A page that still loads its table (Plugins) can be taller for a moment: measure once the card has its place.
      await page.waitForFunction(() => { const c = document.querySelector('.fi-layout > .fi-main-ctn'); return !c || c.getBoundingClientRect().bottom <= innerHeight + 1; }, null, { timeout: 4000 }).catch(() => {});
      const st = await adminState(); await record(id, title); visited.push(id);
      if (!st || !st.kept || !st.shell || st.frames) lost.push({ id, ...st });
      if (st?.offRow) offRow.push(id);
      if (st?.tall) tall.push({ id, ...st.tall });
      if (st?.underCrumb) underCrumb.push(underCrumb.length ? id : { id, ...st.underCrumb });
    };
    const groups = page.locator('.ps-admin-side .ps-host-group'); const gn = await groups.count();
    for (let i = 0; i < gn; i++) {
      await openAdminMenu();
      const head = groups.nth(i).locator('.ps-host-group-head').first();
      const label = (await head.innerText().catch(() => 'sectie ' + i)).trim().split('\n')[0];
      if (await head.evaluate((e) => e.tagName === 'A').catch(() => false)) { await go(head, 'host-' + slug(label), 'Admin-pagina · ' + label); continue; }
      if ((await head.getAttribute('aria-expanded')) !== 'true') { await head.click().catch(() => {}); await sleep(500); }
      const ln = await groups.nth(i).locator('.ps-host-leaf').count();
      for (let j = 0; j < Math.min(ln, 40); j++) {
        await openAdminMenu();
        const h2 = groups.nth(i).locator('.ps-host-group-head').first();
        if ((await h2.getAttribute('aria-expanded')) !== 'true') { await h2.click().catch(() => {}); await sleep(500); }
        const leaf = groups.nth(i).locator('.ps-host-leaf').nth(j);
        const t = (await leaf.innerText().catch(() => 'pagina ' + j)).trim();
        const id = 'host-' + slug(label) + '-' + slug(t);
        if (visited.includes(id)) { if (onPhone) await escape(); continue; }
        await go(leaf, id, `Admin-pagina · ${label} · ${t}`);
      }
    }
    await openAdminMenu();
    if (await page.locator('.ps-admin-side .ps-host-health-open >> visible=true').count()) await go(page.locator('.ps-admin-side .ps-host-health-open').first(), 'host-health', 'Admin-pagina · Health');
    problems.push(`${stand} admin-menu-kept: ${visited.length && !lost.length ? 'ok' : 'WRONG'} ${JSON.stringify({ pages: visited.length, lost: lost.slice(0, 5) })}`);
    if (!onPhone) problems.push(`${stand} admin-desktop-rows: ${visited.length && !offRow.length ? 'ok' : 'WRONG'} ${JSON.stringify({ pages: visited.length, offRow })}`);
    if (!onPhone) problems.push(`${stand} admin-desktop-box: ${visited.length && !tall.length ? 'ok' : 'WRONG'} ${JSON.stringify({ pages: visited.length, tall })}`);
    if (!onPhone) problems.push(`${stand} admin-desktop-under: ${visited.length && !underCrumb.length ? 'ok' : 'WRONG'} ${JSON.stringify({ pages: visited.length, underCrumb })}`);
  } else problems.push(`${stand} host: WRONG no shield link to the Admin page`);
  }
  if (want('seasons') && ['d1440', 'tablet768', 'phone390'].includes(stand.split('-')[0])) await seasons(theme);
  if (want('create') && ['d1440', 'phone390'].includes(stand.split('-')[0])) await createFlow();
  if (want('extras') && ['d1440', 'laag1366', 'laag1100', 'phone390'].includes(stand.split('-')[0])) await extrasFlow(theme);
  // Last, because it changes what the account has read and archived.
  if (want('notify') && theme === 'dark' && ['d1440', 'phone390'].includes(stand.split('-')[0])) await notifyFlow();
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
  // The page's buttons (delete, run, export, save) never lie over a field, right after opening (2-11: four buttons covered
  // the Name field until the Tasks table had loaded, and on a phone for good).
  const over = await page.evaluate(() => { const v = (e) => e.getClientRects().length > 0; const btns = [...document.querySelectorAll('.fi-header-actions-ctn button, .fi-header-actions-ctn a, .fi-ac button')].filter(v);
    const fields = [...document.querySelectorAll('.fi-input-wrp, input:not([type=hidden]), .fi-fo-toggle')].filter(v);
    for (const b of btns) { const r = b.getBoundingClientRect(); for (const f of fields) { const q = f.getBoundingClientRect(); if (r.left < q.right - 1 && r.right > q.left + 1 && r.top < q.bottom - 1 && r.bottom > q.top + 1 && !f.contains(b) && !b.contains(f)) return { button: (b.getAttribute('aria-label') || b.textContent.trim()).slice(0, 30), field: (f.getAttribute('name') || f.id || f.className).slice(0, 40) }; } }
    return null; }).catch(() => null);
  say('schedule-buttons-free', !over, over);
  const reach = () => page.evaluate(() => {
    const box = document.querySelector('.fi-page-main') || document.scrollingElement;
    box.scrollTop = box.scrollHeight;
    // On a phone the page itself scrolls under the bars (the phone frame): scroll it to its end as well.
    document.scrollingElement.scrollTop = document.scrollingElement.scrollHeight;
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
  await reach(); await page.waitForSelector('.fi-ta-row >> visible=true', { timeout: 10000 }).catch(() => {});
  const st = await reach();
  await record('schedule-edit', 'Schedules · taak bewerken (onderaan)');
  say('schedule-edit', st.tasks >= 3 && st.inView && st.clickable && (/^phone/.test(stand) || st.pageScroll <= 1), st);
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
    // 0.7.0 had no Create server on a server page (only the overview, its phone tab bar and the admin list): one there is a test of its own.
    if (await press('server')) await check('create-server-page', 'Create server (serverpagina)'); else problems.push(`${stand} create-server-page: ok none on a server page, as in 0.7.0`);
    // The Admin page: Admin → Servers → Create (Pelican's own link on its page) opens the popup as well.
    await goto('/admin/servers', 2500);
    const link = page.locator('.fi-main-ctn a[href*="/servers/create"] >> visible=true').first();
    if (await link.count()) { await link.click().catch(() => {}); await sleep(800); await check('create-host-admin', 'Create server (Admin-pagina, Servers → Create)'); }
    else problems.push(`${stand} create-host-admin: WRONG no Create in Admin → Servers (${rel(page.url())})`);
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
  if (!onPhone) for (const [btn, menu, id] of [['[x-ref="notifyButton"]', '#ps-notify-menu', 'bell'], ['.ps-account-button', '#ps-account-actions', 'account']]) {
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
      scroll: document.documentElement.scrollHeight - innerHeight, url: location.pathname,
      // What sticks out below the window when the page scrolls: the outermost such boxes, for the diagnosis.
      below: document.documentElement.scrollHeight > innerHeight + 1 ? [...document.querySelectorAll('body *')].filter((e) => { const r = e.getBoundingClientRect(); if (!e.getClientRects().length || r.bottom + scrollY <= innerHeight + 1) return false; for (let q = e.parentElement; q && q !== document.body; q = q.parentElement) { if (/(auto|scroll|hidden|clip)/.test(getComputedStyle(q).overflowY)) return false; if (q.getBoundingClientRect().bottom + scrollY > innerHeight + 1) return false; } return true; }).slice(0, 5).map((e) => e.tagName.toLowerCase() + '.' + String(e.className).trim().split(/\s+/).slice(0, 3).join('.') + ' ' + Math.round(e.getBoundingClientRect().bottom + scrollY)) : [],
      boxes: document.documentElement.scrollHeight > innerHeight + 1 ? ['body > *', '.fi-layout > *', '.fi-main-ctn > *', '.fi-main > *', '.fi-page-header-main-ctn > *'].flatMap((q) => [...document.querySelectorAll(q)].filter((e) => e.getClientRects().length).map((e) => { const r = e.getBoundingClientRect(); return q.split(' ')[0] + '>' + e.tagName.toLowerCase() + '.' + String(e.className).trim().split(/\s+/)[0] + ' ' + Math.round(r.top + scrollY) + '+' + Math.round(r.height); })).concat('fit ' + document.documentElement.style.getPropertyValue('--ps-fit')) : [] };
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
// settings, the Admin page and the console.
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
  await goto('/admin/servers', 2500); info = await look(page); await sleep(300); await record('scroll-admin', 'Scrollbalk · Admin-pagina (kaart naast het menu)'); judge('admin', info, rel(page.url()));
  await goto(serverUrl, 3500);
  await page.evaluate(() => { const t = document.querySelector('.ps-console .xterm-scrollable-element'); if (t) t.dispatchEvent(new WheelEvent('wheel', { deltaY: -400, bubbles: true })); }).catch(() => {});
  await sleep(400); info = await look(page, true); await record('scroll-console', 'Scrollbalk · console'); judge('console', info);
  // A box with scrollbar-width or scrollbar-color of its own gets Chrome's bar (with arrows on Windows) instead of ours:
  // the standard properties switch ::-webkit-scrollbar off for that box (K-B35, Jeroen 3-11: the Admin menu).
  const own = () => page.evaluate(() => [...document.querySelectorAll('*')].filter((e) => { if (!e.getClientRects().length) return false; const cs = getComputedStyle(e); return /auto|scroll/.test(cs.overflowY + cs.overflowX) && (cs.scrollbarWidth !== 'auto' || cs.scrollbarColor !== 'auto'); }).filter((e) => { const cs = getComputedStyle(e); return cs.scrollbarWidth !== 'none'; }).map((e) => `${e.tagName.toLowerCase()}.${String(e.className).trim().split(/\s+/).slice(0, 2).join('.')} ${getComputedStyle(e).scrollbarWidth}`).slice(0, 8)).catch((e) => [`error ${e.message.slice(0, 60)}`]);
  await goto('/admin/servers', 2500); const side = await page.locator('.ps-admin-side .ps-host-side-scroll').count();
  const onAdmin = await own(); await goto('/admin/simplify', 1500); const onSettings = await own(); await goto(overview, 1500); const onOverview = await own();
  say('scroll-own-bar', side > 0 && !onAdmin.length && !onSettings.length && !onOverview.length, { adminMenu: side, admin: onAdmin, settings: onSettings, overview: onOverview });
}

// Pelican plugins (Other plugins in 1.5.2): always a group that folds open, and a plugin's page shows "Pelican plugins ›
// <page>" in the head. Simplify add-ons and Pelican plugins stand apart under the rest of the menu (Jeroen 3-11).
async function otherPlugins() {
  const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url || '/';
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  if (!(await shieldTo(overview))) return say('other-plugins', false, { note: 'no shield link' });
  await openAdminMenu();
  // The menu's order (Jeroen 3-11): People & roles one leaf, Pelican's plugin list under Pelican settings, and apart below
  // the rest Simplify add-ons, then Pelican plugins.
  const order = await page.evaluate(() => [...document.querySelectorAll('.ps-admin-side .ps-host-group')].map((g) => ({ head: g.querySelector('.ps-host-group-head')?.innerText.trim(), apart: g.classList.contains('is-apart'), links: [...g.querySelectorAll('a')].map((a) => new URL(a.href).pathname) }))).catch(() => []);
  const at = (name) => order.findIndex((g) => g.head === name);
  const people = order[at('People & roles')];
  say('admin-menu-order', at('Health') === 0 && !!people && people.links.length === 1 && !order.some((g) => g.links.some((l) => /\/admin\/(users|roles)$/.test(l)))
    && order[at('Pelican settings')]?.links.some((l) => /\/admin\/plugins$/.test(l)) && order.find((g) => g.apart)?.head === (at('Simplify add-ons') >= 0 ? 'Simplify add-ons' : 'Pelican plugins') && at('Pelican plugins') > at('Simplify add-ons')
    && order.filter((g) => g.apart).length === 1 && order.slice(order.findIndex((g) => g.apart)).every((g) => !['Servers', 'Nodes', 'Simplify settings', 'Pelican settings'].includes(g.head)), order.map((g) => g.head + (g.apart ? ' (apart)' : '')));
  const group = '.ps-admin-side .ps-host-group:has(.ps-host-group-head:has-text("Pelican plugins"))';
  const head = page.locator(`${group} .ps-host-group-head`).first();
  // The test panel has a plugin outside Simplify with its own admin page (CLAUDE.md), so the group is real.
  if (!(await head.count())) { say('other-plugins', false, { note: 'no Pelican plugins group in the Admin menu' }); if (phone()) await escape(); return; }
  const before = await head.getAttribute('aria-expanded');
  if (before !== 'true') { await head.click().catch(() => {}); await sleep(600); }
  const after = await head.getAttribute('aria-expanded');
  const leaf = page.locator(`${group} .ps-host-branch a >> visible=true`).first();
  const leafs = await page.locator(`${group} .ps-host-branch a >> visible=true`).count();
  const name = leafs ? (await leaf.innerText().catch(() => '')).trim() : '';
  await record('other-plugins-open', 'Pelican plugins · uitgeklapt');
  await markAdminSide(); if (leafs) { await leaf.click().catch(() => {}); await settle(page, 2000); }
  const st = await adminState();
  const current = await page.evaluate(() => { const a = document.querySelector('.ps-admin-side .ps-host-leaf[aria-current="page"]'); return a ? a.innerText.trim() : null; }).catch(() => null);
  await record('other-plugins-page', 'Pelican plugins · pagina van een plugin');
  say('other-plugins', after === 'true' && leafs >= 1 && !!st && st.kept && st.shell && current === name, { before, after, leafs, name, current, kept: st && st.kept });
}

// Settings › Browse Hub: the list loads in its tab (placeholders first), the page does not scroll (except on a phone), Install on Hub only
// links to the Hub, and Details opens above everything with its buttons clickable. Each check writes ok or WRONG.
async function hubFlow() {
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  await goto('/admin/simplify?tab=hub', 1000);
  // Browse Hub is hidden for now (Settings::HUB, Jeroen 3-11): then neither the row of tabs nor the Admin menu has it.
  const hubLinks = await page.evaluate(() => document.querySelectorAll('a[href*="tab=hub"]').length).catch(() => -1);
  if (!(await page.locator('.s-hub, .s-hub-card, .s-hub-empty').count())) return say('hub-hidden', hubLinks === 0, { links: hubLinks });
  // The placeholder has a grid too: wait for a real card (with its Hub link) or the empty note.
  await page.waitForSelector('.s-hub-card a.s-btn, .s-hub-empty', { timeout: 45000 }).catch(() => {});
  await sleep(800);
  const list = await page.evaluate(() => {
    const links = [...document.querySelectorAll('.s-hub-card a.s-btn')].filter((a) => a.getClientRects().length);
    return { cards: document.querySelectorAll('.s-hub-card:not(:has(.s-hub-skel))').length, empty: document.querySelector('.s-hub-empty')?.textContent.trim() || null,
      pageScroll: document.documentElement.scrollHeight - innerHeight, links: links.length,
      hubOnly: links.every((a) => /^https:\/\/hub\.pelican\.dev\/plugins\/[a-z0-9-]+$/.test(a.href) && a.target === '_blank') };
  });
  await record('settings-hub', 'Instellingen · Browse Hub');
  // On a phone the settings page itself scrolls (1.5.3); elsewhere only the card does.
  say('hub-list', list.cards > 0 && list.links === list.cards && list.hubOnly && (phone() || list.pageScroll <= 1), list);
  const details = page.locator('.s-hub-card button.s-btn >> visible=true').first();
  if (!(await details.count())) return say('hub-details', false, { note: 'no Details button' });
  await details.click().catch(() => {}); await sleep(1500);
  const dialog = await page.evaluate(() => {
    const box = document.querySelector('.s-hub-dialog');
    if (!box || !box.getClientRects().length) return { shown: false };
    const buttons = [...box.querySelectorAll('header a, header button')].map((e) => { const r = e.getBoundingClientRect(); const top = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2); return !!top && (top === e || e.contains(top)); });
    return { shown: true, z: getComputedStyle(box.parentElement).zIndex, clickable: buttons.length === 2 && buttons.every(Boolean), maker: !!box.querySelector('.s-hub-by b') };
  });
  await record('settings-hub-details', 'Instellingen · Browse Hub · details');
  say('hub-details', dialog.shown && Number(dialog.z) >= 20 && dialog.clickable && dialog.maker, dialog);
  await page.locator('.s-hub-dialog header button').first().click().catch(() => {}); await sleep(800);
  say('hub-details-close', !(await page.locator('.s-hub-dialog').count()), {});
}

// 1.5.0 extras: the end of the setup in low windows (also inside the Admin page), the setup's Look step (#86), the
// install screen and the EULA bar (#89) and an announcement pinned in the bell (#92). Each check writes ok or WRONG.
async function extrasFlow(theme) {
  const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url || '/';
  const onPhone = phone(), last = !LIGHT.includes(stand.split('-')[0]) || theme === 'light';
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  const fits = (doc) => doc.evaluate(() => ({ scroll: document.documentElement.scrollHeight, window: innerHeight })).catch(() => null);
  // Setup done: the page itself never scrolls; the add-on list scrolls inside its card.
  await goto('/admin/simplify/setup-done', 1500); await record('setup-done', 'Setup klaar (gewone beheerpagina)');
  let f = await fits(page); say('setup-done', f && f.scroll <= f.window, f);
  // In the Admin page the end of the setup fits the card beside the menu; neither the page nor the card's frame scrolls.
  const card = await page.evaluate(() => { const c = document.querySelector('.fi-layout > .fi-main-ctn'); return { shell: document.documentElement.classList.contains('ps-shell-menu'), page: document.documentElement.scrollHeight - innerHeight, card: c ? c.scrollHeight - c.clientHeight : null }; }).catch(() => null);
  say('setup-done-host', !!card && card.shell && card.page <= 1 && (onPhone || card.card <= 1), card);

  // The setup's Look step (#86): logo, text or both; effect; colour = accent. The preview follows every choice.
  if (stand.split('-')[0] !== 'laag1100') {
    await goto('/admin/simplify/setup', 1500);
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

  // Install screen (#89): a server that installs, one whose installation failed. The EULA (1.5.2: in the card, before
  // First start) is firstFlow's.
  for (const [name, id, sel, label] of [['New World', 'server-installing', '.ps-install', 'installeert'], ['Broken Install', 'server-install-failed', '.ps-install', 'installatie mislukt']]) {
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
  await fixesFlow();
  await goto(overview);
}

// 1.5.3 fixes, each ok or WRONG: Simplify's settings on a phone (the card flows with the page, the settings pane is not
// a small box that scrolls inside), the setup on a phone, no Start or Restart while a server installs, and the quick
// commands above a phone's console stay put (never taken out and put back).
async function fixesFlow() {
  const say = (step, ok, detail) => problems.push(`${stand} ${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  // Simplify's settings right after the first load (1.5.4): the card reaches the footer, also before anything else
  // happened on the page (Khion saw a short card with empty space under it).
  if (!phone()) {
    // A fresh browser (nothing cached yet), straight to the settings, as after signing in on a new computer.
    const fresh = await browser.newContext({ viewport: page.viewportSize(), baseURL: base, storageState: await page.context().storageState() });
    const p2 = await fresh.newPage(); await quiet(p2);
    await p2.goto(new URL('/admin/simplify', base).href, { waitUntil: 'domcontentloaded' }).catch(() => {}); await sleep(3000);
    // In the Admin page's card (V52) the card scrolls and the page does not; the settings' row of tabs is there at once.
    const fl = await p2.evaluate(() => ({ nav: !!document.querySelector('.s-settings-nav'), page: document.documentElement.scrollHeight, window: innerHeight })).catch(() => null);
    const shot = page; page = p2; await record('settings-first-load', 'Instellingen · eerste paginalaad, lege cache'); page = shot;
    await fresh.close().catch(() => {});
    say('settings-first-load', !!fl && fl.nav && fl.page <= fl.window + 2, fl);
  }
  if (phone()) {
    await goto('/admin/simplify', 2000);
    await click('.s-settings-nav a:has-text("Appearance")', 900);
    await record('settings-phone', 'Instellingen · telefoon');
    const st = await page.evaluate(() => ({ nav: !!document.querySelector('.s-settings-nav'), page: document.documentElement.scrollHeight, window: innerHeight })).catch(() => null);
    say('settings-phone', !!st && st.nav && st.page <= st.window + 2, st);
    await goto('/admin/simplify/setup', 2000); await record('setup-phone', 'Setup · telefoon');
    const su = await page.evaluate(() => { const w = document.querySelector('.s-setup'), s = w && w.querySelector('.fi-sc-wizard-step.fi-active'); if (!s) return null; const r = s.getBoundingClientRect(); return { step: Math.round(r.height), inner: s.scrollHeight <= s.clientHeight + 4, width: Math.round(r.width) }; }).catch(() => null);
    say('setup-phone', su && su.step > 120 && su.inner && su.width > 250, su);
  }
  // A server without any picture shows the icon of its kind of egg, not the cube (Khion, 1.5.3: Python and Node.js
  // servers all had the cube).
  {
    const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url || '/';
    await goto(overview, 2500);
    const ic = await page.evaluate(() => { const card = [...document.querySelectorAll('.ps-server-card')].find((c) => /Discord bot/.test(c.innerText)); const box = card && card.querySelector('.ps-card-icon'); if (!box) return null; const img = box.querySelector('img'); const svg = box.querySelector('svg use'); return { picture: !!img && img.getClientRects().length > 0 && img.naturalWidth > 0, icon: svg && svg.closest('svg').getClientRects().length ? svg.getAttribute('href') : null }; }).catch(() => null);
    await record('egg-kind-icon', 'Overzicht · icoon per soort egg');
    say('egg-kind-icon', ic && (ic.picture || /^#ps-kind-/.test(ic.icon || '')), ic);
    // A Steam game (Counter-Strike 2 on steamcmd, as in pelican-eggs) shows the Steam icon, not Java's cup.
    const steam = await page.evaluate(() => { const card = [...document.querySelectorAll('.ps-server-card')].find((c) => /Arena/.test(c.innerText)); return card?.querySelector('.ps-card-icon svg use')?.getAttribute('href') || null; }).catch(() => null);
    say('egg-kind-steam', steam === '#ps-steam', { steam });
    // Pinned announcements on the overview sit under the top bar, as wide as the servers, as in 0.7.0 (Jeroen V23, 2-11).
    const pins = await page.evaluate(() => {
      const vis = (e) => !!e && e.getClientRects().length > 0;
      const bar = [...document.querySelectorAll('.ps-bars')].find((b) => vis(b) && b.children.length); const top = [...document.querySelectorAll('.ps-top')].find(vis);
      const tools = [...document.querySelectorAll('.ps-overview-toolbar')].find(vis); const cards = [...document.querySelectorAll('.ps-server-card')].filter(vis).map((c) => c.getBoundingClientRect());
      if (!bar || !top || !tools || !cards.length) return { bar: !!bar, top: !!top, tools: !!tools, cards: cards.length };
      const b = bar.getBoundingClientRect(), t = top.getBoundingClientRect(), o = tools.getBoundingClientRect();
      // As wide as the servers: the cards' outer edges (the toolbar runs into the side gutters on a phone).
      return { barTop: Math.round(b.top), topBottom: Math.round(t.bottom), barLeft: Math.round(b.left), barRight: Math.round(b.right), toolsLeft: Math.round(Math.min(...cards.map((r) => r.left))), toolsRight: Math.round(Math.max(...cards.map((r) => r.right))), toolsTop: Math.round(o.top) };
    }).catch(() => null);
    await record('pinned-overview', 'Overzicht · vastgezette meldingen onder de bovenbalk');
    say('pinned-overview', !!pins && pins.barTop >= pins.topBottom && pins.barTop < pins.toolsTop && Math.abs(pins.barLeft - pins.toolsLeft) <= 2 && Math.abs(pins.barRight - pins.toolsRight) <= 2, pins);
    // An add-on's account page (as Server Maker's Steam account) opens from the account menu inside Simplify: a real
    // click on the button, nothing over it, no page load (wire:navigate, Jeroen V29, 2-11), Simplify's header on top.
    const accountPage = async (from) => {
      let loads = 0; const onLoad = () => loads++; page.on('load', onLoad);
      await page.locator('.ps-top .ps-account-button >> visible=true').first().click().catch(() => {}); await sleep(700);
      const sel = '#ps-account-actions a[data-tip="Directory account"]';
      const top = await page.evaluate((s) => { const a = [...document.querySelectorAll(s)].find((e) => e.getClientRects().length); if (!a) return null; const r = a.getBoundingClientRect(); return a.contains(document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)); }, sel).catch(() => null);
      await page.locator(`${sel} >> visible=true`).first().click({ timeout: 4000 }).catch(() => {});
      await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {}); await sleep(2000);
      page.off('load', onLoad);
      const at = await page.evaluate(() => ({ path: location.pathname, text: !!document.querySelector('.fi-main-ctn .audit-account-page'), shell: document.documentElement.classList.contains('ps-shell'), header: !!document.querySelector('.ps-admin-top .ps-top'), frames: document.querySelectorAll('.fi-main-ctn iframe').length })).catch(() => null);
      return { from, onTop: top, loads, at };
    };
    const fromOverview = await accountPage('overview');
    await record('account-page', 'Accountmenu · pagina van een add-on in het contentvak');
    const inside = (r) => r.at?.path === '/directory-account' && r.at.text === true && r.at.shell && r.at.header && !r.at.frames;
    say('account-page', fromOverview.onTop === true && fromOverview.loads === 0 && inside(fromOverview), fromOverview);
    await goto(overview, 1500);
    if (urls.servers['Survival SMP']) {
      await goto(urls.servers['Survival SMP'], 3000);
      const fromServer = await accountPage('server');
      say('account-page-server', fromServer.onTop === true && inside(fromServer), fromServer);
      await goto(overview, 2500);
    }
    // Connections (API 6): from the account menu without a page load, measured as a page in the content box; an add-on's
    // row (Server Maker's Steam key) opens its own popup. Without an add-on row there is no menu item and no page.
    {
      // The overview from the goto above may still fire its own load event (goto waits for the DOM only); that load
      // is not the click's, so the count starts once it is done.
      await page.waitForLoadState('load', { timeout: 15000 }).catch(() => {});
      let loads = 0; const onLoad = () => loads++; page.on('load', onLoad);
      // When the click loads the whole page, the result says why: Livewire's own request for the page (and its
      // answer), the page loads after it, the tracked head assets that differ, and what the browser logged.
      const trace = []; const logs = [];
      const onRequest = (r) => { if (r.frame() !== page.mainFrame()) return; const nav = r.headers()['x-livewire-navigate'] !== undefined; if (nav || r.isNavigationRequest()) trace.push({ kind: nav ? 'navigate' : 'document', url: new URL(r.url()).pathname }); };
      const onResponse = (r) => { const q = r.request(); if (q.frame() === page.mainFrame() && (q.headers()['x-livewire-navigate'] !== undefined || q.isNavigationRequest())) trace.push({ kind: 'answer', url: new URL(r.url()).pathname, status: r.status() }); };
      const onConsole = (m) => ['error', 'warning'].includes(m.type()) && logs.push(m.text().slice(0, 200));
      page.on('request', onRequest); page.on('response', onResponse); page.on('console', onConsole);
      const tracked = () => page.evaluate(() => [...document.querySelectorAll('head [data-navigate-track]')].map((e) => e.getAttribute('src') || e.getAttribute('href'))).catch(() => []);
      const before = await tracked();
      await page.locator('.ps-top .ps-account-button >> visible=true').first().click().catch(() => {}); await sleep(700);
      const link = page.locator('#ps-account-actions a[href$="/connections"] >> visible=true').first();
      if (await link.count()) {
        const href = await link.evaluate((a) => ({ href: a.getAttribute('href'), navigate: a.hasAttribute('wire:navigate'), origin: new URL(a.href).origin === location.origin })).catch(() => null);
        await link.click({ timeout: 4000 }).catch(() => {});
        await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {}); await sleep(1500);
        page.off('load', onLoad); page.off('request', onRequest); page.off('response', onResponse); page.off('console', onConsole);
        const after = await tracked();
        const assets = { gone: before.filter((x) => !after.includes(x)), new: after.filter((x) => !before.includes(x)) };
        const at = await page.evaluate(() => ({ path: location.pathname, rows: document.querySelectorAll('.s-connections > *').length, shell: document.documentElement.classList.contains('ps-shell'), header: !!document.querySelector('.ps-admin-top .ps-top') })).catch(() => null);
        await record('connections', 'Accountmenu · Connections');
        say('connections', loads === 0 && at?.path === '/connections' && at.rows > 0 && at.shell && at.header, { loads, ...at, ...(loads ? { link: href, trace, assets, logs } : {}) });
        const connect = page.locator('.s-connections button:has-text("Connect") >> visible=true').first();
        if (await connect.count()) {
          // How the row's button is drawn, so a button that shows as a bare icon says which style took its frame away.
          const look = await connect.evaluate((b) => { const c = getComputedStyle(b); const l = b.querySelector('.fi-btn-label, span'); return { classes: b.className, bg: c.backgroundColor, color: c.color, border: c.borderStyle + ' ' + c.borderWidth, label: l ? { text: l.textContent.trim(), shown: !!l.getClientRects().length, color: getComputedStyle(l).color } : null }; }).catch(() => null);
          await connect.click().catch(() => {}); await sleep(1200);
          const modal = await page.evaluate(() => [...document.querySelectorAll('.fi-modal-window, [role="dialog"]')].some((e) => e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden')).catch(() => false);
          await record('connections-connect', 'Connections · pop-up van een koppeling');
          say('connections-connect', modal, { modal, button: look });
          await escape();
        }
      } else {
        page.off('load', onLoad); page.off('request', onRequest); page.off('response', onResponse); page.off('console', onConsole);
        // Only the core: no add-on gives a row, so the menu has no Connections.
        const item = await page.evaluate(() => [...document.querySelectorAll('#ps-account-actions a[href$="/connections"]')].some((a) => a.getClientRects().length)).catch(() => null);
        say('connections-none', item === false, { item });
        await escape();
      }
      await goto(overview, 1500);
    }
    // Every notification option sits in the bell sheet: this device, messages, Pelican's, and the devices.
    await alpine('d.openAllNotifications()'); await sleep(1500);
    const bell = await page.evaluate(() => { const box = [...document.querySelectorAll('.ps-notify-settings')].find((e) => e.getClientRects().length); if (!box) return { shown: false }; return { shown: true, pelican: !![...box.querySelectorAll('input.ps-switch')].find((i) => /pelican/.test(i.getAttribute('x-on:change') || '') && i.getClientRects().length) }; }).catch(() => null);
    await record('bell-customize', 'Meldingen · alle opties in het bel-blad');
    say('bell-customize', bell && (!bell.shown || bell.pelican), bell);
    await escape();
  }
  if (urls.servers['New World']) {
    await goto(urls.servers['New World'], 3500); await record('server-install-buttons', 'Server · installeert, geen Start/Restart');
    const shown = await page.evaluate(() => [...document.querySelectorAll('.ps button')].filter((b) => b.getClientRects().length && getComputedStyle(b).visibility !== 'hidden' && /^(start|restart|first start)$/i.test((b.innerText.trim() || b.getAttribute('data-tip') || '').trim())).map((b) => b.className + ' ' + (b.innerText.trim() || b.getAttribute('data-tip'))));
    say('install-buttons', shown.length === 0, { shown });
  } else say('install-buttons', false, { note: 'server not seeded: New World' });
  if (phone() && urls.servers['Survival SMP']) {
    await goto(urls.servers['Survival SMP'], 3500);
    await page.evaluate(() => { window.__qc = { adds: 0, gone: 0 }; new MutationObserver((ms) => { for (const m of ms) { for (const n of m.addedNodes) if (n.nodeType === 1 && n.matches('.ps-quick-commands')) window.__qc.adds++; for (const n of m.removedNodes) if (n.nodeType === 1 && n.matches('.ps-quick-commands')) window.__qc.gone++; } }).observe(document.body, { childList: true, subtree: true }); }).catch(() => {});
    // Pelican redraws its console on every status message and reconnect (token-request); do that three times here.
    await page.evaluate(() => { const c = window.Livewire?.all().find((x) => x.el?.querySelector?.('#terminal')); if (c) for (let i = 0; i < 3; i++) setTimeout(() => c.$wire.$refresh(), 800 + i * 1500); }).catch(() => {});
    let hidden = 0;
    for (let i = 0; i < 60; i++) { if (!(await page.evaluate(() => { const r = document.querySelector('.ps-quick-commands'); return !!r && r.getClientRects().length > 0; }).catch(() => false))) hidden++; await sleep(100); }
    const qc = await page.evaluate(() => window.__qc).catch(() => null);
    await record('quick-commands', 'Server · telefoon: quick commands');
    say('quick-commands', qc && qc.adds === 0 && qc.gone === 0 && hidden === 0, { ...qc, hidden });
  }
  // The Admin page on a phone: a page's buttons (Health's refresh, Servers' search and Create) are on the page itself,
  // inside the window, above the bottom bar.
  if (phone()) {
    for (const [url, id] of [['/admin/health', 'host-phone-health'], ['/admin/servers', 'host-phone-servers']]) {
      await goto(url, 2500); await record(id, `Admin-pagina · telefoon, ${url}`);
      const row = await page.evaluate(() => { const bar = document.querySelector('.ps-admin-side .ps-tabbar'); const limit = bar && bar.getClientRects().length ? bar.getBoundingClientRect().top : innerHeight; const tools = [...document.querySelectorAll('.fi-main-ctn :is(.fi-header-actions-ctn, .fi-ta-header-toolbar, .s-health) :is(button, a, input)')].filter((e) => e.getClientRects().length); return { tools: tools.length, inside: tools.every((e) => { const r = e.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth + 1 && r.bottom <= limit + 1; }), bar: !!bar && bar.getClientRects().length > 0 }; }).catch(() => null);
      say(id, !!row && row.tools > 0 && row.inside && row.bar, row);
    }
  }
  // Phones (1.5.4): one frame on every screen. No menu button on top, the bell and the account in the top bar, the
  // overview's bar without "Servers", and every menu a sheet from the bottom over the full width; a tap beside a sheet
  // only closes it.
  if (phone()) {
    const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url || '/';
    const vis = (sel) => page.evaluate((s) => [...document.querySelectorAll(s)].some((e) => e.getClientRects().length > 0 && getComputedStyle(e).visibility !== 'hidden'), sel).catch(() => null);
    const sheet = (sel) => page.evaluate((s) => { const e = [...document.querySelectorAll(s)].find((x) => x.getClientRects().length > 0); if (!e) return null; const r = e.getBoundingClientRect(); return { width: Math.round(r.width), bottom: Math.round(r.bottom), height: Math.round(r.height), window: [innerWidth, innerHeight] }; }, sel).catch(() => null);
    const isSheet = (r, tall = 0) => !!r && r.width >= r.window[0] - 2 && Math.abs(r.bottom - r.window[1]) <= 2 && r.height >= tall * r.window[1];
    await goto(overview, 2500); await ready();
    const top = { menuButton: await vis('.ps-mobile-menu'), bell: await vis('.ps-top [x-ref="notifyButton"]'), account: await vis('.ps-top .ps-account-button'), servers: await page.evaluate(() => [...document.querySelectorAll('.ps-tabbar .ps-tab')].filter((b) => b.getClientRects().length).map((b) => b.innerText.trim())).catch(() => null) };
    await record('phone-frame-overview', 'Telefoon · overzicht: bel en account bovenin, balk onderin');
    say('phone-frame-overview', top.menuButton === false && top.bell && top.account && Array.isArray(top.servers) && top.servers[0] === 'Servers' && top.servers.includes('Admin') && !top.servers.includes('Customize'), top);
    // Pelican's copyright line is at the end of the overview on a phone too, above the bottom bar once you scrolled
    // down, with Simplify's credit in it (K-B29, Jeroen 3-11 on iOS: no copyright on the overview).
    // Measures the text itself: the footer's box runs on under the bar on purpose (its bottom padding).
    await page.evaluate(() => scrollTo(0, document.documentElement.scrollHeight)).catch(() => {}); await sleep(600);
    const foot = await page.evaluate(() => {
      const f = [...document.querySelectorAll('[data-ps-footer], .fi-footer')].find((e) => e.getClientRects().length && getComputedStyle(e).display !== 'none');
      if (!f) return { shown: false };
      const line = f.querySelector('[data-ps-copyright-line]') || f, text = document.createRange(); text.selectNodeContents(line); const r = text.getBoundingClientRect(), bar = [...document.querySelectorAll('.ps-tabbar')].find((e) => e.getClientRects().length)?.getBoundingClientRect();
      const cards = [...document.querySelectorAll('.ps-server-card')].filter((e) => e.getClientRects().length).map((e) => e.getBoundingClientRect().bottom);
      return { shown: true, text: line.innerText.replace(/\s+/g, ' ').trim(), top: Math.round(r.top), bottom: Math.round(r.bottom), bar: bar ? Math.round(bar.top) : null, lastCard: cards.length ? Math.round(Math.max(...cards)) : null, inWindow: r.top >= 0 && r.bottom <= innerHeight, gap: cards.length ? Math.round(r.top - Math.max(...cards)) : null };
    }).catch(() => null);
    await record('phone-overview-footer', 'Telefoon · overzicht onderaan: copyright boven de balk');
    say('phone-overview-footer', !!foot && foot.shown && /©|copyright/i.test(foot.text) && /Simplify/.test(foot.text) && foot.inWindow && (foot.bar === null || foot.bottom <= foot.bar) && (foot.lastCard === null || (foot.gap >= 0 && foot.gap <= 120)), foot);
    await page.evaluate(() => scrollTo(0, 0)).catch(() => {}); await sleep(300);
    await page.locator('.ps-top [x-ref="notifyButton"]').first().click().catch(() => {}); await sleep(900);
    const bell = await sheet('#ps-notify-menu'); await record('phone-sheet-bell', 'Telefoon · meldingen als blad');
    say('phone-sheet-bell', isSheet(bell, 0.85), bell);
    const before = page.url();
    const hit = await page.evaluate(() => { const e = document.elementFromPoint(195, 20); return e ? `${e.tagName.toLowerCase()}.${[...e.classList].slice(0, 2).join('.')}` : null; }).catch(() => null);
    // What the tap did: whether the click reached the page and was taken by the sheet handler, and the bell's state.
    await page.evaluate(() => { window.__tap = null; document.addEventListener('click', (e) => { window.__tap = { target: e.target.tagName.toLowerCase(), prevented: e.defaultPrevented }; }, { capture: true, once: true }); }).catch(() => {});
    await page.mouse.click(195, 20);
    // The sheet leaves with a short transition; a busy runner can take longer than a fixed wait to finish it. The check is
    // whether it closes, so wait up to 3 s for it to be gone and keep how long that took.
    const t0 = Date.now();
    await page.waitForFunction(() => ![...document.querySelectorAll('#ps-notify-menu')].some((e) => e.getClientRects().length > 0 && getComputedStyle(e).visibility !== 'hidden'), null, { timeout: 3000 }).catch(() => {});
    const gone = Date.now() - t0;
    const after = await page.evaluate(() => { const m = document.getElementById('ps-notify-menu'); const ps = document.querySelector('.ps'); return { tap: window.__tap, menu: ps && ps._x_dataStack ? ps._x_dataStack[0].notifyMenu : null, style: m ? m.getAttribute('style') : null, menus: document.querySelectorAll('#ps-notify-menu').length, roots: document.querySelectorAll('.ps').length }; }).catch(() => null);
    const closed = { open: await vis('#ps-notify-menu'), url: page.url() === before, hit, after, ms: gone };
    say('phone-sheet-close', closed.open === false && closed.url, closed);
    for (const sel of CUSTOMIZE) { await page.locator(sel).first().click().catch(() => {}); await sleep(700); }
    const custom = await sheet('#ps-overview-appearance-menu'); await record('phone-sheet-customize', 'Telefoon · Customize (accountmenu) als blad');
    await page.locator('#ps-overview-appearance-menu [data-tab="groups"]').first().click().catch(() => {}); await sleep(300);
    await page.locator('#ps-overview-appearance-menu .ps-customize-edit').first().click().catch(() => {}); await sleep(900);
    const groups = await sheet('#ps-overview-groups-menu'); await record('phone-sheet-groups', 'Telefoon · Server groups als blad');
    say('phone-sheet-customize', isSheet(custom) && isSheet(groups), { custom, groups });
    await escape();
    await page.locator('.ps-top .ps-account-button').first().click().catch(() => {}); await sleep(900);
    const account = await sheet('.ps-account.account-open .ps-account-menu'); await record('phone-sheet-account', 'Telefoon · account als blad');
    say('phone-sheet-account', isSheet(account), account);
    await escape();
    if (urls.servers['Survival SMP']) {
      await goto(urls.servers['Survival SMP'], 3500);
      const srv = { menuButton: await vis('.ps-mobile-menu'), back: await page.evaluate(() => { const b = [...document.querySelectorAll('.ps-back-overview')].find((e) => e.getClientRects().length); return b ? Math.round(b.getBoundingClientRect().width) : null; }).catch(() => null) };
      await page.locator('.ps-phone-more > .ps-phone-section').first().click().catch(() => {}); await sleep(900);
      const more = await sheet('#ps-phone-more-menu'); await record('phone-sheet-more', 'Telefoon · server More als blad');
      say('phone-frame-server', srv.menuButton === false && srv.back && srv.back <= 48 && isSheet(more), { ...srv, more });
      await escape();
    }
    // The Admin page (Jeroen 3-11): the bottom bar is Servers, New, Admin (no Menu tab), and Admin opens the menu as a
    // page of its own: the tree in place of the page, under the header, without a card. A page's breadcrumb
    // (group › page) is its title, Pelican's heading is gone and the page has no card; the group and a swipe from the
    // left edge bring the menu back.
    const adminPhone = () => page.evaluate(() => {
      const shown = (e) => !!e && e.getClientRects().length > 0 && getComputedStyle(e).display !== 'none' && getComputedStyle(e).visibility !== 'hidden';
      const side = document.querySelector('.ps-admin-side .ps-host-side'), card = document.querySelector('.fi-layout > .fi-main-ctn'), top = document.querySelector('.ps-admin-top .ps-top'), crumb = document.querySelector('.ps-admin-crumb');
      const cs = card && getComputedStyle(card);
      return { tabs: [...document.querySelectorAll('.ps-admin-side .ps-tabbar .ps-tab')].filter(shown).map((b) => b.innerText.trim()), menu: shown(side), leaves: [...document.querySelectorAll('.ps-admin-side .ps-host-leaf')].filter(shown).length, groups: [...document.querySelectorAll('.ps-admin-side .ps-host-group-head')].filter(shown).length, page: shown(card),
        below: !!side && !!top && side.getBoundingClientRect().top >= top.getBoundingClientRect().bottom - 1, crumb: shown(crumb) ? crumb.innerText.replace(/\s+/g, ' ').trim() : null,
        heading: [...document.querySelectorAll('.fi-main .fi-header-heading')].some(shown), plus: (() => { const e = document.querySelector('.ps-admin-side .ps-tab-plus'); return e ? getComputedStyle(e).backgroundColor : null; })(), alpine: (() => { const el = document.querySelector('.ps-admin-side'); const d = el && window.Alpine?.$data(el); return d ? { open: d.open, here: d.here, crumb: d.crumb, url: location.pathname + location.hash } : null; })(), cardBox: !!cs && (cs.borderTopWidth !== '0px' || !/rgba\(0, 0, 0, 0\)|transparent/.test(cs.backgroundColor)), pageScroll: document.documentElement.scrollHeight - innerHeight };
    }).catch(() => null);
    await goto(overview, 1500);
    // New keeps the panel's accent on the Admin page too (Jeroen 3-11: it turned grey there).
    const overviewPlus = await page.evaluate(() => { const e = [...document.querySelectorAll('.ps-tabbar .ps-tab-plus')].find((x) => x.getClientRects().length); return e ? getComputedStyle(e).backgroundColor : null; }).catch(() => null);
    await page.locator('.ps-tabbar a.ps-tab:has(use[href="#ps-shield"]) >> visible=true').first().click().catch(() => {}); await page.waitForLoadState('domcontentloaded').catch(() => {}); await settle(page, 1800);
    const opened = await adminPhone(); await record('phone-frame-host', 'Telefoon · Admin opent het menu als pagina');
    say('phone-admin-accent', !!opened && !!overviewPlus && opened.plus === overviewPlus, { overviewPlus, admin: opened?.plus });
    say('phone-frame-host', !!opened && opened.tabs[0] === 'Servers' && opened.tabs[opened.tabs.length - 1] === 'Admin' && !opened.tabs.includes('Menu') && opened.menu && opened.groups > 1 && opened.below && !opened.page && opened.pageScroll <= 1, opened);
    // With the menu open, the bottom bar and the header keep their taps (K-B24, Jeroen 3-11: every tap showed
    // Servers › Servers). Admin again keeps the menu, the account button opens the account menu.
    await page.locator('.ps-admin-side .ps-tabbar button.ps-tab:has(use[href="#ps-shield"]) >> visible=true').first().click().catch(() => {}); await sleep(600);
    const again = await adminPhone();
    say('phone-admin-tab-again', !!again && again.menu && !again.page, again);
    await page.locator('.ps-top .ps-account-button >> visible=true').first().click().catch(() => {}); await sleep(700);
    const accountOpen = await page.evaluate(() => ({ account: !!document.querySelector('.ps-account.account-open'), menu: [...document.querySelectorAll('.ps-admin-side .ps-host-side')].some((e) => e.getClientRects().length > 0) })).catch(() => null);
    say('phone-admin-account', !!accountOpen && accountOpen.account, accountOpen);
    await escape();
    // Admin starts on Health, a group of one page: a page under a group that folds (Servers) opens from its group.
    if (!(await page.locator('.ps-admin-side .ps-host-leaf >> visible=true').count())) { await page.locator('.ps-admin-side button.ps-host-group-head >> visible=true').first().click().catch(() => {}); await sleep(400); }
    const leaf = page.locator('.ps-admin-side .ps-host-group:has(.ps-host-leaf) .ps-host-leaf:not([aria-current]) >> visible=true').first();
    const leafText = (await leaf.innerText().catch(() => '')).trim();
    await leaf.click().catch(() => {}); await settle(page, 1800);
    await page.waitForFunction(() => [...document.querySelectorAll('.ps-admin-crumb')].some((e) => e.getClientRects().length), null, { timeout: 5000 }).catch(() => {});
    const shownPage = await adminPhone(); await record('phone-admin-crumb', 'Telefoon · Admin-pagina met kruimelpad als titel');
    say('phone-admin-crumb', !!leafText && !!shownPage && !shownPage.menu && shownPage.page && !!shownPage.crumb && shownPage.crumb.endsWith(leafText) && !shownPage.heading && !shownPage.cardBox, { ...shownPage, leafText });
    await page.evaluate(() => { const at = (x) => ({ identifier: 1, target: document.body, clientX: x, clientY: 400 }); const fire = (type, x) => document.dispatchEvent(new TouchEvent(type, { bubbles: true, touches: type === 'touchend' ? [] : [new Touch(at(x))], changedTouches: [new Touch(at(x))] })); fire('touchstart', 6); fire('touchend', 220); }).catch(() => {}); await sleep(700);
    const swiped = await adminPhone(); await record('phone-admin-swipe', 'Telefoon · swipe vanaf links: terug naar het menu');
    say('phone-admin-swipe', !!swiped && swiped.menu && !swiped.page, swiped);
    await escape();
    await page.locator('.ps-admin-crumb button >> visible=true').first().click().catch(() => {}); await sleep(600);
    const back = await adminPhone();
    say('phone-admin-crumb-back', !!back && back.menu && !back.page, back);
    // Servers in the bottom bar goes to the overview, also with the menu open (not to the admin page under it).
    await page.locator('.ps-admin-side .ps-tabbar a.ps-tab:has(use[href="#ps-grid"]) >> visible=true').first().click().catch(() => {}); await page.waitForLoadState('domcontentloaded').catch(() => {}); await settle(page, 1500);
    const toOverview = await page.evaluate(() => ({ url: location.pathname, admin: !!document.querySelector('.ps-admin-side') })).catch(() => null);
    const home = new URL(overview, base).pathname.replace(/\/$/, '');
    say('phone-admin-tab-servers', !!toOverview && !toOverview.admin && toOverview.url.replace(/\/$/, '') === home, { ...toOverview, home });
    await escape();
    // Pelican's lists inside Simplify (the Admin page) are short rows: no checkboxes, no column titles and no egg
    // descriptions. The same page in the legacy panel keeps Pelican's own table (K-B5, Jeroen 2-11).
    const measureEggs = () => { const rows = [...document.querySelectorAll('.fi-ta-row')].filter((r) => r.getClientRects().length); const shown = (s) => [...document.querySelectorAll(s)].filter((e) => e.getClientRects().length > 0).length; return { rows: rows.length, tallest: Math.max(0, ...rows.map((r) => Math.round(r.getBoundingClientRect().height))), checkboxes: shown('.fi-ta-table .fi-ta-record-checkbox'), head: shown('.fi-ta-table thead'), descriptions: shown('.fi-ta-table .fi-ta-cell :is([class$="-description"], [class*="-description "])'), names: [...document.querySelectorAll('.fi-ta-row')].filter((r) => r.getClientRects().length && /[A-Za-z]{3}/.test(r.innerText)).length, wide: document.documentElement.scrollWidth > innerWidth + 1 }; };
    await goto(new URL('/admin/eggs', base).href, 2500);
    const eggs = await page.evaluate(measureEggs).catch(() => null);
    await record('phone-eggs', 'Telefoon · Eggs als korte rijen (Simplify)');
    say('phone-eggs', eggs && eggs.rows > 0 && eggs.names === eggs.rows && eggs.tallest <= 90 && !eggs.checkboxes && !eggs.head && !eggs.descriptions, eggs);
    await goto(new URL('/admin/eggs?simplify_legacy=1', base).href, 2500);
    const legacy = await page.evaluate(measureEggs).catch(() => null);
    await record('phone-eggs-legacy', 'Telefoon · Eggs in het legacy-paneel (Pelicans tabel)');
    say('phone-eggs-legacy', legacy && legacy.rows > 0 && legacy.head > 0 && !legacy.wide, legacy);
    await goto(new URL('/admin/eggs?simplify_legacy=0', base).href, 800);
    // Nothing saves itself (P-38): Save stays, a change shows "Changes made" with Save and Discard, and leaving asks first.
    await goto(new URL('/admin/simplify', base).href, 2500);
    const save = await vis('.s-save-button, .s-settings > .s-bar button[type="submit"]');
    const sw = page.locator('.s-settings .fi-active :is([role="switch"], input[type="checkbox"]) >> visible=true').first();
    const unsaved = { save, bar: null, asked: null, stayed: null, gone: null };
    if (await sw.count()) {
      await sw.click().catch(() => {});
      await page.waitForSelector('.s-unsaved:not([hidden])', { timeout: 6000 }).catch(() => {});
      unsaved.bar = await vis('.s-unsaved');
      await record('phone-unsaved', 'Telefoon · Changes made met Save');
      // Leaving through a link of the page asks Save, Discard or Cancel; Cancel stays on the page with the change.
      await page.evaluate(() => { const a = Object.assign(document.createElement('a'), { href: '/admin' }); a.setAttribute('wire:navigate', ''); a.id = 'ps-audit-leave'; a.textContent = '.'; document.body.append(a); a.click(); }).catch(() => {});
      await page.waitForSelector('.s-unsaved-dialog', { timeout: 3000 }).catch(() => {});
      unsaved.asked = await vis('.s-unsaved-dialog');
      await record('phone-unsaved-leave', 'Telefoon · weggaan vraagt eerst');
      await page.locator('.s-unsaved-dialog .s-unsaved-btn').first().click().catch(() => {});
      await sleep(300);
      unsaved.stayed = new URL(page.url()).pathname.endsWith('/admin/simplify') && !(await vis('.s-unsaved-dialog'));
      // Setting it back is no change any more: the bar goes away.
      await sw.click().catch(() => {});
      await page.waitForSelector('.s-unsaved', { state: 'hidden', timeout: 3000 }).catch(() => {});
      unsaved.gone = !(await vis('.s-unsaved'));
    }
    say('phone-unsaved', save === true && unsaved.bar === true && unsaved.asked === true && unsaved.stayed === true && unsaved.gone === true, unsaved);
  }
  // Pull to refresh in the installed app (pull.js): only on the overview (Jeroen 2-11). There a finger pulled down from
  // the top shows the mark and reloads; in the settings a pull must not reload (it sent you back to the overview).
  if (phone()) {
    const pull = async (url, name, label) => {
      const p2 = await page.context().newPage(); await quiet(p2);
      await p2.addInitScript(() => { const mm = window.matchMedia.bind(window); window.matchMedia = (q) => (/display-mode: *standalone|pointer: *coarse/.test(q) ? { matches: true, media: q, onchange: null, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {} } : mm(q)); });
      await p2.setViewportSize(VIEWPORTS.phone390);
      await p2.goto(new URL(url, base).href, { waitUntil: 'load' }).catch(() => {}); await settle(p2, 1200);
      const cdp = await p2.context().newCDPSession(p2); await cdp.send('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 1 });
      let loads = 0; p2.on('load', () => loads++);
      const touch = (type, y) => cdp.send('Input.dispatchTouchEvent', { type, touchPoints: type === 'touchEnd' ? [] : [{ x: 195, y }] });
      await touch('touchStart', 220);
      for (let y = 230; y <= 420; y += 10) { await touch('touchMove', y); await sleep(16); }
      const mark = await p2.evaluate(() => { const m = document.querySelector('.s-pull'); return m ? { shown: m.getClientRects().length > 0, ready: m.classList.contains('is-ready') } : null; }).catch(() => null);
      const shot = page; page = p2; await record(name, label); page = shot;
      // The reload may take longer than a few seconds on the test panel: wait for it, up to 15 s.
      const reloaded = p2.waitForEvent('load', { timeout: 15000 }).catch(() => null);
      await touch('touchEnd', 420); await reloaded; await sleep(500);
      const at = new URL(p2.url()).pathname;
      await p2.close().catch(() => {});
      return { mark, loads, at };
    };
    const home = await pull('/simplify', 'pull-refresh', 'App · overzicht naar beneden vegen');
    say('pull-refresh', !!(home.mark && home.mark.shown && home.mark.ready) && home.loads >= 1, home);
    const settings = await pull('/admin/simplify', 'pull-settings', 'App · instellingen naar beneden vegen');
    say('pull-settings', !settings.mark && settings.loads === 0 && settings.at === '/admin/simplify', settings);
    // The browser's own pull to refresh (Android Chrome) is off on every page, so only the overview's own pull reloads;
    // swiping a sheet down to close it, or pulling in the settings, never reloads (Jeroen 2-11).
    const native = async (url) => { const p3 = await page.context().newPage(); await quiet(p3); await p3.setViewportSize(VIEWPORTS.phone390);
      await p3.goto(new URL(url, base).href, { waitUntil: 'load' }).catch(() => {}); await settle(p3, 800);
      const v = await p3.evaluate(() => [getComputedStyle(document.documentElement).overscrollBehaviorY, getComputedStyle(document.body).overscrollBehaviorY]).catch(() => null);
      await p3.close().catch(() => {}); return v; };
    const nativePull = { overview: await native('/simplify'), settings: await native('/admin/simplify') };
    say('pull-native-off', Object.values(nativePull).every((v) => v && v.every((x) => x === 'none')), nativePull);
    // A sheet swiped down on the overview closes and does not reload.
    {
      const p4 = await page.context().newPage(); await quiet(p4);
      await p4.addInitScript(() => { const mm = window.matchMedia.bind(window); window.matchMedia = (q) => (/display-mode: *standalone|pointer: *coarse/.test(q) ? { matches: true, media: q, onchange: null, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {} } : mm(q)); });
      await p4.setViewportSize(VIEWPORTS.phone390);
      await p4.goto(new URL('/simplify', base).href, { waitUntil: 'load' }).catch(() => {}); await settle(p4, 1200);
      await p4.evaluate(() => { const d = window.Alpine.$data(document.querySelector('.ps')); d.appearanceMenu = true; }).catch(() => {}); await sleep(700);
      const box = await p4.evaluate(() => { const m = document.querySelector('#ps-overview-appearance-menu'); const r = m && m.getClientRects().length ? m.getBoundingClientRect() : null; return r && { x: Math.round(r.left + r.width / 2), y: Math.round(r.top + 12) }; }).catch(() => null);
      const cdp = await p4.context().newCDPSession(p4); await cdp.send('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 1 });
      let loads = 0; p4.on('load', () => loads++);
      if (box) { const t = (type, y) => cdp.send('Input.dispatchTouchEvent', { type, touchPoints: type === 'touchEnd' ? [] : [{ x: box.x, y }] });
        await t('touchStart', box.y); for (let y = box.y + 10; y <= box.y + 200; y += 10) { await t('touchMove', y); await sleep(16); } await t('touchEnd', box.y + 200); await sleep(2500); }
      const shut = await p4.evaluate(() => !document.querySelector('#ps-overview-appearance-menu')?.getClientRects().length).catch(() => null);
      await p4.close().catch(() => {});
      say('pull-sheet', !!box && shut === true && loads === 0, { box, shut, loads });
    }
  }
}

// Notifications (1.5.0, #90): Mark read on one, Clear read, ✕ to Archived, and Archived at the bottom of the window with
// Show more and Mark as unread. Each step checks that the counts move the right way.
async function notifyFlow() {
  const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url || '/';
  const onPhone = phone();
  const openBell = async () => { await goto(overview); await ready(); await sleep(600); if (onPhone) { if (!(await page.locator('.ps-tabbar button[aria-controls="ps-navigation-items"]').count())) await page.locator('.ps-mobile-menu').first().click().catch(() => {}); else await page.locator('.ps-tabbar button[aria-controls="ps-navigation-items"]').first().click().catch(() => {}); await sleep(500); } await page.locator('[x-ref="notifyButton"]').first().click().catch(() => {}); await sleep(900); };
  const count = (scope) => page.evaluate((sc) => { const root = document.querySelector(sc); if (!root) return null; const vis = (e) => e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden'; const items = [...root.querySelectorAll('.ps-notify-item')].filter((e) => vis(e) && !e.closest('.ps-notify-pinned')); const inArchive = (e) => !!e.closest('#ps-notify-archived'); return { items: items.filter((e) => !inArchive(e)).length, unread: items.filter((e) => !inArchive(e) && !e.classList.contains('is-read')).length, archived: items.filter(inArchive).length }; }, scope).catch(() => null);
  const say = (step, ok, detail) => problems.push(`${stand} notify-${step}: ${ok ? 'ok' : 'WRONG'} ${JSON.stringify(detail)}`);
  const bell = '#ps-notify-menu';
  await openBell();
  // The list comes in after the window opens: wait for it (up to 8 s) rather than a fixed pause.
  await page.waitForFunction((sc) => !!document.querySelector(`${sc} .ps-notify-item`), bell, { timeout: 8000 }).catch(() => null);
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
  // On a day without a holiday seasons.js and seasons.css do not load, once this device knows the year's days (V47);
  // on Halloween they do.
  for (const [time, want] of [['2026-07-10T12:00:00', false], ['2026-10-30T12:00:00', true]]) {
    page = await fresh(time);
    try {
      await goto(overview); await sleep(1500);
      const loaded = []; page.on('request', (r) => { if (/seasons\.(js|css)/.test(r.url())) loaded.push(r.url().split('?')[0].split('/').pop()); });
      await page.reload().catch(() => {}); await page.waitForLoadState('load').catch(() => {}); await sleep(2000);
      problems.push(`${stand} season-lazy-${time.slice(5, 10)}: ${(loaded.length > 0) === want ? 'ok' : 'WRONG'} ${JSON.stringify({ want, loaded })}`);
    } catch (e) { problems.push(`${stand} season-lazy: ${e.message.slice(0, 160)}`); }
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
    await click('.s-settings-nav a:has-text("Appearance")', 900);
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
      if (themeOnly && theme !== themeOnly) continue;
      stand = `${name}-${theme}`;
      console.log(`[${new Date().toISOString().slice(11, 19)}] ${stand} (${screens.length} screens, ${problems.length} problems so far)`);
      await page.setViewportSize(size);
      if (section) {
        // One or more sections (comma separated), each on its own stands.
        const sections = { first: [['d1440', 'phone390'], () => firstFlow(theme)], layers: [['d1440', 'laag1366', 'laag1100', 'phone390'], () => layersFlow()], nav: [['d1440', 'laag1366'], () => navFlow()], create: [['d1440', 'laag1366', 'phone390'], () => createFlow()],
          maker: [['d1440', 'phone390'], () => makerFlow()],
          schedule: [['d1440', 'laag1366', 'laag1100', 'phone390'], () => scheduleFlow()],
          extras: [['d1440', 'laag1366', 'laag1100', 'phone390'], () => extrasFlow(theme)], fixes: [['d1440', 'phone390'], () => fixesFlow()], hub: [['d1440', 'laag1366', 'phone390'], () => hubFlow()], seasons: [['d1440', 'tablet768', 'phone390'], () => seasons(theme)] };
        for (const part of section.split(',')) { const [where, run] = sections[part] || [[], null]; if (!run || !where.includes(name)) continue; try { await setTheme(theme); await run(); } catch (e) { problems.push(`${stand} ${part}: ${e.message.slice(0, 200)}`); } }
        continue;
      }
      try { await round(theme); } catch (e) { problems.push(`${stand} ${screen}: round stopped: ${e.message.slice(0, 200)}`); console.log('  ! ' + e.message.slice(0, 200)); if (page.isClosed()) { page = await context.newPage(); await quiet(page); await login('demo@example.com', 'DemoPassword123!'); } }
    }
  }
  // The customer's view, two stands (with the dark round, so a light job does not repeat them).
  const klant = want('klant') && themeOnly !== 'light' && ['d1440', 'phone390'].some((n) => !only || only.includes(n));
  if (klant) {
  const customer = await browser.newContext({ viewport: VIEWPORTS.d1440, baseURL: base });
  const cp = await customer.newPage(); const admin = page; page = cp; page.setDefaultTimeout(8000); await quiet(page);
  await login('sam@example.com', 'DemoPassword123!');
  for (const name of ['d1440', 'phone390']) {
    if (only && !only.includes(name)) continue;
    stand = `klant-${name}-dark`; await page.setViewportSize(VIEWPORTS[name]);
    try {
      const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url || '/';
      await goto(overview); await setTheme('dark'); await goto(overview); await sleep(800); await record('overview', 'Klant · overzicht');
      // A suspended server tells its owner why, inside its card, with the add-on's main link (Hosting V26; the audit's
      // plugin gives Pay invoice): the note sits within the card's edges, without power buttons, and no half-covered usage
      // tile shows above it.
      const sus = await page.evaluate(() => { const card = [...document.querySelectorAll('.ps-server-card')].find((c) => /Old Factions/.test(c.innerText)); const note = card?.querySelector('.ps-card-suspended'); if (!note || !note.getClientRects().length) return { card: !!card, note: false }; const c = card.getBoundingClientRect(), n = note.getBoundingClientRect(); const peek = [...card.querySelectorAll('.ps-card-mini-metric')].filter((e) => { const r = e.getBoundingClientRect(); return e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden' && r.top < n.top - 1 && r.bottom > n.top + 1; }).length; return { card: true, note: true, inside: n.left >= c.left - 1 && n.right <= c.right + 1 && n.bottom <= c.bottom + 1 && n.top >= c.top - 1, power: [...card.querySelectorAll('.ps-power-wrap')].some((e) => e.getClientRects().length), peek }; }).catch(() => null);
      problems.push(`${stand} suspended-card: ${!!sus && sus.card && sus.note && sus.inside && !sus.power && !sus.peek ? 'ok' : 'WRONG'} ${JSON.stringify(sus)}`);
      // The card alone, light and dark, for Simplify-Hosting's screenshots (docs/screenshots there).
      for (const theme of ['dark', 'light']) {
        if (theme === 'light') { await setTheme('light'); await goto(overview); await sleep(800); }
        const card = page.locator('.ps-server-entry', { hasText: 'Old Factions' }).first();
        fs.mkdirSync(path.join(out, `klant-${name}`), { recursive: true });
        await card.screenshot({ path: path.join(out, `klant-${name}`, `suspended-card-${theme}.png`) }).catch((e) => problems.push(`${stand} suspended-card-shot: WRONG ${JSON.stringify(e.message.slice(0, 120))}`));
      }
      await setTheme('dark'); await goto(overview); await sleep(800);
      await click('.ps-account-button'); await record('account', 'Klant · accountmenu'); await escape();
      await goto(urls.servers['Survival SMP'], 3000); await record('server-console', 'Klant · console');
      await click('.ps-info-more-button'); await record('server-more', 'Klant · ⋯-menu'); await escape();
    } catch (e) { problems.push(`${stand}: ${e.message.slice(0, 200)}`); }
  }
  page = admin;
  }
  fs.writeFileSync(path.join(out, 'result.json'), JSON.stringify({ base, screens, problems }, null, 1));
  const flagged = screens.filter((s) => (s.main?.problems?.length || 0) + (s.frames || []).reduce((n, f) => n + (f.problems?.length || 0), 0));
  console.log(`${screens.length} screens, ${flagged.length} with findings, ${problems.length} problems`);
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
