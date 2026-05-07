#!/usr/bin/env bash
#
# scripts/e2e-stack-down.sh
#
# Tear down the local docker-compose stack used by Playwright tests.
#
# By default this stops + removes containers, the docker network, and
# the named volumes (`mysql-data`, `redis-data`). The local working
# tree (.env, dont_delete_install.lock, vendor/, etc.) is left
# untouched.
#
# Pass `--keep-data` to leave the volumes intact (faster restart, but
# the next `e2e-stack-up.sh` will see stale DB rows).
#

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_DIR"

if [ "${1:-}" = "--keep-data" ]; then
  echo "[INFO] stopping containers, keeping volumes"
  docker compose down --remove-orphans
else
  echo "[INFO] stopping containers and removing volumes (mysql-data, redis-data)"
  docker compose down -v --remove-orphans
fi

echo "[OK] stack is down"
