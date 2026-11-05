// The Health button in the top bar (core.js fitWord, dashboard.css) in a real browser, without a panel: the word stands
// beside the heart when the top bar has room for it, on a phone as on a computer (Khion, Jeroen 5-11); a word or a
// panel name too long for the room folds the button to the heart alone, and the top bar never runs past the window.
// Needs Chrome or Chromium (GitHub's runners have Chrome).
const { spawn } = require('node:child_process');
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/health-pill-fit.cjs (set CHROME)'); process.exit(1); }

const root = path.join(__dirname, '..', 'resources');
const css = path.join(root, 'css');
const core = fs.readFileSync(path.join(root, 'js', 'core.js'), 'utf8').replace(/<\/script/gi, '<\\/script');
const esc = (text) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;');

// The top bar as dashboard/header.blade.php renders it on the overview, with the create button and the bell and account.
const page = (brand, word) => `<!doctype html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/components.css"><link rel="stylesheet" href="file://${css}/dashboard.css">
<style>*,::before,::after{box-sizing:border-box}body{margin:0}</style>
</head><body><div class="ps"><div class="ps-main">
<header class="ps-top">
  <div class="ps-heading"><div class="ps-identity"><a href="#"><h1 class="ps-brand"><span class="ps-panel-name">${esc(brand)}</span><span class="ps-title-pelican">× Pelican</span></h1></a></div></div>
  <nav class="ps-top-actions">
    <div class="ps-top-overview">
      <button type="button" class="ps-btn ps-health-pill healthy"><svg aria-hidden="true"></svg><span>${esc(word)}</span></button>
      <a class="ps-btn ps-create-button is-main" href="#"><span class="ps-create-icon"><svg></svg></span><span>Create server</span></a>
    </div>
    <button type="button" class="ps-btn is-icon is-subtle ps-mobile-menu"><svg></svg></button>
    <div class="ps-nav-items">
      <div class="ps-notify"><button type="button" class="ps-btn is-icon is-subtle"><svg></svg></button></div>
      <a class="ps-btn is-icon is-subtle ps-nav-admin" href="#"><svg></svg></a>
      <div class="ps-account"><button type="button" class="ps-btn ps-account-button"><span style="display:block;width:28px;height:28px;border-radius:50%"></span><span class="ps-account-username">Jeroen</span></button></div>
    </div>
  </nav>
</header></div></div>
<pre id="out" style="position:fixed;top:0;left:0;margin:0;width:0;height:0;overflow:hidden"></pre>
<script>${core}</script>
<script>
  const pill = document.querySelector('.ps-health-pill');
  window.Simplify.fitWord(pill);
  addEventListener('load', () => setTimeout(() => {
    const word = pill.querySelector('span'), top = document.querySelector('.ps-top');
    const measure = () => ({
      word: word.getBoundingClientRect().width > 0 && getComputedStyle(word).visibility !== 'hidden', iconOnly: pill.classList.contains('ps-icon-only'),
      ink: getComputedStyle(word).color, ground: getComputedStyle(pill).backgroundColor,
      wordCut: word.scrollWidth > word.clientWidth + 1, pill: Math.round(pill.getBoundingClientRect().width),
      headingOverflow: (() => { const h = document.querySelector('.ps-heading'); return h.scrollWidth > h.clientWidth + 1; })(),
      topOverflow: top.scrollWidth > top.clientWidth + 1, width: innerWidth, pageScrollX: document.documentElement.scrollWidth - innerWidth,
    });
    const first = measure();
    // Settled: the button no longer switches between the word and the heart (no switching back and forth).
    let flips = 0;
    new MutationObserver(() => flips++).observe(pill, { attributes: true, attributeFilter: ['class'] });
    window.__flips = () => flips;
    // The label changes when the host's state changes (x-text): a short word comes back from the heart alone.
    word.textContent = 'OK';
    setTimeout(() => {
      const short = measure(), settled = flips;
      flips = 0;
      setTimeout(() => { document.getElementById('out').textContent = JSON.stringify({ ...first, short, flipsAfter: flips, settled }); }, 1000);
    }, 600);
  }, 300));
</script></body></html>`;

