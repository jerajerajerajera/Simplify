// The Admin menu on a phone is a page of its own (K-B13), not a sheet (K-B24, Jeroen 3-11): with the menu open, a tap
// on the bottom bar (Servers, Admin) or the header (account) must do what it says. sheet.js swallowed every tap
// outside the menu as "a tap beside an open sheet", closed the menu and showed the page under it (Servers › Servers).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/admin-menu-page.cjs (set CHROME)'); process.exit(1); }

const sheet = fs.readFileSync(path.join(__dirname, '..', 'resources', 'js', 'sheet.js'), 'utf8').replace(/<\/script/gi, '<\\/script');
// The open Admin menu as AdminSide draws it on a phone: the tree in place of the page, the bottom bar under it.
const page = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>
<header class="ps"><button id="account" type="button">Account</button></header>
<div class="ps ps-admin-side menu-open">
  <aside class="ps-host-side" id="side" style="display:block;height:300px">
    <a id="leaf" href="#nodes">Nodes</a>
  </aside>
  <nav class="ps-tabbar"><a id="servers" class="ps-tab" href="#overview">Servers</a><button id="admin" type="button" class="ps-tab">Admin</button></nav>
</div>
<pre id="out"></pre>
<script>${sheet}</script>
<script>
  const r = { phone: matchMedia('(max-width: 600px)').matches, escapes: 0, clicks: [] };
  document.getElementById('side').addEventListener('keydown', (e) => { if (e.key === 'Escape') r.escapes++; });
  for (const id of ['account', 'admin', 'servers']) document.getElementById(id).addEventListener('click', () => r.clicks.push(id));
  document.getElementById('account').click();
  document.getElementById('admin').click();
  document.getElementById('servers').click();
  r.hash = location.hash;
  // A drag down on the menu page must not move it (it is no sheet to pull shut).
  const side = document.getElementById('side');
  const at = (y) => new Touch({ identifier: 1, target: side, clientX: 100, clientY: y });
  side.dispatchEvent(new TouchEvent('touchstart', { bubbles: true, touches: [at(100)], changedTouches: [at(100)] }));
  side.dispatchEvent(new TouchEvent('touchmove', { bubbles: true, touches: [at(250)], changedTouches: [at(250)] }));
  r.translate = side.style.translate;
  side.dispatchEvent(new TouchEvent('touchend', { bubbles: true, touches: [], changedTouches: [at(250)] }));
  setTimeout(() => { document.getElementById('out').textContent = JSON.stringify(r); }, 50);
</script></body></html>`;
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-admin-menu-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, page);
const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--window-size=390,844', '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
fs.rmSync(dir, { recursive: true, force: true });
const r = JSON.parse((dom.match(/<pre id="out">([^<]*)<\/pre>/) || [])[1]?.replace(/&quot;/g, '"') || 'null');
let failures = 0;
const check = (name, ok) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(r)}`); if (!ok) failures++; };
check('the test runs at phone width', !!r && r.phone);
check('with the Admin menu open, account, Admin and Servers get their tap', !!r && r.clicks.join() === 'account,admin,servers');
check('Servers follows its link', !!r && r.hash === '#overview');
check('a tap outside the menu does not close it behind your back', !!r && r.escapes === 0);
check('a drag down on the menu page does not move it', !!r && !r.translate);
if (failures) process.exit(1);
