#!/usr/bin/env bash
#
# 服务器端部署脚本（PHP 直装 / 无 Docker）。
#
# 目标：把服务器目录变成 origin 的「只读工作副本」——
#   部署时只做 git fetch + reset --hard，永远不在服务器上 git add / commit / push。
#
# 首次运行会自动 git init + 添加 origin，然后用仓库内容覆盖服务器现有代码；
# 工作区里不在仓库内的文件（.env、storage、附件目录、你自己放的脚本……）不会被删除。
#
# 用法（在服务器上，建议 root 或能 sudo 到 web 用户的账号）：
#   bash deploy/update.sh                      # 默认 --ref prod
#   bash deploy/update.sh --ref dev            # 首次切换可以直接用 dev
#   bash deploy/update.sh --ref prod --dry-run # 只打印将要做什么
#
# 可用环境变量（也可用同名长参数）：
#   APP_DIR=/www/wwwroot/nexusphp
#   ORIGIN=https://github.com/PT-NovaHD/nexusphp.git
#   REF=prod
#   WEB_USER=www-data
#   PHP_BIN=php
#   COMPOSER_BIN=composer
#   PHP_FPM_SERVICE=php-fpm        # 留空则不 reload
#   BACKUP_DIR=<APP_DIR>/../nexusphp-backup
#   SKIP_DB_BACKUP=0
#
set -euo pipefail

APP_DIR="${APP_DIR:-$(pwd)}"
ORIGIN="${ORIGIN:-https://github.com/PT-NovaHD/nexusphp.git}"
REF="${REF:-prod}"
WEB_USER="${WEB_USER:-www-data}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-}"
SKIP_DB_BACKUP="${SKIP_DB_BACKUP:-0}"
DRY_RUN="${DRY_RUN:-0}"

log()  { printf '\033[1;34m[%s]\033[0m %s\n' "$(date +%H:%M:%S)" "$*"; }
warn() { printf '\033[1;33m[WARN]\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }

usage() { sed -n '2,30p' "$0" | sed 's/^# \{0,1\}//'; exit 0; }

while [ $# -gt 0 ]; do
    case "$1" in
        --app-dir)      APP_DIR="$2"; shift 2 ;;
        --origin)       ORIGIN="$2"; shift 2 ;;
        --ref)          REF="$2"; shift 2 ;;
        --web-user)     WEB_USER="$2"; shift 2 ;;
        --php)          PHP_BIN="$2"; shift 2 ;;
        --composer)     COMPOSER_BIN="$2"; shift 2 ;;
        --php-fpm)      PHP_FPM_SERVICE="$2"; shift 2 ;;
        --backup-dir)   BACKUP_DIR="$2"; shift 2 ;;
        --no-db-backup) SKIP_DB_BACKUP=1; shift ;;
        --dry-run)      DRY_RUN=1; shift ;;
        -h|--help)      usage ;;
        *) die "unknown option: $1 (use --help)" ;;
    esac
done

[ -d "$APP_DIR" ] || die "APP_DIR not found: $APP_DIR"
APP_DIR="$(cd "$APP_DIR" && pwd)"
[ -f "$APP_DIR/artisan" ] || die "$APP_DIR does not look like a nexusphp install (no artisan)"
BACKUP_DIR="${BACKUP_DIR:-$(dirname "$APP_DIR")/nexusphp-backup}"

for bin in git "$PHP_BIN"; do
    command -v "$bin" >/dev/null 2>&1 || die "missing command: $bin"
done
[ "$DRY_RUN" = "1" ] || command -v "$COMPOSER_BIN" >/dev/null 2>&1 || die "missing command: $COMPOSER_BIN"

run() {
    if [ "$DRY_RUN" = "1" ]; then
        printf '  (dry-run) %s\n' "$*"
    else
        "$@"
    fi
}

