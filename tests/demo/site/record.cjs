// Records the demo panel as a static site that still works when clicked: every page is kept with its own scripts, and
// every request those scripts make (Livewire calls, panel API) is saved with its answer, so runtime.js can answer them
// later without a panel. Each page is visited, then its buttons, menus and tabs are clicked (and what they open, one
// level deeper) on a fresh copy of the database.
// node record.cjs --base http://127.0.0.1:8001 --panel ../../../panel --urls urls.json --servers servers.json --out site
const fs = require('fs'), path = require('path');
const { chromium } = require('playwright');
const K = require('./key.js');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base').replace(/\/$/, ''), out = arg('out'), panel = arg('panel');
const urls = JSON.parse(fs.readFileSync(arg('urls'), 'utf8'));
const servers = JSON.parse(fs.readFileSync(arg('servers'), 'utf8'));
const minutes = Number(arg('minutes', 50)), maxPages = Number(arg('pages', 400));
const MARK = '__DEMO_ROOT__/';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...a) => console.log(...a);
const db = path.join(panel, 'database/database.sqlite'), pristine = db + '.demo';
fs.copyFileSync(db, pristine);
const restore = () => fs.copyFileSync(pristine, db);
const write = (file, data) => { fs.mkdirSync(path.dirname(file), { recursive: true }); fs.writeFileSync(file, data); };
// The panel's address in any spelling: plain, or with its slashes escaped once or more inside JSON.
const [scheme, host] = base.split('://');
const ORIGIN_ANY = new RegExp(scheme + ':(\\\\*)/\\1/' + host.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '((?:\\1/[A-Za-z0-9._~%+\\-=:@,;!$?]*)*)', 'g');
const origins = (text, to) => text.replace(ORIGIN_ANY, (m, esc, p) => to(p.split(esc + '/').join('/') || '/').split('/').join(esc + '/'));
const pathOf = (u) => { const x = new URL(u, base); return x.pathname + x.search; };
const skip = (p) => /^\/(logout|auth|sanctum|_debugbar|telescope|api\/client\/.*\/download)|\/(download|logout)\b/.test(p);

for (const f of ['key.js', 'runtime.js']) write(path.join(out, 'demo', f), fs.readFileSync(path.join(__dirname, f)));
write(path.join(out, 'demo/replay/servers.json'), '{}');

