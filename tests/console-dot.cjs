// The green "Connected" dot in the live console's title row stays a round dot when the row is short of room (K-B103,
// VoiceCrack93 28-11): with a long translation ("Mit Wings verbunden") the flex row squeezed the dot into an oval.
// In a real browser, without a panel. Needs Chrome or Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/console-dot.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'ps-console-dot-'));
const file = path.join(dir, 'page.html');
// The title row as server.blade.php renders it, in German, at widths from a phone to a narrow console column.
const row = (width) => `<div class="ps-console-title ps-ui" style="width:${width}px"><span class="ps-terminal-icon">›_</span><strong>Live-Konsole</strong>
  <span class="ps-console-dot connected"></span><span class="ps-live-bars"><i></i><i></i><i></i><i></i></span>
  <span class="ps-console-state">Mit Wings verbunden</span>
  <button type="button" class="ps-console-clear ps-console-tool"><svg></svg></button><button type="button" class="ps-console-clear ps-console-tool"><svg></svg></button></div>`;
const widths = [200, 240, 280, 320, 360, 420];
fs.writeFileSync(file, `<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/components.css"><link rel="stylesheet" href="file://${css}/dashboard.css">
</head><body><div class="ps server-active">${widths.map(row).join('')}</div><pre id="out"></pre>
<script>setTimeout(() => { document.getElementById('out').textContent = JSON.stringify([...document.querySelectorAll('.ps-console-dot')].map((dot) => {
  const box = dot.getBoundingClientRect(); return [Math.round(box.width * 10) / 10, Math.round(box.height * 10) / 10];
})); }, 300);</script></body></html>`);
const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--allow-file-access-from-files', '--window-size=800,900', '--virtual-time-budget=2000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
fs.rmSync(dir, { recursive: true, force: true });
const found = dom.match(/<pre id="out"[^>]*>([\s\S]*?)<\/pre>/);
const dots = found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"')) : null;
if (!dots || dots.length !== widths.length) { console.error('FAIL tests/console-dot.cjs could not measure the dots'); process.exit(1); }
const bad = dots.map((size, i) => [widths[i], size]).filter(([, [w, h]]) => w !== h || w < 6);
if (bad.length) {
  console.error('FAIL the console dot is not round: ' + bad.map(([width, [w, h]]) => `row ${width}px → ${w}×${h}`).join(', '));
  process.exit(1);
}
console.log('PASS console dot stays round (' + widths.length + ' widths)');
