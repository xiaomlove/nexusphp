#!/usr/bin/env bash
set -euo pipefail

# 自动安装/移除/查看 银行调度器的 cron 任务
# 用法：
#   ./setup-cron.sh install [--minute 5] [--site-root /path/to/site]
#   ./setup-cron.sh uninstall
#   ./setup-cron.sh show

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$SCRIPT_DIR"
CRON_MINUTE="5"
SITE_ROOT_ARG=""
LOG_FILE_ARG=""
LOG_FILE_DEFAULT=""

ACTION="${1:-}" || true
shift || true

while [[ $# -gt 0 ]]; do
  case "$1" in
    --minute)
      CRON_MINUTE="${2:-5}"; shift 2;;
    --site-root)
      SITE_ROOT_ARG="SITE_ROOT='${2:-}' "; shift 2;;
    --log-file)
      LOG_FILE_ARG="LOG_FILE='${2:-}' "; shift 2;;
    *)
      echo "Unknown option: $1" >&2; exit 2;;
  esac
done

ensure_exec() {
  chmod +x "$PLUGIN_DIR/run-bank-scheduler.sh" || true
  chmod +x "$PLUGIN_DIR/bank-scheduler.php" || true
}

cron_line() {
  local minute="$1"
  # 进入插件目录后执行脚本；可选传入 SITE_ROOT
  local log_env
  if [[ -n "$LOG_FILE_ARG" ]]; then
    log_env="$LOG_FILE_ARG"
  else
    # 默认写入插件目录日志
    LOG_FILE_DEFAULT="$PLUGIN_DIR/logs/bank_scheduler.log"
    log_env="LOG_FILE='$LOG_FILE_DEFAULT' "
  fi
  echo "${minute} * * * * ${SITE_ROOT_ARG}${log_env}/bin/bash -lc 'cd $PLUGIN_DIR && ./run-bank-scheduler.sh'"
}

install_cron() {
  ensure_exec
  local tmpf
  tmpf="$(mktemp)"
  # 过滤掉旧的相同行
  crontab -l 2>/dev/null | grep -v "run-bank-scheduler.sh" > "$tmpf" || true
  cron_line "$CRON_MINUTE" >> "$tmpf"
  crontab "$tmpf"
  rm -f "$tmpf"
  echo "[setup-cron] Installed cron:"
  cron_line "$CRON_MINUTE"
}

uninstall_cron() {
  local tmpf
  tmpf="$(mktemp)"
  crontab -l 2>/dev/null | grep -v "run-bank-scheduler.sh" > "$tmpf" || true
  crontab "$tmpf" || true
  rm -f "$tmpf"
  echo "[setup-cron] Removed cron entries for run-bank-scheduler.sh"
}

show_cron() {
  echo "[setup-cron] Current crontab (filtered):"
  crontab -l 2>/dev/null | sed -n '1,200p' | cat || true
}

verify_cron() {
  local log_path
  if [[ -n "$LOG_FILE_ARG" ]]; then
    # 直接使用传入的路径，不处理字符串
    log_path="$LOG_FILE_ARG"
  else
    log_path="$PLUGIN_DIR/logs/bank_scheduler.log"
  fi
  echo "[setup-cron] Verifying scheduler..."
  echo "[setup-cron] Log path: $log_path"
  mkdir -p "$(dirname "$log_path")" || true
  # 触发一次执行，传递正确的环境变量
  local site_root_val="${SITE_ROOT_ARG#SITE_ROOT='}"
  site_root_val="${site_root_val%'}"
  SITE_ROOT="$site_root_val" LOG_FILE="$log_path" /bin/bash -lc "cd '$PLUGIN_DIR' && ./run-bank-scheduler.sh" || true
  # 校验日志是否写入
  if [[ -f "$log_path" ]]; then
    echo "[setup-cron] OK. Log file: $log_path"
    tail -n 50 "$log_path" | cat
    exit 0
  else
    echo "[setup-cron] FAIL. Log file not found: $log_path" >&2
    echo "[setup-cron] Debug: Checking if bank-scheduler.php exists..."
    ls -la "$PLUGIN_DIR/bank-scheduler.php" || echo "bank-scheduler.php not found!"
    exit 1
  fi
}

case "$ACTION" in
  install)
    install_cron;;
  uninstall)
    uninstall_cron;;
  show)
    show_cron;;
  verify)
    verify_cron;;
  "")
    echo "Usage: $0 {install|uninstall|show|verify} [--minute N] [--site-root PATH] [--log-file FILE]"; exit 2;;
  *)
    echo "Unknown action: $ACTION"; exit 2;;
esac


