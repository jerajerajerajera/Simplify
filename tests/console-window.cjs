// K-B74: "Open the console in a new window" opened an empty white window. The window is 960px wide and its page
// carries the class ps-console-window on <html>; the rule that hides the pop-out button (which has the same class) in
// windows up to 960px hid the whole page with it. Once it showed, the console kept Pelican's own height (30 rows), so
// the window scrolled and the command row lay under its edge. In a real browser against resources/css/dashboard.css
// and server.js, without a panel: the page of the console window shows at 960px with the console filling the window,
// and the button is still hidden there. Needs Chrome or Chromium.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/console-window.cjs (set CHROME)'); process.exit(1); }

const css = path.join(__dirname, '..', 'resources', 'css');
const script = fs.readFileSync(path.join(__dirname, '..', 'resources', 'js', 'server.js'), 'utf8').replace(/<\/script/gi, '<\\/script');
const page = (popup) => `<!doctype html><html${popup ? ' class="ps-console-window"' : ''}><head><meta charset="utf-8">
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/components.css"><link rel="stylesheet" href="file://${css}/dashboard.css">
<style>body { margin: 0; }</style></head><body><div class="fi-layout"><div class="fi-main-ctn">
<div class="ps server-active ps-server-top is-console"><div class="ps-main" style="height: 280px"></div></div>
<main class="fi-main"><div class="fi-page"><div class="fi-page-header-main-ctn"><div class="fi-page-main"><div class="fi-page-content"><div>
<div class="console-wrapper"><div id="terminal" style="height: 716px">
  <div class="ps-console-title ps-ui"><strong>Live console</strong>
    <button type="button" class="ps-console-clear ps-console-tool ps-console-grow" aria-label="Bigger"><svg viewBox="0 0 24 24"></svg></button>
    <button type="button" class="ps-console-clear ps-console-tool ps-console-window" aria-label="Window"><svg viewBox="0 0 24 24"></svg></button></div>
</div></div>
<div id="row" style="display: flex"><input id="send-command" type="text"></div>
</div></div></div></div></div></main></div></div>
<script>window.Simplify = { t: (key) => key }; window.pelicanSimplify = () => ({});</script>
<script>${script}</script>
<script>
  addEventListener('load', () => {
    const size = (el) => { const box = el.getBoundingClientRect(); return [Math.round(box.width), Math.round(box.height)]; };
    const out = { page: getComputedStyle(document.documentElement).display };
    try { window.simplifyServer({}, { server: { uuid: 'u1', minecraft: false } }).fitPage(); } catch (error) { out.error = String(error); }
    Object.assign(out, { terminal: size(document.getElementById('terminal')), button: size(document.querySelector('button.ps-console-window')), fit: document.documentElement.classList.contains('ps-fit'), row: Math.round(document.getElementById('row').getBoundingClientRect().bottom), window: innerHeight });
    document.title = JSON.stringify(out);
  });
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-console-window-'));
const measure = (popup, size) => {
  const file = path.join(dir, 'page.html');
  fs.writeFileSync(file, page(popup));
  // The result travels in the title: a hidden page has no text to dump.
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--allow-file-access-from-files', `--window-size=${size}`, '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const found = dom.match(/<title>([\s\S]*?)<\/title>/);
  return found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')) : null;
};
const popup = measure(true, '960,640');
check('the console window (960px wide) shows its page and the console', !!popup && popup.page !== 'none' && popup.terminal[0] > 300 && popup.terminal[1] > 100, popup);
check('the console fills the console window: it gets the window\'s height and the command row is inside the window', !!popup && !popup.error && popup.fit && popup.terminal[1] < 716 && popup.row > 0 && popup.row <= popup.window, popup);
check('the console window has no button to open another window', !!popup && popup.button[0] === 0, popup);
const narrow = measure(false, '960,640'), wide = measure(false, '1440,900');
check('a server page up to 960px wide has no pop-out button, a wider one has it', !!narrow && !!wide && narrow.button[0] === 0 && wide.button[0] > 0 && narrow.page !== 'none', { narrow, wide });
fs.rmSync(dir, { recursive: true, force: true });
if (failures) { console.error(`FAIL ${failures} console window check(s)`); process.exit(1); }
console.log('PASS the console in its own window shows the console');