async function record(tree, email, starts) {
  const dir = path.join(out, tree), tables = {}, pages = new Set(), started = Date.now();
  clicked = new Set();
  const toTree = (p) => MARK + K.file(p);
  const marked = (text) => origins(text, toTree);
  const relative = (fromFile, text) => {
    const up = '../'.repeat(fromFile.split('/').length - 1) || './';
    return origins(text, (p) => up + K.file(p)).replace(/(\s(?:href|src|action)=")\/(?!\/)([^"]*)"/g, (m, a, p) => a + up + K.file('/' + p) + '"');
  };
  // A page: what the browser loads while reading the page (scripts, src/href) gets a relative address; addresses in
  // data the scripts read later (x-data, wire:snapshot) get the marker, which runtime.js turns into a full address,
  // because scripts such as safeImage() resolve them against the site root.
  const pageHtml = (fromFile, html) => {
    html = html.replace(/(<script\b[^>]*>)([\s\S]*?)(<\/script>)/gi, (m, a, b, c) => relative(fromFile, a) + relative(fromFile, b) + c);
    html = html.replace(/(\s(?:src|href|action|poster|data-update-uri)=")([^"]*)(")/g, (m, a, v, c) => relative(fromFile, a + v + c));
    return marked(html);
  };
  const table = (name) => (tables[name] ??= {});

  const browser = await chromium.launch();
  const context = await browser.newContext({ baseURL: base, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  page.on('close', () => log(`${tree}: the page closed`));
  context.on('page', (p) => { if (p !== page) p.close().catch(() => {}); });
  page.setDefaultTimeout(15000);
  // Everything the page loads from the panel is kept: files as files, calls in the tables.
  page.on('requestfinished', async (req) => {
    try {
      const url = req.url();
      if (!url.startsWith(base)) return;
      const res = await req.response(); if (!res) return;
      const p = pathOf(url), type = (res.headers()['content-type'] || '').split(';')[0];
      if (req.method() === 'POST' && /^\/livewire(-[0-9a-f]+)?\/update/.test(p)) {
        page.demoPosts = (page.demoPosts || 0) + 1;
        const body = JSON.parse(req.postData() || '{}'), answer = await res.json();
        body.components.forEach((c, i) => {
          const back = answer.components?.[i]; if (!back) return;
          const [name, keys, snap] = K.keys(c);
          const t = table('lw-' + K.h(name));
          const entry = { req: { id: snap.memo.id, children: snap.memo.children || {} }, res: marked(JSON.stringify(back)) };
          for (const k of keys) t[k] ??= entry;
        });
        return;
      }
      if (req.resourceType() === 'document') return;
      if (req.method() === 'GET' && !K.isPage(p.split('?')[0])) {
        const buf = await res.body();
        const file = path.join(dir, K.file(p));
        write(file, /^(text\/css|application\/javascript|text\/javascript)$/.test(type) ? relative(K.file(p), buf.toString()) : buf);
        return;
      }
      if (skip(p) || type === 'text/html') return;
      const bare = p.split('?')[0], t = table('x-' + K.h(req.method() + ' ' + bare));
      const entry = { status: res.status(), type, body: marked(await res.text()) };
      t[K.h(req.method() + ' ' + p + ' ' + (req.method() === 'GET' ? '' : req.postData() || ''))] ??= entry;
      t['*'] ??= entry;
    } catch {}
  });
  // Settled: the page has loaded and no call to the panel has been open for 400 ms (polling never lets networkidle happen).
  let open = 0, quiet = Date.now();
  page.on('request', (r) => { if (r.url().startsWith(base) && r.resourceType() !== 'document') open++; });
  const done = (r) => { if (r.url().startsWith(base) && r.resourceType() !== 'document') { open = Math.max(0, open - 1); quiet = Date.now(); } };
  page.on('requestfinished', done); page.on('requestfailed', done);
  const settle = async () => {
    await page.waitForLoadState('load', { timeout: 10000 }).catch(() => {});
    const until = Date.now() + 5000;
    await sleep(250);
    while (Date.now() < until && (open > 0 || Date.now() - quiet < 400)) await sleep(100);
  };

  if (email) {
    await page.goto('/login'); await settle();
    await page.fill('#form\\.login', email); await page.fill('#form\\.password', 'DemoPassword123!');
    await page.keyboard.press('Enter');
    await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 30000 }).catch(() => {});
  }

  const queue = [...starts], seen = new Set(queue.map((u) => pathOf(u))), patterns = new Set();
  const shape = (p) => p.replace(/^\/server\/[^/?]+/, '/server/:s').replace(/\/\d+(?=\/|$|\?)/g, '/:n');
  while (queue.length && pages.size < maxPages) {
    const want = pathOf(queue.shift());
    restore();
    let res;
    try { res = await page.goto(want); } catch (e) { log(`${tree} ${want}: ${e.message}`); continue; }
    if (!res || res.status() >= 400) { log(`${tree} ${want}: HTTP ${res && res.status()}`); continue; }
    await settle();
    const got = pathOf(page.url());
    if (got !== want && K.isPage(want.split('?')[0])) {
      const from = K.file(want);
      write(path.join(dir, from), `<!doctype html><meta charset="utf-8"><script>location.replace(${JSON.stringify('../'.repeat(from.split('/').length - 1) + K.file(got))})</script>`);
      pages.add(from);
    }
    if (got.startsWith('/login')) continue;
    const file = K.file(got);
    if (!pages.has(file) || !fs.existsSync(path.join(dir, file))) {
      const up = '../'.repeat(file.split('/').length);
      let html = pageHtml(file, await res.text());
      html = html.replace(/<head([^>]*)>/i, `<head$1><script>window.DEMO_TREE=${JSON.stringify(tree)}</script><script src="${up}demo/key.js"></script><script src="${up}demo/pages-${tree}.js"></script><script src="${up}demo/runtime.js"></script>`);
      write(path.join(dir, file), html);
      pages.add(file);
      log(`${tree} page ${got}`);
    }
    for (const href of await page.$$eval('a[href]', (as) => as.map((a) => a.href))) {
      if (!href.startsWith(base)) continue;
      const p = pathOf(href.split('#')[0]);
      if (seen.has(p) || skip(p) || !K.isPage(p.split('?')[0])) continue;
      seen.add(p); queue.push(p);
    }
    // Click round: once per page shape, more servers only while there is time.
    const key = shape(got);
    if (patterns.has(key) && Date.now() - started > minutes * 30e3) continue;
    if (Date.now() - started > minutes * 60e3) continue;
    patterns.add(key);
    await clickRound(page, got, settle, key);
  }
  await browser.close();
  for (const [name, t] of Object.entries(tables)) write(path.join(out, 'demo/replay', tree, name + '.json'), JSON.stringify(t));
  write(path.join(out, 'demo/replay', tree, 'servers.json'), JSON.stringify(Object.fromEntries(servers.map((s) => [s.uuid, { uuid: s.uuid, state: s.state || 'running', memory: s.memory, cpu: s.cpu, console: s.console || [] }]))));
  write(path.join(out, 'demo', `pages-${tree}.js`), `window.DEMO_PAGES=${JSON.stringify([...pages].map((p) => tree + '/' + p))};`);
  return pages.size;
}

// Marks what can be clicked on the page right now and is visible; returns [number, what it is] pairs.
const MARKABLE = `button, [role=tab], [role=menuitem], [wire\\:click], [x-on\\:click], [\\@click], summary`;
async function clickables(page, after) {
  return page.evaluate(([sel, after]) => {
    const out = [];
    let i = after;
    for (const el of document.querySelectorAll(sel)) {
      if (el.closest('.xterm, [data-demo-no]') || el.hasAttribute('data-demo-i')) continue;
      const r = el.getBoundingClientRect();
      if (!r.width || !r.height || getComputedStyle(el).visibility === 'hidden') continue;
      if (/log ?out|sign ?out|delete account/i.test(el.textContent)) continue;
      const what = ['wire:click', 'x-on:click', '@click', 'aria-label', 'href'].map((a) => el.getAttribute(a) || '').join('|') + '|' + el.textContent.trim().replace(/\s+/g, ' ').slice(0, 40);
      el.setAttribute('data-demo-i', ++i); out.push([i, what]);
    }
    return out;
  }, [MARKABLE, after]);
}
// Clicks everything on the page once, and what each click opens (menus, dialogs) one level deeper. The page is only
// loaded again (on a fresh database) after a click that went to the panel or left the page.
let clicked = new Set();
async function clickRound(page, url, settle, shape) {
  const end = Date.now() + 120e3;
  const at = (i) => page.locator(`[data-demo-i="${i}"]`);
  let posts = page.demoPosts;
  const fresh = async () => { restore(); await page.goto(url); await settle(); posts = page.demoPosts; return clickables(page, 0); };
  const dirty = () => page.demoPosts !== posts || pathOf(page.url()) !== url;
  const calm = async () => { if (dirty()) return fresh(); await page.keyboard.press('Escape').catch(() => {}); await sleep(150); };
  const first = (await fresh()).filter(([, what]) => !clicked.has(shape + what)).slice(0, 60);
  for (const [i, what] of first) {
    if (Date.now() > end) break;
    clicked.add(shape + what);
    if (process.env.DEMO_DEBUG) log('  click', i, what.slice(0, 90));
    try {
      await calm();
      if (await at(i).count() === 0) await fresh();
      await at(i).click({ timeout: 2000 }); await settle();
      if (pathOf(page.url()) !== url) continue;
      const opened = (await clickables(page, 1000)).filter(([, w]) => !clicked.has(shape + what + w)).slice(0, 8);
      for (const [j, w] of opened) {
        clicked.add(shape + what + w);
        if (process.env.DEMO_DEBUG) log('    then', j, w.slice(0, 90));
        try {
          if (dirty() || await at(j).count() === 0) {
            await fresh(); await at(i).click({ timeout: 2000 }); await settle(); await clickables(page, 1000);
          }
          await at(j).click({ timeout: 2000 }); await settle();
        } catch {}
      }
    } catch (e) { if (process.env.DEMO_DEBUG) log('  failed', e.message.split('\n')[0]); }
  }
}

(async () => {
  const s = (o) => Object.values(o || {}).flatMap((v) => (Array.isArray(v) ? v.map((x) => x.url || x) : [v]));
  const admin = ['/', ...s({ a: urls.app, b: urls.admin }), ...Object.values(urls.servers || {}), ...s(urls.server), ...Object.values(urls.schedules || {})].filter(Boolean);
  log('admin pages', await record('admin', 'demo@example.com', admin));
  log('customer pages', await record('customer', 'sam@example.com', ['/']));
  // The way in: Pelican's own sign-in page, with a box that opens either recording.
  const browser = await chromium.launch(), page = await browser.newPage();
  const res = await page.goto(base + '/login');
  let html = origins(await res.text(), (p) => 'admin/' + K.file(p)).replace(/(\s(?:href|src)=")\/(?!\/)([^"]*)"/g, (m, a, p) => a + 'admin/' + K.file('/' + p) + '"');
  html = html.replace(/<head([^>]*)>/i, `<head$1><script>window.DEMO_TREE="admin"</script><script src="demo/key.js"></script><script src="demo/runtime.js"></script><script src="demo/login.js" defer></script>`);
  write(path.join(out, 'index.html'), html);
  await browser.close();
  write(path.join(out, 'demo/login.js'), fs.readFileSync(path.join(__dirname, 'login.js')));
})();
