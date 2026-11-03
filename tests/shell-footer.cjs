// Pelican's copyright footer on the Admin page and the account pages (html.ps-shell, simplify.css) in a real browser,
// without a panel: the footer sits at the very end of the page in the card, under everything on it, never halfway
// over the page where the card's first screen ended (K-B9, Jeroen 3-11), on a phone and on a computer, marked by
// dashboard.js (data-ps-footer) or not yet. Needs Chrome or Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/shell-footer.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
// Filament's layout (packages/panels, components/layout.blade.php and layout.css) as far as it matters here: the
// footer render hook comes after <main> inside .fi-main-ctn, which Filament shows with display: flex.
const page = (content, marked) => `<!doctype html><html class="ps-shell ps-shell-menu"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>*{box-sizing:border-box}body{margin:0}.fi-layout{display:flex;height:100%;width:100%}.fi-main-ctn{min-width:0;flex:1;flex-direction:column}.fi-main{margin:0 auto;height:100%;width:100%;padding:0 16px}</style>
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/dashboard.css"><link rel="stylesheet" href="file://${css}/simplify.css">
</head><body>
<div class="fi-layout">
  <div x-persist="simplify-admin-top"><div class="ps server-active host-mode ps-admin-top" style="height:64px">Panel</div></div>
  <div x-persist="simplify-admin-side"><div class="ps server-active ps-admin-side" style="height:40px">Menu</div></div>
  <div class="fi-main-ctn" style="display: flex; opacity: 1">
    <main class="fi-main"><div id="content" style="height:${content}px">Page</div></main>
    <footer class="flex flex-col items-center justify-center text-center space-y-2 p-4" ${marked ? 'data-ps-footer' : ''}><a>&copy; 2026 Panel</a></footer>
  </div>
</div>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    const card = document.querySelector('.fi-main-ctn'), footer = card.querySelector('footer'), content = document.getElementById('content');
    const top = card.getBoundingClientRect().top - card.scrollTop;
    const f = footer.getBoundingClientRect(), c = content.getBoundingClientRect();
    document.getElementById('out').textContent = JSON.stringify({
      shown: getComputedStyle(footer).display !== 'none' && f.height > 0,
      footerTop: Math.round(f.top - top), footerBottom: Math.round(f.bottom - top), contentBottom: Math.round(c.bottom - top),
      scrollHeight: card.scrollHeight, clientHeight: card.clientHeight, pageScroll: document.documentElement.scrollHeight - innerHeight,
    });
  }, 200));
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-footer-'));
for (const [stand, size] of [['phone', '390,844'], ['computer', '1440,900']]) {
  for (const marked of [false, true]) {
    for (const [length, content] of [['long', 2400], ['short', 200]]) {
      const file = path.join(dir, `${stand}-${marked}-${length}.html`);
      fs.writeFileSync(file, page(content, marked));
      const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--hide-scrollbars', `--window-size=${size}`, '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
      const r = JSON.parse((dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/) || [])[1]?.replace(/&quot;/g, '"') || 'null');
      const name = `${stand}, ${marked ? 'marked' : 'not marked yet'}, ${length} page:`;
      if (!r) { check(`${name} measured`, false, null); continue; }
      check(`${name} the footer shows`, r.shown, r);
      check(`${name} the footer is under the page, not over it`, r.footerTop >= r.contentBottom - 1, r);
      check(`${name} the footer is the last thing in the card`, Math.abs(r.footerBottom - Math.max(r.scrollHeight, r.clientHeight)) <= 2, r);
      check(`${name} the page itself does not scroll`, r.pageScroll <= 0, r);
    }
  }
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) { console.error(`FAIL ${failures} footer check(s)`); process.exit(1); }
console.log('PASS the copyright footer sits at the end of the page on the Admin and account pages');
