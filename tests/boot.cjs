// The placeholders while Simplify starts (resources/views/dashboard/boot.blade.php) in a real browser, without a panel:
// while Simplify's root still has x-cloak the page shows the shapes in grey over the whole window in the theme's own
// background, never white and never wider than the window; once Alpine starts the root they are gone, and should the
// scripts never start they fade away by themselves. Needs Chrome or Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/boot.cjs (set CHROME)'); process.exit(1); }

// Headless Chrome keeps a window at least 500 px wide: the phone check reads the width it got.
// The partial with its Blade written out by hand: the loops repeated, one kind's branch kept.
function partial(kind) {
  let blade = fs.readFileSync(path.join(__dirname, '..', 'resources', 'views', 'dashboard', 'boot.blade.php'), 'utf8');
  blade = blade.replace(/\{\{--[\s\S]*?--\}\}/g, '').replace(/@php\(.*\)\n/, '').replace(/@once|@endonce/g, '');
  blade = blade.replace(/\{\{ \$kind \}\}/g, kind);
  blade = blade.replace(/@for \(\$i = 0; \$i < (\d+); \$i\+\+\)([\s\S]*?)@endfor/g, (_, n, body) => body.repeat(Number(n)));
  const branches = blade.match(/@if \(\$kind === 'overview'\)([\s\S]*?)@elseif \(\$kind === 'server'\)([\s\S]*?)@else([\s\S]*?)@endif/);
  if (!branches) throw new Error('the partial no longer has the overview, server and other branches');
  return blade.replace(branches[0], { overview: branches[1], server: branches[2] }[kind] ?? branches[3]);
}

const failures = [];
let passes = 0;
function run(name, { kind, dark = false, width = 1440, height = 900, cloaked = true, wait = 500, seek = false }) {
  const page = `<!doctype html><html class="${dark ? 'dark' : ''}"><meta charset="utf-8"><meta name="viewport" content="width=device-width">
<body style="margin:0;background:#fff">
<div class="ps" data-simplify-root ${cloaked ? 'x-cloak' : ''} style="${cloaked ? 'display:none' : ''}">Simplify</div>
${partial(kind)}
<pre id="out"></pre>
<script>
  setTimeout(() => {
    const boot = document.querySelector('.ps-boot');
    // The fade runs on the browser's own animation clock, which headless Chrome's virtual time does not always move
    // as far as its timers: the fade is jumped to its end instead of waited for, and its end time is reported.
    const away = ${seek} ? boot.getAnimations().find((a) => a.animationName === 'ps-boot-away') : null;
    const fadeEnd = away ? away.effect.getComputedTiming().endTime : null;
    if (away) away.currentTime = fadeEnd;
    const style = getComputedStyle(boot);
    const box = boot.getBoundingClientRect();
    const card = document.querySelector('.ps-boot-card');
    document.getElementById('out').textContent = JSON.stringify({
      shown: style.display !== 'none' && style.visibility !== 'hidden',
      background: style.backgroundColor,
      covers: box.left <= 0 && box.top <= 0 && box.right >= innerWidth && box.bottom >= innerHeight,
      wider: document.documentElement.scrollWidth > innerWidth,
      cards: document.querySelectorAll('.ps-boot-card').length,
      cardWidth: card ? Math.round(card.getBoundingClientRect().width) : 0,
      width: innerWidth,
      fadeEnd,
    });
  }, ${wait});
</script></html>`;
  const file = path.join(os.tmpdir(), `simplify-boot-${process.pid}-${passes + failures.length}.html`);
  fs.writeFileSync(file, page);
  let dom = '';
  try {
    dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', `--window-size=${width},${height}`, `--virtual-time-budget=${wait + 2000}`, '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  } finally {
    fs.rmSync(file, { force: true });
  }
  const found = dom.match(/<pre id="out">([^<]*)<\/pre>/);
  return found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"')) : null;
}
function check(name, ok, detail) {
  if (ok) { passes++; console.log('PASS ' + name); } else failures.push(name + (detail ? ' (' + JSON.stringify(detail) + ')' : ''));
}

for (const kind of ['overview', 'server', 'admin']) {
  for (const dark of [false, true]) {
    const r = run('', { kind, dark });
    const white = r && /rgb\(255, 255, 255\)/.test(r.background);
    const expected = dark ? 'rgb(16, 16, 18)' : 'rgb(244, 244, 245)';
    check(`${kind} ${dark ? 'dark' : 'light'}: placeholders cover the window in the theme's background while Simplify starts`, r && r.shown && r.covers && !white && r.background === expected && r.cards > 0, r);
  }
}
const overview = run('', { kind: 'overview' });
check('the overview shows a row of server cards as wide as the real ones', overview && overview.cards === 8 && overview.cardWidth >= 230 && overview.cardWidth <= 270, overview);
const phone = run('', { kind: 'overview', width: 390, height: 844 });
check('on a phone the placeholders are one column and never wider than the window', phone && phone.shown && !phone.wider && phone.width <= 600 && phone.cardWidth === phone.width - 28, phone);
const started = run('', { kind: 'overview', cloaked: false });
check('once Alpine starts Simplify the placeholders are gone', started && !started.shown, started);
const stuck = run('', { kind: 'server', seek: true });
check('should the scripts never start, the placeholders fade away by themselves', stuck && !stuck.shown && stuck.fadeEnd > 0 && stuck.fadeEnd <= 7000, stuck);

if (failures.length) {
  console.error('FAIL ' + failures.join('\nFAIL '));
  process.exit(1);
}
