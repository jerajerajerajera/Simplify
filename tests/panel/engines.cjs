// What testers reported per browser (Londikk 28-11, Firefox): the console on the first visit and after the menu, Files
// without Pelican's upload layer in the way, the file editor taking typing and Find, and how long the pages take.
// Runs in Chromium and Firefox on the same seeded panel as tests/panel/browser.cjs (checks.yml, job browser) and
// prints a timing table per browser. The last line says "All steps passed" or lists the failures.
// node tests/panel/engines.cjs --base http://127.0.0.1:8001 --urls panel/urls.json --out result [--engines chromium,firefox]
const fs = require('fs'), path = require('path');
const playwright = require('playwright');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const base = arg('base', 'http://127.0.0.1:8001'), out = arg('out', 'result');
const urls = JSON.parse(fs.readFileSync(arg('urls'), 'utf8'));
const engines = arg('engines', 'chromium,firefox').split(',');
const EMAIL = 'demo@example.com', PASSWORD = 'DemoPassword123!'; // the audit's seed.php
const SERVER = 'Survival SMP';
const SLOW = 8000; // ms: a page that takes longer than this on the seeded panel fails
fs.mkdirSync(out, { recursive: true });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const failures = [], timings = {};
let page, engine;

const say = (step, ok, detail) => {
  console.log(`${ok ? 'PASS' : 'FAIL'} ${engine} ${step}${detail ? ' ' + JSON.stringify(detail) : ''}`);
  if (!ok) failures.push(`${engine} ${step}`);
};
const shot = (name) => page.screenshot({ path: path.join(out, `${engine}-${name}.png`) }).catch(() => {});
const ready = () => page.waitForFunction(() => { const el = document.querySelector('.ps'); return !!(el && window.Alpine && el._x_dataStack); }, null, { timeout: 20000 }).catch(() => null);
const time = (name, ms) => { (timings[engine] ??= {})[name] = ms; };
const connected = () => page.waitForFunction(() => {
  const dot = document.querySelector('#terminal .ps-console-dot.connected'), screen = document.querySelector('#terminal .xterm-screen');
  return !!dot && !!screen && screen.getBoundingClientRect().height > 60 && (window.__psConsoleLines || 0) > 0;
}, null, { timeout: 20000 }).then(() => true).catch(() => false);
const consoleState = () => page.evaluate(() => ({
  dot: !!document.querySelector('#terminal .ps-console-dot.connected'), screen: Math.round(document.querySelector('#terminal .xterm-screen')?.getBoundingClientRect().height || 0),
  lines: window.__psConsoleLines || 0, xterm: !!window.Xterm, state: document.querySelector('#terminal .ps-console-state')?.textContent || null,
})).catch(() => null);
// Lit pixels in the console's output: text is light on the dark card. Read from a screenshot, because the output is
// drawn with WebGL, so the page itself has nothing to read (Londikk: in Firefox it stayed empty until a resize).
const lit = async () => {
  const shotOf = await page.locator('#terminal .xterm-screen').first().screenshot({ timeout: 5000 }).catch(() => null);
  if (!shotOf) return -1;
  return page.evaluate(async (data) => {
    const img = new Image();
    await new Promise((done) => { img.onload = done; img.onerror = done; img.src = data; });
    const canvas = Object.assign(document.createElement('canvas'), { width: img.width, height: img.height });
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0);
    const px = ctx.getImageData(0, 0, img.width, img.height).data;
    let n = 0;
    for (let i = 0; i < px.length; i += 4) if (Math.max(px[i], px[i + 1], px[i + 2]) > 150) n++;
    return n;
  }, 'data:image/png;base64,' + shotOf.toString('base64')).catch(() => -1);
};
const layers = () => page.evaluate(() => {
  const shown = (el) => !!el && el.getClientRects().length > 0 && getComputedStyle(el).display !== 'none' && getComputedStyle(el).visibility !== 'hidden' && Number(getComputedStyle(el).opacity) > 0.05;
  const drop = document.querySelector('[x-data^="fileDropZone"] > div');
  return { path: location.pathname, drop: shown(drop), dialog: [...document.querySelectorAll('[x-data^="fileUploader"] > div')].some(shown), uploaders: document.querySelectorAll('[x-data^="fileUploader"]').length };
}).catch(() => null);
// A click in the server menu and the time until the page it opens is usable.
const menu = async (suffix, until) => {
  const start = Date.now();
  await page.locator(`.ps-server-menu a[href$="${suffix}"] >> visible=true`).first().click({ timeout: 5000 }).catch(() => {});
  await page.waitForURL((url) => url.pathname.endsWith(suffix), { timeout: 15000 }).catch(() => {});
  await until();
  return Date.now() - start;
};

