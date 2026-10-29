// Records the clickable demo of the whole panel: walks every Simplify screen of a demo panel (customer side, admin side,
// host mode, every add-on page, menus and dialogs open) and saves each as a static page with its stylesheets and
// images, plus manifest.json. Buttons that open another recorded screen carry data-demo-go="<id>"; links are matched
// to recorded screens at the end. build.py (plannen/demo-paneel) turns the result into the Artifact.
// node capture.cjs --base http://127.0.0.1:8001 --urls urls.json --versions versions.json --out demo
const fs = require('fs'), path = require('path'), crypto = require('crypto');
const { chromium } = require('playwright');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base'), out = arg('out'), urls = JSON.parse(fs.readFileSync(arg('urls'), 'utf8'));
const versions = (() => { try { return JSON.parse(fs.readFileSync(arg('versions'), 'utf8')); } catch { return {}; } })();
for (const dir of ['screens', 'css', 'assets']) fs.mkdirSync(path.join(out, dir), { recursive: true });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const slug = (s) => String(s).toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 40) || 'x';
const rel = (u) => u.replace(/^https?:\/\/[^/]+/, '');
const norm = (u) => { try { const x = new URL(u.replace(/&amp;/g, '&'), base); return (x.pathname.replace(/\/+$/, '') || '/') + x.search; } catch { return u; } };
const hash = (s) => crypto.createHash('sha1').update(s).digest('hex').slice(0, 12);
const log = (...a) => console.log(...a);
const screens = [], problems = [], byUrl = {};
const PHONE = { width: 390, height: 844 }, DESKTOP = { width: 1440, height: 900 };

// ---- Saving a page --------------------------------------------------------------------------------------------
// In the browser: form values into attributes, canvases into images, server cards get their address, scripts go,
// and every stylesheet, image and link is turned into a token that the Node side resolves.
const serialize = () => {
  for (const i of document.querySelectorAll('input')) {
    if (i.type === 'checkbox' || i.type === 'radio') i.checked ? i.setAttribute('checked', '') : i.removeAttribute('checked');
    else if (i.type !== 'file' && i.type !== 'password') i.setAttribute('value', i.value);
  }
  for (const t of document.querySelectorAll('textarea')) t.textContent = t.value;
  for (const s of document.querySelectorAll('select')) for (const o of s.options) o.selected ? o.setAttribute('selected', '') : o.removeAttribute('selected');
  for (const c of document.querySelectorAll('canvas')) {
    try { const img = document.createElement('img'); img.src = c.toDataURL(); img.className = c.className; img.setAttribute('style', c.getAttribute('style') || ''); img.style.width = c.clientWidth + 'px'; img.style.height = c.clientHeight + 'px'; c.setAttribute('data-demo-canvas', img.outerHTML); } catch {}
  }
  for (const el of document.querySelectorAll('.ps-server-open, .ps-host-server')) {
    try { const s = window.Alpine?.$data(el)?.s; if (s?.url) el.setAttribute('data-demo-href', s.url); } catch {}
  }
  // Scrolled boxes keep their place; the fitted heights of a server page (--ps-fit, terminal, menu) are noted so
  // the demo can fit them to its own window.
  for (const el of document.querySelectorAll('*')) if (el.scrollTop > 0) el.setAttribute('data-demo-scroll', el.scrollTop);
  for (const el of document.querySelectorAll('#terminal, .ps-server-menu')) if (el.style.height) el.setAttribute('data-demo-h', parseFloat(el.style.height));
  document.documentElement.setAttribute('data-demo-vh', innerHeight);
  const abs = (v) => { try { return new URL(v, location.href).href; } catch { return v; } };
  const doc = document.documentElement.cloneNode(true);
  for (const el of doc.querySelectorAll('script, noscript, link[rel~=modulepreload], link[rel~=preload], link[rel~=prefetch], link[rel~=manifest], link[rel~=preconnect], link[rel~=dns-prefetch]')) el.remove();
  for (const c of doc.querySelectorAll('canvas[data-demo-canvas]')) c.outerHTML = c.getAttribute('data-demo-canvas');
  for (const el of doc.querySelectorAll('*')) {
    for (const name of el.getAttributeNames()) if (/^wire:(snapshot|effects)$/.test(name)) el.removeAttribute(name);
  }
  for (const l of doc.querySelectorAll('link[href]')) l.setAttribute('href', (/stylesheet/.test(l.rel) ? 'demo-css:' : 'demo-asset:') + abs(l.getAttribute('href')));
  for (const el of doc.querySelectorAll('[src]:not(iframe)')) { const v = el.getAttribute('src'); if (v && !/^(data|blob):/.test(v)) el.setAttribute('src', 'demo-asset:' + abs(v)); }
  for (const el of doc.querySelectorAll('[srcset]')) el.removeAttribute('srcset');
  for (const el of doc.querySelectorAll('use, image')) for (const name of ['href', 'xlink:href']) { const v = el.getAttribute(name); if (v && !v.startsWith('#') && !v.startsWith('data:')) el.setAttribute(name, 'demo-asset:' + abs(v)); }
  for (const a of doc.querySelectorAll('a[href]')) { const v = a.getAttribute('href'); if (v && !v.startsWith('#') && !/^(javascript|mailto):/.test(v)) { a.setAttribute('data-demo-url', abs(v)); } }
  for (const f of doc.querySelectorAll('iframe')) { f.setAttribute('src', f.getAttribute('data-demo-src') || 'about:blank'); f.removeAttribute('data-demo-src'); }
  return '<!doctype html>\n' + doc.outerHTML;
};

