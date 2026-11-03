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
    const t0 = Date.now();
    await Promise.all([page.waitForNavigation({ waitUntil: 'commit' }).catch(() => {}), page.locator('a.ps-nav-admin >> visible=true').first().click()]);
    await settle();
    const r = await page.evaluate(read); r.clickToTable = r.table !== undefined ? Math.round(r.origin + r.table - t0) : null;
    if (i > 0) shield.push(r);
  }
  result.rounds.shield = shield;
  await page.screenshot({ path: path.join(out, 'admin-servers.png') });

  // 2. A click in the admin menu (wire:navigate): Servers → Eggs and back, timed until the new table is there.
  const menu = [];
  for (let i = 0; i < rounds; i++) {
    for (const target of ['/admin/eggs', '/admin/servers']) {
      const t0 = Date.now();
      await page.locator(`.ps-admin-side a[href$="${target}"], .ps-host-tree a[href$="${target}"]`).first().click().catch(() => {});
      await page.waitForURL('**' + target, { timeout: 15000 }).catch(() => {});
      await page.waitForSelector('.fi-ta-row, .fi-ta-empty-state', { timeout: 15000 }).catch(() => {});
      menu.push({ target, ms: Date.now() - t0 });
      await sleep(800);
    }
  }
  result.rounds.menu = menu;

  // 3. The same page in the legacy panel: Pelican's own admin page, without Simplify's header and menu.
  await page.goto('/admin/servers?simplify_legacy=1'); await settle();
  const legacy = [];
  for (let i = 0; i <= rounds; i++) { await page.goto('/admin/servers'); await settle(); if (i > 0) legacy.push(await page.evaluate(read)); }
  result.rounds.legacy = legacy;
  await page.goto('/admin/servers?simplify_legacy=0'); await settle();

  const keys = ['ttfb', 'html', 'htmlBytes', 'fcp', 'dcl', 'ready', 'menu', 'table', 'load', 'clickToTable', 'nodes', 'inlineScript', 'svgSymbols'];
  for (const [name, list] of Object.entries({ shield, legacy })) result.medians[name] = Object.fromEntries(keys.map((k) => [k, median(list.map((r) => r[k]))]));
  result.medians.menuClick = median(menu.map((m) => m.ms));
  // The files of the last round of each, with the calls after the page (Livewire, health, notifications).
  const last = (list) => list[list.length - 1] || {};
  result.files = { shield: last(shield).files, legacy: last(legacy).files };
  result.calls = { shield: last(shield).calls, legacy: last(legacy).calls };
  result.kinds = { shield: last(shield).kinds, legacy: last(legacy).kinds };
  fs.writeFileSync(path.join(out, 'speed.json'), JSON.stringify(result, null, 2));

  const row = (k) => `| ${k} | ${result.medians.shield[k] ?? '-'} | ${result.medians.legacy[k] ?? '-'} |`;
  const md = ['# Opening Admin tools', '', `Medians of ${rounds} warm rounds, ms from the start of the navigation (bytes and counts where named).`, '', '| | Simplify (shield) | Legacy (Pelican alone) |', '|---|---|---|', ...keys.map(row), '',
    `Admin menu click (wire:navigate, until the new table): ${result.medians.menuClick} ms`, '', '## Files (last round)', '', '| kind | Simplify count / KB over the wire / KB | Legacy |', '|---|---|---|',
    ...[...new Set([...Object.keys(result.kinds.shield || {}), ...Object.keys(result.kinds.legacy || {})])].map((k) => { const f = (x) => (x ? `${x.count} / ${Math.round(x.bytes / 1024)} / ${Math.round(x.size / 1024)}` : '-'); return `| ${k} | ${f(result.kinds.shield[k])} | ${f(result.kinds.legacy[k])} |`; }),
    '', '## Calls after the page (Simplify)', '', ...(result.calls.shield || []).map((c) => `- ${c.start} ms +${c.ms} ms ${c.name} (${c.size} B)`),
    '', '## Slowest files (Simplify)', '', ...[...(result.files.shield || [])].sort((a, b) => b.ms - a.ms).slice(0, 15).map((f) => `- ${f.ms} ms ${f.name} (${Math.round(f.size / 1024)} KB, start ${f.start})`)];
  fs.writeFileSync(path.join(out, 'speed.md'), md.join('\n') + '\n');
  console.log(md.join('\n'));
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