async function run(name) {
  engine = name;
  // Firefox draws the console with WebGL as on a desktop (headless it has no WebGL unless forced; Pelican then fell
  // back to drawing with the DOM, which hid what testers saw).
  // Under xvfb-run (CI) Firefox runs with a window, where it has WebGL through Mesa; headless it has none.
  const browser = await playwright[name].launch(name === 'firefox'
    ? { headless: !process.env.DISPLAY, firefoxUserPrefs: { 'webgl.force-enabled': true, 'webgl.disabled': false, 'webgl.enable-webgl2': true } }
    : { args: ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'dark' });
  page = await context.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(`${new URL(page.url()).pathname}: ${String(e.message).slice(0, 200)}${e.stack ? ' @ ' + String(e.stack).split('\n').slice(0, 2).join(' | ').slice(0, 200) : ''}`));
  // A rejected promise with an object (not an Error) reaches Playwright as "[object Object]": say what it held.
  await context.addInitScript(() => addEventListener('unhandledrejection', (e) => {
    try { console.warn('simplify-test rejection ' + location.pathname + ' ' + JSON.stringify(e.reason, Object.getOwnPropertyNames(e.reason || {})).slice(0, 300)); } catch {}
  }));
  page.on('console', (m) => { if (m.text().startsWith('simplify-test rejection')) console.log(`INFO ${name} ${m.text()}`); });
  page.on('websocket', (ws) => ws.on('framereceived', (frame) => {
    if (String(frame.payload).includes('"console output"')) page.evaluate(() => { window.__psConsoleLines = (window.__psConsoleLines || 0) + 1; }).catch(() => {});
  }));
  try {
    // The login form only works once Livewire and Alpine are up (Firefox was faster to type than to load them).
    await page.goto(base + '/login', { waitUntil: 'load' });
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    await page.waitForFunction(() => !!window.Livewire && !!window.Alpine, null, { timeout: 15000 }).catch(() => {});
    await page.fill('#form\\.login', EMAIL); await page.fill('#form\\.password', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 30000 }).catch(() => {});
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    const signedIn = !page.url().includes('/login');
    say('signs in', signedIn, signedIn ? null : { url: page.url(), message: await page.locator('.fi-fo-field-wrp-error-message, [data-validation-error]').first().textContent({ timeout: 1000 }).catch(() => null) });
    if (!signedIn) throw new Error('not signed in');

    let start = Date.now();
    await page.goto(base + '/', { waitUntil: 'domcontentloaded' }); await ready();
    await page.locator('.ps-server-card >> visible=true').first().waitFor({ timeout: 20000 }).catch(() => {});
    time('overview', Date.now() - start);

    // The first visit to a server page, as from the overview (a full load).
    const serverUrl = urls.servers && urls.servers[SERVER];
    start = Date.now();
    await page.goto(serverUrl, { waitUntil: 'domcontentloaded' }); await ready();
    const first = await connected();
    time('server page, console connected', Date.now() - start);
    await shot('console-first');
    say('the console connects and shows output on the first visit', first, first ? null : await consoleState());
    await sleep(1500);
    const drawn = await lit();
    // A resize makes xterm draw again: if the text only shows after it, the first drawing was lost.
    await page.setViewportSize({ width: 1440, height: 860 }); await sleep(1200);
    const resized = await lit();
    await page.setViewportSize({ width: 1440, height: 900 }); await sleep(800);
    const webgl = await page.evaluate(() => !!document.querySelector('#terminal canvas') && !document.querySelector('#terminal .xterm-rows > div:not(:empty)')).catch(() => null);
    say('the console shows its text on the first visit, without a resize (Londikk, Firefox)', drawn > 200, { drawn, afterResize: resized, webgl, ...(await consoleState()) });

    // For comparison only: the same first visit without Simplify's redraw (K-B106), in a page of its own.
    const bare = await context.newPage();
    await bare.route(/\/simplify\/assets\/server\.js/, async (route) => {
      const response = await route.fetch();
      await route.fulfill({ response, body: (await response.text()).replace('window.Simplify?.redrawConsole?.(terminal)', 'null') });
    });
    const keep = page;
    page = bare;
    await page.goto(serverUrl, { waitUntil: 'domcontentloaded' }); await ready(); await connected(); await sleep(2300);
    console.log(`INFO ${engine} console without the redraw: ${await lit()} lit pixels (with it: ${drawn})`);
    await shot('console-without-redraw');
    await bare.close().catch(() => {});
    page = keep;

    // Files through the menu: Pelican's upload layer and dialog stay hidden, also after a file is dragged over and out.
    time('menu › Files', await menu('/files', () => page.waitForSelector('.fi-ta-row', { timeout: 15000 }).catch(() => {})));
    await sleep(800);
    const arrived = await layers();
    await shot('files');
    say('Files through the menu: no upload layer or dialog in view', !!arrived && !arrived.drop && !arrived.dialog && arrived.uploaders <= 1, arrived);
    await page.evaluate(() => {
      const fire = (el, type) => { const dt = new DataTransfer(); dt.items.add(new File(['x'], 'x.txt', { type: 'text/plain' })); el.dispatchEvent(new DragEvent(type, { bubbles: true, cancelable: true, dataTransfer: dt })); };
      const row = document.querySelector('.fi-ta-row') || document.body;
      fire(document.body, 'dragenter'); fire(row, 'dragenter'); fire(document.body, 'dragleave'); fire(row, 'dragleave');
    }).catch(() => {});
    await sleep(700);
    const dragged = await layers();
    say('Files: a file dragged over the page and out leaves no upload layer', !!dragged && !dragged.drop && !dragged.dialog, dragged);

    // Back to the console through the menu, then Files and the console once more: it connects each time.
    time('menu › Console', await menu('/console', connected));
    const again = await connected();
    say('the console connects again after the menu', again, again ? null : await consoleState());
    await sleep(1500);
    const drawnAgain = await lit();
    await shot('console-after-menu');
    say('the console shows its text after the menu, without a resize', drawnAgain > 200, { drawn: drawnAgain });
    await menu('/files', () => page.waitForSelector('.fi-ta-row', { timeout: 15000 }).catch(() => {}));
    const twice = await layers();
    say('Files a second time: no upload layer or dialog in view', !!twice && !twice.drop && !twice.dialog && twice.uploaders <= 1, twice);

    // The editor (the audit's Wings calls every file a .jar, so Pelican shows no edit link: the address itself).
    start = Date.now();
    await page.goto(serverUrl.replace(/\/console$/, '') + '/files/edit/server.properties', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('.monaco-editor .view-lines', { timeout: 20000 }).catch(() => {});
    time('file editor', Date.now() - start);
    await sleep(1000);
    await page.locator('.monaco-editor .view-lines').first().click({ timeout: 4000, position: { x: 40, y: 10 } }).catch(() => {});
    await page.keyboard.press('Control+End').catch(() => {});
    await page.keyboard.type('simplifytyped', { delay: 20 }).catch(() => {}); await sleep(500);
    const typed = await page.evaluate(() => ({
      text: (document.querySelector('.monaco-editor .view-lines')?.innerText || '').includes('simplifytyped'),
      inEditor: !!document.activeElement?.closest('.monaco-editor'),
      focus: document.activeElement ? String(document.activeElement.className || document.activeElement.tagName).slice(0, 80) : null,
    })).catch(() => null);
    await shot('editor');
    say('the file editor takes typing', !!typed && typed.text, typed);
    await page.keyboard.press('Control+f').catch(() => {}); await sleep(700);
    const find = await page.evaluate(() => {
      const widget = document.querySelector('.monaco-editor .find-widget');
      return { visible: !!widget && widget.classList.contains('visible'), focus: !!document.activeElement?.closest('.find-widget') };
    }).catch(() => null);
    say('the file editor opens its own Find with Ctrl+F', !!find && find.visible && find.focus, find);
    await page.keyboard.press('Escape').catch(() => {});
    for (let i = 0; i < 'simplifytyped'.length; i++) await page.keyboard.press('Backspace').catch(() => {});

    for (const [step, ms] of Object.entries(timings[engine] || {})) say(`${step} within ${SLOW / 1000} s`, ms <= SLOW, { ms });
    // Without WebGL in the test browser Pelican's console falls back to drawing with the DOM, after an error of its own.
    const noWebgl = errors.filter((e) => /WebGL2 not supported/.test(e));
    if (noWebgl.length) console.log(`INFO ${engine} has no WebGL2 here: Pelican's console drew with the DOM`);
    // Livewire rejects a request the next page load cut off (status null, as Firefox reports it): a test that moves on.
    const cut = errors.filter((e) => /: \[object Object\] @ .*livewire(\.min)?\.js/.test(e));
    if (cut.length) console.log(`INFO ${engine}: ${cut.length} Livewire request(s) cut off by the next page load`);
    const own = errors.filter((e) => !noWebgl.includes(e) && !cut.includes(e));
    say('no script errors on the pages', own.length === 0, own.slice(0, 5));
  } catch (e) {
    say('run', false, { error: String(e.message).slice(0, 300) });
  } finally {
    await browser.close().catch(() => {});
  }
}

(async () => {
  // Pelican lets an address sign in 5 times a minute; browser.cjs signed in just before.
  for (const name of engines) { await sleep(15000); await run(name); }
  console.log('Timings (ms): ' + JSON.stringify(timings));
  fs.writeFileSync(path.join(out, 'engines.json'), JSON.stringify({ timings, failures }, null, 2));
  if (failures.length) { console.log(`FAILED: ${failures.join('; ')}`); process.exit(1); }
  console.log('All steps passed');
})();
