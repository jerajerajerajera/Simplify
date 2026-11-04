// A Filament action with ->labeledFrom('sm') (Egg Updates' Test first and Update) in a real browser, without a panel:
// on a computer only the button with text shows and on a phone only the icon button, never both (K-B48, 4-11).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/labeled-from.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
// Filament's buttons and its rules for labeledFrom('sm') (Filament 5.10, packages/support/resources/css/components:
// the button with text has fi-labeled-from-sm, the icon button is hidden through it), as far as they matter here.
// Filament's own stylesheet comes before Simplify's, as in the panel.
const filament = `.fi-btn,.fi-icon-btn{display:inline-grid}.fi-btn.fi-labeled-from-sm{display:none}
@media (min-width:40rem){.fi-icon-btn:has(+ .fi-btn.fi-labeled-from-sm){display:none}.fi-btn.fi-labeled-from-sm{display:inline-grid}}`;
const action = (where) => `<div class="${where}"><button class="fi-icon-btn fi-ac-icon-btn-action" data-kind="icon">i</button><button class="fi-btn fi-ac-btn-action fi-labeled-from-sm" data-kind="text">Update</button></div>`;
const html = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>${filament}</style>
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/simplify.css">
</head><body>
<table class="fi-ta-table"><tr class="fi-ta-row"><td class="fi-ta-cell">${action('fi-ta-actions')}</td></tr></table>
<div class="fi-header-actions-ctn">${action('fi-ac')}</div>
<div class="fi-ta-actions"><button class="fi-icon-btn" data-kind="plain-row">x</button></div>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    const shown = (el) => getComputedStyle(el).display !== 'none';
    document.getElementById('out').textContent = JSON.stringify({
      groups: [...document.querySelectorAll('.fi-ta-actions, .fi-header-actions-ctn')].filter((g) => g.querySelector('[data-kind="text"]')).map((g) => [...g.querySelectorAll('button')].filter(shown).map((b) => b.dataset.kind)),
      plain: getComputedStyle(document.querySelector('[data-kind="plain-row"]')).display,
    });
  }, 300));
</script></body></html>`;

const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'labeled-from-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, html);

let failed = 0;
for (const [name, width, height, want] of [['d1440', 1440, 900, 'text'], ['tablet768', 768, 1024, 'text'], ['phone390', 390, 844, 'icon']]) {
  const out = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--allow-file-access-from-files', `--window-size=${width},${height}`, '--virtual-time-budget=2000', '--dump-dom', `file://${file}`], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
  const json = out.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/);
  const result = json ? JSON.parse(json[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')) : null;
  const ok = Boolean(result) && result.groups.length === 2 && result.groups.every((g) => g.length === 1 && g[0] === want) && result.plain === 'inline-grid';
  console.log(`${ok ? 'PASS' : 'FAIL'} ${name}: a labeledFrom action shows only its ${want} button, a plain icon button keeps its look ${JSON.stringify(result)}`);
  if (!ok) failed++;
}
fs.rmSync(dir, { recursive: true, force: true });
process.exit(failed ? 1 : 0);
