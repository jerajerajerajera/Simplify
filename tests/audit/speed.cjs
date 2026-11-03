// Opening Admin tools, measured in a browser: from the overview the shield, five times, and per round when the answer
// starts and ends (the server), when Simplify's placeholders give way (Alpine started), when the table is on the
// screen, what the page loads (files, bytes, Livewire calls after the page) and the same for the legacy panel (Pelican
// alone) and for a click in the admin menu. Writes speed.json and speed.md (the medians).
// node speed.cjs --base http://127.0.0.1:8001 --out result [--rounds 5]
const fs = require('fs'), path = require('path');
const { chromium } = require('playwright');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base', 'http://127.0.0.1:8001'), out = arg('out', 'result'), rounds = Number(arg('rounds', '5'));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
fs.mkdirSync(out, { recursive: true });

// In every document: when Simplify's root loses x-cloak and when the page's table has rows, from the start of the navigation.
const marks = () => {
  window.__speed = {};
  const seen = () => {
    const s = window.__speed, now = Math.round(performance.now());
    const root = document.querySelector('[data-simplify-root]');
    if (s.ready === undefined && root && !root.hasAttribute('x-cloak')) s.ready = now;
    const table = document.querySelector('.fi-ta-row, .fi-ta-empty-state');
    if (s.table === undefined && table && table.getBoundingClientRect().height > 0) s.table = now;
    const menu = document.querySelector('.ps-admin-side, .ps-host-tree');
    if (s.menu === undefined && menu && menu.getBoundingClientRect().height > 0) s.menu = now;
  };
  new MutationObserver(seen).observe(document, { subtree: true, childList: true, attributes: true, attributeFilter: ['x-cloak', 'class', 'style'] });
  document.addEventListener('DOMContentLoaded', seen);
};

