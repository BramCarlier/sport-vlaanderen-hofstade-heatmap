#!/data/data/com.termux/files/usr/bin/bash

set -e

APP_DIR="$HOME/heatmap-laravel"
TMP_DIR="$HOME/tmp/heatmap-laravel"

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
require_command node nodejs

if ! command -v php-cgi >/dev/null 2>&1; then
    echo "php-cgi is not installed. Run: pkg install php-cgi"
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

echo "Opening Laravel at http://127.0.0.1:8000"
echo "Press Ctrl+C to stop the server."
node scripts/termux-server.mjs
