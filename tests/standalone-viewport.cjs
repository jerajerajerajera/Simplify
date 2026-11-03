// The installed app (K-B10, Jeroen 3-11): Simplify draws up to the screen edges (viewport-fit=cover, core.js) so the
// bottom bar keeps clear of the iPhone's home bar. A click in a menu (wire:navigate) swaps the page's head for the new
// page's, whose viewport has no viewport-fit: the cover has to come back, or the bottom bar loses its room and drops.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/standalone-viewport.cjs (set CHROME)'); process.exit(1); }

const core = fs.readFileSync(path.join(__dirname, '..', 'resources', 'js', 'core.js'), 'utf8').replace(/<\/script/gi, '<\\/script');
const page = `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<script>Object.defineProperty(navigator, 'standalone', { get: () => true });</script>
</head><body><pre id="out"></pre>
<script>${core}</script>
<script>
  const content = () => document.querySelector('meta[name="viewport"]')?.content || '';
  const first = content();
  // Livewire's wire:navigate: the old head's viewport goes, the new page's (as the server sends it) comes in.
  document.querySelector('meta[name="viewport"]').remove();
  const fresh = document.createElement('meta'); fresh.name = 'viewport'; fresh.content = 'width=device-width, initial-scale=1';
  document.head.append(fresh);
  document.dispatchEvent(new CustomEvent('livewire:navigated'));
  setTimeout(() => { document.getElementById('out').textContent = JSON.stringify({ first, navigated: content(), standalone: document.documentElement.classList.contains('ps-standalone') }); }, 50);
</script></body></html>`;
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-viewport-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, page);
const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
fs.rmSync(dir, { recursive: true, force: true });
const r = JSON.parse((dom.match(/<pre id="out">([^<]*)<\/pre>/) || [])[1]?.replace(/&quot;/g, '"') || 'null');
let failures = 0;
const check = (name, ok) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(r)}`); if (!ok) failures++; };
check('the installed app is recognised', !!r && r.standalone);
check('the installed app draws up to the screen edges on its first page', !!r && /viewport-fit=cover/.test(r.first));
check('it still does after a click in a menu (wire:navigate)', !!r && /viewport-fit=cover/.test(r.navigated));
if (failures) process.exit(1);
