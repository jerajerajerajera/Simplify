"""What PelicanSimplify 0.7.0 shows that Simplify does not: python3 diff-inventory.py old/inventory.json new/inventory.json [allowed.txt]

A label counts as present when Simplify shows the same label (any case) anywhere. allowed.txt lists labels Jeroen agreed
to leave out, one per line. Prints the missing ones per 0.7.0 screen; exits 1 when any are left.
"""
import json, re, sys

def labels(doc):
    out = {}
    for screen, h in doc.items():
        for x in h.get('items', []):
            label = re.sub(r'\s+', ' ', x.get('label') or '').strip()
            if not label or re.fullmatch(r'[\d\s.,:%/·-]+', label):
                continue
            out.setdefault(label.lower(), []).append((screen, x.get('kind'), label, x.get('opener') or x.get('where')))
    return out

old, new = (labels(json.load(open(p))) for p in sys.argv[1:3])
allowed = set(l.strip().lower() for l in open(sys.argv[3]) if l.strip()) if len(sys.argv) > 3 else set()
missing = {k: v for k, v in old.items() if k not in new and k not in allowed}
by_screen = {}
for k, v in missing.items():
    screen, kind, label, where = v[0]
    by_screen.setdefault(screen, []).append(f'{kind}: {label} ({where})')
print(f'# In 0.7.0, not in Simplify: {len(missing)} labels\n')
for screen in sorted(by_screen):
    print(f'## {screen}')
    for line in sorted(by_screen[screen]):
        print(f'- {line}')
    print()
sys.exit(1 if missing else 0)
