// K-B84 and K-B85: a list beside a menu (Admin, a server's pages) in a window between a phone and a wide screen.
// K-B84: a row kept all its columns on one line there, in a card of a few hundred pixels: an e-mail address showed
// its first letters and a count nothing at all. K-B85: the buttons above the list stayed on one row: Sort by ran out
// of the card, the search lay over it and the column button was out of sight.
// In a real browser against resources/css/simplify.css, without a panel: at 700, 800 and 1000 pixels every column of
// a row shows whole, under the name, and every tool above the list stays in the card, clear of the others, also on
// a phone; on a wide screen a row is one line and the tools one row, as before. Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/list-narrow.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
const picture = '<td class="fi-ta-cell"><img src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" style="width: 32px; height: 32px"></td>';
const text = (value, extra = '') => `<td class="fi-ta-cell ${extra}"><span class="fi-ta-text">${value}</span></td>`;
// Users, as Pelican lists them: a checkbox, the picture, the name, the e-mail address, a role and two counts.
const user = (name, mail, role, servers) => `<tr class="fi-ta-row"><td class="fi-ta-cell fi-ta-selection-cell"><input type="checkbox" style="width: 16px; height: 16px; margin: 0"></td>${picture}${text(name, 'name')}${text(mail, 'mail')}<td class="fi-ta-cell role"><span class="fi-badge" style="display: inline-block; padding: 2px 8px">${role}</span></td>${text(servers, 'count')}${text('0', 'count')}<td class="fi-ta-cell"><div class="fi-ta-actions"><button class="fi-icon-btn" style="width: 30px; height: 30px"></button></div></td></tr>`;
// Filament's toolbar of the Servers list: two buttons and Group by, Simplify's Sort by, then the search and the columns.
const toolbar = `<div class="fi-ta-header-toolbar"><div class="fi-ta-actions"><button class="tool" style="width: 36px"></button><button class="tool" style="width: 36px"></button><div class="fi-ta-grouping-settings tool" style="width: 300px"></div></div>
<div class="s-sort"><select class="tool" style="width: 167px"><option>Sort by</option></select></div>
<div class="search-group"><div class="fi-ta-search-field"><div class="fi-input-wrp"><input class="tool" type="search" placeholder="Search" style="width: 100%"></div></div><div class="fi-ta-col-manager-dropdown"><button class="tool" style="width: 36px"></button></div></div></div>`;
// What Filament's own sheet gives these parts, as far as the layout goes.
const filament = `.fi-ta-header-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 24px; }
.fi-ta-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; } .search-group { display: flex; align-items: center; gap: 12px; margin-inline-start: auto; }
.fi-ta-search-field { display: flex; } .fi-input-wrp { display: flex; width: 100%; } .tool { box-sizing: border-box; height: 36px; margin: 0; }`;
const page = `<!doctype html><html class="ps-shell ps-shell-menu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="file://${css}/tokens.css"><style>${filament}</style><link rel="stylesheet" href="file://${css}/simplify.css">
<style>body { margin: 0; } .fi-main { margin: 0 36px 0 312px; } @media (max-width: 600px) { .fi-main { margin: 0 12px; } }</style></head><body><div class="ps ps-admin-top"></div>
<main class="fi-main"><div class="fi-ta-ctn"><div class="fi-ta-header-ctn">${toolbar}</div>
<div class="fi-ta-content-ctn"><table class="fi-ta-table"><tbody>${user('jeroen', 'demo@example.com', 'Root Admin', '8')}${user('sam', 'sam@example.com', 'No Roles', '1')}</tbody></table></div></div></main>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    const main = document.querySelector('.fi-main').getBoundingClientRect();
    const rows = [...document.querySelectorAll('.fi-ta-row')].map((row) => {
      const cell = (name) => row.querySelector('.' + name), rect = (el) => el.getBoundingClientRect();
      const box = row.querySelector('input'), b = rect(box), top = rect(row).top;
      const shown = ['name', 'mail', 'role', 'count'].map((name) => cell(name));
      return {
        whole: shown.every((el) => el.scrollWidth <= el.clientWidth + 1 && el.clientWidth > 0),
        widths: shown.map((el) => el.clientWidth + '/' + el.scrollWidth),
        name: Math.round(rect(cell('name')).left), mail: Math.round(rect(cell('mail')).left),
        lines: new Set(shown.map((el) => Math.round((rect(el).top + rect(el).height / 2 - top) / 12))).size,
        box: document.elementFromPoint(b.left + b.width / 2, b.top + b.height / 2) === box,
      };
    });
    const tools = [...document.querySelectorAll('.tool')].map((el) => el.getBoundingClientRect());
    const inside = tools.every((r) => r.left >= main.left - 1 && r.right <= main.right + 1 && r.width > 0);
    const apart = tools.every((a, i) => tools.every((b, j) => j <= i || a.right <= b.left + 1 || b.right <= a.left + 1 || a.bottom <= b.top + 1 || b.bottom <= a.top + 1));
    const search = document.querySelector('.fi-ta-search-field input').getBoundingClientRect();
    document.getElementById('out').textContent = JSON.stringify({ rows, inside, apart, search: Math.round(search.width), toolRows: new Set(tools.map((r) => Math.round(r.top / 20))).size });
  }, 300));
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-list-narrow-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, page);
for (const size of ['390,844', '700,900', '800,900', '1000,900', '1101,900', '1440,900']) {
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--allow-file-access-from-files', `--window-size=${size}`, '--virtual-time-budget=2000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const found = dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/);
  const out = found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')) : null;
  if (!out) { check(`${size}: measured`, false, dom.slice(0, 200)); continue; }
  const width = Number(size.split(',')[0]);
  check(`${size}: every tool above the list stays in the card, clear of the others (K-B85)`, out.inside && out.apart && out.search >= 40, { inside: out.inside, apart: out.apart, search: out.search });
  if (width <= 1100) {
    check(`${size}: every column of a row shows whole (K-B84)`, out.rows.every((row) => row.whole), out.rows.map((row) => row.widths));
    // A phone has no menu beside the list and leaves the checkboxes out.
    check(`${size}: the other columns stand under the name${width > 600 ? ', and the checkbox takes its click' : ''}`, out.rows.every((row) => row.lines > 1 && row.mail === row.name && (row.box || width <= 600)), out.rows);
  } else {
    check(`${size}: a row is one line`, out.rows.every((row) => row.lines === 1 && row.box), out.rows);
    if (width >= 1440) check(`${size}: the tools are one row`, out.toolRows === 1, { toolRows: out.toolRows });
  }
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) { console.error(`FAIL ${failures} narrow list check(s)`); process.exit(1); }
console.log('PASS a list beside a menu shows its columns and its tools in a narrow window');
