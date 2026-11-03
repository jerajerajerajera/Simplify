// Sheets on a phone slide open and closed instead of popping (Jeroen 3-11): the menus (.ps-dropdown) and the popups
// (.ps-modal) through the ps-t-* classes that Alpine's x-transition sets, the confirm dialog and the toast through
// core.js. On a computer a menu keeps its short fade and a popup goes at once. Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/sheet-motion.cjs (set CHROME)'); process.exit(1); }

const read = (file) => fs.readFileSync(path.join(__dirname, '..', 'resources', file), 'utf8').replace(/<\/(script|style)/gi, '<\\/$1');
const css = ['css/tokens.css', 'css/components.css', 'css/dashboard.css'].map(read).join('\n');
const page = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>${css}</style></head><body>
<div class="ps"><div class="ps-notify"><div id="menu" class="ps-dropdown is-end"><button class="ps-menu-item">One</button></div></div></div>
<div id="layer" class="ps-modal-layer ps-ui" data-open="true"><section class="ps-modal" id="modal" role="dialog"><header class="ps-modal-head"><h2>All notifications</h2></header></section></div>
<pre id="out"></pre>
<script>${read('js/core.js')}</script>
<script>
  const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const results = [];
  const ok = (name, pass, detail) => results.push((pass ? 'PASS ' : 'FAIL ') + name + (pass ? '' : ' ' + JSON.stringify(detail)));
  // Wait for a state, not a fixed time (under load fixed waits measure halfway).
  const until = async (fn) => { for (let t = 0; t < 5000 && !fn(); t += 10) await wait(10); return fn(); };
  const phone = matchMedia('(max-width: 600px)').matches;
  const seconds = (el) => parseFloat(getComputedStyle(el).transitionDuration) || 0;
  // What Alpine's x-transition does on leave: the leave class, then a frame later the end state.
  const leaving = (el) => { el.classList.add('ps-t-out'); const duration = seconds(el), props = getComputedStyle(el).transitionProperty; el.classList.add('ps-t-from'); return { duration, props, from: getComputedStyle(el) }; };
  (async () => {
    const menu = document.getElementById('menu');
    const m = leaving(menu);
    const layer = document.getElementById('layer');
    const l = leaving(layer);
    const modal = getComputedStyle(document.getElementById('modal'));
    // The end state: with the transitions switched off for the reading (headless virtual time does not run them).
    [menu, layer, document.getElementById('modal')].forEach((el) => (el.style.transition = 'none'));
    if (phone) {
      ok('a menu slides down as it closes', m.duration > 0 && /translate/.test(m.props) && m.from.translate !== 'none' && m.from.opacity === '1', { m: { ...m, from: m.from.translate } });
      ok('a popup slides down as it closes and its backdrop fades', l.duration > 0 && modal.transform !== 'none' && /rgba\\(0, 0, 0, 0\\)|transparent/.test(l.from.backgroundColor), { duration: l.duration, transform: modal.transform, bg: l.from.backgroundColor });
      // The confirm dialog and the toast leave the same way, and are gone after.
      const answer = Simplify.confirm({ title: 'Delete?' });
      await until(() => document.querySelector('[data-cancel]'));
      document.querySelector('[data-cancel]').click();
      const dialog = document.querySelector('.ps-modal-layer:not(#layer)');
      ok('the confirm dialog answers at once and slides away', (await answer) === false && !!dialog && dialog.classList.contains('ps-t-out'), { dialog: !!dialog });
      ok('the confirm dialog is gone after', await until(() => !document.querySelector('.ps-modal-layer:not(#layer)')), {});
      Simplify.toast('Saved', 'ok', 200);
      const toast = document.querySelector('.ps-toast');
      ok('a toast fades away instead of vanishing', await until(() => toast.classList.contains('ps-t-out')) && toast.isConnected, { toast: toast.isConnected });
      ok('the toast is gone after', await until(() => !toast.isConnected), {});
    } else {
      ok('on a computer a menu keeps its short fade', m.duration > 0 && m.props === 'opacity' && m.from.opacity === '0', { m: { duration: m.duration, props: m.props } });
      ok('on a computer a popup still closes at once', l.duration === 0, { duration: l.duration });
    }
    document.getElementById('out').textContent = results.join('\\n') + '\\nDONE';
  })().catch((error) => (document.getElementById('out').textContent = 'FAIL ' + error.stack + '\\nDONE'));
</script></body></html>`;

let failed = false;
for (const size of ['390,844', '1440,900']) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-motion-'));
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
  if (!lines.includes('DONE')) { console.error(`FAIL sheet motion did not finish in the browser at ${size}`); console.error(lines.join('\n')); process.exit(1); }
  lines.filter((line) => line !== 'DONE').forEach((line) => { console.log(line); if (line.startsWith('FAIL')) failed = true; });
}
if (failed) process.exit(1);
