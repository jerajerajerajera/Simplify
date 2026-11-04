#!/usr/bin/env bash
# Puts one audit result in a folder of its own on the one results branch, audit-results, instead of a branch per
# result. The branch is always a single commit: every job replaces its own folder and drops folders older than
# 14 days, then swaps the branch only when nobody else pushed in between, and tries again when someone did.
#
#   publish.sh <name> <result folder> <remote url>
#
# Reading a result: git fetch --depth 1 origin audit-results && git show FETCH_HEAD:<name>/style.md
set -euo pipefail

name="$1"
source="$(cd "$2" && pwd)"
remote="$3"
branch=audit-results
keep=$((14 * 24 * 3600))

case "$name" in
  '' | */* | .*) echo "publish.sh: bad result name '$name'" >&2; exit 2 ;;
esac

for try in 1 2 3 4 5 6 7 8; do
  work="$(mktemp -d)"
  cd "$work"
  old=''
  # Only commits and trees: the screenshots of other results stay on GitHub.
  if git clone -q --filter=blob:none --depth 1 --no-checkout --branch "$branch" "$remote" results 2>/dev/null; then
    cd results
    old="$(git rev-parse HEAD)"
    git read-tree "$old"
    git show "$old:stamps.txt" > stamps.old 2>/dev/null || : > stamps.old
  else
    git init -q results
    cd results
    : > stamps.old
  fi

  now="$(date +%s)"
  : > stamps.txt
  while read -r other stamp; do
    [ -n "$other" ] || continue
    if [ "$other" = "$name" ]; then
      continue
    elif [ $((now - stamp)) -gt "$keep" ]; then
      git rm -r -q --cached --ignore-unmatch -- "$other"
    else
      echo "$other $stamp" >> stamps.txt
    fi
  done < stamps.old
  echo "$name $now" >> stamps.txt

  git rm -r -q --cached --ignore-unmatch -- "$name"
  mkdir -p "$name"
  cp -R "$source/." "$name/"
  git add -f -- "$name" stamps.txt
  tree="$(git write-tree --missing-ok)"
  commit="$(git -c user.name=audit -c user.email=audit@users.noreply.github.com commit-tree "$tree" -m "Audit results ($name)")"

  if git push -q --force-with-lease="refs/heads/$branch:$old" "$remote" "$commit:refs/heads/$branch"; then
    echo "Result on $branch, folder $name"
    exit 0
  fi
  cd /
  rm -rf "$work"
  sleep $((try * 3 + RANDOM % 5))
done
echo "publish.sh: could not update $branch" >&2
exit 1
