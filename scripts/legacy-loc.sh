#!/usr/bin/env bash
#
# scripts/legacy-loc.sh
#
# Reports the file count and line-of-code count for the procedural-PHP
# legacy layer. Used by:
#   - .github/workflows/legacy-freeze.yml      (CI step summary)
#   - .github/workflows/legacy-loc-history.yml (append CSV row + badge)
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
#   bash scripts/legacy-loc.sh           # human-readable table (default)
#   bash scripts/legacy-loc.sh --plain   # table + "files\tloc" trailer
#   bash scripts/legacy-loc.sh --csv     # one CSV row: date,sha,files,loc
#   bash scripts/legacy-loc.sh --badge   # shields.io endpoint JSON
#   bash scripts/legacy-loc.sh --help    # show this help

set -euo pipefail

cd "$(dirname "$0")/.."

shopt -s nullglob globstar

MODE="${1:-table}"

case "$MODE" in
    table|--table|--plain|--csv|--badge) ;;
    --help|-h)
        sed -n '3,29p' "$0" | sed 's/^# //; s/^#//'
        exit 0
        ;;
    *)
        echo "legacy-loc.sh: unknown mode '$MODE' — run with --help" >&2
        exit 2
        ;;
esac

TOTAL_FILES=0
TOTAL_LOC=0

# Collect totals per group. The table is only emitted when MODE allows it
# (table / --plain), but the group totals are always computed because
# --csv / --badge need the final TOTAL_LOC.
collect_group() {
    local label="$1"; shift
    local files=0 loc=0 file_loc f
    for f in "$@"; do
        if [ -f "$f" ]; then
            files=$((files + 1))
            file_loc=$(wc -l < "$f")
            loc=$((loc + file_loc))
        fi
    done
    if [ "$MODE" = "table" ] || [ "$MODE" = "--table" ] || [ "$MODE" = "--plain" ]; then
        printf '%-30s %5d files %8d LOC\n' "$label" "$files" "$loc"
    fi
    TOTAL_FILES=$((TOTAL_FILES + files))
    TOTAL_LOC=$((TOTAL_LOC + loc))
}

collect_group 'public/*.php (top-level)' public/*.php
collect_group 'include/**/*.php'         include/**/*.php
collect_group 'classes/**/*.php'         classes/**/*.php

case "$MODE" in
    table|--table)
        echo '---'
        printf '%-30s %5d files %8d LOC\n' 'TOTAL legacy' "$TOTAL_FILES" "$TOTAL_LOC"
        ;;
    --plain)
        echo '---'
        printf '%-30s %5d files %8d LOC\n' 'TOTAL legacy' "$TOTAL_FILES" "$TOTAL_LOC"
        printf '%d\t%d\n' "$TOTAL_FILES" "$TOTAL_LOC"
        ;;
    --csv)
        # ISO-8601 UTC date + short SHA + file count + LOC. No header —
        # the workflow seeds the header on first write. One row per
        # invocation so this is safe to `>> file.csv`.
        DATE="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
        SHA="$(git rev-parse --short HEAD 2>/dev/null || echo 'unknown')"
        printf '%s,%s,%d,%d\n' "$DATE" "$SHA" "$TOTAL_FILES" "$TOTAL_LOC"
        ;;
    --badge)
        # Shields.io endpoint badge JSON. The color band is "lower is
        # better" — Phase 0 mandates monotone decrease, so red marks
        # "still huge", green marks "almost done".
        if [ "$TOTAL_LOC" -gt 40000 ]; then
            COLOR=red
        elif [ "$TOTAL_LOC" -gt 20000 ]; then
            COLOR=orange
        elif [ "$TOTAL_LOC" -gt 5000 ]; then
            COLOR=yellow
        elif [ "$TOTAL_LOC" -gt 0 ]; then
            COLOR=green
        else
            COLOR=brightgreen
        fi
        # Render the LOC with a thousands separator so the badge stays
        # readable as the number shrinks. Formatted in pure bash via
        # sed so we don't depend on the runner locale (LC_NUMERIC=C
        # strips separators; en_US.UTF-8 may not be installed).
        LOC_FMT=$(echo "$TOTAL_LOC" | sed ':a;s/\B[0-9]\{3\}\>/,&/;ta')
        MSG="${LOC_FMT} LOC / ${TOTAL_FILES} files"
        printf '{"schemaVersion":1,"label":"legacy","message":"%s","color":"%s"}\n' \
            "$MSG" "$COLOR"
        ;;
esac
