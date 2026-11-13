// K-B69 (tester Khion, 13-11, 2.0.0-beta.12): the server page and its console stayed 1400px wide with Full width on in
// Customize, and with Pelican's Display width Full. The cap sat on .fi-main-ctn, which the switch never reached. At
// 1912px the page now uses the window with Full width, and stays 1400px without it. Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/server-full-width.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
const filament = '*{box-sizing:border-box}body{margin:0}.fi-layout{display:flex;width:100%;min-height:100dvh}.fi-main-ctn{flex:1;display:flex;flex-direction:column}.fi-main.fi-width-7xl{max-width:80rem}.fi-main.fi-width-full{max-width:100%}';
const links = ['tokens', 'simplify', 'components', 'dashboard'].map((f) => `<link rel="stylesheet" href="file://${css}/${f}.css">`).join('');
const page = (psClass, width) => `<html class="dark"><head><meta charset="utf-8"><style>${filament}</style>${links}</head><body class="fi-body">
<div class="fi-layout"><div class="fi-main-ctn">
  <div class="ps server-active ps-server-top is-console ${psClass}"><div class="ps-main"><div class="ps-top" style="height:40px">Panel</div><div class="ps-server-info" style="height:90px">Server</div></div><nav class="ps-server-menu" style="height:200px">Menu</nav></div>
  <main class="fi-main fi-width-${width}"><div id="terminal" style="height:300px">Console</div></main>
</div></div>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>addEventListener('load', () => setTimeout(() => {
  const w = (sel) => Math.round(document.querySelector(sel).getBoundingClientRect().width);
  document.getElementById('out').textContent = JSON.stringify({ page: w('.fi-main-ctn'), top: w('.ps-top'), console: w('#terminal') });
}, 300));</script></body></html>`;

const cases = [
  { name: 'Full width in Customize', ps: 'is-server-full-width', width: '7xl', wide: true },
  { name: "Pelican's Display width Full", ps: '', width: 'full', wide: true },
  { name: 'neither', ps: '', width: '7xl', wide: false },
];
let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-full-width-'));
for (const c of cases) {
  const file = path.join(dir, 'page.html');
  fs.writeFileSync(file, '<!doctype html>' + page(c.ps, c.width));
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--hide-scrollbars', '--window-size=1912,900', '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const r = JSON.parse((dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/) || [])[1]?.replace(/&quot;/g, '"') || 'null');
  if (!r) { check(`${c.name}: measured`, false, null); continue; }
  if (c.wide) {
    check(`${c.name}: the server page uses the window`, r.page >= 1900, r);
    check(`${c.name}: the header spans the page and the console the room beside the menu`, r.top >= r.page - 80 && r.console >= r.top - 240, r);
  } else {
    check(`${c.name}: the server page stays 1400px`, r.page <= 1400, r);
  }
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) { console.error(`FAIL ${failures} full width check(s)`); process.exit(1); }
console.log('PASS Full width reaches the server page');
