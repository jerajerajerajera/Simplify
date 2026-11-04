// The Admin menu on a phone slides like an app (Jeroen 4-11, proefscherm 3-11): a tap on a page in the menu slides the
// menu away to the left over the page that opened, and Admin, the group in the breadcrumb or a swipe from the edge
// slide it back in. Before, the menu and the page swapped at once ("schiet over"). On a computer, and with reduced
// motion, nothing slides. Needs Chrome or Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/admin-menu-slide.cjs (set CHROME)'); process.exit(1); }

const root = path.join(__dirname, '..', 'resources');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail ?? null)}`); if (!ok) failures++; };

// AdminSide changes the menu only through slide(): no other place may switch it at once.
const blade = read('views/pages/admin-side.blade.php');
// New in the bottom bar closes it at once: the Server Maker's popup opens over it.
const direct = blade.replace("open = false; $dispatch('simplify:create')", '').match(/(?<![\w.])(?:this\.)?open\s*=(?!=)[^;"]*/g) || [];
check('AdminSide opens and closes the menu through slide()', /slide\(on, move = true\)\s*\{/.test(blade) && direct.length === 1 && /this\.open = on/.test(direct[0]), direct);
check('the menu slides after a tap in it has loaded the page (livewire:navigated)', /x-on:livewire:navigated\.window="locate\(true\)"/.test(blade));

const css = ['css/tokens.css', 'css/dashboard.css', 'css/simplify.css'].map(read).join('\n').replace(/<\/style/gi, '<\\/style');
const page = `<!doctype html><html class="ps-shell ps-shell-menu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>*{box-sizing:border-box}body{margin:0}${css}</style></head><body>
<div class="fi-layout">
  <div x-persist="simplify-admin-top"><div class="ps ps-admin-top" style="height:60px">Panel</div></div>
  <div x-persist="simplify-admin-side"><div class="ps server-active ps-admin-side menu-open" id="side">
    <nav class="ps-admin-crumb"><button class="is-last"><span>Nodes</span></button></nav>
    <aside class="ps-host-side" id="menu"><div class="ps-host-side-scroll"><a class="ps-host-leaf" href="#nodes">Nodes</a></div></aside>
  </div></div>
  <div class="fi-main-ctn" id="page"><main class="fi-main"><div style="height:900px">Nodes</div></main></div>
</div>
<pre id="out"></pre>
<script>${read('js/sheet.js').replace(/<\/script/gi, '<\\/script')}</script>
<script>
  const r = { phone: matchMedia('(max-width: 600px)').matches, helper: typeof window.simplifyAdminSlide === 'function' };
  const side = document.getElementById('side');
  const apply = (on) => () => { side.classList.toggle('menu-open', on); };
  const slides = () => document.getAnimations().filter((a) => /view-transition-(old|new)\\(ps-admin-menu\\)/.test(a.effect?.pseudoElement || '')).map((a) => {
    const frames = a.effect.getKeyframes(), timing = a.effect.getComputedTiming();
    return { pseudo: a.effect.pseudoElement, duration: timing.duration, from: frames[0]?.transform, to: frames.at(-1)?.transform };
  });
  (async () => {
    try {
      r.background = getComputedStyle(document.getElementById('menu')).backgroundColor;
      // A tap in the menu has loaded the page: the menu slides away to the left.
      const out = r.helper ? window.simplifyAdminSlide(false, apply(false)) : null;
      await out?.ready;
      r.out = slides();
      await out?.finished;
      r.closed = !side.classList.contains('menu-open');
      // Admin again: the menu slides back in from the left.
      const back = r.helper ? window.simplifyAdminSlide(true, apply(true)) : null;
      await back?.ready;
      r.in = slides();
      await back?.finished;
      r.open = side.classList.contains('menu-open');
    } catch (error) { r.error = String(error); }
    document.getElementById('out').textContent = JSON.stringify(r);
  })();
</script></body></html>`;

// A real browser drawing frames (a view transition waits for them; --dump-dom draws none), driven over the DevTools
// pipe so no package is needed.
const run = (width, reduced = false) => new Promise((resolve, reject) => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-admin-slide-'));
  const file = path.join(dir, 'page.html');
  fs.writeFileSync(file, page);
  const browser = spawn(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--remote-debugging-pipe', '--user-data-dir=' + path.join(dir, 'profile'), 'about:blank'], { stdio: ['ignore', 'ignore', 'ignore', 'pipe', 'pipe'] });
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
    // Chrome's helpers may still write to the profile for a moment after it closed: leaving that folder is harmless.
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
    await send('Emulation.setDeviceMetricsOverride', { width, height: 844, deviceScaleFactor: 1, mobile: false });
    if (reduced) await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    await send('Page.navigate', { url: 'file://' + file });
    for (let tries = 0; tries < 200; tries++) {
      const { result } = await send('Runtime.evaluate', { expression: "document.getElementById('out')?.textContent || ''", returnByValue: true });
      if (result?.value) return finish(JSON.parse(result.value));
      await new Promise((done) => setTimeout(done, 50));
    }
    finish(null);
  })().catch(reject);
});

(async () => {
const phone = await run(390);
const left = (slide, at) => slide && /translateX\(-100%\)/.test(slide[at] || '');
check('the test runs at phone width', !!phone && phone.phone, phone);
check('sheet.js offers the slide to AdminSide', !!phone && phone.helper, phone);
check('the menu has the page background while it slides (not see-through)', !!phone && !/rgba\(0, 0, 0, 0\)|transparent/.test(phone.background), phone?.background);
const out = phone?.out?.find((slide) => slide.pseudo.includes('old'));
check('a tap in the menu slides the menu away to the left', !!out && out.duration > 0 && left(out, 'to'), phone);
check('after the slide the page is shown', !!phone && phone.closed, phone);
const back = phone?.in?.find((slide) => slide.pseudo.includes('new'));
check('Admin slides the menu back in from the left', !!back && back.duration > 0 && left(back, 'from'), phone);
check('after the slide the menu is shown', !!phone && phone.open, phone);

const computer = await run(1280);
check('on a computer the menu beside the page does not slide', !!computer && computer.closed && computer.open && !computer.out?.length && !computer.in?.length, computer);
const still = await run(390, true);
check('with reduced motion nothing slides', !!still && still.closed && still.open && !still.out?.length && !still.in?.length, still);
if (failures) process.exit(1);
})();
