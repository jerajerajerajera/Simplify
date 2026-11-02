// Unsaved changes (P-38, resources/js/unsaved.js) in a real browser, without a panel: a plain form and a Livewire
// form (a small stand-in for Livewire) get the "Changes made" bar, saving takes it away, a failed save says so, and
// leaving with changes asks Save, Discard or Cancel. Needs Chrome or Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/unsaved.cjs (set CHROME)'); process.exit(1); }

const script = fs.readFileSync(path.join(__dirname, '..', 'resources', 'js', 'unsaved.js'), 'utf8');
const page = `<!doctype html><meta charset="utf-8"><body>
<form id="plain" data-simplify-unsaved onsubmit="event.preventDefault(); window.plainSubmitted = (window.plainSubmitted || 0) + 1"><input id="name" value="Lobby"></form>
<div wire:id="c1"><form id="lw" data-simplify-unsaved="data" onsubmit="event.preventDefault(); Livewire.find('c1').call('save')"><input id="lwname"></form></div>
<a id="away" href="/elsewhere" wire:navigate>away</a>
<pre id="out"></pre>
<script>
  // A small stand-in for Livewire: one component with a data property, its commit hook and navigate.
  const hooks = [];
  const component = { id: 'c1', snapshot: { memo: { errors: {} } } };
  const state = { data: { name: 'Lobby', max: 20, motd: null } };
  window.navigated = [];
  window.Livewire = {
    hook: (name, fn) => name === 'commit' && hooks.push(fn),
    navigate: (url) => window.navigated.push(String(url)),
    find: (id) => (id === 'c1' ? { id: 'c1', __instance: { ephemeral: state }, $get: (name) => JSON.parse(JSON.stringify(state[name])), call: (method) => commit([{ method, params: [] }]) } : null),
  };
  window.commit = (calls, errors = {}) => {
    const done = [];
    hooks.forEach((fn) => fn({ component, commit: { calls }, succeed: (cb) => done.push(cb), fail: () => {} }));
    component.snapshot.memo.errors = errors;
    setTimeout(() => done.forEach((cb) => cb()), 5);
  };
</script>
<script>${script.replace(/<\/script/gi, '<\\/script')}</script>
<script>
  const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const bar = () => { const el = document.querySelector('.s-unsaved'); return el && !el.hidden ? el.dataset.mode : null; };
  const dialog = () => !!document.querySelector('.s-unsaved-dialog');
  const type = async (el, value) => { el.value = value; el.dispatchEvent(new Event('input', { bubbles: true })); await wait(150); };
  const results = [];
  const ok = (name, pass, seen) => results.push((pass ? 'PASS ' : 'FAIL ') + name + (pass ? '' : ' ' + JSON.stringify(seen)));
  (async () => {
    await wait(100);
    ok('no bar while nothing changed', bar() === null, bar());
    const name = document.getElementById('name');
    await type(name, 'Lobby 2');
    ok('a change shows Changes made', bar() === 'changes', bar());
    ok('the bar has Discard and Save', [...document.querySelectorAll('.s-unsaved .s-unsaved-btn')].map((b) => b.textContent).join('|') === 'Discard|Save', document.querySelector('.s-unsaved')?.innerText);
    await type(name, 'Lobby');
    ok('setting it back takes the bar away', bar() === null, bar());

    // The Livewire form: what counts is the component's data, not its fields.
    state.data.max = 30;
    document.getElementById('lwname').dispatchEvent(new Event('input', { bubbles: true }));
    await wait(150);
    ok('a Livewire change shows Changes made', bar() === 'changes', bar());
    // Livewire starts wire:navigate on mousedown: with changes that press must not reach the link.
    let pressed = 0;
    document.getElementById('away').addEventListener('mousedown', () => pressed++);
    document.getElementById('away').dispatchEvent(new MouseEvent('mousedown', { bubbles: true, button: 0 }));
    ok('a press on a wire:navigate link does not start Livewire', pressed === 0, pressed);
    document.getElementById('away').click();
    await wait(50);
    ok('leaving with changes asks first', dialog() && window.navigated.length === 0, { dialog: dialog(), navigated: window.navigated });
    document.querySelector('.s-unsaved-dialog .s-unsaved-btn').click(); // Cancel
    await wait(50);
    ok('Cancel stays on the page with the change', !dialog() && window.navigated.length === 0 && bar() === 'changes', { dialog: dialog(), navigated: window.navigated, bar: bar() });

    // Typed back to what it was (a number field gives text, an emptied field gives ''): no change any more.
    state.data.max = '20';
    state.data.motd = '';
    document.getElementById('lwname').dispatchEvent(new Event('input', { bubbles: true }));
    await wait(150);
    ok('typed back to what it was is no change', bar() === null, bar());
    state.data.max = 30;
    state.data.motd = null;
    document.getElementById('lwname').dispatchEvent(new Event('input', { bubbles: true }));
    await wait(150);
    // A save that fails keeps the bar and says so; one that works takes it away.
    commit([{ method: 'save', params: [] }], { max: ['too big'] });
    await wait(150);
    ok('a failed save says so', bar() === 'failed', bar());
    document.querySelector('.s-unsaved .is-primary').click(); // Save in the bar calls the method
    component.snapshot.memo.errors = {};
    await wait(150);
    ok('Save in the bar saves and the bar goes', bar() === null, bar());

    // The Save button in Filament's header is an action named save.
    state.data.max = 40;
    document.getElementById('lwname').dispatchEvent(new Event('input', { bubbles: true }));
    await wait(150);
    commit([{ method: 'mountAction', params: ['save'] }]);
    await wait(150);
    ok("the header's Save action counts as a save", bar() === null, bar());

    // A commit that saves nothing (a poll) keeps the change.
    state.data.max = 50;
    document.getElementById('lwname').dispatchEvent(new Event('input', { bubbles: true }));
    await wait(150);
    commit([{ method: '$refresh', params: [] }]);
    await wait(150);
    ok('a poll keeps the change', bar() === 'changes', bar());

    // Leaving and saving first: saves, then goes.
    document.getElementById('away').click();
    await wait(50);
    document.querySelector('.s-unsaved-dialog .is-primary').click();
    await wait(200);
    ok('Save in the question saves and then leaves', window.navigated.length === 1 && bar() === null, { navigated: window.navigated, bar: bar() });
    document.getElementById('out').textContent = results.join('\\n') + '\\nDONE';
  })().catch((error) => (document.getElementById('out').textContent = 'FAIL ' + error.stack + '\\nDONE'));
</script>`;

const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-unsaved-'));
const file = path.join(dir, 'page.html');
fs.writeFileSync(file, page);
let dom = '';
try {
  dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--virtual-time-budget=10000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
} finally {
  fs.rmSync(dir, { recursive: true, force: true });
}
const out = (dom.match(/<pre id="out">([\s\S]*?)<\/pre>/) || [])[1] || '';
const lines = out.replace(/&quot;/g, '"').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').split('\n').filter(Boolean);
if (!lines.includes('DONE')) { console.error('FAIL unsaved.js did not finish in the browser'); console.error(lines.join('\n')); process.exit(1); }
const tests = lines.filter((line) => line !== 'DONE');
tests.forEach((line) => console.log(line));
if (tests.some((line) => line.startsWith('FAIL'))) process.exit(1);
