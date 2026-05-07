#!/usr/bin/env bash
#
# scripts/e2e-stack-up.sh
#
# Bring the full NexusPHP stack up locally for end-to-end (Playwright)
# testing, idempotently. Safe to re-run.
#
# What it does:
#   1. Generates a `.env` from `.env.example` if missing, and writes
#      docker-compatible defaults (DB_HOST=mysql, REDIS_HOST=redis).
#   2. Builds the `php` and `openresty` images on first run.
#   3. Brings `mysql` + `redis` up and waits for both to be reachable.
#   4. Brings `php` up (which auto-runs `composer install` on first
#      boot).
#   5. Runs `php artisan migrate:fresh --no-interaction`.
#   6. Runs `php artisan e2e:bootstrap`, which seeds settings, the
#      three E2E users (e2eadmin/e2estaff/e2euser), creates the
#      `dont_delete_install.lock`, and disables CAPTCHA + JS challenge
#      auth so plain HTTP `POST /takelogin.php` works.
#   7. Brings the rest of the stack up (queue, scheduler, cleanup,
#      reverb, openresty, phpmyadmin).
#   8. Runs a smoke check: `curl /` should resolve to a 2xx/3xx and
#      a logged-in fetch of `/usercp.php` should be 200.
#
# Required tools: docker, docker compose v2, curl, nc.
#
# Environment variables (all optional):
#   NP_PORT          host port openresty binds to (default 80)
#   NP_REVERB_PORT   host port reverb binds to    (default 8080)
#   DB_PASSWORD      mysql password               (default nexusphp)
#   E2E_RESET        if "1", drops all volumes before booting
#                    (forces a fully clean install)
#

set -euo pipefail

# Move to the repository root regardless of where the script is invoked from.
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_DIR"

echo_info()    { printf '\033[0;34m[INFO]\033[0m %s\n'    "$*"; }
echo_success() { printf '\033[0;32m[OK]\033[0m %s\n'      "$*"; }
echo_warn()    { printf '\033[1;33m[WARN]\033[0m %s\n'    "$*"; }
echo_error()   { printf '\033[0;31m[ERROR]\033[0m %s\n'   "$*" >&2; }

require_tool() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo_error "missing required tool: $1"
    exit 1
  fi
}

require_tool docker
require_tool curl
# nc is optional (used only for the reverb readiness check)

if ! docker compose version >/dev/null 2>&1; then
  echo_error "docker compose v2 is required"
  exit 1
fi

# ---------------------------------------------------------------------------
# 0. Optional reset
# ---------------------------------------------------------------------------
if [ "${E2E_RESET:-0}" = "1" ]; then
  echo_warn "E2E_RESET=1 — destroying volumes and containers"
  docker compose down -v --remove-orphans || true
fi

# ---------------------------------------------------------------------------
# 1. Generate / patch .env
# ---------------------------------------------------------------------------
if [ ! -f .env ]; then
  echo_info "creating .env from .env.example"
  cp .env.example .env
fi

# These hostnames must point at the docker-compose services.
sed -i 's|^DB_HOST=.*|DB_HOST=mysql|'    .env
sed -i 's|^REDIS_HOST=.*|REDIS_HOST=redis|' .env
# `MYSQL_USER=root` is rejected by the mysql:9 image; use a regular
# user. Safe to overwrite — only affects local docker.
sed -i 's|^DB_USERNAME=.*|DB_USERNAME=nexusphp|' .env
sed -i 's|^DB_PASSWORD=.*|DB_PASSWORD=nexusphp|' .env
sed -i 's|^DB_DATABASE=.*|DB_DATABASE=nexusphp|' .env
sed -i 's|^LOG_FILE=.*|LOG_FILE=php://stdout|'   .env

# Export the same values for `docker compose` interpolation.
export DB_USERNAME=nexusphp
export DB_PASSWORD=nexusphp
export DB_DATABASE=nexusphp
export NP_DOMAIN="${NP_DOMAIN:-localhost}"
export NP_PORT="${NP_PORT:-80}"
export NP_REVERB_PORT="${NP_REVERB_PORT:-8080}"

if [ "$NP_PORT" = "$NP_REVERB_PORT" ]; then
  echo_error "NP_PORT ($NP_PORT) must differ from NP_REVERB_PORT ($NP_REVERB_PORT)"
  exit 1
fi

# ---------------------------------------------------------------------------
# 2. Build images
# ---------------------------------------------------------------------------
echo_info "building docker images (php + openresty)"
docker compose build php openresty >/dev/null

# ---------------------------------------------------------------------------
# 3. Start mysql + redis and wait for readiness
# ---------------------------------------------------------------------------
echo_info "starting mysql + redis"
docker compose up -d mysql redis

