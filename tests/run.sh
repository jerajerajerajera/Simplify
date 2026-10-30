#!/usr/bin/env bash
# Runs every check that needs no panel: php -l, the unit tests, the translations, the seasonal dates and the Pelican Hub checks.
set -euo pipefail
cd "$(dirname "$0")/.."
find src tests resources lang -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
php tests/addons.php
php tests/overview.php
php tests/server-groups.php
php tests/host-health.php
php tests/push.php
php tests/announcements-rules.php
php tests/announcements-game-chat.php
php tests/embed.php
php tests/subdomains.php
php tests/lang.php
node tests/seasons.cjs
python3 tests/hub-check.py
