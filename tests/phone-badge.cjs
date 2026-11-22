// K-B82: on a phone a list fills the page's width, and the count on its last tool (filters, grouping) stuck out of the
// page: Filament centres it on the button's corner, half outside the button. The page then slid sideways by those
// few pixels (admin Webhooks, Backups and Activity). In a real browser against resources/css/simplify.css, without a
// panel: at a phone's width nothing sticks out of the page; on a computer the count keeps Filament's corner.
// Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/phone-badge.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
// Filament's own rule for the count, as its stylesheet has it; the page's box scrolls like the panel's on a phone.
const page = `<!doctype html><html class="ps-shell ps-shell-menu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>body { margin: 0; } .fi-icon-btn { position: relative; } .fi-icon-btn > .fi-icon-btn-badge-ctn { inset-inline-start: 100%; top: 4px; z-index: 1; --tw-translate-x: -50%; --tw-translate-y: -50%; width: max-content; translate: var(--tw-translate-x) var(--tw-translate-y); display: flex; position: absolute; }</style>
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/simplify.css"></head><body><div class="ps ps-admin-top"></div>
<div id="box" style="margin: 0 12px; overflow: auto"><div class="fi-ta-ctn"><div class="fi-ta-header-toolbar" style="display: flex; justify-content: space-between">
<button type="button" class="fi-icon-btn" style="width: 44px; height: 44px">+</button>
<button type="button" class="fi-icon-btn" id="tool" style="width: 44px; height: 44px">G<div class="fi-icon-btn-badge-ctn"><span style="display: block; width: 16px; height: 16px">0</span></div></button>
</div></div></div>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    const box = document.getElementById('box'), tool = document.getElementById('tool').getBoundingClientRect(), count = document.querySelector('.fi-icon-btn-badge-ctn').getBoundingClientRect();
    document.getElementById('out').textContent = JSON.stringify({ slides: box.scrollWidth - box.clientWidth, out: Math.round(count.right - tool.right), shown: count.width > 0 });
  }, 300));
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-phone-badge-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, page);
for (const size of ['390,844', '1440,900']) {
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--allow-file-access-from-files', `--window-size=${size}`, '--virtual-time-budget=2000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const found = dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/);
  const out = found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')) : null;
  if (!out) { check(`${size}: measured`, false, dom.slice(0, 200)); continue; }
  if (size === '390,844') check(`${size}: the count on a list's last tool stays inside the page, which does not slide sideways`, out.shown && out.slides <= 0 && out.out <= 0, out);
  else check(`${size}: on a computer the count keeps its place on the button's corner`, out.shown && out.out > 0, out);
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) { console.error(`FAIL ${failures} phone count check(s)`); process.exit(1); }
console.log('PASS a count on a list tool stays inside the page on a phone');
