#!/usr/bin/env bash
# Runs every check that needs no panel: php -l, the unit tests, the translations, the seasonal dates and the Pelican Hub checks.
set -euo pipefail
cd "$(dirname "$0")/.."
find src tests resources lang -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
php tests/addons.php
php tests/overview.php
php tests/admin-tree.php
php tests/legacy-css.php
php tests/server-groups.php
php tests/host-health.php
php tests/health-hook.php
php tests/problems.php
php tests/migrations-keep.php
php tests/push.php
php tests/announcements-rules.php
php tests/announcements-game-chat.php
php tests/embed.php
php tests/subdomains.php
php tests/subdomains-import.php
php tests/hub.php
php tests/assets.php
php tests/lang.php
php tests/changelog.php
bash tests/secrets.sh
node tests/seasons.cjs
node tests/unsaved.cjs
node tests/sheet-drag.cjs
node tests/sheet-motion.cjs
node tests/tooltip-navigate.cjs
node tests/sort-menu.cjs
node tests/boot.cjs
node tests/shell-footer.cjs
node tests/admin-crumb-row.cjs
node tests/admin-crumb-fit.cjs
node tests/labeled-from.cjs
node tests/settings-nav-wrap.cjs
node tests/beta-footer.cjs
node tests/standalone-viewport.cjs
node tests/phone-input-zoom.cjs
node tests/overview-footer.cjs
node tests/server-stats.cjs
node tests/overview-filters.cjs
node tests/card-bars.cjs
node tests/health-pill-steady.cjs
node tests/admin-menu-page.cjs
node tests/admin-menu-slide.cjs
node tests/embed-preview.cjs
node tests/console-placeholders.cjs
node tests/console-header-actions.cjs
node tests/console-title-clicks.cjs
python3 tests/hub-check.py
# The audit scripts live on the branch audit-tools (checked out into tests/audit by audit.yml), not in the plugin.
if [ -f tests/audit/parts.py ]; then python3 tests/audit/parts.py --self-test; fi
# What a download of the repository holds (the Hub's zip): every file the plugin runs on, nothing of the tests or docs.
archive="$(git archive HEAD 2>/dev/null | tar -t 2>/dev/null || true)"
if [ -n "$archive" ]; then
    for need in plugin.json README.md LICENSE.md src/ resources/ lang/ database/; do
        grep -qx "$need" <<<"$archive" || grep -q "^$need" <<<"$archive" || { echo "FAIL the repository download misses $need"; exit 1; }
    done
    if grep -qE '^(docs|tests|scripts|\.github)/' <<<"$archive"; then echo "FAIL the repository download still holds docs, tests, scripts or .github"; exit 1; fi
    echo "PASS the repository download holds the plugin without docs, tests, scripts and .github"
fi
