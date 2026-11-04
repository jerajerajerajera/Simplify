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
    if (!phone) say('server page does not scroll', (await scrolls()) <= 1, { scroll: await scrolls() });
    await axe('server');

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
  }

  // Admin: on a desktop the shield in the top bar switches without a full load; on a phone the Admin tab in the bottom bar.
  await goto('/');
  await page.evaluate(() => { window.__psMark = 1; });
  const admin = page.locator((phone ? '.ps-tabbar a.ps-tab:has(use[href="#ps-shield"])' : 'a.ps-nav-admin') + ' >> visible=true').first();
  await admin.click().catch(() => {});
  await settle(1500);
  const onAdmin = await page.evaluate(() => ({ reloaded: window.__psMark !== 1, path: location.pathname }));
  say('Admin opens without a full page load', onAdmin.path.includes('/admin') && !onAdmin.reloaded, onAdmin);
  say('Admin does not scroll', (await scrolls()) <= 1, { scroll: await scrolls() });
  await shot('admin');
  await axe('admin');

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

(async () => {
  browser = await chromium.launch({ args: ['--no-sandbox'] });
  for (const viewport of [{ name: 'd1440', width: 1440, height: 900 }, { name: 'phone390', width: 390, height: 844 }]) {
    try { await run(viewport); } catch (e) { say('setup', false, { error: String(e.message).slice(0, 300) }); }
  }
  await browser.close();
  fs.writeFileSync(path.join(out, 'axe.json'), JSON.stringify(axeReport, null, 2));
  if (failures.length) { console.log(`FAILED: ${failures.join('; ')}`); process.exit(1); }
  console.log('All steps passed');
})();
