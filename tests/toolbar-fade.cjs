// K-B83: on the overview of someone who is not a root admin, "Create server" stands at the end of the filter row, and
// its end faded out with the plus on its corner cut off. The fade marks a strip that scrolls sideways (a phone's
// filters); the plus sticks a few pixels out of the row, which counted as "more to scroll" on a row that does not
// scroll at all. Against resources/js/dashboard.js, without a panel. Usage: node tests/toolbar-fade.cjs
const path = require('path');
global.window = { addEventListener() {} };
global.document = { addEventListener() {}, querySelector: () => null };
global.localStorage = { getItem: () => null, setItem() {} };
require(path.join(__dirname, '../resources/js/dashboard.js'));
let failed = 0;
const check = (label, ok, detail) => {
  if (!ok) failed++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}${ok ? '' : ': ' + JSON.stringify(detail)}`);
};
// A row as the browser reports it: its own width, the width of what is in it, and whether it scrolls sideways.
const hint = (overflowX, scrollWidth, clientWidth, scrollLeft = 0) => {
  const listeners = {};
  const el = { scrollWidth, clientWidth, scrollLeft, dataset: {}, children: [], addEventListener: (name, fn) => { listeners[name] = fn; } };
  global.getComputedStyle = () => ({ overflowX });
  const d = window.pelicanSimplify({}, { servers: [], user: 'jeroen' });
  d.$nextTick = (fn) => fn();
  d.scrollHint(el);
  return el.dataset.psMore || '';
};

check('a row that does not scroll never fades, also with the plus sticking out of it', hint('visible', 1093, 1088) === '', hint('visible', 1093, 1088));
check('a scrolling row with more to its end fades there', hint('auto', 620, 390) === 'end', hint('auto', 620, 390));
check('scrolled to its end it fades at its start', hint('auto', 620, 390, 230) === 'start', hint('auto', 620, 390, 230));
check('in the middle it fades on both sides', hint('auto', 620, 390, 100) === 'start end', hint('auto', 620, 390, 100));
check('a scrolling row that fits does not fade', hint('auto', 390, 390) === '', hint('auto', 390, 390));

if (failed) { console.log(`${failed} failed`); process.exit(1); }
console.log('PASS the filter row only fades where it scrolls');