// What the document loaded: the navigation's timings and every file and call, by kind.
const read = () => {
  const nav = performance.getEntriesByType('navigation')[0] || {};
  const paint = Object.fromEntries(performance.getEntriesByType('paint').map((p) => [p.name, Math.round(p.startTime)]));
  const files = performance.getEntriesByType('resource').map((r) => ({ name: r.name.replace(location.origin, '').slice(0, 120), kind: r.initiatorType, start: Math.round(r.startTime), ms: Math.round(r.duration), bytes: r.transferSize, size: r.decodedBodySize }));
  const kinds = {};
  for (const f of files) { const k = kinds[f.kind] ||= { count: 0, bytes: 0, size: 0 }; k.count++; k.bytes += f.bytes; k.size += f.size; }
  return {
    url: location.pathname, origin: performance.timeOrigin, ttfb: Math.round(nav.responseStart), html: Math.round(nav.responseEnd), htmlBytes: nav.decodedBodySize, dcl: Math.round(nav.domContentLoadedEventEnd), load: Math.round(nav.loadEventEnd),
    fcp: paint['first-contentful-paint'], ...window.__speed, kinds, calls: files.filter((f) => f.kind === 'fetch' || f.kind === 'xmlhttprequest'), files,
    nodes: document.getElementsByTagName('*').length, inlineScript: [...document.scripts].filter((s) => !s.src).reduce((n, s) => n + s.textContent.length, 0), svgSymbols: document.querySelectorAll('symbol').length,
  };
};
const median = (list) => { const v = list.filter((x) => typeof x === 'number').sort((a, b) => a - b); return v.length ? v[Math.floor(v.length / 2)] : null; };

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, baseURL: base, deviceScaleFactor: 1 });
  await context.addInitScript(marks);
  const page = await context.newPage(); page.setDefaultTimeout(15000);
  await page.goto('/login'); await page.fill('#form\\.login', 'demo@example.com'); await page.fill('#form\\.password', 'DemoPassword123!');
  await Promise.all([page.waitForNavigation().catch(() => {}), page.locator('button[type="submit"]').first().click()]); await sleep(1500);
  const result = { base, rounds: {}, medians: {} };
  const settle = async () => { await page.waitForLoadState('load').catch(() => {}); await page.waitForSelector('.fi-ta-row, .fi-ta-empty-state', { timeout: 15000 }).catch(() => {}); await sleep(2500); };

  // 1. Overview → shield (a whole page, as on the real panel), warm: the first round fills the caches and is left out.
  const shield = [];
  for (let i = 0; i <= rounds; i++) {
    await page.goto('/simplify'); await page.waitForSelector('a.ps-nav-admin >> visible=true', { timeout: 20000 }).catch(() => {}); await sleep(1500);
    await page.evaluate(() => { window.__speedKeep = 1; });
    const t0 = Date.now();
    await page.locator('a.ps-nav-admin >> visible=true').first().click();
    // Until the Admin page stands: its page is there and Simplify's header and menu have started (no x-cloak left).
    await page.waitForFunction(() => location.pathname.startsWith('/admin') && document.querySelector('.fi-main .fi-page') && document.querySelector('.ps-admin-side') && !document.querySelector('[data-simplify-root][x-cloak]'), null, { polling: 'raf', timeout: 20000 }).catch(() => {});
    const clickToReady = Date.now() - t0;
    const reloaded = await page.evaluate(() => window.__speedKeep !== 1);
    await settle();
    const r = await page.evaluate(read); r.clickToReady = clickToReady; r.reloaded = reloaded;
    if (i > 0) shield.push(r);
  }
  result.rounds.shield = shield;
  await page.screenshot({ path: path.join(out, 'admin-servers.png') });

  // 2. A click in the admin menu (wire:navigate): Servers → Eggs and back, timed until the new table is there.
  const menu = [];
  for (let i = 0; i < rounds; i++) {
    for (const target of ['/admin/eggs', '/admin/servers']) {
      const t0 = Date.now();
      // The link may sit in a folded group (Health is the start page): a click on it all the same.
      await page.evaluate((t) => document.querySelector(`.ps-admin-side a[href$="${t}"]`)?.click(), target).catch(() => {});
      await page.waitForURL('**' + target, { timeout: 15000 }).catch(() => {});
      await page.waitForSelector('.fi-ta-row, .fi-ta-empty-state', { timeout: 15000 }).catch(() => {});
      menu.push({ target, ms: Date.now() - t0 });
      await sleep(800);
    }
  }
  result.rounds.menu = menu;

  // 4. Where the browser spends its time: a CPU profile of one shield click and one menu click, self time per function.
  const cdp = await context.newCDPSession(page);
  const profile = async (act) => {
    await cdp.send('Profiler.enable'); await cdp.send('Profiler.setSamplingInterval', { interval: 200 }); await cdp.send('Profiler.start');
    await act(); await settle();
    const { profile: prof } = await cdp.send('Profiler.stop');
    const dt = prof.timeDeltas || [], self = new Map(), byId = new Map(prof.nodes.map((n) => [n.id, n]));
    prof.samples.forEach((id, i) => self.set(id, (self.get(id) || 0) + (dt[i] || 0) / 1000));
    const rows = new Map(), files = new Map();
    for (const [id, ms] of self) {
      const f = byId.get(id).callFrame, file = (f.url || '(' + f.functionName + ')').replace(base, '').replace(/\?.*$/, '');
      const key = `${f.functionName || '(anonymous)'} ${file}:${f.lineNumber + 1}`;
      rows.set(key, (rows.get(key) || 0) + ms); files.set(file, (files.get(file) || 0) + ms);
    }
    const top = (m, n) => [...m].sort((a, b) => b[1] - a[1]).slice(0, n).map(([k, ms]) => ({ ms: Math.round(ms), at: k }));
    return { functions: top(rows, 40), files: top(files, 20) };
  };
  await page.goto('/simplify'); await page.waitForSelector('a.ps-nav-admin >> visible=true', { timeout: 20000 }).catch(() => {}); await sleep(1500);
  result.profile = { shield: await profile(async () => { await page.locator('a.ps-nav-admin >> visible=true').first().click(); await page.waitForURL('**/admin/**').catch(() => {}); }) };
  result.profile.menu = await profile(async () => { await page.evaluate(() => document.querySelector('.ps-admin-side a[href$="/admin/eggs"]')?.click()).catch(() => {}); await page.waitForURL('**/admin/eggs').catch(() => {}); });

  // 3. The same page in the legacy panel: Pelican's own admin page, without Simplify's header and menu.
  await page.goto('/admin/servers?simplify_legacy=1'); await settle();
  const legacy = [];
  for (let i = 0; i <= rounds; i++) { await page.goto('/admin/servers'); await settle(); if (i > 0) legacy.push(await page.evaluate(read)); }
  result.rounds.legacy = legacy;
  await page.goto('/admin/servers?simplify_legacy=0'); await settle();

  const keys = ['clickToReady', 'ttfb', 'html', 'htmlBytes', 'fcp', 'dcl', 'ready', 'menu', 'table', 'load', 'nodes', 'inlineScript', 'svgSymbols'];
  for (const [name, list] of Object.entries({ shield, legacy })) result.medians[name] = Object.fromEntries(keys.map((k) => [k, median(list.map((r) => r[k]))]));
  result.medians.menuClick = median(menu.map((m) => m.ms));
  result.reloads = shield.filter((r) => r.reloaded).length;
  // The files of the last round of each, with the calls after the page (Livewire, health, notifications).
  const last = (list) => list[list.length - 1] || {};
  result.files = { shield: last(shield).files, legacy: last(legacy).files };
  result.calls = { shield: last(shield).calls, legacy: last(legacy).calls };
  result.kinds = { shield: last(shield).kinds, legacy: last(legacy).kinds };
  fs.writeFileSync(path.join(out, 'speed.json'), JSON.stringify(result, null, 2));

  const row = (k) => `| ${k} | ${result.medians.shield[k] ?? '-'} | ${result.medians.legacy[k] ?? '-'} |`;
  const md = ['# Opening Admin tools', '', `Medians of ${rounds} warm rounds, ms from the start of the navigation (bytes and counts where named).`, '', '| | Simplify (shield) | Legacy (Pelican alone) |', '|---|---|---|', ...keys.map(row), '',
    `Admin menu click (wire:navigate, until the new table): ${result.medians.menuClick} ms`, `Shield clicks that loaded a whole page: ${result.reloads} of ${shield.length} (clickToReady: from the click until the Admin page and Simplify stand; the timings below it are of the last whole page)`, '', '## Files (last round)', '', '| kind | Simplify count / KB over the wire / KB | Legacy |', '|---|---|---|',
    ...[...new Set([...Object.keys(result.kinds.shield || {}), ...Object.keys(result.kinds.legacy || {})])].map((k) => { const f = (x) => (x ? `${x.count} / ${Math.round(x.bytes / 1024)} / ${Math.round(x.size / 1024)}` : '-'); return `| ${k} | ${f(result.kinds.shield[k])} | ${f(result.kinds.legacy[k])} |`; }),
    '', '## Calls after the page (Simplify)', '', ...(result.calls.shield || []).map((c) => `- ${c.start} ms +${c.ms} ms ${c.name} (${c.size} B)`),
    '', '## CPU by file (shield click)', '', ...(result.profile?.shield.files || []).map((r) => `- ${r.ms} ms ${r.at}`),
    '', '## CPU by function (shield click)', '', ...(result.profile?.shield.functions || []).map((r) => `- ${r.ms} ms ${r.at}`),
    '', '## CPU by file (menu click)', '', ...(result.profile?.menu.files || []).map((r) => `- ${r.ms} ms ${r.at}`),
    '', '## CPU by function (menu click)', '', ...(result.profile?.menu.functions || []).map((r) => `- ${r.ms} ms ${r.at}`),
    '', '## Slowest files (Simplify)', '', ...[...(result.files.shield || [])].sort((a, b) => b.ms - a.ms).slice(0, 15).map((f) => `- ${f.ms} ms ${f.name} (${Math.round(f.size / 1024)} KB, start ${f.start})`)];
  fs.writeFileSync(path.join(out, 'speed.md'), md.join('\n') + '\n');
  console.log(md.join('\n'));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
