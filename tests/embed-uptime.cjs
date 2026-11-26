// The website list (resources/js/embed.js) in a real browser, with a list as servers.json gives it: a software logo
// keeps its shape (class logo), and the uptime shows how long a server is online or offline, its share of the last
// 30 days and one bar per day in the right colour (green from 99%, yellow from 90%, red below, grey without data).
// data-uptime="off" leaves it out. Needs Chrome or Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/embed-uptime.cjs (set CHROME)'); process.exit(1); }

const script = path.join(__dirname, '..', 'resources', 'js', 'embed.js');
const logo = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');

function page(attributes) {
  return `<!doctype html><html><meta charset="utf-8"><body>
<script>
  const now = Math.floor(Date.now() / 1000);
  const days = Array(30).fill(100); days[0] = null; days[1] = null; days[28] = 95; days[29] = 80;
  const data = { name: 'Test', options: {}, servers: [
    { id: 'a', name: 'Survival', game: 'Minecraft', state: 'online', icon: ${JSON.stringify(logo)}, logo: true,
      uptime: { since: now - (3 * 86400 + 4 * 3600 + 60), percent: 99.4, days } },
    { id: 'b', name: 'Proxy', game: 'Minecraft', state: 'offline', icon: null, logo: false,
      uptime: { since: now - (2 * 3600 + 15 * 60 + 5), percent: 96.1, days: Array(30).fill(100) } },
  ] };
  window.fetch = () => Promise.resolve({ ok: true, json: () => Promise.resolve(data) });
</script>
<div id="simplify-servers"></div>
<script src="file://${script}" ${attributes}></script>
<pre id="out"></pre>
<script>setTimeout(() => {
  const root = document.getElementById('simplify-servers').shadowRoot;
  const cards = [...root.querySelectorAll('.card')].map((card) => ({
    logo: !!card.querySelector('img.icon.logo'),
    head: card.querySelector('.uptime-head span')?.textContent ?? null,
    percent: card.querySelector('.uptime-head b')?.textContent ?? null,
    bars: [...card.querySelectorAll('.days i')].map((bar) => bar.className),
    tip: card.querySelector('.days i:last-child')?.title ?? null,
    state: getComputedStyle(card.querySelector('.state')).color,
    bad: getComputedStyle(root.querySelector('.list')).getPropertyValue('--bad').trim(),
    none: getComputedStyle(root.querySelector('.list')).getPropertyValue('--none').trim(),
    parts: ['card', 'name', 'state', 'uptime'].filter((part) => (part === 'card' ? card : card.querySelector('[part~="' + part + '"]'))?.getAttribute('part')?.split(' ').includes(part)),
  }));
  document.getElementById('out').textContent = JSON.stringify(cards);
}, 500);</script></body></html>`;
}

function run(html) {
  const file = path.join(os.tmpdir(), `simplify-embed-uptime-${process.pid}-${Math.random().toString(36).slice(2)}.html`);
  fs.writeFileSync(file, html);
  try {
    const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--allow-file-access-from-files', '--lang=en-US', '--virtual-time-budget=2500', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
    const found = dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/);
    return found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')) : null;
  } finally {
    fs.rmSync(file, { force: true });
  }
}

const failures = [];
const check = (name, ok, detail) => { if (ok) console.log('PASS ' + name); else failures.push(name + (detail === undefined ? '' : ' (' + JSON.stringify(detail) + ')')); };

const cards = run(page(''));
check('two cards drawn', Array.isArray(cards) && cards.length === 2, cards);
if (Array.isArray(cards) && cards.length === 2) {
  const [a, b] = cards;
  check('a software logo keeps its shape', a.logo === true && b.logo === false, [a.logo, b.logo]);
  check('online for days and hours', a.head === 'Online for 3 d 4 h', a.head);
  check('offline for hours and minutes', b.head === 'Offline for 2 h 15 min', b.head);
  check('the share of the last 30 days', a.percent === '99.4% uptime', a.percent);
  check('offline is red, like a day it was down', b.state === 'rgb(220, 38, 38)' && b.bad === '#dc2626' && a.state !== b.state, [a.state, b.state, b.bad]);
  check('the status page\'s own CSS reaches the card, name, state and uptime as parts', a.parts.length === 4, a.parts);
  check('a day without data stays grey', a.none === '#e4e4e7', a.none);
  check('one bar per day', a.bars.length === 30, a.bars.length);
  check('a day without data is grey', a.bars[0] === '' && a.bars[1] === '', a.bars.slice(0, 2));
  check('green from 99%, yellow from 90%, red below', a.bars[2] === 'up' && a.bars[28] === 'some' && a.bars[29] === 'down', a.bars.slice(27));
  check('a day says its share', /80%$/.test(a.tip || ''), a.tip);
}
const off = run(page('data-uptime="off"'));
check('data-uptime="off" leaves the uptime out', Array.isArray(off) && off.length === 2 && off.every((card) => card.bars.length === 0 && card.head === null), off);

if (failures.length) { console.error('FAIL embed uptime:\n  ' + failures.join('\n  ')); process.exit(1); }
console.log('PASS embed uptime');
