// Pelican's console output is drawn once more after it connected (core.js redrawConsole, K-B106): in Firefox the
// WebGL output could stay empty under "Connected to Wings" until the window changed size (Londikk 28-11). Needs
// Chrome or Chromium (GitHub's runners have Chrome).
const { spawn } = require('node:child_process');
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/console-redraw.cjs (set CHROME)'); process.exit(1); }

const root = path.join(__dirname, '..', 'resources');
const css = path.join(root, 'css');
const core = fs.readFileSync(path.join(root, 'js', 'core.js'), 'utf8').replace(/<\/script/gi, '<\\/script');

// Pelican's console as Simplify leaves it: #terminal with xterm inside, and Pelican's fit on every window resize,
// which here only notes the height it finds.
const page = (style, inner) => `<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0}</style></head><body>
<div id="terminal" style="${style}"><div class="xterm" style="${inner}"></div></div>
<pre id="out"></pre>
<script>${core}</script>
<script>
  const terminal = document.getElementById('terminal'), heights = [];
  addEventListener('resize', () => heights.push(Math.round(terminal.getBoundingClientRect().height)));
  addEventListener('load', () => {
    const first = window.Simplify.redrawConsole ? window.Simplify.redrawConsole(terminal) : null;
    const second = window.Simplify.redrawConsole ? window.Simplify.redrawConsole(terminal) : null;
    setTimeout(() => { document.getElementById('out').textContent = JSON.stringify({ first, second, heights, end: Math.round(terminal.getBoundingClientRect().height), style: terminal.style.height }); }, 400);
  });
</script></body></html>`;

// A browser at the exact width over the DevTools pipe (--window-size stops at 500 wide), so no package is needed.
const run = (html, width, height) => new Promise((resolve, reject) => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-console-redraw-'));
  const file = path.join(dir, 'page.html');
  fs.writeFileSync(file, html);
  const browser = spawn(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--hide-scrollbars', '--remote-debugging-pipe', '--user-data-dir=' + path.join(dir, 'profile'), 'about:blank'], { stdio: ['ignore', 'ignore', 'ignore', 'pipe', 'pipe'] });
  const [, , , input, output] = browser.stdio;
  let id = 0, buffer = '', session = null;
  const waiting = new Map();
  const send = (method, params = {}) => new Promise((done) => { waiting.set(++id, done); input.write(JSON.stringify({ id, method, params, ...(session ? { sessionId: session } : {}) }) + '\0'); });
  output.on('data', (chunk) => {
    buffer += chunk;
    for (let end; (end = buffer.indexOf('\0')) >= 0; buffer = buffer.slice(end + 1)) {
      const message = JSON.parse(buffer.slice(0, end));
      if (waiting.has(message.id)) { waiting.get(message.id)(message.result ?? {}); waiting.delete(message.id); }
    }
  });
  const finish = (result) => {
    clearTimeout(timer);
    browser.once('exit', () => { try { fs.rmSync(dir, { recursive: true, force: true, maxRetries: 10, retryDelay: 100 }); } catch {} resolve(result); });
    session = null;
    send('Browser.close');
    setTimeout(() => browser.kill(), 2000);
  };
  const timer = setTimeout(() => finish(null), 30000);
  browser.on('error', reject);
  (async () => {
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
    session = (await send('Target.attachToTarget', { targetId, flatten: true })).sessionId;
    await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
    await send('Page.navigate', { url: 'file://' + file });
    for (let tries = 0; tries < 200; tries++) {
      const { result } = await send('Runtime.evaluate', { expression: "document.getElementById('out')?.textContent || ''", returnByValue: true });
      if (result?.value) return finish(JSON.parse(result.value));
      await new Promise((done) => setTimeout(done, 50));
    }
    finish(null);
  })().catch(reject);
});

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
(async () => {
  // The height Simplify gave the output (fitPage), and none (a phone, Pelican's own rows).
  for (const [name, style, own, inner] of [['with the height Simplify set', 'height:400px', '400px', 'height:100%'], ['with Pelican\'s own height', '', '', 'height:400px']]) {
    const r = await run(page(style, inner), 1000, 800);
    if (!r) { check(`${name} (measured)`, false, null); continue; }
    // Two resizes: one row less, then its own height back; the second call does nothing (once per output).
    check(`${name}: the console is drawn again with one row less and then its own height (K-B106)`, r.first === true && r.heights.length === 2 && r.heights[0] < r.heights[1] && r.heights[1] === r.end && r.style === own, r);
    check(`${name}: only once`, r.second === false, r);
  }
  if (failures) { console.error(`${failures} console redraw check(s) failed`); process.exit(1); }
  console.log('Console: drawn once more after it connected.');
})();