# 需要以 web 用户身份执行的命令（composer / artisan 会写 storage、bootstrap/cache）
as_web() {
    if [ "$(id -u)" = "0" ] && [ "$WEB_USER" != "root" ]; then
        run sudo -u "$WEB_USER" -H "$@"
    else
        run "$@"
    fi
}

artisan() { as_web "$PHP_BIN" "$APP_DIR/artisan" "$@"; }

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
PREV_HEAD=""

on_error() {
    local code=$?
    printf '\n' >&2
    warn "deploy failed (exit $code). Code rollback:"
    if [ -n "$PREV_HEAD" ]; then
        printf '  git -C %s update-ref refs/heads/%s %s\n' "$APP_DIR" "$REF" "$PREV_HEAD" >&2
        printf '  git -C %s symbolic-ref HEAD refs/heads/%s\n' "$APP_DIR" "$REF" >&2
        printf '  git -C %s reset --hard %s\n' "$APP_DIR" "$REF" >&2
        printf '  composer --working-dir=%s install --no-dev --optimize-autoloader\n' "$APP_DIR" >&2
    fi
    printf '  DB restore: gunzip < %s/db-*.sql.gz | mysql -u... -p... <db>\n' "$BACKUP_DIR" >&2
    exit "$code"
}
trap on_error ERR

log "app dir : $APP_DIR"
log "origin  : $ORIGIN"
log "ref     : $REF"
log "web user: $WEB_USER"
[ "$DRY_RUN" = "1" ] && warn "dry-run mode: nothing will be modified"

##############################################################################
# 1. 备份
##############################################################################
mkdir -p "$BACKUP_DIR"

log "backing up code -> $BACKUP_DIR/code-$TIMESTAMP.tgz"
run tar czf "$BACKUP_DIR/code-$TIMESTAMP.tgz" \
    -C "$APP_DIR" \
    --exclude=./.git --exclude=./vendor --exclude=./node_modules \
    --exclude=./storage --exclude=./public/attachments --exclude=./public/bitbucket \
    --exclude=./attachments --exclude=./bitbucket \
    --exclude=./imdb/cache --exclude=./imdb/images \
    .

