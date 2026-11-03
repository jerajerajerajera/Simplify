// The preview of the saved list in Website embed (resources/views/embed/code.blade.php) in a real browser, without a
// panel: in a dark panel the empty part of the preview is the panel's own background, never white, and the preview is
// only as big as the cards (one card: one card wide; more cards: the rows they fill). Also the iframe code for other
// websites keeps its own background on a dark website. Needs Chrome or Chromium (GitHub's runners have Chrome).
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const zlib = require('node:zlib');

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
if (!chrome) { console.error('FAIL no Chrome or Chromium found for tests/embed-preview.cjs (set CHROME)'); process.exit(1); }

const root = path.join(__dirname, '..');
const blade = fs.readFileSync(path.join(root, 'resources', 'views', 'embed', 'code.blade.php'), 'utf8');
const controller = fs.readFileSync(path.join(root, 'src', 'Embed', 'EmbedController.php'), 'utf8');
const snippet = fs.readFileSync(path.join(root, 'src', 'Embed', 'Services', 'Snippet.php'), 'utf8');
const css = blade.match(/<style>([\s\S]*?)<\/style>/)[1];
const fit = blade.match(/x-on:load="([^"]*)"/)[1].replace(/\$el/g, 'this');
const frameStyle = controller.match(/'page'\) !== '1' => '([^']*)'/)[1];
const snippetStyle = snippet.match(/style="(width:100%;height:'\.\$height\.'px;[^"]*)"/)[1].replace("'.$height.'", '200');

// The frame page as EmbedController::frame() serves it inside a panel (transparent), with the list drawn the way
// embed.js draws it: cards in a grid of columns of at least 300px, in a shadow root.
function frame(cards) {
  return `<!doctype html><html><head><style>html,body{margin:0}${frameStyle}</style></head><body><div id="simplify-servers"></div><script>
    const shadow = document.getElementById('simplify-servers').attachShadow({ mode: 'open' });
    shadow.innerHTML = '<style>.list{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,300px),1fr));gap:12px;align-items:start}.card{height:150px;background:#18181b;border-radius:12px}</style><div class="list">' + '<div class="card"></div>'.repeat(${cards}) + '</div>';
  </script></body></html>`;
}
const attr = (html) => html.replace(/&/g, '&amp;').replace(/"/g, '&quot;');

// A dark panel (Filament sets color-scheme: dark on html.dark) 1060px wide, as in Settings › Website embed.
function page(cards, iframe) {
  return `<!doctype html><html class="dark" style="color-scheme:dark"><meta charset="utf-8">
<body style="margin:0;background:rgb(24,24,27)"><style>${css}</style>
<div style="position:absolute;left:20px;top:20px;width:1032px">${iframe ?? `<iframe class="se-preview" srcdoc="${attr(frame(cards))}" onload="${attr(fit)}"></iframe>`}</div>
<pre id="out" style="position:absolute;top:800px"></pre>
<script>setTimeout(() => { const box = document.querySelector('iframe').getBoundingClientRect();
  document.getElementById('out').textContent = JSON.stringify({ width: Math.round(box.width), height: Math.round(box.height) }); }, 600);</script></html>`;
}

function chromeRun(html, args) {
  const file = path.join(os.tmpdir(), `simplify-embed-preview-${process.pid}-${Math.random().toString(36).slice(2)}.html`);
  fs.writeFileSync(file, html);
  try {
    return execFileSync(chrome, ['--headless', '--no-sandbox', '--disable-gpu', '--no-first-run', '--disable-background-networking', '--hide-scrollbars', '--window-size=1100,900', '--virtual-time-budget=2500', ...args, 'file://' + file], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: 60000 });
  } finally {
    fs.rmSync(file, { force: true });
  }
}
function measure(html) {
  const found = chromeRun(html, ['--dump-dom']).match(/<pre id="out"[^>]*>([^<]*)<\/pre>/);
  return found && found[1] ? JSON.parse(found[1].replace(/&quot;/g, '"')) : null;
}
// The colour of one pixel of a screenshot (8-bit RGB or RGBA PNG, as Chrome writes them).
function pixel(html, x, y) {
  const shot = path.join(os.tmpdir(), `simplify-embed-preview-${process.pid}.png`);
  chromeRun(html, ['--screenshot=' + shot]);
  const png = fs.readFileSync(shot);
  fs.rmSync(shot, { force: true });
  const width = png.readUInt32BE(16);
  const channels = png[25] === 6 ? 4 : 3;
  const chunks = [];
  for (let at = 8; at < png.length;) {
    const length = png.readUInt32BE(at);
    if (png.toString('ascii', at + 4, at + 8) === 'IDAT') chunks.push(png.subarray(at + 8, at + 8 + length));
    at += length + 12;
  }
  const raw = zlib.inflateSync(Buffer.concat(chunks));
  const stride = width * channels;
  let previous = Buffer.alloc(stride);
  let row;
  for (let line = 0; line <= y; line++) {
    const filter = raw[line * (stride + 1)];
    row = Buffer.from(raw.subarray(line * (stride + 1) + 1, (line + 1) * (stride + 1)));
    for (let i = 0; i < stride; i++) {
      const a = i >= channels ? row[i - channels] : 0;
      const b = previous[i];
      const c = i >= channels ? previous[i - channels] : 0;
      const p = a + b - c;
      const paeth = Math.abs(p - a) <= Math.abs(p - b) && Math.abs(p - a) <= Math.abs(p - c) ? a : (Math.abs(p - b) <= Math.abs(p - c) ? b : c);
      row[i] = (row[i] + [0, a, b, (a + b) >> 1, paeth][filter]) & 255;
    }
    previous = row;
  }
  return [row[x * channels], row[x * channels + 1], row[x * channels + 2]];
}

const failures = [];
let passes = 0;
function check(name, ok, detail) {
  if (ok) { passes++; console.log('PASS ' + name); } else failures.push(name + (detail ? ' (' + JSON.stringify(detail) + ')' : ''));
}
const panel = [24, 24, 27];
const same = (a, b) => a.every((v, i) => Math.abs(v - b[i]) <= 2);

// Four cards: three in the first row, one in the second; the empty spot right of it is inside the preview.
const four = page(4);
const empty = pixel(four, 20 + 1032 - 60, 20 + 2 + 150 + 12 + 75);
check('dark panel: the empty part of the preview is the panel background, not white', same(empty, panel), empty);
const one = measure(page(1));
check('one card: the preview is only as wide as the card', one && one.width < 400, one);
check('one card: the preview is only as tall as the card', one && one.height < 200, one);
const many = measure(four);
check('four cards: the preview grows to the rows they fill', many && many.width > 1000 && many.height > 300 && many.height < 360, many);

// The iframe code for other websites on a dark website: its empty part is the website's own background.
const outside = pixel(page(1, `<iframe srcdoc="${attr(frame(1))}" style="${snippetStyle}"></iframe>`), 900, 120);
check('iframe code on a dark website: no white block', same(outside, panel), outside);

if (failures.length) {
  failures.forEach((failure) => console.error('FAIL ' + failure));
  process.exit(1);
}
console.log(`OK ${passes} embed preview checks`);
