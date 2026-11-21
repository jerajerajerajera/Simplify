// The Health button in the top bar said Healthy while the Health page it opens said "2 need attention": the button
// showed only the load of the hosts, the page Pelican's health checks. While the load is fine and a check is not, the
// button (and the Health card on the overview) says Attention needed, in the warning colour; a host that is busy,
// in maintenance or silent keeps its own word. The details under the button name the count, with a link to the page.
// Against resources/js/dashboard.js and the views, without a panel. Usage: node tests/health-pill-attention.cjs
const fs = require('fs');
const path = require('path');
global.window = { addEventListener() {}, Simplify: { t: (key) => (key === 'dashboard.attention_needed' ? 'Attention needed' : key) } };
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
const pill = (boot) => {
  const page = window.pelicanSimplify({}, { servers: [], ...boot });
  return typeof page.healthPill === 'function' ? page.healthPill() : null;
};
const same = (got, state, label) => got?.state === state && got?.label === label;
const healthy = { state: 'healthy', label: 'Healthy' };
let got = pill({ host_health: healthy, health_attention: '2 need attention' });
check('hosts fine, two checks not: Attention needed, in the warning colour', same(got, 'warning', 'Attention needed'), got);
got = pill({ host_health: healthy, health_attention: null });
check('hosts fine and every check ok: Healthy', same(got, 'healthy', 'Healthy'), got);
got = pill({ host_health: { state: 'critical', label: 'Critical load' }, health_attention: '1 needs attention' });
check('a host under critical load keeps its own word', same(got, 'critical', 'Critical load'), got);
got = pill({ host_health: { state: 'unavailable', label: 'Partly unavailable' }, health_attention: '1 needs attention' });
check('a silent host keeps its own word', same(got, 'unavailable', 'Partly unavailable'), got);
got = pill({ host_health: [], health_attention: '1 needs attention' });
check('an admin without the hosts\' numbers sees Attention needed too', same(got, 'warning', 'Attention needed'), got);
got = pill({ host_health: [], health_attention: null });
check('before any numbers the button has no word of its own (the view says Health)', same(got, 'unavailable', ''), got);

const view = (name) => fs.readFileSync(path.join(__dirname, '../resources/views/dashboard', name), 'utf8');
const header = view('header.blade.php');
const button = header.slice(header.indexOf('ps-health-pill'), header.indexOf('</button>', header.indexOf('ps-health-pill')));
check('the button in the top bar shows that state and word', button.includes(':class="healthPill().state"') && button.includes('healthPill().label') && !button.includes('boot.host_health?.label'), button.match(/boot\.host_health\?\.[a-z]+|healthPill\(\)\.[a-z]+/g));
const overview = view('overview.blade.php');
const card = overview.slice(overview.indexOf('ps-health-card'), overview.indexOf('</a>', overview.indexOf('ps-health-card')));
check('the Health card on the overview shows the same', card.includes('healthPill().state') && card.includes('healthPill().label') && !card.includes('boot.host_health?.label'), card.match(/boot\.host_health\?\.[a-z]+|healthPill\(\)\.[a-z]+/g));
const panel = overview.slice(overview.indexOf('id="ps-health-panel"'), overview.indexOf('ps-health-phone-summary'));
check('the details under the button name the count, as a link to the Health page', /<a [^>]*class="ps-health-attention"[^>]*:href="healthUrl\(\)"[^>]*x-show="boot\.health_attention"[^>]*x-text="boot\.health_attention"/.test(panel), panel.match(/ps-health-[a-z-]+/g));
if (failed) { console.log(`${failed} failed`); process.exit(1); }
console.log('PASS the Health button says Attention needed while a health check is not ok');
