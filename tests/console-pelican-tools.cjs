// K-B70: Pelican 1.0.0-rc.3 put the console's output in a wrapper with its own Copy and Download buttons over the top
// right corner, and the command row next to that wrapper. On rc.3 and rc.4 those buttons lay over Simplify's size
// tools (a click on "Open the console in a new window" downloaded the log), the command row fell outside the console's
// card and the server menu was shorter than the console. Both structures are measured here: the output directly in the
// widget (up to rc.2) and in Pelican's wrapper (rc.3 and later). A click test: at the middle of each button of the
// title row the button itself must lie. In a real browser against the real CSS and server.js, without a panel.
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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/console-pelican-tools.cjs (set CHROME)'); process.exit(1); }

const root = path.join(__dirname, '..', 'resources');
const css = path.join(root, 'css');
const inline = (file) => fs.readFileSync(path.join(root, 'js', file), 'utf8').replace(/<\/script/gi, '<\\/script');
// The positioning rules of xterm.css and of Pelican's console.css (rc.4): the wrapper and its tools.
const pelican = `.xterm { position: relative; user-select: none; cursor: text; }
.xterm .xterm-helpers { position: absolute; top: 0; z-index: 5; }
.xterm .xterm-viewport { position: absolute; inset: 0; overflow-y: scroll; }
.xterm .xterm-screen { position: relative; }
.console-wrapper { position: relative; }
.console-tools { position: absolute; top: 6px; right: 22px; z-index: 10; display: flex; gap: 2px; opacity: .5; }
.fi-icon-btn { display: inline-flex; width: 32px; height: 32px; }
#terminal { border-top-left-radius: 10px; border-top-right-radius: 10px; overflow: hidden; }`;
const icon = '<svg class="fi-icon fi-size-md" width="24" height="24" viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/></svg>';
const tool = (id, label) => `<button x-tooltip="{ content: '${label}', theme: $store.theme, allowHTML: false, }" class="fi-icon-btn fi-size-sm" aria-label="${label}" type="button" id="${id}" onclick="window.clicked.push('${id}')">${icon}</button>`;
const terminal = '<div id="terminal"><div class="xterm" style="height: 100%; box-sizing: border-box"><div class="xterm-viewport"><div style="height: 2000px"></div></div><div class="xterm-screen" style="height: 200px"><div class="xterm-helpers"></div></div></div></div>';
// Pelican's console widget: up to rc.2 the output is the widget's child, from rc.3 it sits in .console-wrapper.
const output = {
  'rc.2': terminal,
  'rc.4': `<div class="console-wrapper">${terminal}<div class="console-tools">${tool('console-copy', 'Copy console output')}${tool('console-download', 'Download console output')}</div></div>`,
};
const page = (structure) => `<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="file://${css}/tokens.css"><link rel="stylesheet" href="file://${css}/components.css"><link rel="stylesheet" href="file://${css}/dashboard.css">
<style>${pelican} body { margin: 0; } :root { --ps-console-card: #010203; }</style></head><body><div class="ps server-active ps-server-top is-console"></div>
<nav class="ps-server-menu"></nav>
<main class="fi-main"><div class="fi-page"><div class="fi-page-header-main-ctn">
  <header class="fi-header"><div class="fi-header-actions-ctn"><div class="fi-ac"><button type="button" class="fi-btn" onclick="window.clicked.push('explain')">${icon}<span>Explain</span></button></div></div></header>
  <div class="fi-page-main" style="height: 500px; overflow: auto"><div class="fi-page-content">
    <div id="widget">${output[structure]}
      <div id="row" class="flex items-center w-full" style="display: flex; border-bottom-right-radius: 10px; border-bottom-left-radius: 10px"><svg width="20" height="20"></svg><input id="send-command" type="text"></div>
    </div>
  </div></div></div></div></main>
<template id="bar"><div class="ps-console-title ps-ui"><strong>Live console</strong><span class="ps-console-dot"></span><span class="ps-live-bars" hidden></span>
  <span class="ps-console-state" data-connecting="Connecting" data-connected="Connected">Connecting</span>
  <button type="button" class="ps-console-clear ps-console-tool ps-console-grow" data-normal="Normal" data-bigger="Bigger" aria-label="Bigger"><svg viewBox="0 0 24 24"></svg></button>
  <button type="button" class="ps-console-clear ps-console-tool ps-console-window" aria-label="Window"><svg viewBox="0 0 24 24"></svg></button></div></template>
<pre id="out"></pre>
<script>window.clicked = []; window.Simplify = { t: (key) => key }; window.pelicanSimplify = () => ({});</script>
<script>${inline('server.js')}</script>
<script>
  addEventListener('load', () => {
    const out = {};
    try {
      const server = window.simplifyServer({}, { server: { uuid: 'u1', minecraft: false } });
      Object.assign(server, { $refs: { consoleBar: document.getElementById('bar') }, overviewPreferences: {}, fitPage() {}, setOverviewPreference() {} });
      server.consoleBar(true);
      const main = document.querySelector('.fi-main'), widget = document.getElementById('widget'), row = document.getElementById('row');
      server.fitConsole(500, main);
      const buttons = [...document.querySelectorAll('#terminal > .ps-console-title button')];
      out.labels = buttons.map((el) => el.getAttribute('aria-label'));
      out.own = {};
      for (const btn of buttons) {
        const box = btn.getBoundingClientRect();
        if (!box.width) continue;
        const hit = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
        out.own[btn.getAttribute('aria-label')] = !!hit && btn.contains(hit);
      }
      out.pelicanTools = [...document.querySelectorAll('.console-tools')].map((el) => el.getBoundingClientRect().height);
      buttons.filter((el) => /console output$/.test(el.getAttribute('aria-label'))).forEach((el) => el.click());
      out.clicked = [...window.clicked];
      const style = getComputedStyle(widget);
      out.card = { border: style.borderTopWidth, clip: style.overflow, height: widget.offsetHeight, ground: style.backgroundColor };
      out.wrapperBorder = document.querySelector('.console-wrapper') ? getComputedStyle(document.querySelector('.console-wrapper')).borderTopWidth : '0px';
      out.rowHeight = Math.round(row.getBoundingClientRect().height);
      out.rowInside = row.getBoundingClientRect().bottom <= widget.getBoundingClientRect().bottom && getComputedStyle(row).borderTopWidth === '1px';
      out.menu = document.querySelector('.ps-server-menu').style.height;
    } catch (error) { out.error = String(error); }
    document.getElementById('out').textContent = JSON.stringify(out);
  });
</script></body></html>`;

