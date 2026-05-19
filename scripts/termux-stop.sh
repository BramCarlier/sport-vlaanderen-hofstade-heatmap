#!/data/data/com.termux/files/usr/bin/bash

set -e

TMP_DIR="$HOME/tmp/heatmap-laravel"
PHP_FPM_PID="$TMP_DIR/php-fpm.pid"
NGINX_PID="$TMP_DIR/nginx.pid"

stop_pid_file() {
    local file="$1"
    local name="$2"

    if [ -f "$file" ]; then
        local pid
        pid="$(cat "$file" 2>/dev/null || true)"

        if [ -n "$pid" ]; then
            echo "Stopping $name ($pid)..."
            kill "$pid" 2>/dev/null || true
        fi

        rm -f "$file"
    else
        echo "$name is not running."
    fi
}

stop_pid_file "$NGINX_PID" "nginx"
stop_pid_file "$PHP_FPM_PID" "php-fpm"

echo "Stopped Sport Vlaanderen Hofstade Heatmap."
