// The seasonal touches (resources/js/seasons.js) fall on the right days: holidays that move each year against their
// published dates, the Dutch-only days, overlaps and the panel's birthday. Usage: node tests/seasons.cjs
const path = require('path');
global.window = {};
global.document = {};
require(path.join(__dirname, '../resources/js/seasons.js'));
const S = window.SimplifySeasons;
const day = (text) => { const [y, m, d] = text.split('-').map(Number); return new Date(y, m - 1, d, 12); };
const keys = (text, options) => S.active(day(text), options).map((s) => s.key).join(' ');
let failed = 0;
const check = (label, got, want) => {
  const ok = got === want;
  if (!ok) failed++;
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}${ok ? '' : `: got "${got}", want "${want}"`}`);
};

// [date, holiday that must be on]: first days as published for 2025-2028.
const on = [
  ['2025-01-29', 'lunar'], ['2026-02-17', 'lunar'], ['2027-02-06', ''], ['2027-02-07', 'lunar'], ['2028-01-26', 'lunar'],
  ['2025-03-14', 'holi'], ['2026-03-04', 'holi'], ['2027-03-22', 'holi'],
  ['2025-03-30', 'fitr'], ['2026-03-20', 'fitr'], ['2027-03-09', 'fitr'],
  ['2025-04-20', 'easter'], ['2026-04-05', 'easter'], ['2027-03-28', 'easter'], ['2026-04-07', ''],
  ['2025-05-12', 'vesak'], ['2026-05-01', 'vesak'],
  ['2025-06-06', 'adha'], ['2026-05-27', 'adha'], ['2027-05-16', 'adha'],
  ['2025-10-20', 'diwali'], ['2026-11-08', 'diwali'], ['2027-10-29', 'diwali halloween'], ['2028-10-17', 'diwali'],
  ['2025-12-15', 'hanukkah christmas'], ['2026-12-05', 'hanukkah christmas'], ['2028-01-01', 'newyear hanukkah'],
  ['2026-10-30', 'halloween'], ['2026-11-03', ''], ['2026-07-10', ''],
];
for (const [date, want] of on) check(`${date} ${want || 'nothing'}`, keys(date), want);

check("King's Day only in Dutch", keys('2026-04-27'), '');
check("King's Day in Dutch", keys('2026-04-27', { dutch: true }), 'kingsday');
check("King's Day moves to Saturday when the 27th is a Sunday", keys('2025-04-26', { dutch: true }), 'kingsday');
check('Hanukkah counts its nights', String(S.active(day('2026-12-07')).find((s) => s.key === 'hanukkah').day), '3');
check('the shortest holiday leads', S.active(day('2026-12-07'))[0].key, 'hanukkah');
check("the panel's birthday from one year on", keys('2026-03-12', { born: day('2025-03-12') }), 'birthday');
check('no birthday on the day it was set up', keys('2025-03-12', { born: day('2025-03-12') }), '');
check("a server's birthday", String(S.birthday(day('2024-06-07').getTime() / 1000, day('2026-06-07'))), '2');

if (failed) {
  console.log(`FAIL ${failed} seasonal checks`);
  process.exit(1);
}
