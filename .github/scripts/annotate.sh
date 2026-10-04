#!/usr/bin/env bash
# Runs a command; on failure publishes its output as a GitHub annotation (readable through the
# checks API) and records the failure so a later step can fail the job. Lets every quality tool
# run in a single pass instead of stopping at the first failing one.
# Usage: annotate.sh <title> <command> [args...]
title="$1"
shift
out="$(mktemp)"

if "$@" >"$out" 2>&1; then
  cat "$out"
  exit 0
fi

cat "$out"
msg="$(head -c 30000 "$out" | sed 's/\x1b\[[0-9;]*m//g' | python3 -c 'import sys; print(sys.stdin.read().replace("%", "%25").replace("\r", "").replace("\n", "%0A"))')"
echo "::error title=${title} failed::${msg}"
echo "${title}" >>"${GITHUB_WORKSPACE}/.ci-failures"
exit 0
