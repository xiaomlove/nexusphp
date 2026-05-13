#!/usr/bin/env bash
#
# scripts/phpstan-baseline-guard.sh
#
# Fails if the PHPStan baseline grew compared to the PR's base branch.
#
# Why: phpstan-baseline.neon is a list of *known* type-system violations the
# codebase carries today. Every new violation should either be fixed in the
# offending file or — if that genuinely isn't possible in this PR — be added
# to the baseline as an explicit, reviewable decision. CI's existing
# `phpstan analyse` step only checks "no NEW errors" relative to the current
# baseline; nothing stops a PR from sneaking new entries INTO the baseline.
# This script closes that gap by counting entries on both sides.
#
# Usage:
#   bash scripts/phpstan-baseline-guard.sh [BASE_REF]
#
# BASE_REF defaults to "origin/php8" when unset, which matches GitHub
# Actions' merge-base behaviour for PRs targeting php8. CI may pass
# "origin/${GITHUB_BASE_REF}" explicitly.
#
# The "entries" metric is the count of `message:` keys in the baseline —
# one per ignored violation — not raw line count, so reformatting the
# baseline file (e.g. a sort) is a no-op for this guard.
#
# Exit codes:
#   0  baseline unchanged or shrunk (good)
#   1  baseline grew (fails CI)
#   2  baseline file missing on one side (skip — first-ever PR adding it)
#   3  base ref unreachable (likely a shallow clone — see CI hint below)

set -euo pipefail

BASE_REF="${1:-origin/php8}"
BASELINE="phpstan-baseline.neon"

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

count_entries() {
    # `message:` is the canonical first key of each baseline entry under the
    # Larastan/PHPStan baseline schema. One per violation. We grep the
    # literal key prefix so YAML re-indentation (2 → 4 spaces) doesn't
    # change the count.
    grep -cE '^[[:space:]]*message:' "$1" 2>/dev/null || echo 0
}

if [ ! -f "$BASELINE" ]; then
    echo "phpstan-baseline-guard: $BASELINE missing on HEAD; skipping." >&2
    exit 2
fi

if ! git rev-parse --verify "$BASE_REF" >/dev/null 2>&1; then
    echo "phpstan-baseline-guard: base ref '$BASE_REF' not found." >&2
    echo "phpstan-baseline-guard: in CI, ensure 'fetch-depth: 0' on actions/checkout, or fetch the base ref explicitly." >&2
    exit 3
fi

CURRENT_COUNT=$(count_entries "$BASELINE")

# `git show` extracts a single file at a ref without a worktree checkout.
BASE_BASELINE=$(mktemp)
trap 'rm -f "$BASE_BASELINE"' EXIT

if ! git show "${BASE_REF}:${BASELINE}" > "$BASE_BASELINE" 2>/dev/null; then
    echo "phpstan-baseline-guard: $BASELINE not present at $BASE_REF (probably first PR introducing it); skipping." >&2
    exit 2
fi

BASE_COUNT=$(count_entries "$BASE_BASELINE")

DELTA=$((CURRENT_COUNT - BASE_COUNT))

printf 'phpstan-baseline-guard: %s\n' "$BASELINE"
printf '  base (%s): %d entries\n' "$BASE_REF" "$BASE_COUNT"
printf '  head     : %d entries\n' "$CURRENT_COUNT"
printf '  delta    : %+d\n' "$DELTA"

if [ "$DELTA" -gt 0 ]; then
    echo "" >&2
    echo "phpstan-baseline-guard: FAIL — the baseline grew by $DELTA entries." >&2
    echo "  Fix the new violations in-place. If adding to the baseline is" >&2
    echo "  genuinely the right call (e.g. a third-party trait emitting a" >&2
    echo "  false positive), call out the rationale explicitly in the PR" >&2
    echo "  description so the reviewer can make an informed waiver." >&2
    echo "" >&2
    echo "  Reviewer note: to land a PR that intentionally grows the" >&2
    echo "  baseline, merge with admin override (\`Bypass branch" >&2
    echo "  protections\`); there is no per-PR label bypass." >&2
    exit 1
fi

if [ "$DELTA" -lt 0 ]; then
    echo "phpstan-baseline-guard: nice — baseline shrunk by $((-DELTA)) entries."
fi

exit 0
