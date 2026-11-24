// Health's CPU, RAM and storage bars take the colour of their load (Khion 23-11): green below 85%, yellow from 85%,
// red from 95%, the thresholds HostHealth already uses for a node's state, so a bar and the node's label agree.
// Network has no bar; a bar without a value has no colour. Usage: node tests/health-bar-colours.cjs
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
global.window = { addEventListener() {} };
global.document = { addEventListener() {}, querySelector: () => null };
global.localStorage = { getItem: () => null, setItem() {} };
global.requestAnimationFrame = () => 0;
global.cancelAnimationFrame = () => {};
require(path.join(__dirname, '../resources/js/dashboard.js'));
const root = path.join(__dirname, '..');
let failed = 0;
const check = (label, ok, detail) => {
  if (!ok) failed++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}${ok ? '' : ': ' + JSON.stringify(detail)}`);
};
const node = (cpu, memory, disk) => ({ hosts: 1, available: 1, cpu_percent: cpu, memory_used: memory, memory_total: 100, disk_used: disk, disk_total: 100, network_rx_bytes: 1, network_tx_bytes: 1 });
const app = window.pelicanSimplify({}, { servers: [], features: { host_health: true }, host_health: node(7.4, 84.9, 97.8) });
const load = (kind, h) => (typeof app.hostLoad === 'function' ? app.hostLoad(kind, h) : 'missing');
check('a calm CPU bar is green', load('cpu') === 'healthy', load('cpu'));
check('RAM just under 85% is still green', load('memory') === 'healthy', load('memory'));
check('storage at 97.8% is red', load('disk') === 'critical', load('disk'));
const busy = node(85, 94.9, 95);
check('85% is yellow, 94.9% yellow, 95% red (per node)', [load('cpu', busy), load('memory', busy), load('disk', busy)].join() === 'warning,warning,critical', [load('cpu', busy), load('memory', busy), load('disk', busy)]);
check('network has no colour', load('network') === '', load('network'));
check('a bar without a value has no colour', load('cpu', { hosts: 1, available: 0, cpu_percent: null }) === '', load('cpu', { cpu_percent: null }));

let php = '';
try { php = execFileSync('php', ['-r', `require '${root}/src/Health/HostHealth.php'; echo json_encode([\\Simplify\\Core\\Health\\HostHealth::LOAD_WARNING ?? null, \\Simplify\\Core\\Health\\HostHealth::LOAD_CRITICAL ?? null, \\Simplify\\Core\\Health\\HostHealth::loadState(84.9), \\Simplify\\Core\\Health\\HostHealth::loadState(85), \\Simplify\\Core\\Health\\HostHealth::loadState(95)]);`], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }); } catch (error) { php = String(error.stderr || error.message).split('\n')[0]; }
check('PHP and JS use the same thresholds (85, 95)', php.trim() === '[85,95,"healthy","warning","critical"]', php.trim());

const overview = fs.readFileSync(path.join(root, 'resources/views/dashboard/overview.blade.php'), 'utf8');
const meters = overview.match(/<span class="ps-meter"[^>]*>/g) || [];
check('every Health bar on the overview asks for its colour', meters.length === 3 && meters.every((m) => m.includes('hostLoad(metric.key')), meters);
const side = fs.readFileSync(path.join(root, 'resources/views/pages/admin-side.blade.php'), 'utf8');
check('the Health bar in the admin menu colours its CPU and RAM the same way', /class="ps-meter \{\{[^}]*HostHealth::loadState\(\$percent\)/.test(side), side.match(/class="ps-meter[^"]*"/));
const css = fs.readFileSync(path.join(root, 'resources/css/dashboard.css'), 'utf8');
check('green, yellow and red come from the theme tokens', /\.ps-meter\.healthy > span \{ background: var\(--ps-ok\)/.test(css) && /\.ps-meter\.warning > span \{ background: var\(--ps-warn\)/.test(css) && /\.ps-meter\.critical > span \{ background: var\(--ps-danger\)/.test(css), 'dashboard.css');
if (failed) { console.log(`${failed} failed`); process.exit(1); }
