// The Health pill keeps its state when you switch to Admin and back (K-B41): the next page shows the host health it had
// until its own numbers are in, instead of Unavailable for a moment (a wider pill that moves the top bar's buttons).
// Usage: node tests/health-pill-steady.cjs
const fs = require('fs');
const path = require('path');
global.window = { addEventListener() {} };
global.document = { addEventListener() {}, querySelector: () => null };
global.localStorage = { getItem: () => null, setItem() {} };
global.requestAnimationFrame = () => 0;
global.cancelAnimationFrame = () => {};
require(path.join(__dirname, '../resources/js/dashboard.js'));
let failed = 0;
const check = (label, ok, detail) => {
  if (!ok) failed++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}${ok ? '' : ': ' + JSON.stringify(detail)}`);
};
const healthy = { state: 'healthy', label: 'Healthy', nodes: [] };
const first = window.pelicanSimplify({}, { servers: [], features: { host_health: true } });
check('the page keeps the host health it loaded', typeof first.setHostHealth === 'function', typeof first.setHostHealth);
if (typeof first.setHostHealth === 'function') {
  first.setHostHealth(healthy);
  const next = window.pelicanSimplify({}, { servers: [], features: { host_health: true } });
  next.restoreHostHealth();
  check('the next page starts with that host health', next.boot.host_health?.label === 'Healthy', next.boot.host_health);
  const own = window.pelicanSimplify({}, { servers: [], features: { host_health: true }, host_health: { state: 'warning', label: 'High load' } });
  own.restoreHostHealth();
  check('a page with its own snapshot keeps its own', own.boot.host_health.label === 'High load', own.boot.host_health);
  first.setHostHealth({});
  const after = window.pelicanSimplify({}, { servers: [], features: { host_health: true } });
  after.restoreHostHealth();
  check('an empty answer does not wipe the remembered state', after.boot.host_health?.label === 'Healthy', after.boot.host_health);
}
const header = fs.readFileSync(path.join(__dirname, '../resources/views/dashboard/header.blade.php'), 'utf8');
const pill = header.slice(header.indexOf('ps-health-pill'), header.indexOf('</button>', header.indexOf('ps-health-pill')));
check('before any numbers the pill says Health, not Unavailable', !pill.includes("$u('unavailable')"), pill.match(/\$u\('[a-z_]+'\)/g));
if (failed) { console.log(`${failed} failed`); process.exit(1); }
