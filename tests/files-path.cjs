// K-B78: in a folder of the file manager, and in a file opened from it, there was no way back up. Pelican shows the path
// as the page's breadcrumb (Files > plugins), and Simplify hid every breadcrumb on a server page, so the only way out
// of a folder was the Files item of the menu, back to the root. In a real browser against resources/css/dashboard.css,
// without a panel: with more than one step the path shows above the list and a click on a step lands on its link; the
// root (one step) and other pages keep no breadcrumb. Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/files-path.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
// Filament's page: the header (breadcrumb, then the title) above the page's content, here a list with its toolbar.
const crumbs = (steps) => `<nav class="fi-breadcrumbs" aria-label="Breadcrumb"><ol class="fi-breadcrumbs-list" style="display: flex; gap: 8px; margin: 0; padding: 0; list-style: none">${steps.map((step) => `<li class="fi-breadcrumbs-item" style="display: flex"><a class="fi-breadcrumbs-item-label" href="#${step}">${step}</a></li>`).join('')}</ol></nav>`;
const page = (kind, steps) => `<!doctype html><html class="${process.env.FIT === '0' ? '' : 'ps-fit'}" style="--ps-fit: 400px"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/components.css"><link rel="stylesheet" href="file://${css}/dashboard.css">
<style>body { margin: 0; }</style></head><body><div class="fi-layout"><div class="fi-main-ctn">
<div class="ps server-active ps-server-top"><div class="ps-main" style="height: 120px"></div></div>
<main class="fi-main"><div class="fi-page ${kind}"><div class="fi-page-header-main-ctn">
<header class="fi-header fi-header-has-breadcrumbs"><div>${crumbs(steps)}<h1 class="fi-header-heading">Files</h1></div></header>
<div class="fi-page-main"><div class="fi-page-content"><div class="fi-ta"><div class="fi-ta-ctn"><div class="fi-ta-header-toolbar" style="display: flex; justify-content: space-between"><button type="button">New file</button><input id="search" type="search" style="width: 240px; height: 36px"></div>
<table class="fi-ta-table"><tbody><tr class="fi-ta-row"><td class="fi-ta-cell">LuckPerms.jar</td></tr></tbody></table></div></div></div></div>
</div></div></main></div></div>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    const nav = document.querySelector('.fi-breadcrumbs'), link = nav.querySelector('a'), search = document.getElementById('search'), main = document.querySelector('.fi-main');
    const shown = nav.getClientRects().length > 0;
    const out = { shown, title: document.querySelector('.fi-header-heading').getClientRects().length > 0 };
    if (shown) {
      const r = link.getBoundingClientRect(), box = nav.getBoundingClientRect(), field = search.getBoundingClientRect(), card = main.getBoundingClientRect();
      out.own = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2) === link;
      out.above = box.bottom <= field.top;
      out.inside = box.left >= card.left && box.right <= card.right && box.top >= card.top;
      out.steps = [...nav.querySelectorAll('a')].filter((a) => a.getClientRects().length).length;
    }
    document.getElementById('out').textContent = JSON.stringify(out);
  }, 300));
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-files-path-'));
const measure = (kind, steps, size) => {
  const file = path.join(dir, 'page.html');
  fs.writeFileSync(file, page(kind, steps));
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--allow-file-access-from-files', `--window-size=${size}`, '--virtual-time-budget=2000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const found = dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/);
  return found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')) : null;
};
const LIST = 'fi-resource-list-records-page fi-resource-files', EDIT = 'fi-resource-edit-file-page';
for (const size of ['1440,900', '390,844']) {
  const folder = measure(LIST, ['Files', 'plugins', 'LuckPerms'], size);
  check(`${size}: in a folder the path shows above the list, and a click on a step lands on its link`, !!folder && folder.shown && folder.own === true && folder.above === true && folder.inside === true && folder.steps === 3 && !folder.title, folder);
  const file = measure(EDIT, ['Files', 'server.properties'], size);
  check(`${size}: a file opened from the list shows the path back`, !!file && file.shown && file.own === true && file.steps === 2 && !file.title, file);
  const root = measure(LIST, ['Files'], size);
  check(`${size}: the root of the file manager has no path row`, !!root && !root.shown, root);
  const other = measure('fi-resource-edit-record-page fi-resource-schedules', ['Schedules', 'Daily restart', 'Edit'], size);
  check(`${size}: other pages keep their breadcrumb hidden`, !!other && !other.shown, other);
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) { console.error(`FAIL ${failures} file manager path check(s)`); process.exit(1); }
console.log('PASS the file manager shows its path in a folder and in a file');
