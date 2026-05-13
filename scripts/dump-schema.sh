#!/usr/bin/env bash
#
# scripts/dump-schema.sh
#
# Regenerates database/schema/mysql-schema.sql from the current migration
# history. Run this after merging a migration that adds/changes a table,
# so that future CI runs and fresh local installs can skip replaying the
# full 225-migration timeline.
#
# Usage:
#   bash scripts/dump-schema.sh
#
# Prerequisites:
#   * A working MySQL instance configured in your .env (DB_CONNECTION=mysql)
#   * vendor/ populated (composer install)
#   * mysqldump on PATH (apt: mysql-client; brew: mysql-client)
#
# This wraps Laravel's `migrate:fresh` + `schema:dump` so we always start
# from a known-empty database — otherwise schema:dump captures whatever
# rows happen to be in your dev DB. The `--prune` flag is deliberately
# NOT passed: we keep all .php migration files so contributors with
# pre-existing databases can still upgrade incrementally.
#
# CI does NOT call this — the schema file is committed verbatim. If the
# committed schema drifts from the migration history (e.g. someone adds
# a migration without re-running this script), the gap is caught by the
# Feature suite's migrate step, which Laravel will warn about.

set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

if [ ! -f vendor/autoload.php ]; then
    echo "dump-schema: vendor/ is missing. Run \`composer install\` first." >&2
    exit 1
fi

if ! command -v mysqldump >/dev/null 2>&1; then
    echo "dump-schema: mysqldump not on PATH. Install it (apt: mysql-client, brew: mysql-client)." >&2
    exit 1
fi

echo "dump-schema: refreshing database from migrations…"
php artisan migrate:fresh --force --no-interaction >/dev/null

echo "dump-schema: writing database/schema/mysql-schema.sql…"
php artisan schema:dump

echo "dump-schema: done. Review the diff with \`git diff database/schema/\`."
echo "dump-schema: commit the result if it changed."