let failures = 0;
const check = (name, ok, detail) => { console.log(`${ok ? 'PASS' : 'FAIL'} ${name}${ok ? '' : ' ' + JSON.stringify(detail)}`); if (!ok) failures++; };
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'simplify-console-pelican-'));
for (const structure of Object.keys(output)) {
  for (const size of ['1440,900', '390,844']) {
    const file = path.join(dir, 'page.html');
    fs.writeFileSync(file, page(structure));
    const dom = execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--allow-file-access-from-files', `--window-size=${size}`, '--virtual-time-budget=3000', '--dump-dom', 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
    const found = dom.match(/<pre id="out">([\s\S]*?)<\/pre>/);
    const out = found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&')) : null;
    const name = `Pelican ${structure}, ${size}`;
    if (!out || out.error) { check(`${name}: measured`, false, out); continue; }
    const shown = Object.keys(out.own);
    check(`${name}: a click on each button of the console's title row lands on that button`, shown.length > 0 && shown.every((label) => out.own[label]), out.own);
    check(`${name}: the command row is part of the console's card`, out.card.border === '1px' && out.card.clip === 'hidden' && out.wrapperBorder === '0px' && out.rowInside && out.rowHeight >= 62, out);
    // A theme sets the console's colour through its token; the card was fixed to Pelican's #131a20 (K-B95).
    check(`${name}: the console's card takes its colour from --ps-console-card`, out.card.ground === 'rgb(1, 2, 3)', out.card);
    check(`${name}: the server menu is as tall as the console's card`, out.menu === out.card.height + 'px', out);
    if (structure === 'rc.2') continue;
    const copy = out.labels.indexOf('Copy console output'), download = out.labels.indexOf('Download console output'), grow = out.labels.indexOf('Bigger');
    check(`${name}: Pelican's Copy and Download are in the title row, after the add-on's button and before the size tools`, out.labels.indexOf('Explain') < copy && copy < download && download < grow, out.labels);
    check(`${name}: Pelican's own tool row does not show`, out.pelicanTools.every((height) => height === 0), out.pelicanTools);
    check(`${name}: a click on them goes to Pelican's buttons`, out.clicked.includes('console-copy') && out.clicked.includes('console-download'), out.clicked);
  }
}
fs.rmSync(dir, { recursive: true, force: true });
check("without a theme the console's card keeps Pelican's terminal colour (#131a20)", /--ps-console-card:\s*#131a20;/.test(fs.readFileSync(path.join(css, 'tokens.css'), 'utf8')), null);
if (failures) { console.error(`FAIL ${failures} console check(s)`); process.exit(1); }
console.log("PASS the console's title row, command row and menu fit Pelican's console up to rc.2 and from rc.3");
