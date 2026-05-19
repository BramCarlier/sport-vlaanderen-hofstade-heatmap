#!/data/data/com.termux/files/usr/bin/bash

set -e

cd "$HOME/heatmap-laravel"
mkdir -p storage/app

echo "Starting local Android backend at http://127.0.0.1:8000"
echo "Press Ctrl+C to stop."

node scripts/termux-node-backend.mjs
