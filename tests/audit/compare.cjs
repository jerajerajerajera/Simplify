// The server overview measured, to compare Simplify with PelicanSimplify 0.7.0 on the same eight servers: per view
// (cards and list) the height of one card or row, the gaps, how many servers fit without scrolling, and the parts
// that take the room (the bar above the servers, the card's own parts and their padding). Writes compare.json and
// a screenshot per view.
// node compare.cjs --base http://127.0.0.1:8001 --out result --only d1440
const fs = require('fs'), path = require('path');
const { chromium } = require('playwright');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base', 'http://127.0.0.1:8001'), out = arg('out', 'result');
const only = arg('only', '') ? arg('only').split(',') : ['d1440', 'laag1366'];
const VIEWPORTS = { d1440: { width: 1440, height: 900 }, laag1366: { width: 1366, height: 650 }, d1920: { width: 1920, height: 1080 }, phone390: { width: 390, height: 844 } };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
fs.mkdirSync(out, { recursive: true });

// Everything is read in the page: the boxes, their padding and the parts of the first card.
const measure = () => {
  const px = (v) => Math.round(parseFloat(v) || 0);
  const box = (e) => { if (!e) return null; const r = e.getBoundingClientRect(), cs = getComputedStyle(e); return { h: Math.round(r.height), w: Math.round(r.width), top: Math.round(r.top), pad: [cs.paddingTop, cs.paddingRight, cs.paddingBottom, cs.paddingLeft].map(px).join(' '), gap: cs.display.includes('grid') || cs.display.includes('flex') ? `${px(cs.rowGap)}/${px(cs.columnGap)}` : null }; };
  const name = (e) => (e.className && String(e.className).trim().split(/\s+/).filter((c) => !/^(is-|has-|x-)/.test(c)).slice(0, 2).join('.')) || e.tagName.toLowerCase();
  const visible = (e) => e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden' && e.getBoundingClientRect().height > 0;
  const grid = document.querySelector('.ps-server-grid'), scroll = document.querySelector('.ps-server-scroll') || document.scrollingElement;
  const cards = [...document.querySelectorAll('.ps-server-entry')].filter((e) => visible(e) && !e.classList.contains('is-node-heading'));
  const view = scroll.getBoundingClientRect(), bottom = Math.min(view.bottom, innerHeight);
  const fit = cards.filter((e) => { const r = e.getBoundingClientRect(); return r.top >= view.top - 1 && r.bottom <= bottom + 1; }).length;
  const rows = [...new Set(cards.map((e) => Math.round(e.getBoundingClientRect().top)))];
  const card = cards[0] && (cards[0].querySelector('.ps-server-card') || cards[0]);
  const parts = [];
  const walk = (e, depth) => { for (const c of e.children) { if (!visible(c)) continue; const b = box(c); if (b.h < 4) continue; parts.push({ part: '  '.repeat(depth) + name(c), ...b, top: b.top - Math.round(card.getBoundingClientRect().top), font: getComputedStyle(c).fontSize }); if (depth < 2) walk(c, depth + 1); } };
  if (card) walk(card, 0);
  // What sits above the first server: the top bar, the title, the toolbar.
  const above = [];
  if (cards[0]) { const first = cards[0].getBoundingClientRect().top; for (const e of document.querySelectorAll('header, nav, .ps-top, .ps-top-overview, .ps-overview-toolbar, .ps-toolbar, .ps-server-stage > *, .ps-overview > *, .fi-topbar')) { if (!visible(e)) continue; const r = e.getBoundingClientRect(); if (r.bottom <= first + 1 && r.height > 8 && r.width > 200) above.push({ part: name(e), ...box(e) }); } }
  const badges = card ? [...card.querySelectorAll('[class*="badge"], [class*="chip"], [class*="pill"], [class*="tag"]')].filter(visible).map((e) => ({ part: name(e), ...box(e), text: e.textContent.trim().slice(0, 24) })) : [];
  return { window: `${innerWidth}x${innerHeight}`, servers: cards.length, fitWithoutScrolling: fit, columns: rows.length ? cards.filter((e) => Math.round(e.getBoundingClientRect().top) === rows[0]).length : 0,
    rowPitch: rows.length > 1 ? rows[1] - rows[0] : null, card: box(cards[0]), cardInner: box(card), grid: box(grid), scrollArea: box(scroll), firstServerTop: cards[0] ? Math.round(cards[0].getBoundingClientRect().top) : null,
    scrolls: scroll.scrollHeight > scroll.clientHeight + 2 ? scroll.scrollHeight - scroll.clientHeight : 0, pageScroll: document.documentElement.scrollHeight - innerHeight, above, parts, badges };
};

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: VIEWPORTS.d1440, baseURL: base, deviceScaleFactor: 1 });
  const page = await context.newPage(); page.setDefaultTimeout(10000);
  await context.addInitScript(() => { try { localStorage.setItem('pelicanSimplify.theme', 'dark'); localStorage.setItem('theme', 'dark'); } catch {} });
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.goto('/login'); await page.fill('#form\\.login', 'demo@example.com'); await page.fill('#form\\.password', 'DemoPassword123!');
  await Promise.all([page.waitForNavigation().catch(() => {}), page.locator('button[type="submit"]').first().click()]); await sleep(1500);
  const result = { base, product: null, stands: {} };
  for (const name of only) {
    const size = VIEWPORTS[name]; if (!size) continue;
    await page.setViewportSize(size);
    const found = [];
    for (const url of ['/simplify', '/']) { await page.goto(url).catch(() => {}); await page.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {}); await page.waitForSelector('.ps-server-entry', { timeout: 15000 }).catch(() => {}); await sleep(1500); if (await page.locator('.ps-server-entry').count()) break; fs.mkdirSync(path.join(out, name), { recursive: true }); await page.screenshot({ path: path.join(out, name, `tried-${found.length}.png`) }); found.push(url + ' ' + page.url() + ' ' + (await page.title().catch(() => ''))); }
    result.product = await page.evaluate(() => document.querySelector('.ps')?.getAttribute('data-version') || document.querySelector('meta[name="generator"]')?.content || null).catch(() => null);
    result.stands[name] = { url: page.url(), tried: found };
    for (const view of ['grid', 'list']) {
      await page.evaluate((v) => { const el = document.querySelector('.ps'); const d = el && window.Alpine && window.Alpine.$data(el); if (d) { const want = v; if (typeof d.setOverviewView === 'function') d.setOverviewView(want); else d.overviewView = want; } }, view).catch(() => {});
      await sleep(1500);
      const m = await page.evaluate(measure).catch((e) => ({ error: e.message.slice(0, 200) }));
      m.overviewView = await page.evaluate(() => { const el = document.querySelector('.ps'); return el && window.Alpine ? window.Alpine.$data(el).overviewView : null; }).catch(() => null);
      fs.mkdirSync(path.join(out, name), { recursive: true });
      await page.screenshot({ path: path.join(out, name, `overview-${view}.png`) });
      await page.screenshot({ path: path.join(out, name, `overview-${view}-full.png`), fullPage: true }).catch(() => {});
      result.stands[name][view] = m;
      console.log(name, view, JSON.stringify({ servers: m.servers, fit: m.fitWithoutScrolling, card: m.card && m.card.h, pitch: m.rowPitch, view: m.overviewView }));
    }
  }
  fs.writeFileSync(path.join(out, 'compare.json'), JSON.stringify(result, null, 1));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
