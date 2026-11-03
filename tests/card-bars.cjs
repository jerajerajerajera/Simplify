// The CPU and RAM tiles on an overview card (K-B17): the same bars as the server page instead of a vague line, from 0 to
// what the server may use, the newest bar on its own, and a slide to the left on each refresh. Usage: node tests/card-bars.cjs
const path = require('path');
let frames = [];
global.window = { addEventListener() {} };
global.document = { addEventListener() {}, querySelector: () => null };
global.localStorage = { getItem: () => null, setItem() {} };
global.requestAnimationFrame = (f) => frames.push(f);
global.cancelAnimationFrame = () => {};
require(path.join(__dirname, '../resources/js/dashboard.js'));
let failed = 0;
const check = (label, ok, detail) => {
  if (!ok) failed++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}${ok ? '' : ': ' + JSON.stringify(detail)}`);
};
const d = window.pelicanSimplify({}, { servers: [], user: 'jeroen' });
const GB = 1073741824;
const tops = (p) => [...(p || '').matchAll(/V([\d.]+)h/g)].map((m) => 28 - Number(m[1]));
const lefts = (p) => [...(p || '').matchAll(/M([\d.-]+) /g)].map((m) => Number(m[1]));
const server = (cpu, ram, extra = {}) => ({ uuid: 'a', usage: { cpu_percent: cpu, cpu_limit: 400, memory_bytes: ram, memory_limit_bytes: 8 * GB, ...extra } });
const run = () => { const f = frames; frames = []; f.forEach((step) => step(performance.now() + 2000)); };

check('the card draws bars, not a line', typeof d.cardBars === 'function', typeof d.cardBars);
if (typeof d.cardBars === 'function') {
  d.recordOverview([server(4, 2 * GB)]);
  run();
  d.recordOverview([server(300, 6 * GB)]);
  run();
  const s = server(300, 6 * GB);
  const cpu = tops(d.cardBars(s, 'cpu')), now = tops(d.cardBars(s, 'cpu', true));
  check('the older bar is apart from the newest', cpu.length === 1 && now.length === 1, { cpu, now });
  check('4% of 400% is a small bar, not the full height', cpu[0] < 2, cpu);
  check('300% of 400% is three quarters high', Math.abs(now[0] - 18.75) < 0.01, now);
  check('RAM scales to its limit: 6 of 8 GB', Math.abs(tops(d.cardBars(s, 'ram', true))[0] - 18.75) < 0.01, d.cardBars(s, 'ram', true));
  check('the newest bar stands at the right edge', Math.abs(lefts(d.cardBars(s, 'cpu', true))[0] - (100 - 100 / 24 + 0.6)) < 0.01, d.cardBars(s, 'cpu', true));
  check('the colour stays with the load', d.cardMetricLevel(s, 'cpu') === 'is-warn' && d.cardMetricLevel(s, 'ram') === 'is-warn');

  // A refresh moves the bars one step left, smoothly, instead of jumping.
  d.recordOverview([server(10, 1 * GB)]);
  check('after a refresh the bars start one step to the right', d.cardGlide === 0 && Math.abs(lefts(d.cardBars(s, 'cpu', true))[0] - (100 + 0.6)) < 0.01, d.cardGlide);
  run();
  check('and slide into place', d.cardGlide === 1, d.cardGlide);

  for (let i = 0; i < 40; i++) d.recordOverview([server(10, 1 * GB)]);
  run();
  check('a card keeps one bar per refresh, plus the one sliding out', d.overviewHistory.a.length === 25, d.overviewHistory.a.length);
  check('an unknown value draws nothing', d.cardBars({ uuid: 'b', usage: {} }, 'cpu') === '');
}
if (failed) { console.log(`${failed} failed`); process.exit(1); }
