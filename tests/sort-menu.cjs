// The "Sort by" menu of a list inside Simplify (G1, sort.js): the list's sortable columns, the sorted one chosen with
// its direction, and a choice presses that column's own sort button. Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/sort-menu.cjs (set CHROME)'); process.exit(1); }

const read = (file) => fs.readFileSync(path.join(__dirname, '..', 'resources', file), 'utf8').replace(/<\/(script|style)/gi, '<\\/$1');
const page = `<!doctype html><html class="ps-frame"><head><meta charset="utf-8"></head><body>
<div class="fi-ta-ctn"><div class="fi-ta-header-toolbar"><div><button>+</button></div><div><div class="fi-ta-search-field"><input></div></div></div>
<table class="fi-ta-table"><thead><tr><th aria-sort="none"><button class="fi-ta-header-cell-sort-btn" id="name">Name</button></th><th>Egg</th><th aria-sort="descending"><button class="fi-ta-header-cell-sort-btn" id="user">Username</button></th></tr></thead><tbody></tbody></table></div>
<pre id="out"></pre>
<script>${read('js/sort.js')}</script>
<script>
  const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const results = [];
  const ok = (name, pass, detail) => results.push((pass ? 'PASS ' : 'FAIL ') + name + (pass ? '' : ' ' + JSON.stringify(detail)));
  const until = async (fn) => { for (let t = 0; t < 3000 && !fn(); t += 10) await wait(10); return fn(); };
  (async () => {
    const select = await until(() => document.querySelector('.fi-ta-header-toolbar .s-sort-select'));
    ok('the toolbar has a Sort by menu', !!select, {});
    const options = [...select.options].map((o) => o.textContent);
    ok('it lists the sortable columns only', JSON.stringify(options) === JSON.stringify(['Sort by', 'Name', 'Username']), options);
    ok('the sorted column is chosen', select.value === '1', select.value);
    const dir = document.querySelector('.s-sort-dir');
    ok('a direction button says descending', dir && dir.getAttribute('aria-label') === 'Descending', dir?.outerHTML);
    ok('the menu stands before the search field', select.closest('.s-sort').nextElementSibling?.querySelector('.fi-ta-search-field') != null, {});
    let pressed = null;
    document.getElementById('name').addEventListener('click', () => (pressed = 'name'));
    select.value = '0'; select.dispatchEvent(new Event('change'));
    ok('choosing Name presses its own sort button', pressed === 'name', pressed);
    document.getElementById('out').textContent = results.join('\\n') + '\\nDONE';
  })().catch((error) => (document.getElementById('out').textContent = 'FAIL ' + error.stack + '\\nDONE'));
</script></body></html>`;

let failed = false;
for (const size of ['1440,900']) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-sort-'));
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
  if (!lines.includes('DONE')) { console.error(`FAIL sort menu check did not finish in the browser at ${size}`); console.error(lines.join('\n')); process.exit(1); }
  lines.filter((line) => line !== 'DONE').forEach((line) => { console.log(line); if (line.startsWith('FAIL')) failed = true; });
}
if (failed) process.exit(1);
