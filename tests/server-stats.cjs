// The numbers on the server page (K-B11): bars from 0 to the server's limit, the colour of the load (W4), the share as
// "35% of 400%", download and upload apart, and bars that glide between two measurements. Runs server.js without a panel.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const lines = { 'dashboard.share_of': ':percent of :amount', 'dashboard.of_allocated': 'of :amount allocated' };
let frames = [];
const window = {
  Simplify: { t: (key, params = {}) => Object.entries(params).reduce((text, [k, v]) => text.replace(':' + k, v), lines[key] || key) },
  pelicanSimplify: () => ({ trafficBytes: (b) => (b / 1073741824).toFixed(1) + ' GB' }),
  matchMedia: () => ({ matches: false }),
};
const context = vm.createContext({ window, performance: { now: () => 0 }, requestAnimationFrame: (f) => frames.push(f), cancelAnimationFrame: () => {}, console });
vm.runInContext(fs.readFileSync(path.join(__dirname, '..', 'resources', 'js', 'server.js'), 'utf8'), context);

const fails = [];
const check = (ok, text) => { console.log((ok ? 'PASS ' : 'FAIL ') + text); if (!ok) fails.push(text); };
const page = (usage) => {
  const c = window.simplifyServer({}, { server: { usage, permissions: {} } });
  c.trafficBytes = (b) => (b / 1073741824).toFixed(1) + ' GB';
  return c;
};
// The top of each bar in a path ("M x base v -h ..."), per bar.
const heights = (d) => [...(d || '').matchAll(/v(-?[\d.]+)/g)].map((m) => Number(m[1]));
const starts = (d) => [...(d || '').matchAll(/M([\d.-]+) /g)].map((m) => Number(m[1]));
const GB = 1073741824;

// 1. 1.3% of 400% is a small bar, not the full height (the old line scaled to its own highest point).
let c = page({ cpu_limit: 400, memory_limit_bytes: 12 * GB, disk_limit_bytes: 20 * GB });
for (const cpu of [1.2, 1.3, 1.3]) c.record({ cpu, memory: 5.3 * GB, disk: 6.3 * GB, rx: 0, tx: 0 }, 0);
const cpuBars = heights(c.spark('cpu')).concat(heights(c.spark('cpu', true)));
check(cpuBars.length === 3 && cpuBars.every((h) => Math.abs(h) <= 1.01), 'CPU bars run from 0 to the limit: 1.3% of 400% stays a sliver');
check(Math.abs(heights(c.spark('ram', true))[0]) > 10 && Math.abs(heights(c.spark('ram', true))[0]) < 12, 'memory 5.3 of 12 GB fills under half of the bar');

// 2. The colour of the load, as on the overview cards: below 60% calm, from 60% warning, from 85% alarm.
const level = (cpu, limit) => { const p = page({ cpu_limit: limit }); p.stats = { cpu }; return p.metricLevel('cpu'); };
check(level(236, 400) === 'is-ok' && level(240, 400) === 'is-warn' && level(340, 400) === 'is-hot', 'the bars take the W4 colours at 60% and 85%');
check(level(50, 0) === '', 'without a limit the bars stay grey');

// 3. The line under the number: the share of the limit.
c.stats = { cpu: 140, memory: 5.3 * GB, disk: 6.3 * GB };
check(c.metricLimit('cpu') === '35% of 400%', 'CPU says "35% of 400%"');
c.stats.cpu = 1.3;
check(c.metricLimit('cpu') === '<1% of 400%', 'a load under 1% says "<1%"');
check(c.metricLimit('ram') === '44% of 12.0 GB', 'memory says "44% of 12.0 GB"');
check(page({}).metricLimit('disk') === 'of ∞ allocated', 'no limit keeps "of ∞ allocated"');

// 4. Network: download and upload apart, download up and upload down from the middle line.
c = page({});
c.record({ rx: 0, tx: 0 }, 0);
c.record({ rx: 1000, tx: 9000 }, 1);
check(heights(c.spark('rx', true))[0] < 0 && heights(c.spark('tx', true))[0] > 0, 'download bars go up, upload bars go down');
check(Math.abs(heights(c.spark('tx', true))[0]) > Math.abs(heights(c.spark('rx', true))[0]), 'download and upload share one scale');

// 5. Smooth: no faster polling, but the bars slide one step over the time between two measurements.
c = page({ cpu_limit: 100 });
frames = [];
c.record({ cpu: 10 }, 0);
c.record({ cpu: 20 }, 2);
check(c.glide === 0 && frames.length === 1, 'a new measurement starts a slide over the 2 seconds since the last one');
const before = starts(c.spark('cpu', true))[0];
c.glide = 1;
const after = starts(c.spark('cpu', true))[0];
check(before > 99 && after < before && after > 95, 'the newest bar slides in from the right edge');
c.glide = 0.5;
check(c.metricValue('cpu') === '20.0%', 'the number changes once per measurement, not while the bars slide');

if (fails.length) process.exit(1);
