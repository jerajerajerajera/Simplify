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
const css = fs.readFileSync(path.join(__dirname, '..', 'resources', 'css', 'simplify.css'), 'utf8');
const page = `<!doctype html><meta charset="utf-8"><style>:root { --gray-50: #fafafa; --gray-100: #f4f4f5; --gray-200: #e4e4e7; --gray-700: #3f3f46; --gray-800: #27272a; --gray-900: #18181b; --gray-950: #09090b; }</style>
<style>${css.replace(/<\/style/gi, '')}</style><body>
<form id="plain" data-simplify-unsaved onsubmit="event.preventDefault(); window.plainSubmitted = (window.plainSubmitted || 0) + 1"><input id="name" value="Lobby"></form>
<div wire:id="c1"><form id="lw" data-simplify-unsaved="data" onsubmit="event.preventDefault(); Livewire.find('c1').call('save')"><input id="lwname"></form></div>
<div wire:id="c2"><div id="keyform" data-simplify-unsaved="data"><input id="support" type="text" wire:model="data.support"><input id="apikey" type="password" autocomplete="off"></div></div>
<a id="away" href="/elsewhere" wire:navigate>away</a>
<pre id="out"></pre>
<script>
  // A small stand-in for Livewire: one component with a data property, its commit hook and navigate.
  const hooks = [];
  const component = { id: 'c1', snapshot: { memo: { errors: {} } } };
  const state = { data: { name: 'Lobby', max: 20, motd: null } };
  // An add-on's API key field (wire:model): what is typed goes into the component's data.
  const keyState = { data: { key: null, support: 'https://help.example' } };
  document.addEventListener('input', (event) => event.target.id === 'apikey' && (keyState.data.key = event.target.value || null));
  document.addEventListener('input', (event) => event.target.id === 'support' && (keyState.data.support = event.target.value || null));
  // Headless Chrome remembers no passwords: the test says which field the browser filled.
  const matches = Element.prototype.matches;
  Element.prototype.matches = function (selector) { return /autofill/.test(selector) ? this.dataset.autofilled === 'yes' : matches.call(this, selector); };
  window.navigated = [];
  window.Livewire = {
    hook: (name, fn) => name === 'commit' && hooks.push(fn),
    navigate: (url) => window.navigated.push(String(url)),
    find: (id) => (id === 'c2' ? { id: 'c2', __instance: { ephemeral: keyState, canonical: { data: { key: null, support: 'https://help.example' } } }, $get: (name) => name.split('.').reduce((node, part) => node?.[part], JSON.parse(JSON.stringify(keyState))), call: () => {} } : id === 'c1' ? { id: 'c1', __instance: { ephemeral: state }, $get: (name) => JSON.parse(JSON.stringify(state[name])), call: (method) => commit([{ method, params: [] }]) } : null),
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

    // K-B54: the browser fills the admin's saved password into an API key field and shows it on the first click.
    const apikey = document.getElementById('apikey');
    ok('a key field never takes a saved password', apikey.getAttribute('autocomplete') === 'new-password', apikey.getAttribute('autocomplete'));
    apikey.dataset.autofilled = 'yes';
    apikey.value = 'my-panel-password';
    apikey.dispatchEvent(new Event('input', { bubbles: true }));
    await wait(150);
    ok('a password the browser filled is no change', bar() === null && apikey.value === '' && keyState.data.key === null, { bar: bar(), value: apikey.value, key: keyState.data.key });
    // A key someone picks or types in the field itself counts.
    apikey.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    apikey.value = 'cf-key';
    apikey.dispatchEvent(new Event('input', { bubbles: true }));
    await wait(150);
    ok('a key typed in the field is a change', bar() === 'changes' && keyState.data.key === 'cf-key', { bar: bar(), key: keyState.data.key });
    delete apikey.dataset.autofilled;
    apikey.value = '';
    apikey.dispatchEvent(new Event('input', { bubbles: true }));
    await wait(150);
    ok('emptying the key again takes the bar away', bar() === null, bar());
    // K-B59: the same browser fills the admin's user name into the text field before it (the Support link).
    const support = document.getElementById('support');
    support.value = 'https://help.example';
    support.dataset.autofilled = 'yes';
    support.value = 'jeroen';
    support.dispatchEvent(new Event('input', { bubbles: true }));
    await wait(150);
    ok('a user name the browser filled into a text field is no change', bar() === null && support.value === 'https://help.example' && keyState.data.support === 'https://help.example', { bar: bar(), value: support.value, support: keyState.data.support });
    support.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    support.value = 'https://help.example/new';
    support.dispatchEvent(new Event('input', { bubbles: true }));
    await wait(150);
    ok('a link typed in the field is a change', bar() === 'changes', bar());
    delete support.dataset.autofilled;
    support.value = 'https://help.example';
    support.dispatchEvent(new Event('input', { bubbles: true }));
    await wait(150);
    ok('typing it back takes the bar away', bar() === null, bar());
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
    // K-B54: the bar follows the theme, light on light and dark on dark.
    const light = (el) => { const [r, g, b] = getComputedStyle(el).backgroundColor.match(/\\d+/g).map(Number); return (r + g + b) / 3 > 128; };
    await type(name, 'Lobby 3');
    const shownBar = document.querySelector('.s-unsaved');
    const inLight = light(shownBar);
    document.documentElement.classList.add('dark');
    const inDark = light(shownBar);
    document.documentElement.classList.remove('dark');
    ok('the bar is light in the light theme and dark in the dark theme', inLight === true && inDark === false, { inLight, inDark });
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