const downloaded = {};
async function fetchText(request, url) { const r = await request.get(url, { timeout: 20000 }); if (!r.ok()) throw new Error('HTTP ' + r.status()); return r; }
// Downloads a file once and returns its name in assets/ (or null when it does not load).
function asset(request, url) {
  return (downloaded[url] ??= download(request, url));
}
async function download(request, url) {
  try {
    const r = await fetchText(request, url);
    const type = (r.headers()['content-type'] || '').split(';')[0];
    let ext = path.extname(new URL(url).pathname).toLowerCase().slice(0, 8);
    if (!/^\.[a-z0-9]+$/.test(ext)) ext = { 'image/svg+xml': '.svg', 'image/png': '.png', 'image/jpeg': '.jpg', 'image/webp': '.webp', 'font/woff2': '.woff2', 'text/css': '.css' }[type] || '.bin';
    const name = hash(url) + ext;
    const body = await r.body();
    if (ext === '.css' || type === 'text/css') return await stylesheet(request, url, body.toString());
    fs.writeFileSync(path.join(out, 'assets', name), body);
    return '../assets/' + name;
  } catch (e) { problems.push('asset ' + url + ': ' + e.message); return null; }
}
async function replaceAsync(text, re, fn) {
  const found = [...text.matchAll(re)];
  const values = await Promise.all(found.map((m) => fn(...m)));
  let i = 0;
  return text.replace(re, () => values[i++]);
}
const CSS_URL = /url\(\s*(&quot;|['"]?)([^'")&]+?)\1\s*\)/g;
async function stylesheet(request, url, text) {
  const name = hash(url) + '.css';
  const fixed = await replaceAsync(text, CSS_URL, async (m, q, u) => {
    if (/^(data|#)/.test(u)) return m;
    const file = await asset(request, new URL(u, url).href);
    return file ? `url(${file})` : m;
  });
  fs.writeFileSync(path.join(out, 'css', name), fixed);
  return '../css/' + name;
}
async function resolveTokens(request, html, pageUrl) {
  html = await replaceAsync(html, /demo-css:([^"' >]+)/g, async (m, u) => (await asset(request, u.replace(/&amp;/g, '&'))) || 'about:blank');
  html = await replaceAsync(html, /demo-asset:([^"' >]+)/g, async (m, u) => (await asset(request, u.replace(/&amp;/g, '&'))) || '');
  return replaceAsync(html, CSS_URL, async (m, q, u) => {
    if (/^(data|#|\.\.\/)/.test(u)) return m;
    const file = await asset(request, new URL(u, pageUrl).href);
    return file ? `url(${file})` : m;
  });
}

// Every recorded page gets this script: buttons marked data-demo-go open that screen in the demo, other buttons and
// links say they are not part of the demo, and the theme follows the demo's light/dark switch.
const DEMO_SCRIPT = `<script>(()=>{const top_=window.top;const theme=(d)=>{document.documentElement.classList.toggle('dark',d);document.querySelectorAll('.ps,.ps-ui').forEach(e=>e.classList.toggle('dark',d));document.querySelectorAll('iframe').forEach(f=>{try{f.contentWindow.postMessage({demo:'theme',dark:d},'*')}catch(e){}})};addEventListener('message',e=>{if(e.data&&e.data.demo==='theme')theme(e.data.dark)});document.addEventListener('click',e=>{const go=e.target.closest('[data-demo-go]');if(go){e.preventDefault();e.stopPropagation();top_.postMessage({demo:'go',id:go.getAttribute('data-demo-go')},'*');return}const a=e.target.closest('a,button,[role=tab],[role=button],input[type=submit]');if(!a)return;if(a.matches('a[target=_blank]')&&/^https?:/.test(a.getAttribute('data-demo-url')||'')&&!a.hasAttribute('data-demo-inside')){e.preventDefault();open(a.getAttribute('data-demo-url'),'_blank','noopener');return}e.preventDefault();e.stopPropagation();top_.postMessage({demo:'dead',label:(a.innerText||a.getAttribute('aria-label')||a.getAttribute('data-tip')||'').trim().replace(/\\s+/g,' ').slice(0,60)},'*')},true);document.addEventListener('submit',e=>e.preventDefault(),true);const r=document.documentElement,was=parseFloat(r.style.getPropertyValue('--ps-fit'));const fit=()=>{if(was){const m=document.querySelector('.fi-main-ctn > .fi-main'),on=!!m&&innerWidth>600&&innerHeight>500;r.classList.toggle('ps-fit',on);if(on){const f=document.querySelector('.fi-footer,[data-ps-footer]')?.offsetHeight||0,h=Math.max(240,Math.floor(innerHeight-(m.getBoundingClientRect().top+scrollY)-f-14));r.style.setProperty('--ps-fit',h+'px');document.querySelectorAll('#terminal[data-demo-h]').forEach(e=>e.style.height=Math.max(240,Math.floor(e.offsetHeight+h-m.scrollHeight))+'px');const t=document.getElementById('terminal'),mn=document.querySelector('.ps-server-menu[data-demo-h]');if(t&&mn)mn.style.height=t.parentElement.offsetHeight+'px'}else document.querySelectorAll('[data-demo-h]').forEach(e=>e.style.height='')}document.querySelectorAll('[data-demo-scroll]').forEach(e=>e.scrollTop=+e.dataset.demoScroll)};addEventListener('resize',fit);addEventListener('load',fit);fit();top_.postMessage({demo:'ready'},'*')})();</script>`;

async function serializeTo(frame, file, request) {
  const html = await frame.evaluate(serialize);
  const fixed = (await resolveTokens(request, html, frame.url())).replace(/<\/body>/i, DEMO_SCRIPT + '</body>');
  fs.writeFileSync(path.join(out, 'screens', file), fixed);
}

// Saves the page as screen <id>: marks first, then each visible iframe as its own file, then the page itself.
async function snapshot(page, screen, marks) {
  await page.evaluate(() => document.querySelectorAll('[data-demo-go],[data-demo-src]').forEach((e) => { e.removeAttribute('data-demo-go'); e.removeAttribute('data-demo-src'); }));
  for (const { click, to } of marks) {
    try { const n = await locate(page, click).count(); if (n) await locate(page, click).first().evaluate((e, id) => e.setAttribute('data-demo-go', id), to); } catch {}
  }
  const request = page.context().request;
  const frames = await page.$$('iframe');
  let n = 0;
  for (const handle of frames) {
    if (!(await handle.isVisible().catch(() => false))) continue;
    const frame = await handle.contentFrame();
    if (!frame || !/^https?:/.test(frame.url())) continue;
    await frame.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    const file = `${screen.id}~${++n}.html`;
    try { await serializeTo(frame, file, request); await handle.evaluate((e, f) => e.setAttribute('data-demo-src', f), file); } catch (e) { problems.push(`${screen.id} frame: ${e.message}`); }
  }
  await serializeTo(page, screen.id + '.html', request);
  screen.file = screen.id + '.html';
  screens.push(screen);
  log('saved', screen.id, '-', screen.title);
}

// ---- Walking the screens --------------------------------------------------------------------------------------
const locate = (page, click) => {
  const c = typeof click === 'string' ? { css: click } : click;
  let l = page.locator(c.css + ' >> visible=true');
  if (c.text) l = l.filter({ hasText: c.text instanceof RegExp ? c.text : new RegExp('^\\s*' + c.text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*$') });
  return c.nth ? l.nth(c.nth) : l.first();
};
const settle = async (page, ms = 700) => { await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {}); await sleep(ms); };
const alpine = (page, body) => page.evaluate((b) => new Function('d', b)(window.Alpine.$data(document.querySelector('.ps'))), body);

async function walk(page, node, reach, parent, inherited = []) {
  const id = node.id;
  try {
    await reach();
  } catch (e) { problems.push(`${id}: could not open (${String(e.message).split('\n')[0]})`); return; }
  // Children found on the page itself: every tab, menu item or tree leaf that matches.
  let children = [...(node.children || [])];
  if (node.expand) {
    const texts = await page.locator(node.expand.css + ' >> visible=true').allInnerTexts().catch(() => []);
    const ids = new Set();
    texts.slice(0, node.expand.limit || texts.length).forEach((raw, nth) => {
      const text = raw.trim().split('\n')[0].trim();
      if (!text) return;
      let child = `${id}-${slug(text)}`;
      if (ids.has(child) || screens.some((x) => x.id === child)) child += '-' + nth;
      ids.add(child);
      children.push({ id: child, title: (node.expand.title || '') + text, click: { css: node.expand.css, nth }, sibling: true, ...(node.expand.child || {}) });
    });
  }
  const own = children.map((c) => ({ click: c.click, to: c.id }));
  const marks = [...inherited, ...own];
  if (node.toggle && parent) marks.push({ click: node.click, to: parent.id });
  if (node.sibling && parent) for (const s of parent._children || []) marks.push({ click: s.click, to: s.id });
  for (const l of node.links || []) marks.push(l);
  node._children = children;
  const screen = { id, title: node.title, group: node.group || parent?.group, parent: parent?.id || null, url: rel(page.url()) };
  if (!parent || node.root) byUrl[norm(page.url())] ??= id;
  for (const alias of node.aliases || []) byUrl[norm(alias)] ??= id;
  await snapshot(page, screen, marks);
  if (node.phone) {
    await page.setViewportSize(PHONE);
    try { await reach(); await snapshot(page, { ...screen, id: id + '@phone', phoneOf: id }, marks); } catch (e) { problems.push(`${id}@phone: ${e.message}`); }
    await page.setViewportSize(DESKTOP);
  }
  for (const child of children) {
    await walk(page, { group: node.group || parent?.group, ...child }, async () => {
      await reach();
      const target = locate(page, child.click);
      if (!(await target.count())) throw new Error('button not found: ' + JSON.stringify(child.click));
      await target.click();
      await settle(page, child.wait || 900);
    }, { ...node, group: node.group || parent?.group }, node.expand?.keep ? [...inherited, ...own] : inherited);
  }
}
const open = (page, url, after) => async () => {
  const r = await page.goto(rel(url));
  await settle(page, 1200);
  if (!r || r.status() !== 200) throw new Error('HTTP ' + (r ? r.status() : 'none') + ' for ' + rel(url));
  if (page.url().includes('/login')) throw new Error('sent to the login');
  if (after) await after(page);
};

async function login(browser, email) {
  const context = await browser.newContext({ viewport: DESKTOP, baseURL: base });
  const page = await context.newPage();
  page.setDefaultTimeout(10000);
  await page.goto('/login'); await settle(page);
  await page.fill('#form\\.login', email); await page.fill('#form\\.password', 'DemoPassword123!');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 30000 }).catch(() => {});
  await page.evaluate(() => localStorage.setItem('theme', 'light'));
  return page;
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await login(browser, 'demo@example.com');
  const overview = urls.app.find((p) => /App\\Pages\\Overview$/.test(p.class))?.url;
  const settings = urls.admin.find((p) => /Admin\\Pages\\Settings$/.test(p.class))?.url;
  const ready = (p) => p.waitForSelector('.ps-server-card', { timeout: 15000 }).catch(() => {});
  const view = (name) => async (p) => { await ready(p); await alpine(p, `d.setOverviewView('${name}')`); await sleep(700); };
  const mainServer = 'Survival SMP';
  const serverUrl = urls.servers[mainServer];

  // Customer side, as the admin sees it.
  await walk(page, { id: 'overview', title: 'Overzicht', group: 'Klantkant', phone: true, children: [
    { id: 'overview-account', title: 'Accountmenu open', click: '.ps-account-button', toggle: true },
    { id: 'overview-bell', title: 'Meldingen (bel) open', click: '[aria-controls="ps-notify-menu"]', toggle: true },
    { id: 'overview-admin', title: 'Beheermenu open', click: '[x-ref="adminButton"]', toggle: true },
    { id: 'overview-customize', title: 'Weergave aanpassen', click: '.ps-overview-appearance:not(.ps-overview-groups) > button', toggle: true, links: ['grid', 'compact', 'list'].map((v, nth) => ({ click: { css: '[x-on\\:click="setOverviewView(view.key)"]', nth }, to: v === 'grid' ? 'overview' : 'overview-' + v })) },
    { id: 'overview-groups', title: 'Servergroepen', click: '.ps-overview-groups > button', toggle: true },
    { id: 'overview-health', title: 'Gezondheid van de host', click: '.ps-health-pill', toggle: true },
  ] }, open(page, overview, ready));
  await walk(page, { id: 'overview-compact', title: 'Overzicht, compact', group: 'Klantkant', root: false }, open(page, overview, view('compact')));
  await walk(page, { id: 'overview-list', title: 'Overzicht, lijst', group: 'Klantkant', root: false }, open(page, overview, view('list')));
  await alpine(page, `d.setOverviewView('grid')`).catch(() => {});
  for (const p of urls.app.filter((x) => x.url !== overview)) {
    await walk(page, { id: 'app-' + slug(p.title), title: p.title, group: 'Klantkant', phone: true }, open(page, p.url));
  }

  // The server page and every add-on page of a server.
  await walk(page, { id: 'server', title: `Serverpagina (${mainServer})`, group: 'Server', phone: true, aliases: [serverUrl], children: [
    { id: 'server-menu', title: 'Server ⋯-menu', click: '.ps-info-more-button', toggle: true },
  ] }, open(page, serverUrl, async () => sleep(1500)));
  // Pelican's Edit schedule page with three tasks: the content must scroll inside its box so Tasks stays reachable.
  if (urls.schedules?.[mainServer]) await walk(page, { id: 'server-schedule-edit', title: `Schedule bewerken (${mainServer})`, group: 'Server', phone: true }, open(page, urls.schedules[mainServer], async () => sleep(1500)));
  else problems.push('no schedule to record');
  const serverPages = new Map();
  for (const [name, pages] of Object.entries(urls.server)) for (const p of pages) if (p.url && !serverPages.has(p.class)) serverPages.set(p.class, { ...p, server: name });
  // Each add-on page on the main server when it has one there, otherwise on the first server that has it.
  for (const p of serverPages.values()) {
    const onMain = (urls.server[mainServer] || []).find((x) => x.class === p.class);
    const target = onMain || p;
    const r = await page.request.get(rel(onMain ? onMain.url : p.url)).catch(() => null);
    let use = target;
    if (!r || r.status() !== 200) {
      use = null;
      for (const [name, pages] of Object.entries(urls.server)) {
        const q = pages.find((x) => x.class === p.class);
        const rr = q && (await page.request.get(rel(q.url)).catch(() => null));
        if (rr && rr.status() === 200) { use = { ...q, server: name }; break; }
      }
    }
    if (!use) { problems.push(`server page ${p.class}: no server opens it`); continue; }
    const id = 'server-' + slug(p.title || p.class.split('\\').pop());
    await walk(page, { id, title: `${p.title} (${use.server || mainServer})`, group: 'Server' }, open(page, use.url, async () => sleep(1200)));
  }

  // Admin side: the settings with every tab, people, announcements and every add-on page.
  await walk(page, { id: 'settings', title: 'Instellingen', group: 'Beheer', phone: true,
    expand: { css: 'main [role=tab]', title: 'Instellingen · ', child: { wait: 1500 } } }, open(page, settings));
  for (const p of urls.admin.filter((x) => x.url !== settings && !/Setup$/.test(x.class))) {
    const extra = /People$/.test(p.class) ? { expand: { css: 'main .s-segment', title: 'People · ' }, children: [{ id: 'people-add', title: 'Persoon toevoegen', click: { css: 'main button', text: /Add person/i }, wait: 1200 }] }
      : /AnnouncementResource$/.test(p.class) ? { children: [{ id: 'announcements-new', title: 'Nieuwe aankondiging', click: { css: 'main button, main a', text: /^\s*New announcement\s*$/i }, wait: 1200 }] } : {};
    await walk(page, { id: 'admin-' + slug(p.title), title: p.title, group: 'Beheer', ...extra }, open(page, p.url));
  }

  // Host mode last: switching it on is saved for this admin.
  const hostOn = async (p) => {
    await ready(p);
    if (await p.locator('.ps-host >> visible=true').count()) return;
    await p.locator('.ps-account-button').first().click(); await sleep(400);
    await p.locator('#ps-account-actions button[aria-label="Switch to host mode"]').first().click(); await settle(p, 2500);
  };
  // Every section and page of the admin tree once, as siblings; the server list with the first three servers.
  await walk(page, { id: 'host', title: 'Hostmodus', group: 'Hostmodus', phone: true,
    expand: { css: '.ps-host-group-head, .ps-host-leaf', title: 'Hostmodus · ', child: { wait: 2500 } },
    children: [{ id: 'host-serverlist', title: 'Hostmodus · serverlijst', click: { css: '.ps-host-tabs button', nth: 1 }, links: [{ click: { css: '.ps-host-tabs button', nth: 0 }, to: 'host' }], expand: { css: '.ps-host-server strong', title: 'Hostmodus · server ', limit: 3, child: { wait: 2000 } } }],
  }, open(page, overview, hostOn));
  await alpine(page, 'd.setHostMode(false)').catch(() => {});

  // The customer's own view: only their servers, with the hosting links.
  const customer = await login(browser, 'sam@example.com');
  await walk(customer, { id: 'customer', title: 'Overzicht als klant (sam)', group: 'Klantkant', phone: true, root: false, children: [
    { id: 'customer-account', title: 'Accountmenu klant', click: '.ps-account-button', toggle: true },
  ] }, open(customer, overview, ready));

  // Links between recorded screens: a link or server card whose address is a recorded screen opens it.
  const files = fs.readdirSync(path.join(out, 'screens'));
  for (const f of files) {
    const p = path.join(out, 'screens', f);
    let html = fs.readFileSync(p, 'utf8');
    html = html.replace(/data-demo-(url|href)="([^"]*)"/g, (m, kind, u) => {
      const id = byUrl[norm(u)];
      return id && !m.includes('data-demo-go') ? `${m} data-demo-go="${id}"` : m;
    });
    fs.writeFileSync(p, html);
  }
  // A button marked twice keeps the first mark; a duplicated attribute is not valid HTML.
  for (const f of files) {
    const p = path.join(out, 'screens', f);
    fs.writeFileSync(p, fs.readFileSync(p, 'utf8').replace(/(data-demo-go="[^"]*")((?:\s+[^\s>]+)*?)\s+data-demo-go="[^"]*"/g, '$1$2'));
  }
  fs.writeFileSync(path.join(out, 'manifest.json'), JSON.stringify({ made: new Date().toISOString(), versions, screens, problems }, null, 2));
  log(`${screens.length} screens, ${problems.length} problems`);
  for (const p of problems) log('PROBLEM ' + p);
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
