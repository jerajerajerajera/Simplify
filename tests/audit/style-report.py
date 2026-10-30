#!/usr/bin/env python3
"""Style verdict of a Screen audit result: which screens break Simplify's UI rules (project memory "UI-regels").
   python3 style-report.py result/result.json [more result.json ...] > style.md ; exit 1 when a rule is broken.
   Rules: one content box that never scrolls on server pages and in host mode; no box inside the box; action
   buttons in host mode's title bar (never under the form); Simplify's look inside host mode's frames (no bare
   Filament buttons or breadcrumbs); dropdowns at most two thirds of the window, scrolling only when needed, arrow
   centred under the button; the content box the same height on every page of a stand."""
import json, sys, re, collections

STYLE = {'boxinbox': 'vak in het vak', 'savebelow': 'knop in de pagina in plaats van in de titelbalk', 'toolslost': 'knoppen van de pagina ontbreken in de titelbalk',
         'filamentlook': 'kale Filament-stijl in hostmodus', 'boxoff': 'contentvak valt onder het venster', 'cardoff': 'contentkaart valt onder het venster',
         'dropdowntall': 'dropdown hoger dan 2/3 venster', 'dropdownscroll': 'dropdown scrolt terwijl hij hoger mag', 'arrowoff': 'dropdown-pijltje niet gecentreerd',
         'pagescroll': 'pagina scrolt (mag niet op server-, host- en Simplify-beheerpagina\'s)', 'hscroll': 'pagina breder dan het venster', 'overlap': 'kop: naam, seizoensicoontjes en knoppen overlappen', 'popupoff': 'pop-up/tooltip deels buiten beeld', 'brokenimg': 'kapot plaatje zichtbaar (icoon laadt niet)'}
NOSCROLL = re.compile(r'^(server\d?-(?!open-in-admin)|host|klant.*server)')  # screens whose page must never scroll

rows, heights = [], collections.defaultdict(list)
for f in sys.argv[1:]:
    data = json.load(open(f))
    for s in data['screens']:
        docs = [('', s.get('main') or {})] + [(' [frame]', fr) for fr in s.get('frames') or []]
        for tag, m in docs:
            for p in m.get('problems') or []:
                k = p['kind']
                if k not in STYLE: continue
                # A phone scrolls its server page by design (the fit applies from 601 px); frames scroll inside the box.
                # Simplify's own admin pages in the normal mode (Settings, Health, People, add-ons) must not scroll either.
                noscroll = NOSCROLL.match(s['id']) or (s['id'].startswith('admin-') and (m.get('info') or {}).get('scope') == 'simplify')
                if k in ('pagescroll', 'hscroll') and (tag or not noscroll or (m.get('w') or 9999) <= 600): continue
                if k == 'cardoff' and tag: continue
                rows.append((s['stand'], s['id'], s.get('title', ''), k + tag, p['text'], s.get('shot', '')))
            box = (m.get('info') or {}).get('box')
            if box and not tag:
                top, rest = box.split('..'); bottom = rest.split(' of ')[0]
                heights[(s['stand'], 'host' if s['id'].startswith('host') else 'admin' if s['id'].startswith(('tile-', 'create-')) else 'server')].append((int(bottom) - int(top), s['id']))
# The content box must be the same height on every page of a stand (host mode pages, server pages).
for (stand, kind), hs in heights.items():
    counts = collections.Counter(h for h, _ in hs); common = counts.most_common(1)[0][0]
    for h, sid in hs:
        if abs(h - common) > 3: rows.append((stand, sid, '', 'boxheight', f'{kind} content box {h}px high, the other pages of this stand {common}px', ''))

per = collections.defaultdict(list)
for r in rows: per[(r[0], r[1])].append(r)
print(f'# Stijlcontrole: {len(per)} scherm(en) met overtredingen, {len(rows)} bevinding(en)\n')
for (stand, sid), rs in sorted(per.items()):
    print(f'## {stand} · {sid}  {rs[0][2]}' + (f'  ({rs[0][5]})' if rs[0][5] else ''))
    for r in rs: print(f'- **{r[3]}** ({STYLE.get(r[3].split(" ")[0], r[3])}): {r[4]}')
    print()
sys.exit(1 if rows else 0)
