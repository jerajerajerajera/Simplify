// A tooltip goes when the page changes (B-K2 / K-B53): the bell kept its "Notifications" tooltip over the next page,
// because the bell stays (wire:navigate keeps the top bar) and nothing told the tooltip the page had changed: not a
// Livewire navigation and not a new page in host mode's frame. Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/tooltip-navigate.cjs (set CHROME)'); process.exit(1); }

const read = (file) => fs.readFileSync(path.join(__dirname, '..', 'resources', file), 'utf8').replace(/<\/(script|style)/gi, '<\\/$1');
const css = ['css/tokens.css', 'css/components.css', 'css/dashboard.css'].map(read).join('\n');
const page = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>${css}</style></head><body>
<div class="ps"><button id="bell" class="ps-btn is-icon" data-tip="Notifications">B</button><iframe id="frame" src="about:blank"></iframe></div>
<pre id="out"></pre>
<script>${read('js/core.js')}</script>
<script>
  const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const results = [];
  const ok = (name, pass, detail) => results.push((pass ? 'PASS ' : 'FAIL ') + name + (pass ? '' : ' ' + JSON.stringify(detail)));
  const until = async (fn) => { for (let t = 0; t < 3000 && !fn(); t += 10) await wait(10); return fn(); };
  const shown = () => !!document.querySelector('.ps-tooltip.is-visible');
  (async () => {
    const bell = document.getElementById('bell');
    const frame = document.getElementById('frame');
    bell.focus();
    ok('the bell shows its tooltip while it has focus', await until(shown), {});
    document.dispatchEvent(new Event('livewire:navigating'));
    ok('a Livewire navigation takes the tooltip away', !shown(), {});
    bell.blur(); bell.focus();
    await until(shown);
    frame.srcdoc = '<p>Next page</p>';
    ok('a new page in the frame takes the tooltip away', await until(() => !shown()), {});
    bell.blur(); bell.focus();
    await until(shown);
    bell.remove();
    window.dispatchEvent(new Event('resize'));
    ok('a tooltip whose button left the page goes', await until(() => !shown()), {});
    document.getElementById('out').textContent = results.join('\\n') + '\\nDONE';
  })().catch((error) => (document.getElementById('out').textContent = 'FAIL ' + error.stack + '\\nDONE'));
</script></body></html>`;

let failed = false;
for (const size of ['390,844', '1440,900']) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-tooltip-'));
  const file = path.join(dir, 'page.html');
  fs.writeFileSync(file, page);
  let dom = '';
  try {
    dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--window-size=' + size, '--virtual-time-budget=15000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  } finally {
    fs.rmSync(dir, { recursive: true, force: true });
  }
  const out = (dom.match(/<pre id="out">([\s\S]*?)<\/pre>/) || [])[1] || '';
  const lines = out.replace(/&quot;/g, '"').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').split('\n').filter(Boolean);
  if (!lines.includes('DONE')) { console.error(`FAIL tooltip check did not finish in the browser at ${size}`); console.error(lines.join('\n')); process.exit(1); }
  lines.filter((line) => line !== 'DONE').forEach((line) => { console.log(line); if (line.startsWith('FAIL')) failed = true; });
}
if (failed) process.exit(1);
