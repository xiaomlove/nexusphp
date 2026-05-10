#!/bin/sh

# 定义颜色
COLOR_RED='\033[0;31m'
COLOR_GREEN='\033[0;32m'
COLOR_YELLOW='\033[1;33m'
COLOR_BLUE='\033[0;34m'
COLOR_RESET='\033[0m'

# 封装彩色输出函数
echo_info() {
  echo -e "${COLOR_BLUE}[INFO]${COLOR_RESET} $*"
}

echo_success() {
  echo -e "${COLOR_GREEN}[SUCCESS]${COLOR_RESET} $*"
}

echo_warn() {
  echo -e "${COLOR_YELLOW}[WARN]${COLOR_RESET} $*"
}

echo_error() {
  echo -e "${COLOR_RED}[ERROR]${COLOR_RESET} $*"
}

wait_for_service() {
  name="$1"
  host="$2"
  port="$3"
  maxWaitSeconds="$4"
  waited=0

 echo_info "🔍 Checking $name at $host:$port..."

  until nc -z "$host" "$port" >/dev/null 2>&1; do
    if [ "$waited" -ge "$maxWaitSeconds" ]; then
      echo_error "❌ $name not available after ${maxWaitSeconds}s. Exiting."
      exit 1
    fi
    echo_info "⏳ Waiting for $name... (${waited}s elapsed)"
    sleep 2
    waited=$((waited + 2))
  done

  echo_success "✅ $name is available."
}

wait_for_service "MySQL" mysql 3306 30
wait_for_service "Redis" redis 6379 30

# 正式开始
echo_info "Starting container for SERVICE_NAME=$SERVICE_NAME..."

ROOT_PATH="/var/www/html"

SOURCE_DIR="${ROOT_PATH}/nexus/Install/install"
TARGET_DIR="${ROOT_PATH}/public"
ENV_FILE="${ROOT_PATH}/.env"
VENDOR_AUTOLOAD_FILE="${ROOT_PATH}/vendor/autoload.php"

# PHP-FPM runs as `www-data` and only needs write access to the two
# directories Laravel actually writes at runtime — the storage tree
# (logs, framework views/cache/sessions, app/) and bootstrap/cache
# (compiled config, routes, services). The repo is bind-mounted from
# the host (`./:/var/www/html`), so a recursive chown of $ROOT_PATH
# would also chown the host's working tree to UID 82, breaking the
# host-side `npm install` / `composer install` / IDE workflow until
# the user manually `sudo chown -R`s it back. Scoping the chown to
# the two write-required subtrees keeps both ends happy.
mkdir -p "$ROOT_PATH/storage" "$ROOT_PATH/bootstrap/cache"
chown -R www-data:www-data "$ROOT_PATH/storage" "$ROOT_PATH/bootstrap/cache"

if [ "$SERVICE_NAME" = "php" ]; then
    if [ ! -f "$ENV_FILE" ] || [ ! -f "$VENDOR_AUTOLOAD_FILE" ]; then
      echo_info ".env file: $ENV_FILE or vendor autoload file: $VENDOR_AUTOLOAD_FILE not exists, copy $SOURCE_DIR to $TARGET_DIR ..."
      cp -r "$SOURCE_DIR" "$TARGET_DIR"
      sed -i 's|LOG_FILE.*|LOG_FILE=php://stdout|g' "$ROOT_PATH/.env.example"
      if [ -f "$ENV_FILE" ]; then
        echo_info "update LOG_FILE + DB_HOST + REDIS_HOST ..."
        sed -i 's|LOG_FILE.*|LOG_FILE=php://stdout|g' "$ENV_FILE"
        sed -i 's|DB_HOST.*|DB_HOST=mysql|g' "$ENV_FILE"
        sed -i 's|REDIS_HOST.*|REDIS_HOST=redis|g' "$ENV_FILE"
      fi
    else
      echo_success ".env file: $ENV_FILE and vendor autoload file: $VENDOR_AUTOLOAD_FILE already exists, skip copy install file ..."
    fi

    # composer install
    if [ ! -f "$VENDOR_AUTOLOAD_FILE" ]; then
      echo_info "vendor autoload file: $VENDOR_AUTOLOAD_FILE not exists, run composer install ..."
      git config --global --add safe.directory ${ROOT_PATH}
      composer install --working-dir=${ROOT_PATH}
    else
      echo_success "vendor autoload file: $VENDOR_AUTOLOAD_FILE already exists, skip run composer install ..."
    fi

    # Bump PHP-FPM worker pool for the local/E2E stack. The upstream
    # default `pm.max_children = 5` saturates under 2-worker Playwright
    # runs (each browser context fires 5+ concurrent asset requests),
    # which surfaces as truncated HTML responses on /login.php and
    # /usercp.php. 20 children is overkill for one developer but keeps
    # parallel E2E suites reliable. Production deployments override
    # this image / config and are unaffected.
    FPM_CONF="/usr/local/etc/php-fpm.d/www.conf"
    if [ -f "$FPM_CONF" ]; then
      sed -i \
        -e 's|^pm.max_children\s*=.*|pm.max_children = 20|' \
        -e 's|^pm.start_servers\s*=.*|pm.start_servers = 4|' \
        -e 's|^pm.min_spare_servers\s*=.*|pm.min_spare_servers = 2|' \
        -e 's|^pm.max_spare_servers\s*=.*|pm.max_spare_servers = 8|' \
        "$FPM_CONF"
      echo_info "PHP-FPM pool tuned: max_children=20 start=4 spare=2..8"
    fi

    # 最后启动 PHP-FPM
    exec php-fpm
