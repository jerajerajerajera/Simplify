// The status page's extras in embed.js, in a real browser: a pinned server that is unreachable longer than the set
// minutes gets a banner and a red card; a planned stop does not; the incident log shows ongoing, resolved and planned
// incidents with the admin's note. A list on another website shows none of it unless it asks (data-incidents="on").
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/embed-incidents.cjs (set CHROME)'); process.exit(1); }

const script = path.join(__dirname, '..', 'resources', 'js', 'embed.js');

function page(attributes, after = 15) {
  return `<!doctype html><html><meta charset="utf-8"><body>
<script>
  const now = Math.floor(Date.now() / 1000);
  const data = { name: 'Test', options: { banner_after: ${after}, incidents: true }, servers: [
    { id: 'a', name: 'Proxy', game: 'Minecraft', state: 'online', pinned: false },
    { id: 'p', name: 'Survival', game: 'Minecraft', state: 'offline', pinned: true },
    { id: 'q', name: 'Creative', game: 'Minecraft', state: 'offline', pinned: true },
  ], incidents: [
    { server: 'p', name: 'Survival', started: now - 35 * 60, ended: null, planned: false, note: 'We are <b>on it</b>.' },
    { server: 'q', name: 'Creative', started: now - 90 * 60, ended: null, planned: true, note: null },
    { server: 'a', name: 'Proxy', started: now - 2 * 86400, ended: now - 2 * 86400 + 18 * 60, planned: false, note: null },
  ] };
  window.fetch = () => Promise.resolve({ ok: true, json: () => Promise.resolve(data) });
</script>
<div id="simplify-servers"></div>
<script src="file://${script}" ${attributes}></script>
<pre id="out"></pre>
<script>setTimeout(() => {
  const root = document.getElementById('simplify-servers').shadowRoot;
  const out = {
    banners: [...root.querySelectorAll('.banner')].map((banner) => banner.textContent),
    bannerPart: root.querySelector('.banner')?.getAttribute('part') ?? null,
    names: [...root.querySelectorAll('.card .name')].map((name) => name.textContent),
    down: [...root.querySelectorAll('.card.down .name')].map((name) => name.textContent),
    downPart: root.querySelector('.card.down')?.getAttribute('part') ?? null,
    tags: [...root.querySelectorAll('.incident .tag')].map((tag) => tag.textContent),
    notes: [...root.querySelectorAll('.incident .note')].map((note) => note.textContent),
    markup: root.querySelectorAll('.incident b, .banner span b').length,
    log: root.querySelector('.log h2')?.textContent ?? null,
  };
  document.getElementById('out').textContent = JSON.stringify(out);
}, 500);</script></body></html>`;
}

function run(html) {
  const file = path.join(os.tmpdir(), `simplify-embed-incidents-${process.pid}-${Math.random().toString(36).slice(2)}.html`);
  fs.writeFileSync(file, html);
  try {
    const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--allow-file-access-from-files', '--lang=en-US', '--virtual-time-budget=2500', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
    const found = dom.match(/<pre id="out"[^>]*>([^<]*)<\/pre>/);
    return found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&')) : null;
  } finally {
    fs.rmSync(file, { force: true });
  }
}

const failures = [];
const check = (name, ok, detail) => { if (ok) console.log('PASS ' + name); else failures.push(name + (detail === undefined ? '' : ' (' + JSON.stringify(detail) + ')')); };

const status = run(page('data-page="1"'));
check('the status page drew', status !== null, status);
if (status) {
  check('a banner for the pinned server that is down 35 minutes', status.banners.length === 1 && status.banners[0].startsWith('Survival is unreachable'), status.banners);
  check('the banner holds the admin\'s note as text', /We are <b>on it<\/b>\.$/.test(status.banners[0] || '') && status.markup === 0, [status.banners, status.markup]);
  check('the banner is a part for own CSS', status.bannerPart === 'banner', status.bannerPart);
  check('its card is red, the planned one not', status.down.join() === 'Survival· Pinned' && status.downPart === 'card card-down', [status.down, status.downPart]);
  check('the pinned servers say so', status.names.filter((name) => name.endsWith('· Pinned')).length === 2, status.names);
  check('the log: ongoing, planned and resolved', status.tags.join() === 'Ongoing,Planned,Resolved', status.tags);
  check('the log names the last 14 days', status.log === 'Incidents · last 14 days', status.log);
}
const early = run(page('data-page="1"', 60));
check('no banner before the set minutes', early !== null && early.banners.length === 0 && early.down.length === 0, early);
const website = run(page(''));
check('a list on another website shows no banner or log', website !== null && website.banners.length === 0 && website.log === null, website);
const asked = run(page('data-incidents="on"'));
check('data-incidents="on" shows them there too', asked !== null && asked.banners.length === 1 && asked.log !== null, asked);

if (failures.length) { console.error('FAIL embed incidents:\n  ' + failures.join('\n  ')); process.exit(1); }
console.log('PASS embed incidents');
