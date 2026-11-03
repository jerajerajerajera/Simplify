// Sheets on phones follow the thumb and close when dragged down (resources/js/sheet.js). K-B22 (Jeroen 3-11): the
// full-screen window with every notification (a popup, .ps-modal) did not close on a pull down; only the menus did.
// The test drags in a real browser at phone width: a menu, the notifications window by its head and by its list, a short
// drag that springs back, a list that is scrolled down (it scrolls instead) and a sideways swipe (it stays open).
// Needs Chrome or Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/sheet-drag.cjs (set CHROME)'); process.exit(1); }

const script = fs.readFileSync(path.join(__dirname, '..', 'resources', 'js', 'sheet.js'), 'utf8').replace(/<\/script/gi, '<\\/script');
const page = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { margin: 0; }
  .ps-dropdown { position: fixed; inset: auto 0 0; height: 300px; background: #eee; }
  .ps-modal-layer { position: fixed; inset: 0; display: grid; place-items: end stretch; }
  .ps-modal { display: flex; flex-direction: column; height: 600px; background: #fff; }
  .ps-modal-head { flex: none; height: 52px; }
  .ps-modal-body { flex: 1; min-height: 0; overflow-y: auto; }
  .ps-modal-body > div { height: 2000px; }
</style></head><body>
<div class="ps"><div class="ps-dropdown" id="menu"><button id="item">Item</button></div></div>
<div class="ps-modal-layer" id="layer" data-open="true"><section class="ps-modal" role="dialog" id="modal">
  <header class="ps-modal-head" id="head">All notifications</header>
  <div class="ps-modal-body" id="body"><div id="list">list</div></div>
</section></div>
<pre id="out"></pre>
<script>
  // The menus and the popups close on Escape (Alpine's x-on:keydown.escape); here that hides them.
  window.shut = [];
  document.getElementById('menu').addEventListener('keydown', (e) => { if (e.key === 'Escape') { shut.push('menu'); e.currentTarget.style.display = 'none'; } });
  window.addEventListener('keydown', (e) => { if (e.key === 'Escape' && document.getElementById('layer').dataset.open === 'true' && e.target.closest?.('#layer')) { shut.push('modal'); } });
</script>
<script>${script}</script>
<script>
  const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const results = [];
  const ok = (name, pass, detail) => results.push((pass ? 'PASS ' : 'FAIL ') + name + (pass ? '' : ' ' + JSON.stringify(detail)));
  const touch = (type, el, x, y) => {
    const t = new Touch({ identifier: 1, target: el, clientX: x, clientY: y });
    el.dispatchEvent(new TouchEvent(type, { bubbles: true, cancelable: true, touches: type === 'touchend' ? [] : [t], targetTouches: type === 'touchend' ? [] : [t], changedTouches: [t] }));
  };
  const drag = async (el, dx, dy, check) => {
    touch('touchstart', el, 100, 300);
    for (let i = 1; i <= 5; i++) { touch('touchmove', el, 100 + dx * i / 5, 300 + dy * i / 5); await wait(16); }
    const during = check ? check() : null;
    touch('touchend', el, 100 + dx, 300 + dy);
    await wait(1000);
    return during;
  };
  const shift = (el) => new DOMMatrix(getComputedStyle(el).transform).m42 + (parseFloat((getComputedStyle(el).translate || '').split(' ')[1]) || 0);
  (async () => {
    const modal = document.getElementById('modal');
    // A sideways swipe on the window (going back) leaves it where it is.
    let during = await drag(document.getElementById('head'), 160, 30, () => shift(modal));
    ok('a sideways swipe does not move or close the window', during === 0 && !shut.includes('modal'), { during, shut });
    // A short pull springs back and keeps the window open.
    during = await drag(document.getElementById('head'), 0, 40, () => shift(modal));
    ok('the window follows the thumb', during > 30, { during });
    ok('a short pull springs back and keeps the window open', !shut.includes('modal') && shift(modal) === 0, { shut, after: shift(modal) });
    // A list that is scrolled down scrolls back up instead of closing.
    const body = document.getElementById('body');
    body.scrollTop = 400;
    during = await drag(document.getElementById('list'), 0, 200, () => shift(modal));
    ok('a scrolled list does not close the window', during === 0 && !shut.includes('modal'), { during, shut });
    body.scrollTop = 0;
    // A pull down on the list at its top closes the notifications window (K-B22).
    await drag(document.getElementById('list'), 0, 200);
    ok('a pull down on the list closes the notifications window', shut.includes('modal'), { shut });
    shut.length = 0; modal.style.cssText = '';
    // And by its head.
    await drag(document.getElementById('head'), 0, 200);
    ok('a pull down on the head closes the notifications window', shut.includes('modal'), { shut });
    document.getElementById('layer').dataset.open = 'false';
    document.getElementById('layer').style.display = 'none';
    // Menus keep closing on a pull down (the window's Escape closed the menu as well: open it again).
    shut.length = 0; document.getElementById('menu').style.display = '';
    await drag(document.getElementById('item'), 0, 200);
    ok('a pull down closes a menu', shut.join() === 'menu', { shut });
    document.getElementById('out').textContent = results.join('\\n') + '\\nDONE';
  })().catch((error) => (document.getElementById('out').textContent = 'FAIL ' + error.stack + '\\nDONE'));
</script></body></html>`;

const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-sheet-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, page);
let dom = '';
try {
  dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--window-size=390,844', '--touch-events=enabled', '--virtual-time-budget=15000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
} finally {
  fs.rmSync(dir, { recursive: true, force: true });
}
const out = (dom.match(/<pre id="out">([\s\S]*?)<\/pre>/) || [])[1] || '';
const lines = out.replace(/&quot;/g, '"').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').split('\n').filter(Boolean);
if (!lines.includes('DONE')) { console.error('FAIL sheet.js did not finish in the browser'); console.error(lines.join('\n')); process.exit(1); }
const tests = lines.filter((line) => line !== 'DONE');
tests.forEach((line) => console.log(line));
if (tests.some((line) => line.startsWith('FAIL'))) process.exit(1);