if [ "$SKIP_DB_BACKUP" != "1" ] && [ -f "$APP_DIR/.env" ]; then
    env_val() { grep -E "^$1=" "$APP_DIR/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
    DB_DATABASE="$(env_val DB_DATABASE)"
    DB_USERNAME="$(env_val DB_USERNAME)"
    DB_PASSWORD="$(env_val DB_PASSWORD)"
    DB_HOST="$(env_val DB_HOST)"
    DB_PORT="$(env_val DB_PORT)"
    DB_HOST="${DB_HOST:-127.0.0.1}"
    DB_PORT="${DB_PORT:-3306}"
    if [ -n "$DB_DATABASE" ] && command -v mysqldump >/dev/null 2>&1; then
        log "backing up database -> $BACKUP_DIR/db-$TIMESTAMP.sql.gz"
        if [ "$DRY_RUN" = "1" ]; then
            printf '  (dry-run) mysqldump %s -> %s/db-%s.sql.gz\n' "$DB_DATABASE" "$BACKUP_DIR" "$TIMESTAMP"
        else
            MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --quick --default-character-set=utf8mb4 \
                -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" | gzip > "$BACKUP_DIR/db-$TIMESTAMP.sql.gz"
        fi
    else
        warn "mysqldump not available or DB_DATABASE empty, skipped DB backup"
    fi
else
    warn "DB backup skipped"
fi

##############################################################################
# 2. 确保目录是一个只读工作副本
##############################################################################
if [ ! -d "$APP_DIR/.git" ]; then
    log "no .git found, initializing read-only work copy"
    run git -C "$APP_DIR" init -q
    run git -C "$APP_DIR" remote add origin "$ORIGIN"
    if [ "$DRY_RUN" = "1" ]; then
        log "dry-run: repo not initialized yet, the remaining steps would run:"
        printf '  git -C %s fetch --prune origin %s\n' "$APP_DIR" "$REF"
        printf '  git -C %s update-ref refs/heads/%s FETCH_HEAD\n' "$APP_DIR" "$REF"
        printf '  git -C %s symbolic-ref HEAD refs/heads/%s\n' "$APP_DIR" "$REF"
        printf '  git -C %s reset --hard %s\n' "$APP_DIR" "$REF"
        printf '  %s --working-dir=%s install --no-dev --optimize-autoloader\n' "$COMPOSER_BIN" "$APP_DIR"
        printf '  %s %s/artisan nexus:update\n' "$PHP_BIN" "$APP_DIR"
        exit 0
    fi
else
    CURRENT_ORIGIN="$(git -C "$APP_DIR" remote get-url origin 2>/dev/null || true)"
    if [ -z "$CURRENT_ORIGIN" ]; then
        run git -C "$APP_DIR" remote add origin "$ORIGIN"
    elif [ "$CURRENT_ORIGIN" != "$ORIGIN" ]; then
        warn "origin is '$CURRENT_ORIGIN', expected '$ORIGIN' (keeping current value; pass --origin to change)"
        ORIGIN="$CURRENT_ORIGIN"
    fi
fi

PREV_HEAD="$(git -C "$APP_DIR" rev-parse --verify -q HEAD || true)"
[ -n "$PREV_HEAD" ] && log "previous HEAD: $PREV_HEAD"

##############################################################################
# 3. 拉取并覆盖工作区
##############################################################################
# fetch 只写 .git，不动工作区；dry-run 也执行，方便看清目标提交
log "fetching origin/$REF"
git -C "$APP_DIR" fetch --prune origin "$REF"
TARGET="$(git -C "$APP_DIR" rev-parse FETCH_HEAD)"
log "target commit: $TARGET ($(git -C "$APP_DIR" log -1 --format='%ci %s' "$TARGET"))"

# 让 HEAD 指向本地同名分支，再 reset --hard：
#   - 不经过 checkout，工作区里仓库之外的文件不会触发 "untracked would be overwritten"
#   - 仓库里已删除的旧文件会被清掉
#   - .env / storage / 附件目录都在 .gitignore 里，reset 不会碰
run git -C "$APP_DIR" update-ref "refs/heads/$REF" "$TARGET"
run git -C "$APP_DIR" symbolic-ref HEAD "refs/heads/$REF"
run git -C "$APP_DIR" reset --hard "$TARGET"

##############################################################################
# 4. 依赖 + 数据库升级
##############################################################################
log "composer install"
as_web "$COMPOSER_BIN" --working-dir="$APP_DIR" install --no-dev --optimize-autoloader --no-interaction --no-progress

log "php artisan nexus:update (env / extra queries / migrate)"
artisan nexus:update

log "clearing caches"
artisan optimize:clear
artisan filament:optimize || warn "filament:optimize not available, skipped"
artisan horizon:terminate || true

if [ "$(id -u)" = "0" ] && [ "$WEB_USER" != "root" ]; then
    log "fixing permissions on writable dirs"
    run chown -R "$WEB_USER:$WEB_USER" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
fi

if [ -n "$PHP_FPM_SERVICE" ]; then
    log "reloading $PHP_FPM_SERVICE"
    run systemctl reload "$PHP_FPM_SERVICE"
else
    warn "PHP_FPM_SERVICE not set, reload php-fpm manually so OPcache picks up the new code"
fi

##############################################################################
# 5. 报告
##############################################################################
log "deployed $REF@$TARGET"

if [ "$DRY_RUN" != "1" ]; then
    echo
    log "server-only files (untracked, NOT deleted by this script):"
    git -C "$APP_DIR" status --porcelain --untracked-files=all | sed -n 's/^?? //p' | head -50
    echo
    log "ignored runtime files stay untouched: .env, storage/, public/attachments, public/bitbucket, resources/upload ..."
    [ -n "$PREV_HEAD" ] && log "rollback code: git -C $APP_DIR reset --hard $PREV_HEAD"
fi
