// The panel's name in the phone top bar in a real browser, without a panel: on a server page, beside the back button,
// a logo and a name that fit on the Admin page fit there too, and a name too long ends in "…" instead of being cut off
// (K-B104, VoiceCrack93: "VoiceNation Pane" in the iOS app). Needs Chrome or Chromium (GitHub's runners have Chrome).
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const candidates = [process.env.CHROME, '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/opt/pw-browsers/chromium'].filter(Boolean);
const chrome = candidates.map((file) => (fs.existsSync(file) && fs.statSync(file).isDirectory() ? findChrome(file) : file)).find((file) => file && fs.existsSync(file));
function findChrome(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isFile() && entry.name === 'chrome') return full;
    if (entry.isDirectory()) { const found = findChrome(full); if (found) return found; }
  }
  return null;
}
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/brand-fit.cjs (set CHROME)'); process.exit(1); }

const root = path.join(__dirname, '..', 'resources');
const css = path.join(root, 'css');
const esc = (text) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;');
const logo = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><circle cx="32" cy="32" r="30" fill="#e8590c"/></svg>');

// The phone top bar as dashboard/header.blade.php renders it: on a server page with the back button, on the Admin
// page without it, with a logo beside the panel's name, the heart, the bell and the account.
const page = (root, brand, back) => `<!doctype html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/components.css"><link rel="stylesheet" href="file://${css}/dashboard.css"><link rel="stylesheet" href="file://${css}/seasons.css">
<style>*,::before,::after{box-sizing:border-box}body{margin:0}</style>
</head><body><div class="${root}"><div class="ps-main">
<header class="ps-top">
  <div class="ps-heading">
    <div class="ps-identity"><a href="#"><h1 class="ps-brand"><img class="ps-brand-logo" src="${logo}" alt="" style="height:32px"><span class="ps-panel-name">${esc(brand)}</span><span class="ps-title-pelican">× Pelican</span></h1></a><span class="ps-season-badges" style="display:none"></span></div>
    ${back ? '<a href="#" class="ps-btn ps-back-overview"><svg aria-hidden="true"></svg><span>All servers</span></a>' : ''}
  </div>
  <nav class="ps-top-actions">
    <button type="button" class="ps-btn is-icon is-subtle ps-mobile-menu"><svg></svg></button>
    <div class="ps-nav-items">
      <div class="ps-notify"><button type="button" class="ps-btn is-icon is-subtle"><svg></svg></button></div>
      <div class="ps-account"><button type="button" class="ps-btn ps-account-button"><span style="display:block;width:28px;height:28px;border-radius:50%"></span><span class="ps-account-username">Jeroen</span></button></div>
    </div>
  </nav>
</header></div></div>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    const name = document.querySelector('.ps-panel-name'), brand = document.querySelector('.ps-brand'), top = document.querySelector('.ps-top');
    const box = (el) => el.getBoundingClientRect();
    // Where the name is cut: by its own box (with "…"), or by a box around it (no "…", the bug).
    const clip = [...document.querySelectorAll('.ps-heading, .ps-identity, .ps-identity > a, .ps-brand')].filter((el) => getComputedStyle(el).overflow !== 'visible' && box(name).right > box(el).right + 0.5).map((el) => el.className || el.tagName);
    document.getElementById('out').textContent = JSON.stringify({
      nameWidth: Math.round(box(name).width), nameRight: Math.round(box(name).right), brandRight: Math.round(box(brand).right),
      ownEllipsis: name.scrollWidth > name.clientWidth + 1 && getComputedStyle(name).textOverflow === 'ellipsis',
      cutOutside: clip, full: name.scrollWidth <= name.clientWidth + 1 && clip.length === 0,
      topOverflow: top.scrollWidth > top.clientWidth + 1, pageScrollX: document.documentElement.scrollWidth - innerWidth,
    });
  }, 300));
</script></body></html>`;

const server = 'ps server-active ps-server-top', admin = 'ps server-active host-mode ps-admin-top';

// A browser at the exact width over the DevTools pipe (--window-size stops at 500 wide), so no package is needed.
const run = (html, width, height) => new Promise((resolve, reject) => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-brand-fit-'));
  const file = path.join(dir, 'page.html');
  fs.writeFileSync(file, html);
  const browser = spawn(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--hide-scrollbars', '--remote-debugging-pipe', '--user-data-dir=' + path.join(dir, 'profile'), 'about:blank'], { stdio: ['ignore', 'ignore', 'ignore', 'pipe', 'pipe'] });
  const [, , , input, output] = browser.stdio;
  let id = 0, buffer = '', session = null;
  const waiting = new Map();
  const send = (method, params = {}) => new Promise((done) => { waiting.set(++id, done); input.write(JSON.stringify({ id, method, params, ...(session ? { sessionId: session } : {}) }) + '\0'); });
  output.on('data', (chunk) => {
    buffer += chunk;
    for (let end; (end = buffer.indexOf('\0')) >= 0; buffer = buffer.slice(end + 1)) {
      const message = JSON.parse(buffer.slice(0, end));
      if (waiting.has(message.id)) { waiting.get(message.id)(message.result ?? {}); waiting.delete(message.id); }
    }
  });
  const finish = (result) => {
    clearTimeout(timer);
    browser.once('exit', () => { try { fs.rmSync(dir, { recursive: true, force: true, maxRetries: 10, retryDelay: 100 }); } catch {} resolve(result); });
    session = null;
    send('Browser.close');
    setTimeout(() => browser.kill(), 2000);
  };
  const timer = setTimeout(() => finish(null), 30000);
  browser.on('error', reject);
  (async () => {
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
    session = (await send('Target.attachToTarget', { targetId, flatten: true })).sessionId;
    await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
    await send('Page.navigate', { url: 'file://' + file });
    for (let tries = 0; tries < 200; tries++) {
      const { result } = await send('Runtime.evaluate', { expression: "document.getElementById('out')?.textContent || ''", returnByValue: true });
      if (result?.value) return finish(JSON.parse(result.value));
      await new Promise((done) => setTimeout(done, 50));
    }
    finish(null);
  })().catch(reject);
});

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
// A name that is cut ends in "…" in its own box; a box around it never cuts it off without one (K-B104).
const tidy = (r) => r.cutOutside.length === 0 && !r.topOverflow && r.pageScrollX <= 0;
(async () => {
  for (const width of [390, 375, 430]) {
    const onServer = await run(page(server, 'VoiceNation Panel', true), width, 844);
    const onAdmin = await run(page(admin, 'VoiceNation Panel', false), width, 844);
    if (!onServer || !onAdmin) { check(`phone ${width} (measured)`, false, null); continue; }
    check(`phone ${width}, Admin page: logo and "VoiceNation Panel" in full`, onAdmin.full && tidy(onAdmin), onAdmin);
    check(`phone ${width}, server page: logo and "VoiceNation Panel" in full, as on the Admin page`, onServer.full && tidy(onServer), onServer);
    const long = await run(page(server, 'Deckzwabbers Gameservers Nederland', true), width, 844);
    check(`phone ${width}, server page, long name: ends in "…", never cut off without one`, long && tidy(long) && (long.full || long.ownEllipsis), long);
  }
  if (failures) { console.error(`${failures} panel name check(s) failed`); process.exit(1); }
  console.log('Panel name: in full where it fits, else ending in "…".');
})();
