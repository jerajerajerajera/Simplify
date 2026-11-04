// The Admin page's breadcrumb on a computer (html.ps-shell-menu, dashboard.css and simplify.css) in a real browser,
// without a panel: with room to spare no part of it is cut short ("All ser…" on Health, K-B47, Jeroen 4-11); only
// when the names do not fit does a part give way, and the breadcrumb then stays within its own room. Needs Chrome or
// Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/admin-crumb-fit.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
// Filament's page header (packages/panels, components/header and header.css, from sm up) as far as it matters here.
const filament = `*{box-sizing:border-box}body{margin:0}
.fi-main-ctn{display:flex;flex-direction:column;min-width:0}.fi-main{margin:0 auto;height:100%;width:100%}
.fi-page-header-main-ctn{display:flex;flex-direction:column;row-gap:32px}
.fi-header{display:flex;flex-direction:row;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px}
.fi-header>div:first-child:not(.fi-header-actions-ctn){max-width:100%;width:max-content}
.fi-header-subheading{margin:8px 0 0;max-width:42rem;font-size:18px;line-height:28px}
.fi-header-actions-ctn{display:flex;flex-shrink:0;align-items:center;gap:12px;align-self:flex-end;max-width:100%}
.fi-header.fi-header-has-subheading .fi-header-actions-ctn{align-self:center}
.fi-ac{display:flex;flex:1;gap:12px;justify-content:flex-end}.fi-btn{height:36px;padding:0 12px}`;

const crumbs = {
  health: '<a class="ps-admin-crumb-home"><svg></svg><span>All servers</span></a><svg class="ps-admin-crumb-sep ps-admin-crumb-home"></svg><button class="is-last"><svg></svg><span>Health</span></button>',
  page: '<a class="ps-admin-crumb-home"><svg></svg><span>All servers</span></a><svg class="ps-admin-crumb-sep ps-admin-crumb-home"></svg><button><svg></svg><span>Servers</span></button><span class="ps-admin-crumb-part"><svg class="ps-admin-crumb-sep"></svg><strong>Database hosts</strong></span>',
  long: '<a class="ps-admin-crumb-home"><svg></svg><span>All servers</span></a><svg class="ps-admin-crumb-sep ps-admin-crumb-home"></svg><button><svg></svg><span>' + 'Simplify add-ons and other plugins '.repeat(3) + '</span></button><span class="ps-admin-crumb-part"><svg class="ps-admin-crumb-sep"></svg><strong>' + 'A very long page name '.repeat(4) + '</strong></span>',
};
const page = (crumb) => `<!doctype html><html class="ps-shell ps-shell-menu"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>*{box-sizing:border-box}body{margin:0}.fi-main-ctn{display:flex;flex-direction:column;min-width:0}</style>
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/dashboard.css"><link rel="stylesheet" href="file://${css}/simplify.css">
</head><body>
<div class="fi-layout">
  <div x-persist="simplify-admin-top"><div class="ps ps-admin-top" style="height:70px">Panel</div></div>
  <div x-persist="simplify-admin-side"><div class="ps ps-admin-side">
    <nav class="ps-admin-crumb">${crumbs[crumb]}</nav>
    <aside class="ps-host-side">Menu</aside>
  </div></div>
  <div class="fi-main-ctn"><main class="fi-main"><div style="height:400px">Page</div></main></div>
</div>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    const nav = document.querySelector('.ps-admin-crumb'), main = document.querySelector('.fi-main-ctn').getBoundingClientRect();
    const b = nav.getBoundingClientRect();
    document.getElementById('out').textContent = JSON.stringify({
      cut: [...nav.querySelectorAll('span, strong')].filter((e) => e.scrollWidth > e.clientWidth + 1).map((e) => e.textContent.trim().slice(0, 20)),
      crumb: { left: Math.round(b.left), right: Math.round(b.right), width: Math.round(b.width) }, main: { left: Math.round(main.left), right: Math.round(main.right) },
      overflow: nav.scrollWidth > nav.clientWidth + 1, pageScrollX: document.documentElement.scrollWidth - innerWidth,
    });
  }, 200));
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-crumb-fit-'));
for (const [stand, size] of [['d1920', '1920,1080'], ['d1440', '1440,900'], ['laag1100', '1100,700']]) {
  for (const crumb of Object.keys(crumbs)) {
    const file = path.join(dir, `${stand}-${crumb}.html`);
    fs.writeFileSync(file, page(crumb));
    const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--hide-scrollbars', `--window-size=${size}`, '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
    const r = JSON.parse((dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/) || [])[1]?.replace(/&quot;/g, '"') || 'null');
    const name = `${stand}, ${crumb}:`;
    if (!r) { check(`${name} measured`, false, null); continue; }
    if (crumb === 'long') {
      check(`${name} names that do not fit give way inside the breadcrumb`, r.cut.length > 0 && !r.overflow && r.pageScrollX <= 0, r);
    } else {
      check(`${name} with room to spare nothing is cut short`, r.cut.length === 0, r);
    }
  }
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) process.exit(1);
