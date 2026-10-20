#!/usr/bin/env bash
set -euo pipefail

# 自动定位插件目录、站点根目录与PHP
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="${PLUGIN_DIR:-$SCRIPT_DIR}"
RUNNER="$PLUGIN_DIR/bank-scheduler.php"
PHP_BIN="${PHP_BIN:-$(command -v php)}"

# 可选外部传入 SITE_ROOT/LOG_DIR/LOG_FILE/LOCK_FILE；否则智能回退
SITE_ROOT="${SITE_ROOT:-}"

if [[ -z "${SITE_ROOT}" ]]; then
  # 自上而上寻找同时包含 vendor/autoload.php 与 include/bittorrent.php 的目录
  find_site_root() {
    local d="$PLUGIN_DIR"
    for i in {1..8}; do
      if [[ -f "$d/vendor/autoload.php" && -f "$d/include/bittorrent.php" ]]; then
        echo "$d"; return 0
      fi
      local p="$(dirname "$d")"
      [[ "$p" == "$d" ]] && break
      d="$p"
    done
    return 1
  }
  if SITE_ROOT_DETECTED="$(find_site_root)"; then
    SITE_ROOT="$SITE_ROOT_DETECTED"
  else
    SITE_ROOT=""
  fi
fi

if [[ -n "${SITE_ROOT}" ]]; then
  LOG_DIR_DEFAULT="$SITE_ROOT/storage/logs"
else
  LOG_DIR_DEFAULT="$PLUGIN_DIR/logs"
fi

LOG_DIR="${LOG_DIR:-$LOG_DIR_DEFAULT}"
mkdir -p "$LOG_DIR"
LOG_FILE="${LOG_FILE:-$LOG_DIR/bank_scheduler.log}"
mkdir -p "$(dirname "$LOG_FILE")" || true

# 基于插件路径生成唯一锁文件
hash_cmd="md5sum"; command -v md5sum >/dev/null 2>&1 || hash_cmd="shasum"
lock_hash=$(echo -n "$PLUGIN_DIR" | $hash_cmd | awk '{print $1}')
LOCK_FILE="${LOCK_FILE:-/tmp/${lock_hash}_bank_scheduler.lock}"

export SITE_ROOT PHP_BIN RUNNER LOG_FILE
/usr/bin/flock -n "$LOCK_FILE" bash -c '
  ts="$(date "+%Y-%m-%d %H:%M:%S")"
  echo "[$ts] start" >> "$LOG_FILE"
  # 完全抑制所有PHP警告和通知，只显示致命错误
  "$PHP_BIN" -d error_reporting=0 -d display_errors=0 "$RUNNER" >> "$LOG_FILE" 2>&1
  ec=$?
  ts2="$(date "+%Y-%m-%d %H:%M:%S")"
  echo "[$ts2] exit code: $ec" >> "$LOG_FILE"
  exit $ec
'


