#!/data/data/com.termux/files/usr/bin/bash

set -e

APP_DIR="$HOME/heatmap-laravel"
TMP_DIR="$HOME/tmp/heatmap-laravel"
NGINX_CONF="$TMP_DIR/nginx.conf"
PHP_FPM_CONF="$TMP_DIR/php-fpm.conf"
PHP_FPM_PID="$TMP_DIR/php-fpm.pid"
NGINX_PID="$TMP_DIR/nginx.pid"
PHP_FPM_SOCK="$TMP_DIR/php-fpm.sock"

cd "$APP_DIR"

echo "Starting Sport Vlaanderen Hofstade Heatmap..."

export TMPDIR="$HOME/tmp"
export TEMP="$TMPDIR"
export TMP="$TMPDIR"
mkdir -p "$TMPDIR" "$TMP_DIR"

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "$1 is not installed. Run: pkg install $2"
        exit 1
    fi
}

require_command php php
require_command composer composer
require_command nginx nginx

if ! command -v php-fpm >/dev/null 2>&1; then
    echo "php-fpm is not installed or not available. Try: pkg install php-fpm"
    echo "If Termux says php-fpm is part of php, run: pkg reinstall php"
    exit 1
fi

if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    echo "Installing Composer dependencies..."
    composer install
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "Generating app key..."
    php artisan key:generate
fi

mkdir -p database storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if ! grep -q '^DB_CONNECTION=sqlite' .env; then
    echo "Configuring SQLite database for Termux..."
    sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env || true
    sed -i 's/^DB_HOST=.*/# DB_HOST=127.0.0.1/' .env || true
    sed -i 's/^DB_PORT=.*/# DB_PORT=3306/' .env || true
    sed -i 's/^DB_DATABASE=.*/# DB_DATABASE=laravel/' .env || true
    sed -i 's/^DB_USERNAME=.*/# DB_USERNAME=root/' .env || true
    sed -i 's/^DB_PASSWORD=.*/# DB_PASSWORD=/' .env || true
fi

touch database/database.sqlite

echo "Running migrations..."
php artisan migrate --force

echo "Clearing caches..."
php artisan optimize:clear

if [ -f package.json ]; then
    if command -v yarn >/dev/null 2>&1; then
        echo "Installing/building frontend assets with Yarn..."
        yarn install
        yarn build
    elif command -v corepack >/dev/null 2>&1; then
        echo "Enabling Corepack and building frontend assets with Yarn..."
        corepack enable
        yarn install
        yarn build
    else
        echo "Yarn/Corepack is not installed. Frontend build skipped."
        echo "Install it with: pkg install nodejs && corepack enable"
    fi
fi

if [ -f "$NGINX_PID" ]; then
    OLD_NGINX_PID="$(cat "$NGINX_PID" 2>/dev/null || true)"
    if [ -n "$OLD_NGINX_PID" ]; then
        kill "$OLD_NGINX_PID" 2>/dev/null || true
    fi
fi

if [ -f "$PHP_FPM_PID" ]; then
    OLD_FPM_PID="$(cat "$PHP_FPM_PID" 2>/dev/null || true)"
    if [ -n "$OLD_FPM_PID" ]; then
        kill "$OLD_FPM_PID" 2>/dev/null || true
    fi
fi

rm -f "$PHP_FPM_SOCK"

cat > "$PHP_FPM_CONF" <<EOF
[global]
pid = $PHP_FPM_PID
error_log = $TMP_DIR/php-fpm-error.log

[www]
listen = $PHP_FPM_SOCK
listen.owner = $(whoami)
listen.group = $(whoami)
listen.mode = 0660
user = $(whoami)
group = $(whoami)
pm = ondemand
pm.max_children = 4
pm.process_idle_timeout = 10s
pm.max_requests = 200
clear_env = no
catch_workers_output = yes
php_admin_value[error_log] = $APP_DIR/storage/logs/php-fpm.log
php_admin_flag[log_errors] = on
env[APP_ENV] = local
env[TMPDIR] = $TMPDIR
EOF

cat > "$NGINX_CONF" <<EOF
worker_processes 1;
pid $NGINX_PID;
error_log $TMP_DIR/nginx-error.log;

events {
    worker_connections 256;
}

http {
    include $PREFIX/etc/nginx/mime.types;
    default_type application/octet-stream;
    access_log $TMP_DIR/nginx-access.log;
    sendfile on;

    server {
        listen 127.0.0.1:8000;
        server_name localhost;
        root $APP_DIR/public;
        index index.php index.html;

        location / {
            try_files \$uri \$uri/ /index.php?\$query_string;
        }

        location ~ \.php$ {
            include $PREFIX/etc/nginx/fastcgi_params;
            fastcgi_pass unix:$PHP_FPM_SOCK;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            fastcgi_param DOCUMENT_ROOT \$document_root;
        }

        location ~ /\.ht {
            deny all;
        }
    }
}
EOF

echo "Starting php-fpm..."
php-fpm --fpm-config "$PHP_FPM_CONF" --daemonize

echo "Starting nginx..."
nginx -c "$NGINX_CONF"

echo "Laravel is running at http://127.0.0.1:8000"
echo "Logs: $TMP_DIR"
echo "Stop with: scripts/termux-stop.sh"