elif [ "$SERVICE_NAME" = "queue" ]; then
    echo_info "Start Queue Worker...";
    while true; do
      if [ -f "$ENV_FILE" ] && [ -f "$VENDOR_AUTOLOAD_FILE" ]; then
        echo_success "[Queue] env: $ENV_FILE and vendor autoload file: $VENDOR_AUTOLOAD_FILE exists, Run horizon at $(date '+%Y-%m-%d %H:%M:%S')";
        php artisan horizon;
      else
        echo_info "[Queue] .env or vendor not exists，wait 5 seconds ...";
        sleep 5;
      fi
    done
elif [ "$SERVICE_NAME" = "scheduler" ]; then
    echo_info "Start Scheduler ...";
    while true; do
      if [ -f "$ENV_FILE" ] && [ -f "$VENDOR_AUTOLOAD_FILE" ]; then
        echo_success "[Scheduler] env: $ENV_FILE and vendor autoload file: $VENDOR_AUTOLOAD_FILE exists, Run schedule:run at $(date '+%Y-%m-%d %H:%M:%S')";
        php artisan schedule:run --verbose --no-interaction;
        sleep 60;
      else
        echo_info "[Scheduler] .env or vendor not exists，wait 5 seconds...";
        sleep 5;
      fi
    done
elif [ "$SERVICE_NAME" = "cleanup" ]; then
    echo_info "Start Cleanup ...";
    while true; do
      if [ -f "$ENV_FILE" ] && [ -f "$VENDOR_AUTOLOAD_FILE" ]; then
        echo_success "[Cleanup] env: $ENV_FILE and vendor autoload file: $VENDOR_AUTOLOAD_FILE exists, Run cleanup at $(date '+%Y-%m-%d %H:%M:%S')";
        php include/cleanup_cli.php;
        sleep 60;
      else
        echo_info "[Cleanup] .env or vendor not exists，wait 5 seconds...";
        sleep 5;
      fi
    done
elif [ "$SERVICE_NAME" = "reverb" ]; then
    echo_info "Start Reverb websocket server ...";
    while true; do
      if [ -f "$ENV_FILE" ] && [ -f "$VENDOR_AUTOLOAD_FILE" ]; then
        echo_success "[Reverb] env: $ENV_FILE and vendor autoload file: $VENDOR_AUTOLOAD_FILE exists, starting reverb:start at $(date '+%Y-%m-%d %H:%M:%S')";
        php artisan reverb:start --host=0.0.0.0 --port=8080;
        echo_warn "[Reverb] reverb:start exited with code $?, restarting in 5 seconds...";
        sleep 5;
      else
        echo_info "[Reverb] .env or vendor not exists，wait 5 seconds...";
        sleep 5;
      fi
    done
else
    echo_error "Unknown SERVICE_NAME: $SERVICE_NAME, exiting."
    exit 1
fi
