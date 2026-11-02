"""Section compare: the overview against the baseline (Simplify 1.5.5), per stand and view. A size may grow by at most
10% and at least as many servers fit without scrolling, give or take 10%. Prints ok or WRONG per measure, exit 1 on WRONG.
python3 compare-verdict.py baseline/compare.json result/compare.json
"""
import json
import sys

base, new = (json.load(open(p)) for p in sys.argv[1:3])
lines, wrong = [], 0
for stand, views in new['stands'].items():
    for view in ('grid', 'list'):
        b, n = (base['stands'].get(stand) or {}).get(view), views.get(view)
        if not b or not n:
            lines.append(f'- WRONG {stand} {view}: no baseline or no measurement'); wrong += 1
            continue
        checks = [
            ('card height', (b.get('card') or {}).get('h'), (n.get('card') or {}).get('h'), 'max'),
            ('row pitch', b.get('rowPitch'), n.get('rowPitch'), 'max'),
            ('first server top', b.get('firstServerTop'), n.get('firstServerTop'), 'max'),
            ('servers in view', b.get('fitWithoutScrolling'), n.get('fitWithoutScrolling'), 'min'),
        ]
        for name, bv, nv, kind in checks:
            if bv is None:
                continue
            ok = nv is not None and (nv <= bv * 1.1 + 1 if kind == 'max' else nv * 1.1 >= bv)
            wrong += not ok
            lines.append(f'- {"ok" if ok else "WRONG"} {stand} {view} {name}: {nv} (baseline {bv}, {"at most +10%" if kind == "max" else "at least -10%"})')
print(f'# Compare with the baseline: {wrong} WRONG\n')
print('\n'.join(lines))
sys.exit(1 if wrong else 0)
