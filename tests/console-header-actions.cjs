// Add-on buttons in a page's header that Simplify hides (Jeroen via Explain, 3-11): on Pelican's Console page the header
// is hidden (dashboard.css), so its add-on buttons (Explain) come into the console's title row and a click goes to the
// hidden button, while Pelican's own power buttons stay out; in host mode's frame a button that an add-on puts in the
// header-actions render hook comes into the title bar too, and the modal it opens still shows (frame.js, simplify.css).
// In a real browser, without a panel. Needs Chrome or Chromium (GitHub's runners have Chrome).
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/console-header-actions.cjs (set CHROME)'); process.exit(1); }

const root = path.join(__dirname, '..', 'resources');
const css = path.join(root, 'css');
const inline = (file) => fs.readFileSync(path.join(root, 'js', file), 'utf8').replace(/<\/script/gi, '<\\/script');
const icon = '<svg class="fi-icon fi-size-md" viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/></svg>';
// Filament's header as Pelican's Console page renders it: the power buttons (an ActionGroup as button group), an add-on's
// header action (registerCustomHeaderActions) and an add-on's button in the header-actions render hook, with its modal.
const header = (dropdown = true) => `<header class="fi-header"><div><h1 class="fi-header-heading">Console</h1></div><div class="fi-header-actions-ctn">
  <div wire:id="hook"><button type="button" class="fi-btn" id="hook-source" wire:click="mountAction('simplify-explain-panel')" onclick="window.clicked.push('hook')">${icon}<span>Explain logs</span></button>
    <div class="fi-modal" id="hook-modal"><div class="fi-modal-window" style="position:fixed;inset:40px;background:#fff">Explanation</div></div></div>
  <div class="fi-ac">
    <div class="fi-btn-group"><button type="button" class="fi-btn" wire:click="mountAction('start')">Start</button><button type="button" class="fi-btn" wire:click="mountAction('restart')">Restart</button><button type="button" class="fi-btn fi-color-danger" wire:click="mountAction('stop')">Stop</button></div>
    <button type="button" class="fi-btn" id="action-source" wire:click="mountAction('simplify-explain')" onclick="window.clicked.push('action')">${icon}<span>Explain</span></button>
    ${dropdown ? '<div class="fi-dropdown"><button type="button" class="fi-icon-btn">More</button></div>' : ''}
  </div></div></header>`;
const run = (file, html, size = '1440,900') => {
  fs.writeFileSync(file, html);
  const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--allow-file-access-from-files', `--window-size=${size}`, '--virtual-time-budget=4000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  const found = dom.match(/<pre id="out"[^>]*>([\s\S]*?)<\/pre>/);
  return found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>')) : null;
};

const consolePage = `<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/components.css"><link rel="stylesheet" href="file://${css}/dashboard.css">
</head><body><div class="ps server-active ps-server-top is-console"></div>
<main class="fi-main"><div class="fi-page"><div class="fi-page-header-main-ctn">${header()}<div class="fi-page-content"><div><div id="terminal" style="height:300px"></div></div></div></div></div></main>
<template id="bar"><div class="ps-console-title ps-ui" data-quick="Quick commands"><strong>Live console</strong><span class="ps-console-dot"></span><span class="ps-live-bars" hidden></span>
  <span class="ps-console-state" data-connecting="Connecting" data-connected="Connected">Connecting</span>
  <button type="button" class="ps-console-clear ps-console-tool ps-console-grow" data-normal="Normal" data-bigger="Bigger"><svg></svg></button>
  <button type="button" class="ps-console-clear ps-console-tool ps-console-window"><svg></svg></button></div></template>
<pre id="out"></pre>
<script>window.clicked = []; window.Simplify = { t: (key) => key }; window.pelicanSimplify = () => ({});</script>
<script>${inline('server.js')}</script>
<script>
  const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  (async () => {
    const out = {};
    try {
      const page = window.simplifyServer({}, { server: { uuid: 'u1', minecraft: false } });
      Object.assign(page, { $refs: { consoleBar: document.getElementById('bar') }, overviewPreferences: {}, fitPage() {}, quickCommands() {}, setOverviewPreference() {} });
      page.consoleBar(true);
      const tools = () => [...document.querySelectorAll('#terminal > .ps-console-title button')];
      out.labels = tools().map((el) => el.getAttribute('aria-label') || el.className);
      const bridged = tools().filter((el) => /^Explain/.test(el.getAttribute('aria-label') || ''));
      out.visible = bridged.map((el) => el.getBoundingClientRect().width > 0);
      out.icon = bridged.map((el) => Math.round(el.querySelector('svg')?.getBoundingClientRect().width || 0));
      out.headerHidden = document.querySelector('.fi-header').getBoundingClientRect().height === 0;
      bridged.forEach((el) => el.click());
      out.clicked = [...window.clicked];
      document.getElementById('action-source').setAttribute('disabled', '');
      await wait(50);
      out.disabled = tools().find((el) => el.getAttribute('aria-label') === 'Explain')?.disabled ?? null;
    } catch (error) { out.error = String(error); }
    document.getElementById('out').textContent = JSON.stringify(out);
  })();
</script></body></html>`;

