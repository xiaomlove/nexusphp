#!/usr/bin/env bash
#
# scripts/legacy-loc.sh
#
# Reports the file count and line-of-code count for the procedural-PHP
# legacy layer. Used by:
#   - .github/workflows/legacy-freeze.yml (CI step summary)
#   - local development to track Phase 1+ migration progress
#
# Phase 0 rule: this number must be MONOTONICALLY DECREASING.
# New PHP files MUST NOT be added to these paths. See:
#   - CONTRIBUTING.md
#   - docs/legacy-strategy.md
#
# The "legacy" layer is defined as:
#   - public/*.php  (top-level only — these are the legacy entry points)
#   - include/**/*.php  (bittorrent.php, functions.php, globalfunctions.php, ...)
#   - classes/**/*.php  (the old class hierarchy)
#
# Anything under nexus/, app/, routes/, resources/views/, database/,
# tests/ is the modern Laravel layer and is NOT counted here.
#
# Usage:
#   bash scripts/legacy-loc.sh          # human-readable table
#   bash scripts/legacy-loc.sh --plain  # machine-readable: "files\tloc"

set -euo pipefail

cd "$(dirname "$0")/.."

shopt -s nullglob globstar

TOTAL_FILES=0
TOTAL_LOC=0

print_group() {
    local label="$1"; shift
    local files=0 loc=0 file_loc f
    for f in "$@"; do
        if [ -f "$f" ]; then
            files=$((files + 1))
            file_loc=$(wc -l < "$f")
            loc=$((loc + file_loc))
        fi
    done
    printf '%-30s %5d files %8d LOC\n' "$label" "$files" "$loc"
    TOTAL_FILES=$((TOTAL_FILES + files))
    TOTAL_LOC=$((TOTAL_LOC + loc))
}

print_group 'public/*.php (top-level)' public/*.php
print_group 'include/**/*.php'         include/**/*.php
print_group 'classes/**/*.php'         classes/**/*.php

echo '---'
printf '%-30s %5d files %8d LOC\n' 'TOTAL legacy' "$TOTAL_FILES" "$TOTAL_LOC"

if [ "${1:-}" = '--plain' ]; then
    printf '%d\t%d\n' "$TOTAL_FILES" "$TOTAL_LOC"
fi
