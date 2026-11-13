// Customize's choices and Reset layout (resources/js/dashboard.js) without a panel. Usage: node tests/customize-reset.cjs
// K-B65 (betatester, 9-11, 2.0.0-beta.11): Simplify kept these choices under PelicanSimplify's name, so a browser that
// ran PelicanSimplify opened server pages with its old bigger console and full width. Simplify keeps them under its own
// name now, takes over only the old choices that cannot change a page's layout, and Reset layout puts all back.
const path = require('path');
const store = {};
global.window = { addEventListener() {} };
const classes = new Set();
global.document = { addEventListener() {}, querySelector: () => null, documentElement: { classList: { remove: (c) => classes.delete(c) } } };
global.localStorage = { getItem: (k) => (k in store ? store[k] : null), setItem: (k, v) => (store[k] = String(v)), removeItem: (k) => delete store[k] };
require(path.join(__dirname, '../resources/js/dashboard.js'));
let failed = 0;
const check = (label, ok, detail) => {
  if (!ok) failed++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}${ok ? '' : ': ' + JSON.stringify(detail)}`);
};
const make = () => window.pelicanSimplify({}, { servers: [], user: 'kirby' });

// A browser that ran PelicanSimplify.
store['pelicanSimplify.overviewAppearance.kirby'] = JSON.stringify({ serverBiggerConsole: true, fullWidth: true, serverFullWidth: true, healthPlace: 'bottom', columns: '4', cardWidth: 'wide', halloween: false, showQuickActions: false });
const a = make();
a.restoreOverviewPreferences();
const p = a.overviewPreferences;
check('the old bigger console does not come over', p.serverBiggerConsole === false, p);
check('the old full width does not come over', p.fullWidth === false, p);
check('the old place of Health does not come over', p.healthPlace === 'top-bar', p);
check('safe old choices come over (columns, card width, seasons, quick actions)', p.columns === '4' && p.cardWidth === 'wide' && p.halloween === false && p.quickActions === 'off', p);
check('they are kept under Simplify\'s own name', JSON.parse(store['simplify.overview.kirby'] || 'null')?.columns === '4', store);

// From then on Simplify's own choices count, a bigger console too.
a.setOverviewPreference('serverBiggerConsole', true);
const b = make();
b.restoreOverviewPreferences();
check('a choice made in Simplify stays', b.overviewPreferences.serverBiggerConsole === true, b.overviewPreferences);
check('the old choices are not read again', b.overviewPreferences.fullWidth === false, b.overviewPreferences);

// Reset layout.
classes.add('ps-console-big');
b.resetOverviewPreferences();
const def = make().overviewPreferences;
check('Reset layout puts every choice back', JSON.stringify(b.overviewPreferences) === JSON.stringify(def), b.overviewPreferences);
check('and keeps that on this device', JSON.parse(store['simplify.overview.kirby']).serverBiggerConsole === false && JSON.parse(store['simplify.overview.kirby']).columns === 'auto', store['simplify.overview.kirby']);
check('and takes the bigger console off the page at once', !classes.has('ps-console-big'));

// A new browser: nothing saved, the standard choices.
for (const k of Object.keys(store)) delete store[k];
const c = make();
c.restoreOverviewPreferences();
check('a new browser starts from the standard choices', JSON.stringify(c.overviewPreferences) === JSON.stringify(def), c.overviewPreferences);

if (failed) { console.log(`${failed} failed`); process.exit(1); }
