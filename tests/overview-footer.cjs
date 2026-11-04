// The overview on a phone (dashboard.css, K-B45, Jeroen 4-11): Pelican's copyright line stands at the bottom of the
// page, just above the bottom bar, when there is little on it, and moves down with the page when there is more. It
// never stands halfway up the screen right under a short list of servers. Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/overview-footer.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
// Filament's layout as far as it matters: .fi-layout (flex), .fi-main-ctn (display: flex, column), <main>, then the footer.
const page = (content) => `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>*{box-sizing:border-box}body{margin:0}.fi-layout{display:flex;height:100%;width:100%}.fi-main-ctn{min-width:0;flex:1;flex-direction:column}.fi-main{margin:0 auto;height:100%;width:100%}</style>
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/dashboard.css">
</head><body>
<div class="fi-layout"><div class="fi-main-ctn" style="display: flex; opacity: 1">
  <main class="fi-main"><div class="ps"><div class="ps-main"><div id="content" style="height:${content}px">Servers</div></div>
    <nav class="ps-tabbar"><a class="ps-tab" style="display:block;height:56px">Servers</a><a class="ps-tab" style="display:block;height:56px">Admin</a></nav></div></main>
  <footer data-ps-footer><span id="line">&copy; 2026 Panel</span></footer>
</div></div>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    const line = document.getElementById('line').getBoundingClientRect(), content = document.getElementById('content').getBoundingClientRect();
    const bar = document.querySelector('.ps-tabbar').getBoundingClientRect();
    document.getElementById('out').textContent = JSON.stringify({ lineTop: Math.round(line.top), lineBottom: Math.round(line.bottom), contentBottom: Math.round(content.bottom), barTop: Math.round(bar.top), page: document.documentElement.scrollHeight, window: innerHeight });
  }, 200));
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-overview-footer-'));
for (const [length, content] of [['one server', 120], ['many servers', 2400]]) {
  const file = path.join(dir, `${content}.html`);
  fs.writeFileSync(file, page(content));
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--hide-scrollbars', '--window-size=390,844', '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const r = JSON.parse((dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/) || [])[1]?.replace(/&quot;/g, '"') || 'null');
  if (!r) { check(`${length}: measured`, false, null); continue; }
  check(`${length}: the copyright line is under the servers`, r.lineTop >= r.contentBottom - 1, r);
  check(`${length}: the copyright line is fully above the bottom bar at the end of the page`, r.lineBottom - Math.max(0, r.page - r.window) <= r.barTop + 1, r);
  if (length === 'one server') check(`${length}: the copyright line stands at the bottom, just above the bar (not halfway up)`, r.barTop - r.lineBottom <= 40 && r.page <= r.window, r);
  else check(`${length}: the page grows and the copyright line moves down with it`, r.page > r.window && r.lineTop > r.window, r);
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) { console.error(`FAIL ${failures} overview footer check(s)`); process.exit(1); }
console.log('PASS the copyright line on the overview stands at the bottom of the page on a phone');
