// Phones: every field that takes typing has text of at least 16px, so Safari on an iPhone does not zoom in on it and
// leave the page zoomed in and sliding sideways (Jeroen ja 4-11). Computers keep their smaller fields. In a real
// browser, without a panel. Needs Chrome or Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/phone-input-zoom.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
// The fields the search for hidden problems found under 16px (4-11), with the classes they have in the views.
const fields = `
<div class="ps"><div class="ps-frame-search"><input type="search" data-name="servers menu search"></div></div>
<div class="ps"><input class="ps-input ps-node-search" type="search" data-name="node search"></div>
<div class="ps"><input class="ps-input ps-group-name" type="text" data-name="group name"></div>
<div class="ps"><div class="ps-group-order"><select class="ps-input" data-name="group order"><option>A</option></select></div></div>
<div class="ps"><label class="ps-field">Name<input class="ps-input" type="text" data-name="field"></label></div>
<div class="ps"><label class="ps-field">About<textarea class="ps-input" data-name="text area"></textarea></label></div>
<div class="s-hub-search"><input type="search" data-name="hub search"></div>
<div class="s-color"><input class="s-color-hex" type="text" maxlength="7" data-name="hex colour"></div>
<input type="checkbox" data-name="checkbox" data-skip="1">`;
const html = `<!doctype html><html class="ps-shell"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/components.css">
<link rel="stylesheet" href="file://${css}/dashboard.css"><link rel="stylesheet" href="file://${css}/simplify.css">
</head><body>${fields}
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    document.getElementById('out').textContent = JSON.stringify([...document.querySelectorAll('[data-name]')].filter((e) => !e.dataset.skip).map((e) => [e.dataset.name, parseFloat(getComputedStyle(e).fontSize)]));
  }, 300));
</script></body></html>`;

const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'phone-input-zoom-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, html);
const sizes = (width, height) => {
  const out = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--allow-file-access-from-files', `--window-size=${width},${height}`, '--virtual-time-budget=2000', '--dump-dom', `file://${file}`], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
  const json = out.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/);
  return json ? JSON.parse(json[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')) : [];
};

let failed = 0;
for (const [name, width, height] of [['phone360', 360, 740], ['phone390', 390, 844]]) {
  const list = sizes(width, height);
  const small = list.filter(([, size]) => size < 16);
  const ok = list.length === 8 && small.length === 0;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}: every field that takes typing has text of at least 16px${small.length ? ' ' + JSON.stringify(small) : ''}`);
  if (!ok) failed++;
}
const desk = sizes(1440, 900);
const node = desk.find(([n]) => n === 'node search');
const kept = node && node[1] < 16;
console.log(`${kept ? 'PASS' : 'FAIL'} d1440: a computer keeps the smaller fields (node search ${node ? node[1] : '?'}px)`);
if (!kept) failed++;
fs.rmSync(dir, { recursive: true, force: true });
process.exit(failed ? 1 : 0);
