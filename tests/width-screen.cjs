// K-B66 (betatester, 9-11, 2.0.0-beta.11 on Pelican beta38): with Pelican's Display width set to "Screen" the Health
// page and the server console lay over the whole window, over Simplify's menu. Filament gives .fi-main.fi-width-screen
// position: fixed and inset: 0; on Simplify's pages the page stays in its own place. Needs Chrome or Chromium.
const { execFileSync } = require('node:child_process');
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/width-screen.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
// Filament 5.7.6 (Pelican beta38) and 5.10.2 (rc.2) as far as it matters: Display width "Screen".
const filament = '*{box-sizing:border-box}body{margin:0}.fi-layout{display:flex;width:100%;min-height:100dvh}.fi-main-ctn{flex:1;display:flex;flex-direction:column}:is(.fi-main,.fi-simple-main).fi-width-screen{inset:0;position:fixed}';
const links = ['tokens', 'simplify', 'components', 'dashboard'].map((f) => `<link rel="stylesheet" href="file://${css}/${f}.css">`).join('');
const measure = `<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>addEventListener('load', () => setTimeout(() => {
  const main = document.querySelector('.fi-main'), box = document.querySelector('.fi-main-ctn').getBoundingClientRect(), m = main.getBoundingClientRect();
  document.getElementById('out').textContent = JSON.stringify({ position: getComputedStyle(main).position, left: Math.round(m.left), right: Math.round(m.right), top: Math.round(m.top), boxLeft: Math.round(box.left), boxRight: Math.round(box.right), boxTop: Math.round(box.top) });
}, 300));</script>`;
const pages = {
  // The Admin page (Health) in Simplify's frame: header, menu, the page beside it.
  admin: `<html class="dark ps-shell ps-shell-menu"><head><meta charset="utf-8"><style>${filament}</style>${links}</head><body class="fi-body">
<div class="fi-layout">
  <div x-persist="simplify-admin-top"><div class="ps server-active host-mode ps-admin-top" style="height:64px">Panel</div></div>
  <div x-persist="simplify-admin-side"><div class="ps server-active ps-admin-side"><div class="ps-host-side">Menu</div><div class="ps-admin-crumb">Health</div></div></div>
  <div class="fi-main-ctn"><main class="fi-main fi-width-screen"><div style="height:300px">Health</div></main></div>
</div>${measure}</body></html>`,
  // A server page: Simplify's server card above Pelican's console.
  server: `<html class="dark"><head><meta charset="utf-8"><style>${filament}</style>${links}</head><body class="fi-body">
<div class="fi-layout">
  <div class="fi-main-ctn"><div class="ps server-active ps-server-top" style="height:120px">Server</div><main class="fi-main fi-width-screen"><div style="height:300px">Console</div></main></div>
</div>${measure}</body></html>`,
};

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-width-'));
for (const [name, html] of Object.entries(pages)) {
  const file = path.join(dir, `${name}.html`);
  fs.writeFileSync(file, '<!doctype html>' + html);
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--hide-scrollbars', '--window-size=1912,900', '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const r = JSON.parse((dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/) || [])[1]?.replace(/&quot;/g, '"') || 'null');
  if (!r) { check(`${name}: measured`, false, null); continue; }
  check(`${name}: the page is not pinned over the window`, r.position !== 'fixed', r);
  check(`${name}: the page stays inside its own box`, r.left >= r.boxLeft - 1 && r.right <= r.boxRight + 1 && r.top >= r.boxTop - 1, r);
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) { console.error(`FAIL ${failures} width check(s)`); process.exit(1); }
console.log('PASS Display width "Screen" keeps the page in its place');
