// The browser line in a beta's footer (resources/js/core.js, dashboard.js enhanceCopyright) in a real browser, without
// a panel: after a pre-release version (2.0.0-beta.1) the copyright line names the browser, system, window, app or
// browser, and light or dark, so a screenshot shows where it was taken (Jeroen 3-11); a stable version shows none.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/beta-footer.cjs (set CHROME)'); process.exit(1); }

const js = path.join(__dirname, '..', 'resources', 'js');
const page = (version, dark) => `<!doctype html><html${dark ? ' class="dark"' : ''}><meta charset="utf-8"><body>
<script>window.simplifyLang = { dashboard: { enhanced_by: 'Enhanced by', build: 'Build :version' } }; try { localStorage.setItem('pelicanSimplify.theme', '${dark ? 'dark' : 'light'}'); } catch {}</script>
<script src="file://${js}/core.js"></script><script src="file://${js}/dashboard.js"></script>
<footer><p>&copy; 2026 Pelican</p></footer>
<pre id="out"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    const app = window.pelicanSimplify({}, { build_version: '${version}' });
    app.enhanceCopyright.call({ boot: { build_version: '${version}' } });
    document.getElementById('out').textContent = JSON.stringify(document.querySelector('[data-ps-copyright-line]')?.textContent || '');
  }, 200));
</script></body></html>`;

const cases = [
  ['Chrome on Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', ' · Chrome 131 · Windows · '],
  ['Edge on Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0', ' · Edge 131 · Windows · '],
  ['Safari on iPhone', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1', ' · Safari 18 · iOS 18 · '],
  ['Samsung on Android', 'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0.0.0 Mobile Safari/537.36', ' · Samsung 26 · Android 14 · '],
  ['Firefox on Linux', 'Mozilla/5.0 (X11; Linux x86_64; rv:132.0) Gecko/20100101 Firefox/132.0', ' · Firefox 132 · Linux · '],
  ['Safari on macOS', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15', ' · Safari 18 · macOS · '],
];

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-beta-footer-'));
const run = (name, version, ua, dark) => {
  const file = path.join(dir, `${name.replace(/\W+/g, '-')}-${version}-${dark}.html`);
  fs.writeFileSync(file, page(version, dark));
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--allow-file-access-from-files', `--user-agent=${ua}`, '--window-size=1200,800', '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const found = dom.match(/<pre id="out">([\s\S]*?)<\/pre>/);
  return found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')) : '';
};

for (const [name, ua, expected] of cases) {
  const line = run(name, '2.0.0-beta.1', ua, name.includes('iPhone'));
  const theme = name.includes('iPhone') ? 'Dark' : 'Light';
  check(`beta footer names ${name}`, line.includes('2.0.0-beta.1' + expected) && / · \d+×\d+ @[\d.]+x · Browser · /.test(line) && line.endsWith(' · ' + theme), { line });
}
const stable = run('stable', '2.0.0', cases[0][1], false);
check('stable footer shows no browser line', stable.endsWith('2.0.0') && !/Chrome|Windows|×/.test(stable), { line: stable });

fs.rmSync(dir, { recursive: true, force: true });
if (failures) process.exit(1);
