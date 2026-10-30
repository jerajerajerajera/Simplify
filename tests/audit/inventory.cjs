// The functions a product shows, harvested by clicking through it: per screen every button, link, menu item, tab and
// settings field a person can see, also inside the menus that open from a button. Run once on PelicanSimplify 0.7.0
// and once on Simplify; diff-inventory.py lists what 0.7.0 has and Simplify does not.
// node inventory.cjs --base http://127.0.0.1:8001 --out result
const fs = require('fs'), path = require('path');
const { chromium } = require('playwright');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base', 'http://127.0.0.1:8001'), out = arg('out', 'result');
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
fs.mkdirSync(path.join(out, 'inventory'), { recursive: true });

// Everything a person can use on the page now, with the place it sits in.
const harvest = () => {
  const v = (e) => { if (!e.getClientRects().length) return false; const cs = getComputedStyle(e); if (cs.visibility === 'hidden' || cs.display === 'none' || +cs.opacity === 0) return false; const r = e.getBoundingClientRect(); return r.width > 2 && r.height > 2; };
  const text = (e) => (e.getAttribute('aria-label') || e.innerText || e.getAttribute('data-tip') || e.getAttribute('title') || e.value || '').trim().replace(/\s+/g, ' ').slice(0, 60);
  const where = (e) => { const c = e.closest('[role="menu"], [role="dialog"], nav, aside, header, .fi-tabs, form, section, article'); return c ? (c.getAttribute('aria-label') || c.getAttribute('role') || c.tagName.toLowerCase()) : 'page'; };
  const items = [];
  for (const e of document.querySelectorAll('button, a[href], [role="menuitem"], [role="tab"], summary, input:not([type="hidden"]), select, textarea, [role="switch"], [role="checkbox"]')) {
    if (!v(e)) continue;
    let label = text(e);
    if (/^(input|select|textarea)$/i.test(e.tagName) || e.getAttribute('role') === 'switch' || e.getAttribute('role') === 'checkbox') {
      const id = e.id && document.querySelector(`label[for="${CSS.escape(e.id)}"]`);
      label = ((id && id.innerText) || e.closest('label')?.innerText || e.getAttribute('aria-label') || e.getAttribute('placeholder') || e.name || '').trim().replace(/\s+/g, ' ').slice(0, 60);
      items.push({ kind: 'field', label, where: where(e) }); continue;
    }
    if (!label) continue;
    items.push({ kind: e.getAttribute('role') === 'tab' ? 'tab' : e.getAttribute('role') === 'menuitem' ? 'menu' : e.tagName === 'A' ? 'link' : 'button', label, where: where(e), href: e.tagName === 'A' ? e.getAttribute('href') : undefined });
  }
  return { title: document.title, h1: (document.querySelector('h1, h2') || {}).innerText || '', items };
};

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, baseURL: base })).newPage(); page.setDefaultTimeout(8000);
  page.on('dialog', (d) => d.dismiss().catch(() => {}));
  await page.goto('/login'); await page.fill('#form\\.login', 'demo@example.com'); await page.fill('#form\\.password', 'DemoPassword123!');
  await Promise.all([page.waitForNavigation().catch(() => {}), page.locator('button[type="submit"]').first().click()]); await sleep(1500);
  const screens = {}, seen = new Set();
  const settle = async () => { await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {}); await sleep(1500); };
  const take = async (key) => {
    const h = await page.evaluate(harvest).catch(() => null); if (!h) return;
    // Menus and popovers that open from a button on this screen: open each, harvest what appeared, close it.
    const openers = await page.locator('button[aria-haspopup], button[aria-expanded="false"], summary').evaluateAll((els) => els.map((e, i) => i)).catch(() => []);
    for (const i of openers.slice(0, 25)) {
      const b = page.locator('button[aria-haspopup], button[aria-expanded="false"], summary').nth(i);
      if (!(await b.isVisible().catch(() => false))) continue;
      const before = new Set(h.items.map((x) => x.kind + x.label));
      await b.click({ timeout: 3000 }).catch(() => {}); await sleep(500);
      const after = await page.evaluate(harvest).catch(() => null);
      if (after) for (const x of after.items) if (!before.has(x.kind + x.label)) { h.items.push({ ...x, opener: (await b.innerText().catch(() => '')).trim().slice(0, 40) || (await b.getAttribute('aria-label').catch(() => '')) }); before.add(x.kind + x.label); }
      await page.keyboard.press('Escape').catch(() => {}); await sleep(250);
      if (!page.url().includes(key.split(' ')[0])) { await page.goBack().catch(() => {}); await settle(); }
    }
    // Tabs: each tab's fields.
    const tabs = await page.locator('[role="tab"]:visible').count().catch(() => 0);
    for (let t = 0; t < Math.min(tabs, 20); t++) {
      await page.locator('[role="tab"]:visible').nth(t).click({ timeout: 3000 }).catch(() => {}); await sleep(700);
      const after = await page.evaluate(harvest).catch(() => null);
      const have = new Set(h.items.map((x) => x.kind + x.label));
      if (after) for (const x of after.items) if (!have.has(x.kind + x.label)) h.items.push({ ...x, tab: t });
    }
    screens[key] = h;
    await page.screenshot({ path: path.join(out, 'inventory', key.replace(/[^a-z0-9]+/gi, '-').slice(0, 60) + '.png') }).catch(() => {});
    console.log(key, h.items.length);
  };
  const visit = async (url, key) => { if (seen.has(url)) return; seen.add(url); await page.goto(url).catch(() => {}); await settle(); await take(key || new URL(page.url()).pathname); };

  // The overview (Simplify's page in both products).
  await visit('/simplify', 'overview');
  // A server: its page and every page of its menu.
  const serverLink = await page.evaluate(() => { const a = [...document.querySelectorAll('a[href*="/server/"]')][0]; return a ? a.getAttribute('href') : null; });
  let serverBase = serverLink;
  if (!serverBase) { await page.locator('.ps-server-open').first().click().catch(() => {}); await settle(); serverBase = new URL(page.url()).pathname; }
  if (serverBase) {
    await visit(serverBase, 'server console');
    const prefix = (serverBase.match(/^\/server\/[^/]+/) || [serverBase])[0];
    const links = await page.evaluate((p) => [...new Set([...document.querySelectorAll('a[href]')].map((a) => new URL(a.getAttribute('href'), location.href).pathname).filter((u) => u.startsWith(p)))], prefix);
    for (const u of links.slice(0, 30)) await visit(u, 'server ' + u.slice(prefix.length));
  }
  // The plugin's admin pages (and their tabs).
  await page.goto('/admin').catch(() => {}); await settle();
  const admin = await page.evaluate(() => [...new Set([...document.querySelectorAll('a[href]')].map((a) => new URL(a.getAttribute('href'), location.href).pathname).filter((u) => /^\/admin\//.test(u) && /simplify/i.test(u)))]);
  for (const u of admin.slice(0, 30)) await visit(u, 'admin ' + u);
  fs.writeFileSync(path.join(out, 'inventory.json'), JSON.stringify(screens, null, 1));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
