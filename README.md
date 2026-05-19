# Sport Vlaanderen Hofstade Heatmap

Laravel backend for the Sport Vlaanderen Hofstade event heatmap.

## Run locally on Android with Termux

Clone or update the project in your Termux home folder:

```bash
git clone https://github.com/BramCarlier/sport-vlaanderen-hofstade-heatmap.git ~/heatmap-laravel
cd ~/heatmap-laravel
```

Install the required Termux packages:

```bash
pkg update && pkg install php php-cgi composer git unzip nodejs -y
corepack enable
```

Make the startup script executable:

```bash
chmod +x scripts/termux-start.sh
```

Start the project:

```bash
./scripts/termux-start.sh
```

Then open this URL on the same Android device:

```text
http://127.0.0.1:8000
```

Stop the project with `Ctrl+C` in the Termux session.

## What the Termux startup script does

The script:

- Goes to `~/heatmap-laravel`
- Creates `.env` from `.env.example` when missing
- Installs Composer dependencies when `vendor/` is missing
- Generates `APP_KEY` when needed
- Configures SQLite for local Android usage
- Creates `database/database.sqlite`
- Runs migrations
- Clears Laravel caches
- Installs/builds frontend assets with Yarn when available
- Starts a small Node server on `http://127.0.0.1:8000`
- Sends Laravel requests to `php-cgi`

This avoids both `php artisan serve` and `php-fpm`, because both can fail on Termux with a PHP lock-file permission error.

## Laravel Nova

Do not commit the Nova license key to GitHub.

Configure Nova locally through Composer auth or environment variables only.

Example:

```bash
composer config repositories.nova '{"type":"composer","url":"https://nova.laravel.com"}'
composer config http-basic.nova.laravel.com YOUR_NOVA_EMAIL YOUR_NOVA_LICENSE_KEY
composer require laravel/nova
```

## Useful commands

Pull the latest version:

```bash
cd ~/heatmap-laravel
git pull origin main
```

Run the app again:

```bash
./scripts/termux-start.sh
```
