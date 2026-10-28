// Browser round of Simplify in a lab panel. First the actions on desktop in light mode (setup wizard, settings tabs,
// the overview's views, chips, order, groups, power, host health, the server page, people and roles,
// notifications), then every page on desktop and phone, light and dark. Records HTTP 5xx, page and console errors
// and sideways scrolling; writes a screenshot per step and result.json.
// node lab.cjs --base http://127.0.0.1:8001 --urls urls.json --out shots
const fs = require('fs'), path = require('path');
const { chromium } = require('playwright');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base'), out = arg('out'), urls = JSON.parse(fs.readFileSync(arg('urls'), 'utf8'));
const rel = (u) => u.replace(/^https?:\/\/[^/]+/, '');
const issues = [], checks = [];
const ok = (name, pass, detail = '') => { checks.push({ name, pass, detail: pass ? '' : detail }); if (!pass) issues.push(name + (detail ? ': ' + detail : '')); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const slug = (s) => s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, baseURL: base });
  const page = await context.newPage();
  page.setDefaultTimeout(10000);
  let current = 'login';
  page.on('pageerror', (e) => issues.push(current + ' page error: ' + e.message));
  page.on('console', (m) => { if (m.type() === 'error' && !/favicon|net::ERR|status of 4|WebSocket|websocket/.test(m.text())) issues.push(current + ' console: ' + m.text().slice(0, 200)); });
  page.on('response', (r) => { if (r.status() >= 500) issues.push(current + ' HTTP ' + r.status() + ' ' + r.url().replace(base, '')); });
  const settle = async () => { await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {}); await sleep(600); };
  const shot = async (name) => {
    await page.screenshot({ path: path.join(out, name + '.png'), fullPage: true });
    const wide = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    ok(`no sideways scrolling: ${name}`, wide <= 1, wide + 'px too wide');
  };
  const open = async (url, name) => {
    current = name;
    const response = await page.goto(rel(url)).catch(() => null); await settle();
    ok(`${name} opens`, !!response && response.status() === 200 && !page.url().includes('/login'), response ? 'HTTP ' + response.status() + ' ' + page.url() : 'no response');
    return response;
  };
  const main = async () => page.locator('main').first().innerText().catch(() => '');
  const click = async (locator, name) => {
    const target = locator.first();
    const there = (await target.count()) > 0;
    ok(`button: ${name}`, there, 'not found');
    if (there) { await target.click(); await settle(); }
    return there;
  };
  const modal = () => page.locator('.fi-modal-window:visible').last();
  const closeModal = async () => { await page.keyboard.press('Escape'); await sleep(500); };
  // One part that breaks is reported and the round goes on with the next part.
  const step = async (name, fn) => {
    try { await fn(); } catch (e) { ok(`${name} ran through`, false, String(e.message).split('\n')[0]); await shot('error-' + slug(name)).catch(() => {}); }
  };

  await page.goto('/login'); await settle();
  await page.fill('#form\\.login', 'lab@example.com'); await page.fill('#form\\.password', 'LabPassword123!');
  await page.click('button[type="submit"]'); await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 30000 }).catch(() => {});
  ok('login', !page.url().includes('/login'), page.url());
  await page.evaluate(() => localStorage.setItem('theme', 'light'));

  // The setup wizard: the first visit to the settings sends a root admin there.
  await step('The setup wizard', async () => {
    current = 'setup';
    await page.goto(rel(urls.settings)); await settle();
    ok('first visit to the settings opens the setup', page.url().includes('simplify-setup'), page.url());
    for (let step = 1; step <= 4; step++) {
      await shot(`action-setup-step-${step}`);
      const next = page.locator('main button:visible', { hasText: /^\s*Next\s*$/ });
      if (step < 4) await click(next, `setup next (step ${step})`);
    }
    ok('the last step lists the add-ons', /Server Maker/.test(await main()), (await main()).slice(0, 200));
    await click(page.locator('main button[type=submit]:visible', { hasText: 'Finish' }), 'setup finish');
    await page.waitForURL((u) => !u.pathname.includes('simplify-setup'), { timeout: 15000 }).catch(() => {}); await settle();
    ok('finishing the setup opens the settings', /\/admin\/simplify(\?|$)/.test(page.url()), page.url());
  });

  // Settings: every tab, then save.
  await step('Settings', async () => {
    current = 'settings';
    for (const tab of ['General', 'Appearance', 'Add-ons']) {
      if (await click(page.locator('main [role=tab]', { hasText: tab }), `settings tab ${tab}`)) await shot(`action-settings-${slug(tab)}`);
    }
    await click(page.locator('main [role=tab]', { hasText: 'General' }), 'settings back to General');
    await click(page.locator('main button[type=submit]', { hasText: 'Save' }), 'settings save');
    ok('settings saved', /saved/i.test(await page.locator('body').innerText()));
    await shot('action-settings-saved');
  });

  // Overview (the 0.7.0 page): views, owner and game chips, server order, groups, power and host health.
  const cards = () => page.locator('.ps-server-card:visible').count();
  const ps = (fn, arg) => page.evaluate(([body, a]) => new Function('d', 'a', body)(window.Alpine.$data(document.querySelector('.ps')), a), [fn, arg]);
  await step('Overview', async () => {
    await open(urls.overview, 'overview actions');
    await page.waitForSelector('.ps-server-card', { timeout: 15000 }).catch(() => {});
    ok('overview lists the servers', /Survival/.test(await main()) && /Rust weekend/.test(await main()), (await main()).slice(0, 300));
    ok('six server cards', (await cards()) === 6, (await cards()) + ' cards');
    for (const view of ['grid', 'compact', 'list']) {
      await ps('d.setOverviewView(a)', view); await sleep(800);
      await shot(`action-overview-${view}`);
      await page.setViewportSize({ width: 390, height: 844 }); await sleep(800);
      await shot(`action-overview-${view}-phone`);
      await page.setViewportSize({ width: 1440, height: 900 }); await sleep(500);
      ok(`view ${view} shows the servers`, (await cards()) === 6, (await cards()) + ' cards');
    }
    await ps('d.setOverviewView(a)', 'grid'); await sleep(500);
    for (const [scope, expect] of [['My servers', 5], ['Others', 1], ['All servers', 6]]) {
      if (await click(page.locator('.ps-owner-filters .ps-chip', { hasText: scope }), `owner chip ${scope}`)) {
        ok(`owner chip ${scope} shows ${expect} servers`, (await cards()) === expect, (await cards()) + ' cards');
      }
    }
    const games = page.locator('.ps-game-filters .ps-chip');
    ok('game chips', (await games.count()) >= 2, (await games.count()) + ' chips');
    if ((await games.count()) >= 2) { await games.nth(1).click(); await sleep(500); await shot('action-overview-game-chip'); await games.first().click(); await sleep(500); }
    if (await click(page.locator('.ps-overview-appearance:not(.ps-overview-groups) > button'), 'customize')) { await shot('action-overview-customize'); await page.keyboard.press('Escape'); await sleep(300); }
  });

  await step('Groups and server order', async () => {
    current = 'groups';
    if (await click(page.locator('.ps-overview-groups > button'), 'groups')) {
      await click(page.locator('#ps-overview-groups-menu .ps-segmented button', { hasText: /Created/ }), 'order by created');
      await shot('action-groups-menu');
      await page.keyboard.press('Escape'); await sleep(300);
    }
    await ps(`const ids = d.boot.servers.filter((s) => /Survival|Creative/.test(s.name)).map((s) => s.uuid);
      d.overviewGroups = [{ id: 'lab', name: 'Minecraft', color: '#2f6fde', servers: ids, sort: 'overview' }]; return d.wire.saveOverviewGroups(d.overviewGroups, 'name');`);
    await open(urls.overview, 'overview with a group');
    await page.waitForSelector('.ps-server-card', { timeout: 15000 }).catch(() => {});
    ok('group section shows', (await page.locator('.ps-node-heading', { hasText: 'Minecraft' }).count()) === 1, (await main()).slice(0, 300));
    await shot('action-groups-saved');
  });

  await step('Power from a card', async () => {
    current = 'power';
    const card = page.locator('.ps-server-entry.has-power').first();
    ok('cards have power buttons', (await card.count()) > 0, 'no card with power buttons');
    if (await card.count()) {
      await card.hover(); await sleep(400);
      const start = card.locator('.ps-overview-power button[data-power]:not([disabled])').first();
      if (await start.count()) { await start.click(); await sleep(600); await shot('action-power'); }
    }
  });

  await step('Host health', async () => {
    current = 'host health';
    if (await click(page.locator('.ps-health-pill'), 'Healthy button')) { await sleep(600); await shot('action-host-health'); await page.keyboard.press('Escape'); }
  });

  // Host mode: the account menu switches it on; the admin tree, a page in the frame, a server, then back.
  await step('Host mode', async () => {
    await open(urls.overview, 'host mode');
    await click(page.locator('.ps-account-button'), 'account menu'); await sleep(400);
    if (!(await click(page.locator('#ps-account-actions button[aria-label="Switch to host mode"]'), 'switch to host mode'))) return;
    await sleep(1500);
    ok('host mode shows the admin tree', (await page.locator('.ps-host-tree .ps-host-group:visible').count()) >= 3, String(await page.locator('.ps-host-tree .ps-host-group').count()));
    const framed = async () => page.frameLocator('.ps-host-frame:not(.is-server) iframe').locator('body');
    ok('the page in the frame has no Pelican menus', (await (await framed()).locator('.fi-topbar:visible, .fi-sidebar:visible').count()) === 0);
    ok('the page in the frame loaded', (await (await framed()).locator('.fi-main').count().catch(() => 0)) > 0);
    await shot('action-host-mode');
    if (await click(page.locator('.ps-host-leaf', { hasText: 'Nodes' }), 'Nodes in the tree')) { await sleep(1500); await shot('action-host-nodes'); }
    if (await click(page.locator('.ps-host-tabs button', { hasText: 'Servers' }), 'servers tab')) {
      if (await click(page.locator('.ps-host-server', { hasText: 'Survival' }), 'Survival in host mode')) { await sleep(2500); await shot('action-host-server'); ok('the server in the frame loaded', (await page.frameLocator('.ps-host-frame.is-server iframe').locator('.fi-main').count().catch(() => 0)) > 0); }
    }
    await page.setViewportSize({ width: 390, height: 844 }); await sleep(800); await shot('action-host-mode-phone');
    await page.setViewportSize({ width: 1440, height: 900 }); await sleep(500);
    await page.reload(); await settle();
    ok('host mode stays on after a reload', (await page.locator('.ps-host:visible').count()) === 1);
    await click(page.locator('.ps-account-button'), 'account menu'); await sleep(400);
    await click(page.locator('#ps-account-actions button[aria-label="Switch to simple mode"]'), 'switch to simple mode'); await sleep(800);
    ok('simple mode shows the cards again', (await page.locator('.ps-overview:visible').count()) === 1);
  });

  // The server page: card, numbers, menu and console; then colour, background and another section.
  await step('The server page', async () => {
    const survival = urls.servers['Survival'];
    await open(survival, 'server page actions');
    await sleep(1500);
    const top = () => page.locator('.ps-server-info').innerText().catch(() => '');
    ok('server card shows the name and join address', /Survival/.test(await top()) && /127\.0\.0\.1:25565/.test(await top()), (await top()).slice(0, 300));
    ok('four or five number cards', (await page.locator('.ps-server-stats .ps-stat:visible').count()) >= 4);
    ok('the menu lists the server sections', (await page.locator('.ps-server-menu .ps-widget').count()) >= 5, String(await page.locator('.ps-server-menu .ps-widget').count()));
    ok('the console has the live console title', (await page.locator('#terminal .ps-console-title').count()) === 1);
    ok("Pelican's own numbers are hidden", (await page.locator('.fi-wi-stats-overview:visible, .fi-wi-chart:visible').count()) === 0);
    ok('no empty chart cards under the console', (await page.locator('[wire\\:name$="Chart"]:visible').count()) === 0);
    ok('the footer is there', (await page.locator('[data-ps-footer]:visible, .fi-footer:visible').count()) > 0);
    await shot('action-server-page');
    if (await click(page.locator('.ps-info-more-button'), 'server ⋯ menu')) {
      await shot('action-server-menu');
      if (await click(page.locator('#ps-info-menu button', { hasText: 'Server color' }), 'server colour')) {
        await click(page.locator('.ps-color-dropdown .ps-swatch').nth(7), 'blue swatch');
        await shot('action-server-color');
        await click(page.locator('.ps-color-dropdown .ps-btn.is-primary'), 'save colour');
        await sleep(1000);
      }
    }
    const png = await page.evaluate(() => { const c = document.createElement('canvas'); c.width = 1200; c.height = 300; const g = c.getContext('2d'); const gr = g.createLinearGradient(0, 0, 1200, 300); gr.addColorStop(0, '#2f6fde'); gr.addColorStop(1, '#f5c542'); g.fillStyle = gr; g.fillRect(0, 0, 1200, 300); return c.toDataURL('image/png').split(',')[1]; });
    await page.locator('#ps-info-menu input[type=file]').setInputFiles({ name: 'banner.png', mimeType: 'image/png', buffer: Buffer.from(png, 'base64') });
    await sleep(3000); await settle();
    ok('the card shows the uploaded background', (await page.locator('.ps-server-info img.ps-server-banner[src*="/simplify/banners/"]').count()) > 0);
    await shot('action-look-saved');
    if (await click(page.locator('.ps-server-menu .ps-widget', { hasText: 'Files' }), 'Files in the menu')) {
      ok('Files keeps the server card and menu', (await page.locator('.ps-server-info:visible').count()) === 1 && (await page.locator('.ps-server-menu .ps-widget[aria-current=page]', { hasText: 'Files' }).count()) === 1);
      await shot('action-server-files');
    }
    await open(urls.overview, 'overview after look');
    await page.waitForSelector('.ps-server-card', { timeout: 15000 }).catch(() => {});
    const banner = await page.locator('img.ps-card-background[src*="/simplify/banners/"]').count();
    ok('overview shows the banner', banner > 0, 'no banner on the overview');
    await shot('action-overview-with-banner');
  });

  // People and roles.
  await step('People and roles', async () => {
    await open(urls.people, 'people actions');
    ok('people lists sam', /sam@example\.com/.test(await main()));
    await page.fill('main input.s-search', 'sam'); await sleep(1200); await settle();
    ok('people search finds sam only', /sam@example\.com/.test(await main()) && !/lab@example\.com/.test(await main()));
    await page.fill('main input.s-search', ''); await sleep(1200); await settle();
    if (await click(page.locator('main button', { hasText: 'Add person' }), 'add person')) { ok('add person form opens', (await modal().count()) > 0); await shot('action-people-add'); await closeModal(); }
    if (await click(page.locator('main button', { hasText: /^\s*Edit\s*$/ }), 'edit person')) { ok('edit person form opens', (await modal().count()) > 0); await shot('action-people-edit'); await closeModal(); }
    if (await click(page.locator('main .s-segment', { hasText: 'Roles' }), 'roles tab')) {
      await shot('action-roles');
      if (await click(page.locator('main button', { hasText: /Add role|New role/ }), 'add role')) {
        const form = modal();
        ok('add role form opens', (await form.count()) > 0);
        await shot('action-roles-add');
        await form.locator('input[type=text]').first().fill('Helpers');
        await form.locator('button[type=submit]').last().click(); await settle();
        ok('new role is listed', /Helpers/.test(await main()), (await main()).slice(0, 300));
        await shot('action-roles-added');
      }
    }
  });

  // Notifications: the switches save at once.
  await step('Notifications', async () => {
    await open(urls.notifications, 'notifications actions');
    const switches = page.locator('main input.s-switch');
    ok('notification switches', (await switches.count()) >= 1, '0 switches');
    if (await switches.count()) { await switches.first().click(); await settle(); await shot('action-notifications-switched'); await switches.first().click(); await settle(); }
  });

  // The app files and assets.
  await step('The app files and assets', async () => {
    current = 'app files';
    for (const [name, url, type] of [['manifest', '/simplify/manifest.webmanifest', /json/], ['worker', '/simplify-worker.js', /javascript/], ['stylesheet', null, /css/]]) {
      let target = url;
      if (!target) target = await page.evaluate(() => [...document.querySelectorAll('link[rel=stylesheet]')].map((l) => l.getAttribute('href')).find((h) => h && h.includes('/simplify/assets/')) || '');
      if (!target) { ok(`${name} is linked`, false, 'no link'); continue; }
      const response = await page.request.get(rel(target));
      ok(`${name} serves (${target.replace(base, '')})`, response.status() === 200 && type.test(response.headers()['content-type'] || ''), response.status() + ' ' + response.headers()['content-type']);
      if (name === 'manifest' && response.status() === 200) {
        const manifest = await response.json().catch(() => ({}));
        ok('manifest has a name and icons', !!manifest.name && Array.isArray(manifest.icons) && manifest.icons.length > 0, JSON.stringify(manifest).slice(0, 200));
      }
    }
  });

  // Every page on desktop and phone, light and dark.
  await step('Every page on desktop and phone, light and dark', async () => {
    const viewports = { desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844 } };
    const pages = { overview: urls.overview, settings: urls.settings, setup: urls.setup, people: urls.people, notifications: urls.notifications };
    for (const [name, url] of Object.entries(urls.servers)) pages['server-' + slug(name)] = url;
    for (const [vp, size] of Object.entries(viewports)) {
      await page.setViewportSize(size);
      for (const theme of ['light', 'dark']) {
        const tag = vp + '-' + theme;
        await page.evaluate((t) => localStorage.setItem('theme', t), theme);
        for (const [name, url] of Object.entries(pages)) {
          await open(url, `${name} ${tag}`);
          if (name === 'overview') await page.waitForSelector('.ps-server-card', { timeout: 15000 }).catch(() => {});
          if (name === 'overview' || name === 'server-survival') await sleep(1500);
          await shot(`${name}-${tag}`);
        }
      }
    }
  });

  fs.writeFileSync(path.join(out, 'result.json'), JSON.stringify({ base, checks, issues }, null, 2));
  console.log(`${checks.filter((c) => c.pass).length}/${checks.length} checks passed, ${issues.length} issues`);
  for (const i of issues) console.log('ISSUE ' + i);
  await browser.close();
  process.exit(issues.length ? 1 : 0);
})();
