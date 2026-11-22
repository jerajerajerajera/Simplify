// A description under a column's text in one of Pelican's lists (a plugin's summary, the event of an activity line, a
// schedule's time zone) was hidden in Simplify's lists, so a plugin was a bare name. It shows as one grey line, cut
// off where the row ends, never wider than the card and never on a second line. Eggs keep theirs out of the list: a
// paragraph each, which shows on the egg's own page.
// In a real browser against resources/css/simplify.css, without a panel. Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/list-descriptions.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
const long = 'Adds a page to the Admin area where every server gets a public listing, a banner and a short text, with a search for visitors and a feed for the website. '.repeat(2);
// A row as Filament draws a text column with a description: the text, and the description under it. The page gives
// Filament's two greys, which Simplify's muted colour is made of.
const row = (name, description) => `<tr class="fi-ta-row"><td class="fi-ta-cell name"><div class="fi-ta-text fi-ta-text-has-descriptions"><p class="fi-ta-text-item">${name}</p><p class="fi-ta-text-description">${description}</p></div></td><td class="fi-ta-cell"><span class="fi-ta-text">1.2.0</span></td><td class="fi-ta-cell"><div class="fi-ta-actions"><button class="fi-icon-btn" style="width: 30px; height: 30px"></button></div></td></tr>`;
const list = (resource) => `<div class="fi-page fi-resource-${resource}"><div class="fi-ta-ctn"><div class="fi-ta-content-ctn"><table class="fi-ta-table"><tbody>${row('Site directory', long)}${row('Paper', 'UTC')}</tbody></table></div></div></div>`;
const page = `<!doctype html><html class="ps-shell ps-shell-menu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/simplify.css">
<style>:root { --gray-400: #a1a1aa; --gray-600: #52525b; } body { margin: 0; color: #18181b; } .fi-main { margin: 0 36px 0 312px; } @media (max-width: 600px) { .fi-main { margin: 0 12px; } }</style></head><body><div class="ps ps-admin-top"></div>
<main class="fi-main">${list('plugins')}${list('eggs')}</main>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    const main = document.querySelector('.fi-main').getBoundingClientRect();
    const read = (resource) => [...document.querySelectorAll('.fi-resource-' + resource + ' .fi-ta-text-description')].map((el) => {
      const r = el.getBoundingClientRect(), name = el.previousElementSibling.getBoundingClientRect(), style = getComputedStyle(el);
      return { shown: style.display !== 'none' && r.height > 0, height: Math.round(r.height), inside: r.right <= main.right + 1 && r.left >= main.left - 1, under: r.top >= name.bottom - 1, cut: el.scrollWidth > el.clientWidth + 1, grey: style.color !== getComputedStyle(el.previousElementSibling).color };
    });
    document.getElementById('out').textContent = JSON.stringify({ plugins: read('plugins'), eggs: read('eggs'), sideways: document.documentElement.scrollWidth > innerWidth + 1 });
  }, 300));
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-list-descriptions-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, page);
for (const size of ['390,844', '800,900', '1440,900']) {
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--allow-file-access-from-files', `--window-size=${size}`, '--virtual-time-budget=2000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const found = dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/);
  const out = found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')) : null;
  if (!out) { check(`${size}: measured`, false, dom.slice(0, 200)); continue; }
  const [longOne, shortOne] = out.plugins;
  check(`${size}: a description shows under its name, in grey`, out.plugins.every((d) => d.shown && d.under && d.grey), out.plugins);
  check(`${size}: a long description is one line, cut off inside the card`, longOne.height <= 20 && longOne.cut && longOne.inside && !out.sideways, { longOne, sideways: out.sideways });
  check(`${size}: a short description shows whole`, shortOne.shown && shortOne.height <= 20 && !shortOne.cut, shortOne);
  check(`${size}: Eggs keep their descriptions out of the list`, out.eggs.every((d) => !d.shown), out.eggs);
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) { console.error(`FAIL ${failures} list description check(s)`); process.exit(1); }
console.log('PASS a description in a list shows as one grey line, except in Eggs');