const cases = [
  ['phone 390, short name, "Alles O.K.": the word shows', '390,844', 'ICE PANEL', 'Alles O.K.', true],
  ['phone 390, "Healthy": the word shows', '390,844', 'Deckzwabbers', 'Healthy', true],
  ['phone 390, long word: the heart alone', '390,844', 'ICE PANEL', 'Teilweise nicht verfügbar', false],
  ['phone 390, long panel name: the heart alone', '390,844', 'Deckzwabbers Gameservers Nederland', 'Alles O.K.', false, false],
  ['phone 320, "Hohe Auslastung": the heart alone', '320,700', 'ICE PANEL', 'Hohe Auslastung', false],
  ['computer 1440, long word: the word shows', '1440,900', 'Deckzwabbers Gameservers', 'Teilweise nicht verfügbar', true],
];

// A browser at the exact width over the DevTools pipe (--window-size stops at 500 wide), so no package is needed.
const run = (html, width, height) => new Promise((resolve, reject) => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-health-fit-'));
  const file = path.join(dir, 'page.html');
  fs.writeFileSync(file, html);
  const browser = spawn(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--hide-scrollbars', '--remote-debugging-pipe', '--user-data-dir=' + path.join(dir, 'profile'), 'about:blank'], { stdio: ['ignore', 'ignore', 'ignore', 'pipe', 'pipe'] });
  const [, , , input, output] = browser.stdio;
  let id = 0, buffer = '', session = null;
  const waiting = new Map();
  const send = (method, params = {}) => new Promise((done) => { waiting.set(++id, done); input.write(JSON.stringify({ id, method, params, ...(session ? { sessionId: session } : {}) }) + '\0'); });
  output.on('data', (chunk) => {
    buffer += chunk;
    for (let end; (end = buffer.indexOf('\0')) >= 0; buffer = buffer.slice(end + 1)) {
      const message = JSON.parse(buffer.slice(0, end));
      if (waiting.has(message.id)) { waiting.get(message.id)(message.result ?? {}); waiting.delete(message.id); }
    }
  });
  const finish = (result) => {
    clearTimeout(timer);
    browser.once('exit', () => { try { fs.rmSync(dir, { recursive: true, force: true, maxRetries: 10, retryDelay: 100 }); } catch {} resolve(result); });
    session = null;
    send('Browser.close');
    setTimeout(() => browser.kill(), 2000);
  };
  const timer = setTimeout(() => finish(null), 30000);
  browser.on('error', reject);
  (async () => {
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
    session = (await send('Target.attachToTarget', { targetId, flatten: true })).sessionId;
    await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
    await send('Page.navigate', { url: 'file://' + file });
    for (let tries = 0; tries < 200; tries++) {
      const { result } = await send('Runtime.evaluate', { expression: "document.getElementById('out')?.textContent || ''", returnByValue: true });
      if (result?.value) return finish(JSON.parse(result.value));
      await new Promise((done) => setTimeout(done, 50));
    }
    finish(null);
  })().catch(reject);
});

// WCAG contrast of the word on the tinted button: rgb(…) or color(srgb …) as Chrome reports a color-mix().
const channels = (text) => { const n = (text.match(/[\d.]+/g) || []).map(Number); return /^color\(/.test(text) ? n.slice(0, 3).map((v) => v * 255) : n.slice(0, 3); };
const luminance = (rgb) => { const [r, g, b] = rgb.map((v) => { v /= 255; return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
const contrast = (a, b) => { const [x, y] = [luminance(channels(a)), luminance(channels(b))].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
(async () => {
  for (const [name, size, brand, word, wordShows, shortShows = true] of cases) {
    const [width, height] = size.split(',').map(Number);
    const r = await run(page(brand, word), width, height);
    if (!r) { check(`${name} (measured)`, false, null); continue; }
    const fits = wordShows ? r.word && !r.iconOnly && !r.wordCut : !r.word && r.iconOnly;
    if (r.word) check(`${name}; the word reads on the button (4.5:1)`, contrast(r.ink, r.ground) >= 4.5, { ink: r.ink, ground: r.ground, ratio: contrast(r.ink, r.ground).toFixed(2) });
    check(`${name}; the button stays put (no word/heart loop)`, r.flipsAfter === 0, { flipsAfter: r.flipsAfter, settled: r.settled });
    check(name, r.width === width && fits && !r.topOverflow && !r.headingOverflow && r.pageScrollX <= 0, r);
    if (shortShows) check(`${name}; a short word afterwards shows`, r.short.word && !r.short.iconOnly && !r.short.topOverflow, r.short);
    else check(`${name}; a short word afterwards: still the heart, the name ends in "…" within its room`, r.short.iconOnly && !r.short.headingOverflow && !r.short.topOverflow, r.short);
  }
  if (failures) { console.error(`${failures} health button check(s) failed`); process.exit(1); }
  console.log('Health button: the word when it fits, else the heart.');
})();
