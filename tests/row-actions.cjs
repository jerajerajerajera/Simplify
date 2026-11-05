// Phones: a list row with many buttons (Egg Updates' Test first and Update next to Edit, Export, Duplicate) keeps its
// main button and ⋯, so the page never slides sideways; ⋯ opens a sheet that presses the row's own buttons
// (resources/js/row-actions.js, Jeroen 5-11). Needs Chrome or Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/row-actions.cjs (set CHROME)'); process.exit(1); }

const read = (file) => fs.readFileSync(path.join(__dirname, '..', 'resources', file), 'utf8').replace(/<\/(script|style)/gi, '<\\/$1');
const icon = '<svg viewBox="0 0 24 24" width="20" height="20"><path d="M4 12h16"/></svg>';
const button = (name, color = 'gray') => `<button type="button" class="fi-icon-btn fi-color-${color}" aria-label="${name}" onclick="window.pressed.push('${name}')">${icon}</button>`;
const row = (egg, update) => `<tr class="fi-ta-row"><td class="fi-ta-cell"><span class="fi-ta-text">${egg}</span></td><td class="fi-ta-cell"><div class="fi-ta-actions">${update ? button('Test first') + button('Update', 'success') : ''}${button('Edit')}${button('Export')}${button('Duplicate')}</div></td></tr>`;
const page = `<!doctype html><html class="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>${read('css/tokens.css')}</style><style>${read('css/dashboard.css')}</style><style>${read('css/simplify.css')}</style>
<style>.fi-ta-actions { display: flex; gap: 4px; } .fi-icon-btn { flex: none; width: 36px; height: 36px; } td { white-space: nowrap; }</style></head>
<body><div class="ps ps-admin-top"></div>
<div class="fi-ta-ctn"><table class="fi-ta-table"><tbody>${row('FTB Server', true)}${row('Fabric', false)}</tbody></table></div>
<pre id="out"></pre>
<script>window.pressed = [];</script>
<script>${read('js/sheet.js')}</script>
<script>${read('js/row-actions.js')}</script>
<script>
  const wait = (ms) => new Promise((r) => setTimeout(r, ms));
  const shown = (el) => !!el && el.getClientRects().length > 0 && getComputedStyle(el).display !== 'none';
  (async () => {
    await wait(100);
    const r = { phone: matchMedia('(max-width: 600px)').matches, wide: document.documentElement.scrollWidth > innerWidth + 1 };
    const [ftb, fabric] = document.querySelectorAll('.fi-ta-row .fi-ta-actions');
    r.ftb = [...ftb.querySelectorAll('button')].filter(shown).map((b) => b.getAttribute('aria-label'));
    r.fabric = [...fabric.querySelectorAll('button')].filter(shown).map((b) => b.getAttribute('aria-label'));
    ftb.querySelector('.ps-row-more').click();
    await wait(80);
    const sheet = document.querySelector('.ps-row-sheet .ps-dropdown');
    r.title = sheet?.querySelector('.ps-dropdown-title')?.textContent;
    r.items = [...(sheet?.querySelectorAll('.ps-menu-item') || [])].map((b) => b.textContent.trim());
    r.sheetFixed = !!sheet && getComputedStyle(sheet).position === 'fixed';
    [...sheet.querySelectorAll('.ps-menu-item')].find((b) => b.textContent.trim() === 'Export').click();
    await wait(300);
    r.pressed = window.pressed.slice();
    r.closed = !document.querySelector('.ps-row-sheet');
    document.getElementById('out').textContent = JSON.stringify(r);
  })().catch((e) => (document.getElementById('out').textContent = JSON.stringify({ error: String(e) })));
</script></body></html>`;
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-row-actions-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, page);
let dom = '';
try {
  dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--window-size=390,844', '--virtual-time-budget=5000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
} finally {
  fs.rmSync(dir, { recursive: true, force: true });
}
const raw = (dom.match(/<pre id="out">([^<]*)<\/pre>/) || [])[1] || 'null';
const r = JSON.parse(raw.replace(/&quot;/g, '"').replace(/&amp;/g, '&'));
let failures = 0;
const check = (name, ok) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(r)}`); if (!ok) failures++; };
check('the test runs at phone width', !!r && r.phone);
check('a row with five buttons shows Update and ⋯ only', !!r && r.ftb?.join() === 'Update,More actions');
check('a row with three buttons and none coloured shows ⋯ only', !!r && r.fabric?.join() === 'More actions');
check('the page is no wider than the phone', !!r && r.wide === false);
check('⋯ opens a sheet with the row\'s name and every button by its name', !!r && r.title === 'FTB Server' && r.items?.join() === 'Test first,Update,Edit,Export,Duplicate' && r.sheetFixed);
check('a tap in the sheet presses the row\'s own button and closes the sheet', !!r && r.pressed?.join() === 'Export' && r.closed);
if (failures) process.exit(1);
