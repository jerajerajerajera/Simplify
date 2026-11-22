// A server card's name on the overview (dashboard.js fitCardTitle) with a heading font whose letters reach above and
// below their line, like Segoe Print or Pacifico (a theme can set one): a short name keeps its size. The box measured
// its height, and those letters made it taller than the line even at 12px, so every name went down to 12px (K-B95).
// A name too long for two lines still gets smaller. Needs Chrome or Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/card-title-font.cjs (set CHROME)'); process.exit(1); }

const root = path.join(__dirname, '..', 'resources');
const css = path.join(root, 'css');
const dashboard = fs.readFileSync(path.join(root, 'js', 'dashboard.js'), 'utf8').replace(/<\/script/gi, '<\\/script');
const esc = (text) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;');

// A font with tall ascent and descent, from a font every runner has.
const page = (view, name) => `<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/components.css"><link rel="stylesheet" href="file://${css}/dashboard.css">
<style>*,::before,::after{box-sizing:border-box}body{margin:0}
@font-face{font-family:Tall;src:local("DejaVu Sans"),local("Liberation Sans"),local("Arial");ascent-override:150%;descent-override:60%}
.ps-card-title-row h3{font-family:Tall,sans-serif}</style>
</head><body><div class="ps"><div class="ps-server-grid ${view === 'compact' ? 'is-compact' : ''}" style="width:300px">
<div class="ps-card-title"><div class="ps-card-title-row"><h3>${esc(name)}</h3></div></div>
</div></div>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>window.Alpine = window.Alpine || { data() {} };</script>
<script>${dashboard}</script>
<script>
  addEventListener('load', () => document.fonts.ready.then(() => {
    const d = window.pelicanSimplify({}, { servers: [], user: 'jeroen' });
    d.overviewView = ${JSON.stringify(view)};
    const row = document.querySelector('.ps-card-title-row'), title = row.querySelector('h3');
    d.fitCardTitle(row, title.textContent);
    const range = document.createRange(); range.selectNodeContents(title);
    const lines = new Set([...range.getClientRects()].map((r) => Math.round(r.top))).size;
    document.getElementById('out').textContent = JSON.stringify({ font: document.fonts.check('16px Tall'), size: parseFloat(title.style.fontSize), lines, wide: title.scrollWidth > title.clientWidth + 1 });
  }));
</script></body></html>`;

// A browser at the exact width over the DevTools pipe (--window-size stops at 500 wide), so no package is needed.
const run = (html, width, height) => new Promise((resolve, reject) => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-card-title-'));
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
  const short = await run(page('grid', 'Survival'), 800, 600);
  check('the tall test font is there', !!short && short.font, short);
  check('grid, tall font: a short name keeps its size (23px)', !!short && short.size === 23, short);
  const long = await run(page('grid', 'Deckzwabbers Survival Server With A Very Long Name For Everyone'), 800, 600);
  check('grid, tall font: a name too long for two lines gets smaller and fits in two', !!long && long.size < 23 && long.lines <= 2 && !long.wide, long);
  const compact = await run(page('compact', 'Survival'), 800, 600);
  check('compact, tall font: a short name keeps its size (18px)', !!compact && compact.size === 18, compact);
  if (failures) { console.error(`${failures} card title check(s) failed`); process.exit(1); }
  console.log('Card titles: a tall font keeps the size a name fits in.');
})();
