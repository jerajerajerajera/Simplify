// Pelican's console page loads its widgets late (Filament's lazy widgets): until a widget is there, Livewire shows an
// empty card of 8rem (`.fi-loading-section`) in its place. Simplify replaces Pelican's numbers and charts with its own
// cards, so on the console page none of those placeholders may show: the numbers' card stood above the console for a
// moment and the console jumped down and up again (video Jeroen 3-11, Beelink, 2.0.0-beta.3). In a real browser
// against resources/css/dashboard.css, without a panel. Needs Chrome or Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/console-placeholders.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
// The widgets of Pelican's console page (app/Filament/Server/Pages/Console.php), in its order, as Livewire names them
// (Filament registers a widget under its class name). Only the console itself is not lazy.
const lazy = ['ServerOverview', 'ServerCpuChart', 'ServerMemoryChart', 'ServerNetworkChart'];
const placeholder = (widget) => `<div role="status" aria-busy="true" class="fi-section fi-loading-section" style="height: 8rem" wire:id="${widget}" wire:name="App\\Filament\\Server\\Widgets\\${widget}"><span class="fi-sr-only">Loading...</span></div>`;
const page = (console) => `<!doctype html><html><meta charset="utf-8">
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/components.css"><link rel="stylesheet" href="file://${css}/dashboard.css">
<body><div class="ps server-active ps-server-top${console ? ' is-console' : ''}"></div>
<main class="fi-main"><div class="fi-page-content">
${placeholder(lazy[0])}
<div wire:id="console" wire:name="App\\Filament\\Server\\Widgets\\ServerConsole"><div id="console-card" style="height: 300px"></div></div>
${lazy.slice(1).map(placeholder).join('\n')}
</div></main>
<pre id="out"></pre>
<script>
  addEventListener('load', () => {
    const shown = {};
    document.querySelectorAll('[wire\\\\:name]').forEach((el) => { shown[el.getAttribute('wire:id')] = el.getBoundingClientRect().height > 0; });
    document.getElementById('out').textContent = JSON.stringify(shown);
  });
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-console-placeholders-'));
const run = (console) => {
  const file = path.join(dir, `page-${console}.html`);
  fs.writeFileSync(file, page(console));
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--allow-file-access-from-files', '--window-size=1440,900', '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const found = dom.match(/<pre id="out">([\s\S]*?)<\/pre>/);
  return found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')) : {};
};

const onConsole = run(true);
for (const widget of lazy) check(`console page hides the loading card of Pelican's ${widget}`, onConsole[widget] === false, onConsole);
check('console page keeps Pelican\'s console', onConsole.console === true, onConsole);
// Off the console page Simplify leaves Pelican's widgets alone (another plugin's page may show them).
const elsewhere = run(false);
check('other server pages leave Pelican\'s loading cards alone', lazy.every((widget) => elsewhere[widget] === true), elsewhere);

fs.rmSync(dir, { recursive: true, force: true });
if (failures) process.exit(1);