const framed = `<!doctype html><html class="ps-frame" data-ps-frame="native"><head><meta charset="utf-8">
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/components.css"><link rel="stylesheet" href="file://${css}/dashboard.css"><link rel="stylesheet" href="file://${css}/simplify.css">
</head><body><main class="fi-main"><div class="fi-page"><div class="fi-page-header-main-ctn">${header(false)}<div class="fi-page-main">Logs</div></div></div></main>
<script>window.clicked = []; parent.frameClicked = window.clicked;</script>
<script>${inline('frame.js')}</script></body></html>`;
const hostPage = (frameFile) => `<!doctype html><html><head><meta charset="utf-8"></head><body>
<div class="ps-host-main"><div class="ps-host-head is-page">Logs</div><iframe id="frame" src="file://${frameFile}" style="width:1000px;height:700px"></iframe></div>
<pre id="out"></pre>
<script>
  addEventListener('load', () => setTimeout(() => {
    const out = {};
    try {
      const doc = document.getElementById('frame').contentDocument;
      const tools = [...document.querySelectorAll('.ps-frame-tools button')];
      out.labels = tools.map((el) => el.getAttribute('aria-label'));
      tools.filter((el) => /^Explain/.test(el.getAttribute('aria-label'))).forEach((el) => el.click());
      out.clicked = [...(window.frameClicked || [])];
      const source = doc.getElementById('hook-source');
      out.sourceHidden = source.getClientRects().length === 0 || getComputedStyle(source).visibility === 'hidden';
      const modal = doc.querySelector('#hook-modal .fi-modal-window');
      out.modalShows = modal.getClientRects().length > 0 && doc.defaultView.getComputedStyle(modal).visibility === 'visible' && modal.getBoundingClientRect().width > 100;
    } catch (error) { out.error = String(error); }
    document.getElementById('out').textContent = JSON.stringify(out);
  }, 800));
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-header-actions-'));

const c = run(path.join(dir, 'console.html'), consolePage);
if (!c || c.error) check('console page measured', false, c);
else {
  check('console page: the header stays hidden', c.headerHidden, c);
  check("console page: the add-on's header action and render-hook button are in the console's title row", c.labels.includes('Explain') && c.labels.includes('Explain logs'), c);
  check('console page: they sit before the size tools', c.labels.indexOf('Explain') < c.labels.findIndex((l) => /ps-console-grow/.test(l)) && c.labels.indexOf('Explain logs') < c.labels.findIndex((l) => /ps-console-grow/.test(l)), c);
  check("console page: Pelican's power buttons and dropdowns stay out", !c.labels.some((l) => /^(Start|Restart|Stop|More)$/.test(l)), c);
  check('console page: the buttons show, with a small icon', c.visible.length === 2 && c.visible.every(Boolean) && c.icon.every((w) => w === 14), c);
  check('console page: a click goes to the hidden button', c.clicked.includes('action') && c.clicked.includes('hook'), c);
  check('console page: a disabled header action is disabled in the title row', c.disabled === true, c);
}

const frameFile = path.join(dir, 'frame.html');
fs.writeFileSync(frameFile, framed);
const f = run(path.join(dir, 'host.html'), hostPage(frameFile));
if (!f || f.error) check('frame measured', false, f);
else {
  check("frame: the render hook's button is in host mode's title bar", f.labels.includes('Explain logs'), f);
  check("frame: the header action is there too, Pelican's power buttons as before", f.labels.includes('Explain') && !f.labels.some((l) => /^(Start|Restart|Stop)$/.test(l)), f);
  check('frame: a click goes to the hidden button', f.clicked.includes('hook'), f);
  check("frame: the hook's own button is hidden in the page", f.sourceHidden, f);
  check("frame: the hook's modal still shows", f.modalShows, f);
}
fs.rmSync(dir, { recursive: true, force: true });
if (failures) { console.error(`FAIL ${failures} header action check(s)`); process.exit(1); }
console.log('PASS add-on buttons in a hidden header stay reachable');
