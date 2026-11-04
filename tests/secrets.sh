#!/usr/bin/env bash
# Fails when a tracked file holds something that never belongs in a public repository: a token in a known shape, a
# Cloudflare token with a real value, a private IP address outside tests/, or a host of the owner's own panel.
# GitHub's push protection knows token shapes only; this also catches the internal addresses of WR-12.
set -euo pipefail
cd "$(dirname "$0")/.."

found=0
check() { # check <what> <regex> [pathspec...]
    local what=$1 regex=$2
    shift 2
    local hits status=0
    hits=$(git grep -nIE -e "$regex" -- "${@:-.}" ':!tests/secrets.sh') || status=$?
    if [ "$status" -gt 1 ]; then
        echo "FAIL $what: git grep could not run"
        exit 2
    fi
    if [ -n "$hits" ]; then
        echo "FAIL $what:"
        echo "$hits" | cut -c1-160 | sed 's/^/  /'
        found=1
    fi
}

check 'Pelican API key' 'pnl_[A-Za-z0-9]{16,}'
check 'API key (sk-)' 'sk-(ant-|proj-)?[A-Za-z0-9_-]{20,}'
check 'AWS key' 'AKIA[0-9A-Z]{16}'
check 'GitHub token' 'gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,}'
check 'bearer token' 'Bearer [A-Za-z0-9._~+/-]{30,}'
check 'Cloudflare token with a value' 'CLOUDFLARE_API_TOKEN=[A-Za-z0-9_-]{30,}'
check 'private key' '-----BEGIN [A-Z ]*PRIVATE KEY-----'
check 'private IP address outside tests/' '(^|[^0-9.])(10\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}|192\.168\.[0-9]{1,3}\.[0-9]{1,3}|172\.(1[6-9]|2[0-9]|3[01])\.[0-9]{1,3}\.[0-9]{1,3})([^0-9.]|$)' . ':!tests'
check "a host of the owner's panel" '[A-Za-z0-9-]+\.deckzwabbers\.nl'

if [ "$found" = 1 ]; then
    exit 1
fi
echo 'PASS no tokens, private addresses or hosts of the owner'"'"'s panel in tracked files'
