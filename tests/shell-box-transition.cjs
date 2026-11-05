// K-B61: on the Admin page the content box of Plugins ran about 80px below the window for half a second after it
// opened. Filament gives .fi-main-ctn `transition: all 150ms`, so when the box's minimum height went from a whole
// window (Filament, dashboard.css) to Simplify's 0, the browser animated min-height; a transition beats even an
// !important rule, and on a busy page it stays at its start for a while. In Simplify's frame the box only fades
// (opacity), its sizes never animate. Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/shell-box-transition.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
// Filament's layout.css as far as it matters: the box fades in and animates every property, and without a top bar it
// is at least a window high.
const filament = '*{box-sizing:border-box}body{margin:0}.fi-layout{display:flex;width:100%;min-height:100dvh}.fi-main-ctn{flex:1;flex-direction:column;opacity:0;transition-property:all;transition-duration:150ms}.fi-body:not(.fi-body-has-topbar) .fi-main-ctn{min-height:100dvh}';
const page = `<!doctype html><html class="dark"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>${filament}</style>
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/simplify.css"><link rel="stylesheet" href="file://${css}/components.css"><link rel="stylesheet" href="file://${css}/dashboard.css">
</head><body class="fi-body">
<div class="fi-layout">
  <div x-persist="simplify-admin-top"><div class="ps server-active host-mode ps-admin-top" style="height:64px">Panel</div></div>
  <div x-persist="simplify-admin-side"><div class="ps server-active ps-admin-side"><div class="ps-host-side">Menu</div><div class="ps-admin-crumb">Plugins</div></div></div>
  <div class="fi-main-ctn" style="display: flex; opacity: 1"><main class="fi-main"><div style="height:400px">Plugins</div></main></div>
</div>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    const root = document.documentElement, box = document.querySelector('.fi-main-ctn');
    const before = getComputedStyle(box).minHeight;
    // The page arrives in Simplify's frame (head.blade.php, wire:navigate): the frame's rules take over at once.
    root.classList.add('ps-shell', 'ps-shell-menu');
    const cs = getComputedStyle(box), b = box.getBoundingClientRect();
    document.getElementById('out').textContent = JSON.stringify({ before, minHeight: cs.minHeight, bottom: Math.round(b.bottom), window: innerHeight,
      animated: box.getAnimations().map((a) => a.transitionProperty || a.animationName), fades: /opacity|all/.test(cs.transitionProperty) });
  }, 300));
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-box-'));
for (const [stand, size] of [['d1100', '1100,800'], ['laag1366', '1366,768'], ['hoog1280', '1280,1024']]) {
  const file = path.join(dir, `${stand}.html`);
  fs.writeFileSync(file, page);
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--hide-scrollbars', `--window-size=${size}`, '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const r = JSON.parse((dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/) || [])[1]?.replace(/&quot;/g, '"') || 'null');
  if (!r) { check(`${stand}: measured`, false, null); continue; }
  check(`${stand}: the box was a window high before the frame's rules (the case being tested)`, r.before === r.window + 'px', r);
  check(`${stand}: the box's minimum height is 0 right away, not animated`, r.minHeight === '0px' && !r.animated.includes('min-height'), r);
  check(`${stand}: the box ends inside the window right away`, r.bottom <= r.window + 2, r);
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) { console.error(`FAIL ${failures} box check(s)`); process.exit(1); }
console.log('PASS the Admin page\'s box never animates its height');
