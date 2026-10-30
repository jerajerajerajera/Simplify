// Screenshots of PelicanSimplify 0.7.0 in a lab panel with the same servers as the Simplify lab, so each Simplify
// screen can be put next to the 0.7.0 screen it copies: the overview and a server page, desktop and phone, light and dark.
// node reference.cjs --base http://127.0.0.1:8001 --servers servers.json --out shots
const fs = require('fs'), path = require('path');
const { chromium } = require('playwright');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base'), out = arg('out'), servers = JSON.parse(fs.readFileSync(arg('servers'), 'utf8'));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, baseURL: base })).newPage();
  page.setDefaultTimeout(15000);
  const settle = async () => { await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {}); await sleep(2500); };
  await page.goto('/login'); await settle();
  await page.fill('#form\\.login', 'lab@example.com'); await page.fill('#form\\.password', 'LabPassword123!');
  await page.click('button[type="submit"]'); await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 30000 }).catch(() => {});
  const survival = servers.find((s) => s.name === 'Survival');
  const pages = { overview: '/simplify', server: '/simplify?server=' + survival.uuid };
  for (const [vp, size] of Object.entries({ desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844 } })) {
    await page.setViewportSize(size);
    for (const theme of ['light', 'dark']) {
      await page.evaluate((t) => { localStorage.setItem('theme', t); }, theme);
      for (const [name, url] of Object.entries(pages)) {
        await page.goto(url).catch(() => {}); await settle();
        await page.screenshot({ path: path.join(out, `ref-${name}-${vp}-${theme}.png`), fullPage: true });
      }
    }
  }
  // The other overview layouts and the Healthy details, on desktop in light.
  await page.setViewportSize({ width: 1440, height: 900 }); await page.evaluate(() => localStorage.setItem('theme', 'light'));
  await page.goto('/simplify'); await settle();
  const health = page.locator('button', { hasText: /Healthy|Unavailable|Attention/ }).first();
  if (await health.count()) { await health.click().catch(() => {}); await sleep(800); await page.screenshot({ path: path.join(out, 'ref-overview-health-open.png'), fullPage: true }); }
  fs.writeFileSync(path.join(out, 'ref-html.html'), await page.content());
  await browser.close();
})();