wait_for_container() {
  local service="$1"
  local check_cmd="$2"
  local label="$3"
  local max_seconds="${4:-60}"
  local waited=0

  while ! docker compose exec -T "$service" sh -c "$check_cmd" >/dev/null 2>&1; do
    if [ "$waited" -ge "$max_seconds" ]; then
      echo_error "$label not ready after ${max_seconds}s"
      docker compose logs --tail=20 "$service"
      return 1
    fi
    sleep 2
    waited=$((waited + 2))
  done
  echo_success "$label ready (${waited}s)"
}

wait_for_container mysql "mysqladmin ping -h127.0.0.1 -uroot -p$DB_PASSWORD --silent" "mysql"
wait_for_container redis 'redis-cli ping | grep -q PONG' "redis"

# ---------------------------------------------------------------------------
# 4. Start php (composer install runs automatically on first boot)
# ---------------------------------------------------------------------------
echo_info "starting php container"
docker compose up -d php

# Wait for vendor/ to appear (auto-installed by entrypoint).
echo_info "waiting for vendor/autoload.php (composer install)"
waited=0
while [ ! -f vendor/autoload.php ]; do
  if [ "$waited" -ge 300 ]; then
    echo_error "composer install did not finish in 5 minutes"
    docker compose logs --tail=40 php
    exit 1
  fi
  sleep 5
  waited=$((waited + 5))
done
echo_success "vendor/autoload.php exists"

# Wait for php-fpm to actually be ready to handle artisan calls.
sleep 2

# ---------------------------------------------------------------------------
# 5. Run migrations
# ---------------------------------------------------------------------------
echo_info "running migrate:fresh"
docker compose exec -T php php artisan migrate:fresh --no-interaction --force \
  | tail -20

# ---------------------------------------------------------------------------
# 6. Bootstrap the database for E2E (settings, users, install lock)
# ---------------------------------------------------------------------------
echo_info "running e2e:bootstrap"
docker compose exec -T php php artisan e2e:bootstrap

# Restart php so PHP-FPM workers pick up fresh settings (their static
# `$settings` cache survives Redis flushes).
docker compose restart php >/dev/null
sleep 5

# ---------------------------------------------------------------------------
# 7. Bring the rest of the stack up
# ---------------------------------------------------------------------------
echo_info "starting queue + scheduler + cleanup + reverb + openresty + phpmyadmin"
docker compose up -d
sleep 5

# ---------------------------------------------------------------------------
# 8. Smoke check
# ---------------------------------------------------------------------------
APP_URL="http://${NP_DOMAIN}:${NP_PORT}"

echo_info "smoke: GET ${APP_URL}/ (expect 302 -> /login.php)"
http_code="$(curl -sS -o /dev/null -w '%{http_code}' "${APP_URL}/")"
if [ "$http_code" != "200" ] && [ "$http_code" != "302" ] && [ "$http_code" != "301" ]; then
  echo_error "GET / returned $http_code (expected 2xx/3xx)"
  exit 1
fi
echo_success "GET / -> $http_code"

echo_info "smoke: POST /takelogin.php with e2eadmin"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT
login_code="$(curl -sS -c "$JAR" -o /dev/null -w '%{http_code}' \
  -X POST -d 'username=e2eadmin&password=E2eAdmin2026' \
  "${APP_URL}/takelogin.php")"
if [ "$login_code" != "302" ]; then
  echo_error "takelogin.php returned $login_code (expected 302)"
  exit 1
fi
echo_success "takelogin.php -> 302"

usercp_code="$(curl -sS -b "$JAR" -o /dev/null -w '%{http_code}' "${APP_URL}/usercp.php")"
if [ "$usercp_code" != "200" ]; then
  echo_error "/usercp.php returned $usercp_code (expected 200)"
  exit 1
fi
echo_success "/usercp.php -> 200 (logged in)"

if command -v nc >/dev/null 2>&1; then
  if nc -z -w 2 "${NP_DOMAIN}" "${NP_REVERB_PORT}"; then
    echo_success "reverb reachable on ${NP_DOMAIN}:${NP_REVERB_PORT}"
  else
    echo_warn "reverb NOT reachable on ${NP_DOMAIN}:${NP_REVERB_PORT} (BROADCAST_CONNECTION=log will still work for tests)"
  fi
fi

echo
echo_success "stack is up:"
echo "    app:        ${APP_URL}/"
echo "    filament:   ${APP_URL}/nexusphp"
echo "    livewire:   ${APP_URL}/browse"
echo "    phpmyadmin: docker compose port phpmyadmin 80"
echo "    reverb:     ws://${NP_DOMAIN}:${NP_REVERB_PORT}"
echo
echo "    e2e users:  e2eadmin / E2eAdmin2026  (CLASS_STAFF_LEADER, id=1)"
echo "                e2estaff / E2eStaff2026  (CLASS_MODERATOR)"
echo "                e2euser  / E2eUser2026   (CLASS_USER)"
echo
echo "    tear down:  scripts/e2e-stack-down.sh"
