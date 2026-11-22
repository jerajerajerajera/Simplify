// K-B98 (Themes lab, 22-11): with Filament on dark (or system on a dark OS), choosing Light in Customize Simplify and
// reloading made only the top bar light; the card and the background stayed dark. Filament 5 keeps its mode in
// localStorage 'filament:theme:mode' and, on alpine:init, puts the class dark back from that key. Simplify only
// wrote 'theme' (Filament 3 and 4). This page runs head.blade.php's script, core.js and then Filament 5's own start-up
// logic, the way a reload does. Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/theme-filament-mode.cjs (set CHROME)'); process.exit(1); }

const root = path.join(__dirname, '..');
const core = fs.readFileSync(path.join(root, 'resources/js/core.js'), 'utf8').replace(/<\/script/gi, '<\\/script');
// The script part of head.blade.php, with its Blade values filled in as a panel without a theme plugin has them.
const headBlade = fs.readFileSync(path.join(root, 'resources/views/head.blade.php'), 'utf8');
const headScript = ([...headBlade.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((m) => m[1]).find((code) => code.includes('ps-panel-theme')) || '').replace(/@js\([^;]*?\)\)/g, 'false');

const page = `<!doctype html><html><head><meta charset="utf-8"><meta name="theme-color" content="#fff"></head>
<body><pre id="out"></pre>
<script>
  // Filament 5.10/5.11 (public/js/filament/filament/app.js, alpine:init), reduced to what sets the class.
  window.filamentStart = () => {
    const i = localStorage.getItem('filament:theme:mode') ?? 'system';
    const dark = i === 'dark' || (i === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
    return dark;
  };
  window.reload = () => { const s = document.createElement('script'); s.textContent = document.getElementById('head').textContent; document.head.append(s); };
  // A user whose Filament mode is dark and who picked Light in Simplify before this fix.
  localStorage.clear();
  localStorage.setItem('filament:theme:mode', 'dark');
  localStorage.setItem('pelicanSimplify.theme', 'light');
</script>
<script id="head">${headScript}</script>
<script>${core}</script>
<script>
  const out = {};
  out.oldChoice = window.filamentStart();
  // Now in the menu: Filament dark, choose Light, reload.
  localStorage.setItem('filament:theme:mode', 'dark');
  SimplifyTheme.set('light');
  out.light = window.filamentStart();
  out.lightKey = localStorage.getItem('filament:theme:mode');
  SimplifyTheme.set('dark');
  out.dark = window.filamentStart();
  SimplifyTheme.set('oled');
  out.oled = window.filamentStart();
  out.oledKey = localStorage.getItem('filament:theme:mode');
  SimplifyTheme.set('system');
  out.systemKey = localStorage.getItem('filament:theme:mode');
  out.olderFilament = localStorage.getItem('theme');
  document.getElementById('out').textContent = JSON.stringify(out);
</script></body></html>`;
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-theme-mode-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, page);
const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
fs.rmSync(dir, { recursive: true, force: true });
const json = (dom.match(/<pre id="out">(.*?)<\/pre>/s) || [])[1] || '{}';
const got = JSON.parse(json.replace(/&quot;/g, '"').replace(/&amp;/g, '&'));

const failures = [];
const check = (ok, what) => ok || failures.push(what);
check(got.oldChoice === false, 'a Light chosen earlier stays light after a reload, also when Filament had dark stored');
check(got.light === false && got.lightKey === 'light', `Light in Customize Simplify stays light after a reload (Filament key ${got.lightKey})`);
check(got.dark === true, 'Dark stays dark after a reload');
check(got.oled === true && got.oledKey === 'dark', `OLED is dark for Filament (key ${got.oledKey})`);
check(got.systemKey === 'system', `System hands Filament system (key ${got.systemKey})`);
check(got.olderFilament === 'system', `Filament 3 and 4 still hear the choice in 'theme' (got ${got.olderFilament})`);

if (failures.length) {
  console.log('FAIL theme follows Filament 5 after a reload (K-B98):\n - ' + failures.join('\n - '));
  process.exit(1);
}
console.log("PASS theme follows Filament 5 after a reload (K-B98): Light, Dark, OLED and System also land in 'filament:theme:mode'");
