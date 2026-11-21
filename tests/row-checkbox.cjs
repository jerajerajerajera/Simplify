// K-B73: in Pelican's lists with bulk actions and a picture per row (Eggs, Users) the row's checkbox lay under the
// picture: the picture sits in the row's margin, and with a checkbox before it that put it over the checkbox, so a
// click on the checkbox landed on the picture. A row without a checkbox (your own account in Users) also started
// 16px before the others. A click test in a real browser against resources/css/simplify.css, without a panel:
// at the middle of the checkbox the checkbox itself must lie. Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/row-checkbox.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
const picture = '<td class="fi-ta-cell fi-ta-cell-icon fi-align-center"><img src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" style="width: 32px; height: 32px"></td>';
// Filament's rows: the selection cell first (empty for a record that cannot be selected), then the columns.
const row = (name, select) => `<tr class="fi-ta-row">${select === null ? '' : `<td class="fi-ta-cell fi-ta-selection-cell">${select ? '<input type="checkbox" style="width: 16px; height: 16px; margin: 0">' : ''}</td>`}${picture}<td class="fi-ta-cell fi-ta-cell-name"><span class="fi-ta-text">${name}</span></td><td class="fi-ta-cell"><div class="fi-ta-actions"><button class="fi-icon-btn" style="width: 30px; height: 30px"></button></div></td></tr>`;
const page = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/simplify.css">
<style>body { margin: 0; }</style></head><body><div class="ps ps-admin-top"></div>
<div class="fi-ta-ctn"><table class="fi-ta-table" id="bulk"><tbody>${row('Own account', false)}${row('Paper', true)}</tbody></table></div>
<div class="fi-ta-ctn"><table class="fi-ta-table" id="plain"><tbody>${row('Without bulk actions', null)}</tbody></table></div>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    const left = (el) => Math.round(el.getBoundingClientRect().left);
    const measure = (tr) => {
      const box = tr.querySelector('input'), img = tr.querySelector('img'), name = tr.querySelector('.fi-ta-cell-name');
      const out = { row: left(tr), img: left(img), name: left(name), box: null, own: null, shown: !!box && box.getClientRects().length > 0 };
      if (out.shown) {
        const r = box.getBoundingClientRect();
        out.box = Math.round(r.left);
        out.own = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2) === box;
        const p = img.getBoundingClientRect();
        out.apart = r.right <= p.left || p.right <= r.left;
      }
      return out;
    };
    const [own, paper] = [...document.querySelectorAll('#bulk .fi-ta-row')].map(measure);
    document.getElementById('out').textContent = JSON.stringify({ own, paper, plain: measure(document.querySelector('#plain .fi-ta-row')) });
  }, 300));
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-row-checkbox-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, page);
for (const size of ['1440,900', '768,1024', '390,844']) {
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--allow-file-access-from-files', `--window-size=${size}`, '--virtual-time-budget=2000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const found = dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/);
  const out = found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')) : null;
  if (!out) { check(`${size}: measured`, false, dom.slice(0, 200)); continue; }
  if (size === '390,844') {
    // Phones leave the checkboxes out: every row keeps its picture in the margin.
    check(`${size}: no checkboxes, and every row starts alike`, !out.paper.shown && out.own.img === out.paper.img && out.paper.img === out.plain.img && out.own.name === out.plain.name, out);
    continue;
  }
  check(`${size}: a click on a row's checkbox lands on the checkbox, beside the picture`, out.paper.own === true && out.paper.apart === true && out.paper.box < out.paper.img, out.paper);
  check(`${size}: a row without a checkbox starts where the others start`, out.own.img === out.paper.img && out.own.name === out.paper.name, out);
  check(`${size}: a list without bulk actions keeps the picture in the row's margin`, out.plain.img < out.paper.img && out.plain.name < out.paper.name, out);
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) { console.error(`FAIL ${failures} row checkbox check(s)`); process.exit(1); }
console.log('PASS a row\'s checkbox and picture stand beside each other');
