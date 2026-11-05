// K-B58 (Jeroen 5-11): with a theme plugin the overview was black (the theme's gray-950) while the Admin pages were
// Simplify's grey, and after going back and forth the overview went from grey to black. Dark is now one grey
// everywhere, also with a theme, and OLED (Customize › Theme) is the only black. Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/theme-background.cjs (set CHROME)'); process.exit(1); }

const root = path.join(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8').replace(/<\/script/gi, '<\\/script');
const tokens = read('resources/css/tokens.css');
const core = read('resources/js/core.js');
const head = fs.readFileSync(path.join(root, 'resources/views/head.blade.php'), 'utf8');
// Pelican's own grays (Filament's Zinc), as a panel without a theme plugin has them.
const zinc = ':root { --gray-50: #fafafa; --gray-900: #18181b; --gray-950: #09090b; }';
const page = `<!doctype html><html><head><meta charset="utf-8"><meta name="theme-color" content="#fff"><style>${zinc}</style><style>${tokens}</style></head>
<body><div class="ps dark" id="ps"></div><pre id="out"></pre>
<script>${core}</script>
<script>
  const r = document.documentElement;
  const bg = () => getComputedStyle(document.getElementById('ps')).getPropertyValue('--ps-bg').trim();
  const color = () => { const probe = document.createElement('i'); probe.style.color = bg(); document.body.append(probe); const c = getComputedStyle(probe).color; probe.remove(); return c; };
  const out = {};
  SimplifyTheme.set('dark'); out.dark = color();
  r.classList.add('ps-panel-theme'); out.themeDark = color();
  SimplifyTheme.set('oled'); out.themeOled = color();
  r.classList.remove('ps-panel-theme'); out.oled = color();
  out.oledClasses = r.classList.contains('dark') && r.classList.contains('ps-oled');
  out.filament = localStorage.getItem('theme');
  out.metaOled = document.querySelector('meta[name="theme-color"]').content;
  SimplifyTheme.set('dark'); out.darkAgain = !r.classList.contains('ps-oled');
  document.getElementById('out').textContent = JSON.stringify(out);
</script></body></html>`;
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-theme-bg-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, page);
const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
fs.rmSync(dir, { recursive: true, force: true });
const json = (dom.match(/<pre id="out">(.*?)<\/pre>/s) || [])[1] || '{}';
const got = JSON.parse(json.replace(/&quot;/g, '"').replace(/&amp;/g, '&'));
// Chrome writes a color-mix() result as color(srgb r g b) with 0-1 channels.
const rgb = (text) => { const n = (String(text).match(/[\d.]+/g) || []).slice(0, 3).map(Number); return /^color\(srgb/.test(String(text)) ? n.map((v) => v * 255) : n; };
const near = (a, b) => rgb(a).length === 3 && rgb(a).every((v, i) => Math.abs(v - rgb(b)[i]) <= 2);

const failures = [];
const check = (ok, what) => ok || failures.push(what);
check(near(got.dark, 'rgb(16,16,18)'), `dark is Simplify's grey #101012 (got ${got.dark})`);
check(near(got.themeDark, got.dark), `dark with a theme plugin is the same grey, not the theme's black (got ${got.themeDark})`);
check(near(got.oled, 'rgb(0,0,0)') && near(got.themeOled, 'rgb(0,0,0)'), `OLED is pure black, also with a theme plugin (got ${got.oled}, ${got.themeOled})`);
check(got.oledClasses === true, 'OLED is a dark theme (dark and ps-oled on <html>)');
check(got.filament === 'dark', `Filament's own switcher hears dark for OLED (got ${got.filament})`);
check(got.metaOled === '#000000', `the phone's status bar is black in OLED (got ${got.metaOled})`);
check(got.darkAgain === true, 'going back to Dark drops OLED');
check(/classList\.toggle\('ps-panel-theme'/.test(head) && /ps-oled/.test(head), 'every page sets the theme-plugin and OLED classes before paint (head.blade.php)');

if (failures.length) {
  console.log('FAIL theme background (K-B58):\n - ' + failures.join('\n - '));
  process.exit(1);
}
console.log('PASS theme background (K-B58): one grey in dark, with or without a theme plugin; OLED is black');
