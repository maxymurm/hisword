#!/bin/bash
# ────────────────────────────────────────────────────────────────────
# HisWord Deploy Script — Laravel Forge compatible
# ────────────────────────────────────────────────────────────────────
# This script is run by Forge on each deployment.
# Place it in Forge > Sites > Deploy Script, or run it manually:
#   bash deploy.sh
# ────────────────────────────────────────────────────────────────────

set -e

cd /home/forge/hisword.app/backend

# Enter maintenance mode (allow Forge IP)
php artisan down --refresh=15 --retry=60 || true

# Pull latest from git
git pull origin main

# Install PHP dependencies
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Install Node dependencies & build frontend
npm ci --production=false
npm run build

# Run database migrations
php artisan migrate --force

# Seed YES2 versions (idempotent — uses firstOrCreate)
php artisan db:seed --class=Yes2VersionSeeder --force

# Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache 2>/dev/null || true

# Restart queue workers
php artisan queue:restart

# Restart Reverb WebSocket server (if managed by supervisor)
sudo supervisorctl restart reverb 2>/dev/null || true

# Storage link (idempotent)
php artisan storage:link 2>/dev/null || true

# Exit maintenance mode
php artisan up
