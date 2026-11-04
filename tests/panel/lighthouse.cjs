// Reads the Lighthouse reports the browser job made (result/lh-<name>.report.json) and prints the scores; fails when
// a score is under its budget in tests/panel/budgets.json (the first run sets the budgets: a score may not fall).
// node tests/panel/lighthouse.cjs --out result
const fs = require('fs'), path = require('path');
const arg = (n, d) => { const i = process.argv.indexOf('--' + n); return i > 0 ? process.argv[i + 1] : d; };
const out = arg('out', 'result');
const budgets = JSON.parse(fs.readFileSync(path.join(__dirname, 'budgets.json'), 'utf8'));
let failed = false;
for (const file of fs.readdirSync(out).filter((f) => /^lh-.*\.report\.json$/.test(f))) {
  const name = file.replace(/^lh-|\.report\.json$/g, '');
  const report = JSON.parse(fs.readFileSync(path.join(out, file), 'utf8'));
  const scores = Object.fromEntries(Object.entries(report.categories).map(([k, c]) => [k, Math.round((c.score || 0) * 100)]));
  const under = Object.entries(budgets[name] || budgets.default || {}).filter(([k, min]) => (scores[k] ?? 0) < min);
  console.log(`${under.length ? 'FAIL' : 'PASS'} lighthouse ${name} ${JSON.stringify(scores)}${under.length ? ' under budget: ' + JSON.stringify(Object.fromEntries(under)) : ''}`);
  if (under.length) failed = true;
}
process.exit(failed ? 1 : 0);
