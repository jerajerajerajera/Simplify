#!/usr/bin/env python3
"""Which parts of the screen audit a change can touch.

    python3 tests/audit/parts.py [--self-test] [path ...]   (paths also on stdin, one per line)

Prints the parts for audit.cjs --parts: a comma-separated list, "all" when a changed file can reach every screen,
or nothing when no changed file is visible in the audit. Unknown files count as "all", so a wrong guess costs time,
never coverage. The full audit still runs before every release.
"""
import re
import sys

PARTS = ['overview', 'server', 'admin', 'host', 'seasons', 'create', 'extras', 'notify', 'klant']
# First matching rule wins; a rule's parts are added for every file. 'all' and 'none' are rules of their own.
RULES = [
    (r'^(docs/|.*\.md$|LICENSE|CHANGELOG|README|plugin\.json$|\.github/(?!workflows/audit\.yml))', 'none'),
    (r'^tests/(?!audit/)', 'none'),
    (r'^(tests/audit/|\.github/workflows/audit\.yml)', 'all'),
    (r'^database/', 'none'),
    (r'^lang/(?!en/)', 'none'),
    (r'^resources/css/seasons\.css$|^resources/js/seasons\.js$|^resources/views/dashboard/(seasons|halloween)\.blade\.php$', 'seasons'),
    (r'^resources/(css|js|fonts|img|overrides)/', 'all'),
    (r'^resources/views/(head|form-page)\.blade\.php$', 'all'),
    (r'^resources/views/dashboard/(overview|header|tabbar|account|customize-menu|groups-menu|icons|assets)\.blade\.php$', 'all'),
    (r'^resources/views/dashboard/host\.blade\.php$', 'host,create'),
    (r'^resources/views/dashboard/(health-checks)\.blade\.php$', 'overview,host'),
    (r'^resources/views/dashboard/(notify-item)\.blade\.php$', 'overview,notify'),
    (r'^resources/views/dashboard/create-buttons\.blade\.php$', 'overview,create,klant'),
    (r'^resources/views/pages/(overview)\.blade\.php$', 'overview,klant,seasons,extras'),
    (r'^resources/views/pages/server\.blade\.php$', 'server,extras,klant'),
    (r'^resources/views/pages/(settings|setup|setup-done|people)\.blade\.php$', 'admin,host'),
    (r'^resources/views/partials/server-banners\.blade\.php$', 'overview,klant'),
    (r'^resources/views/partials/', 'admin,host'),
    (r'^resources/views/(announcements)/', 'overview,admin,notify,klant'),
    (r'^resources/views/(embed|hub|fields)/', 'admin,server'),
    (r'^src/(Overview|Health)/', 'overview,host,extras,klant'),
    (r'^src/(Server|Console)/', 'server,extras,klant'),
    (r'^src/(Announcements|Push)/', 'overview,admin,notify,klant'),
    (r'^src/(Embed|Subdomains)/', 'server,admin'),
    (r'^src/(People|Hub)/', 'admin,host'),
    (r'^src/Filament/Admin/', 'admin,host'),
    (r'^src/Filament/App/', 'overview,klant'),
    (r'^src/Filament/Server/', 'server,klant'),
    (r'^src/WebApp/', 'overview,notify'),
    (r'^src/Addons/', 'server,admin,host,create'),
]


def parts_for(paths):
    found, every = set(), False
    for path in paths:
        path = path.strip()
        if not path:
            continue
        for pattern, parts in RULES:
            if re.search(pattern, path):
                break
        else:
            parts = 'all'
        if parts == 'all':
            every = True
        elif parts != 'none':
            found.update(parts.split(','))
    if every:
        return 'all'
    return ','.join(p for p in PARTS if p in found)


def self_test():
    cases = [
        (['docs/regels.md', 'README.md', 'CHANGELOG.md'], ''),
        (['tests/addons.php', 'database/migrations/2026_simplify_x.php', 'lang/nl/simplify.php'], ''),
        (['src/Overview/ServerCards.php'], 'overview,host,extras,klant'),
        (['src/Server/Console.php', 'docs/bugs.md'], 'server,extras,klant'),
        (['resources/views/pages/settings.blade.php'], 'admin,host'),
        (['resources/css/seasons.css'], 'seasons'),
        (['resources/css/dashboard.css'], 'all'),
        (['src/Something/New.php'], 'all'),
        (['tests/audit/audit.cjs'], 'all'),
        (['.github/workflows/checks.yml'], ''),
        (['.github/workflows/audit.yml'], 'all'),
        (['src/Announcements/Livewire/Bar.php', 'src/Embed/Filament/Website.php'], 'overview,server,admin,notify,klant'),
    ]
    bad = [(files, want, parts_for(files)) for files, want in cases if parts_for(files) != want]
    for files, want, got in bad:
        print(f'parts.py: {files} -> {got!r}, expected {want!r}', file=sys.stderr)
    if bad:
        sys.exit(1)
    print(f'parts.py: {len(cases)} cases ok')


if __name__ == '__main__':
    args = sys.argv[1:]
    if args == ['--self-test']:
        self_test()
    else:
        paths = args or sys.stdin.read().splitlines()
        print(parts_for(paths))
