// Simplify's settings outside Simplify's frame (the legacy panel) in a real browser, without a panel: with many add-ons
// the row of tabs breaks onto more lines on a computer instead of scrolling sideways (K-B33, Jeroen 3-11); a phone keeps
// one row that scrolls. Needs Chrome or Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/settings-nav-wrap.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
const labels = ['Appearance', 'General', 'Your phone app', 'Website embed', 'Automatic subdomains', 'Announcements', 'Egg Updates', 'Explain', 'Hosting', 'Server Maker', 'Toolkit', 'Add-ons', 'Browse Hub', 'Pelican settings'];
// Simplify's settings in the legacy panel (no html.ps-shell): Pelican's page with the row of tabs at its start.
const page = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>*{box-sizing:border-box}body{margin:0}.fi-main{max-width:1320px;margin:0 auto;padding:0 32px}</style>
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/simplify.css">
</head><body><main class="fi-main"><div class="fi-page">
<nav class="s-settings-nav">${labels.map((l, i) => `<a class="s-settings-nav-item" href="#"${i ? '' : ' aria-current="page"'}><svg width="16" height="16"></svg><span>${l}</span></a>`).join('')}</nav>
<h1>Simplify settings</h1></div></main>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>addEventListener('load', () => setTimeout(() => { const nav = document.querySelector('.s-settings-nav'), r = nav.getBoundingClientRect();
  document.getElementById('out').textContent = JSON.stringify({ sideways: nav.scrollWidth - nav.clientWidth, left: Math.round(r.left), right: Math.round(r.right), width: innerWidth,
    rows: new Set([...nav.children].map((a) => Math.round(a.getBoundingClientRect().top))).size, pageWide: document.documentElement.scrollWidth - innerWidth }); }, 200));</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-settings-nav-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, page);
for (const [stand, size] of [['d1440', '1440,900'], ['laag1100', '1100,700'], ['phone390', '390,844']]) {
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--hide-scrollbars', `--window-size=${size}`, '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const r = JSON.parse((dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/) || [])[1]?.replace(/&quot;/g, '"') || 'null');
  if (!r) { check(`${stand}: measured`, false, null); continue; }
  check(`${stand}: the page never scrolls sideways`, r.pageWide <= 0, r);
  if (stand === 'phone390') check(`${stand}: the tabs stay one row that scrolls`, r.rows === 1 && r.sideways > 0, r);
  else check(`${stand}: every tab is on the screen, on more lines instead of a sideways scroll (K-B33)`, r.sideways <= 1 && r.rows > 1, r);
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) { console.error(`FAIL ${failures} settings row check(s)`); process.exit(1); }
console.log('PASS the row of settings tabs breaks onto more lines on a computer and scrolls on a phone');
