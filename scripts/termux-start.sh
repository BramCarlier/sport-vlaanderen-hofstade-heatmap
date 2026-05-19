#!/data/data/com.termux/files/usr/bin/bash

set -e

cd "$HOME/heatmap-laravel"

echo "Starting Sport Vlaanderen Hofstade Heatmap..."

if ! command -v php >/dev/null 2>&1; then
    echo "PHP is not installed. Run: pkg install php"
    exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
    echo "Composer is not installed. Run: pkg install composer"
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

mkdir -p database

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

echo "Opening Laravel at http://127.0.0.1:8000"
php artisan serve --host=127.0.0.1 --port=8000
