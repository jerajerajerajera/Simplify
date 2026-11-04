// The buttons in the console's title row (add-on actions, Bigger, pop-out window) could not be clicked (Jeroen 4-11,
// Beelink, 2.0.0-beta.4): the title row lies over the top padding of xterm's own element, and xterm's viewport
// (absolute, over the whole element) and helpers (z-index 5) came later in the page, so they took the click. A click
// test, not a screenshot: at the middle of each button the button itself must lie. In a real browser against
// resources/css/dashboard.css and xterm's own positioning, without a panel. Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/console-title-clicks.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
// The positioning rules of xterm.css (@xterm/xterm, as Pelican ships it): the element, its viewport, screen and helpers.
const xterm = `.xterm { position: relative; user-select: none; cursor: text; }
.xterm .xterm-helpers { position: absolute; top: 0; z-index: 5; }
.xterm .xterm-helper-textarea { position: absolute; opacity: 0; left: -9999em; top: 0; z-index: -5; }
.xterm .xterm-viewport { position: absolute; inset: 0; overflow-y: scroll; }
.xterm .xterm-screen { position: relative; }
.xterm .xterm-screen canvas { position: absolute; left: 0; top: 0; }
.xterm .xterm-decoration-container .xterm-decoration { z-index: 6; position: absolute; }`;
const page = `<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/components.css"><link rel="stylesheet" href="file://${css}/dashboard.css">
<style>${xterm}</style></head><body><div class="ps server-active ps-server-top is-console"></div>
<main class="fi-main"><div class="fi-page-content"><div>
<div id="terminal" style="height: 400px">
  <div class="ps-console-title ps-ui"><strong>Live console</strong><span class="ps-console-dot connected"></span><span class="ps-live-bars"><i></i><i></i></span>
    <span class="ps-console-state">Connected to Wings</span>
    <button type="button" class="ps-console-clear ps-console-tool ps-console-action" aria-label="Explain"><svg viewBox="0 0 24 24"></svg></button>
    <button type="button" class="ps-console-clear ps-console-tool ps-console-grow" aria-label="Bigger"><svg viewBox="0 0 24 24"></svg></button>
    <button type="button" class="ps-console-clear ps-console-tool ps-console-window" aria-label="Window"><svg viewBox="0 0 24 24"></svg></button></div>
  <div class="xterm" style="height: 100%; box-sizing: border-box"><div class="xterm-viewport"><div style="height: 2000px"></div></div>
    <div class="xterm-screen" style="height: 300px"><div class="xterm-helpers"><textarea class="xterm-helper-textarea"></textarea></div><canvas width="800" height="300"></canvas></div></div>
</div></div></div></main>
<pre id="out"></pre>
<script>
  addEventListener('load', () => {
    const out = {};
    document.querySelectorAll('#terminal > .ps-console-title button').forEach((btn) => {
      const box = btn.getBoundingClientRect();
      const hit = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
      out[btn.getAttribute('aria-label')] = box.width > 0 && !!hit && btn.contains(hit);
    });
    document.getElementById('out').textContent = JSON.stringify(out);
  });
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-console-title-'));
for (const size of ['1440,900', '1366,700', '390,844']) {
  const file = path.join(dir, 'page.html');
  fs.writeFileSync(file, page);
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--allow-file-access-from-files', `--window-size=${size}`, '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const found = dom.match(/<pre id="out">([\s\S]*?)<\/pre>/);
  const out = found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')) : {};
  // Bigger and the pop-out window are hidden on a phone (dashboard.css); what shows must take its own click.
  const shown = size === '390,844' ? ['Explain'] : ['Explain', 'Bigger', 'Window'];
  for (const label of shown) check(`${size}: a click on the console's ${label} button lands on the button`, out[label] === true, out);
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) process.exit(1);
