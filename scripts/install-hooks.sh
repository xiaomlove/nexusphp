#!/usr/bin/env bash
#
# scripts/install-hooks.sh
#
# Activates the repo-tracked git hooks under `.githooks/` for the
# current clone. This is idempotent and safe to re-run; it never
# touches your `.git/hooks/` directly and never overwrites a hooks
# path you've already pointed somewhere else (it will warn instead).
#
# Why a script and not auto-on-`composer install`?
#   - `core.hooksPath` is a per-clone setting; CI clones, deploy
#     clones, and sandbox clones explicitly should NOT run hooks.
#   - Composer's post-autoload-dump runs in those environments too,
#     so silently enabling hooks there is wrong.
#
# Usage:
#   bash scripts/install-hooks.sh

set -euo pipefail

cd "$(dirname "$0")/.."

HOOKS_DIR=".githooks"

if [ ! -d "$HOOKS_DIR" ]; then
    echo "install-hooks: $HOOKS_DIR/ not found in repo root — aborting." >&2
    exit 1
fi

# Make every tracked hook executable. Git ignores the unix x-bit
# inside the working tree by default (the +x bit on a tracked file is
# part of the index), so we re-assert it locally for clones that came
# in via `git clone` on Windows / WSL / archive download.
chmod +x "$HOOKS_DIR"/* 2>/dev/null || true

CURRENT="$(git config --get core.hooksPath 2>/dev/null || true)"

if [ "$CURRENT" = "$HOOKS_DIR" ]; then
    echo "install-hooks: core.hooksPath already set to $HOOKS_DIR — nothing to do."
    exit 0
fi

if [ -n "$CURRENT" ] && [ "$CURRENT" != "$HOOKS_DIR" ]; then
    echo "install-hooks: core.hooksPath is already set to '$CURRENT'." >&2
    echo "install-hooks: refusing to overwrite. To force, run:" >&2
    echo "    git config core.hooksPath $HOOKS_DIR" >&2
    exit 1
fi

git config core.hooksPath "$HOOKS_DIR"

echo "install-hooks: core.hooksPath set to $HOOKS_DIR."
echo "install-hooks: tracked hooks:"
for h in "$HOOKS_DIR"/*; do
    [ -f "$h" ] || continue
    case "$(basename "$h")" in
        *.md|*.txt) continue ;;
    esac
    echo "    $(basename "$h")"
done
echo "install-hooks: bypass a single commit with \`git commit --no-verify\`."
