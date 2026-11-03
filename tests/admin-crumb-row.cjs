// The Admin page on a computer (html.ps-shell-menu, simplify.css) in a real browser, without a panel: the page's own
// buttons (Pelican's header actions, such as Run setup again and Save) sit in the middle of the breadcrumb's row,
// also when the page has a subheading that goes on a line of its own under that row (K-B21, Settings › General,
// Jeroen 3-11), and nothing of the page starts under that row. Needs Chrome or Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/admin-crumb-row.cjs (set CHROME)'); process.exit(1); }

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
const page = (subheading) => `<!doctype html><html class="ps-shell ps-shell-menu"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>${filament}</style>
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/dashboard.css"><link rel="stylesheet" href="file://${css}/simplify.css">
</head><body>
<div class="fi-layout">
  <div x-persist="simplify-admin-top"><div class="ps ps-admin-top" style="height:70px">Panel</div></div>
  <div x-persist="simplify-admin-side"><div class="ps ps-admin-side">
    <nav class="ps-admin-crumb"><a class="ps-admin-crumb-home"><span>All servers</span></a><button class="is-last"><span>Settings</span></button></nav>
    <aside class="ps-host-side">Menu</aside>
  </div></div>
  <div class="fi-main-ctn">
    <main class="fi-main"><div class="fi-page"><div class="fi-page-header-main-ctn">
      <header class="fi-header ${subheading ? 'fi-header-has-subheading' : ''}">
        <div><h1 class="fi-header-heading">General</h1>${subheading ? '<p class="fi-header-subheading">Version 2.0.0-beta.3 · Changes apply to everyone on this panel after saving.</p>' : ''}</div>
        <div class="fi-header-actions-ctn"><div class="fi-ac"><button class="fi-btn">Run setup again</button><button class="fi-btn">Save</button></div></div>
      </header>
      <div class="fi-page-main"><div style="height:1600px">Page</div></div>
    </div></div></main>
  </div>
</div>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    const box = (e) => { const b = e.getBoundingClientRect(); return { top: Math.round(b.top), bottom: Math.round(b.bottom), middle: Math.round(b.top + b.height / 2) }; };
    const crumb = box(document.querySelector('.ps-admin-crumb'));
    document.getElementById('out').textContent = JSON.stringify({
      crumb, top: box(document.querySelector('.ps-admin-top')), buttons: [...document.querySelectorAll('.fi-btn')].map(box),
      body: box(document.querySelector('.fi-page-main')), sub: document.querySelector('.fi-header-subheading') ? box(document.querySelector('.fi-header-subheading')) : null,
      pageScroll: document.documentElement.scrollHeight - innerHeight,
    });
  }, 200));
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-crumb-'));
for (const [stand, size] of [['d1440', '1440,900'], ['laag1366', '1366,650']]) {
  for (const subheading of [false, true]) {
    const file = path.join(dir, `${stand}-${subheading}.html`);
    fs.writeFileSync(file, page(subheading));
    const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--hide-scrollbars', `--window-size=${size}`, '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
    const r = JSON.parse((dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/) || [])[1]?.replace(/&quot;/g, '"') || 'null');
    const name = `${stand}, ${subheading ? 'with' : 'without'} a subheading:`;
    if (!r) { check(`${name} measured`, false, null); continue; }
    check(`${name} the buttons sit in the middle of the breadcrumb's row`, r.buttons.every((b) => Math.abs(b.middle - r.crumb.middle) <= 2), r);
    check(`${name} the buttons keep room under the header`, r.buttons.every((b) => b.top >= r.top.bottom + 8), r);
    check(`${name} nothing of the page starts under the breadcrumb's row`, r.body.top >= r.crumb.bottom - 1 && (!r.sub || r.sub.top >= r.crumb.bottom - 1), r);
    check(`${name} the page itself does not scroll`, r.pageScroll <= 0, r);
  }
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) { console.error(`FAIL ${failures} breadcrumb row check(s)`); process.exit(1); }
console.log("PASS the Admin page's buttons sit on the breadcrumb's row on a computer");
