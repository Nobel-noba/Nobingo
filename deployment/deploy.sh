#!/usr/bin/env bash
# ==============================================================================
# NOBINGO ZERO-DOWNTIME PRODUCTION DEPLOYMENT AUTOMATION SCRIPT
# ==============================================================================
set -eo pipefail

APP_DIR="/var/www/nobingo"
cd "$APP_DIR"

echo "=================================================="
echo " Starting Nobingo Deployment: $(date -u)"
echo "=================================================="

# 1. Activate Maintenance Mode with user-friendly retry
echo "-> Enabling maintenance mode..."
php artisan down --render="errors::503" --retry=60 --secret="nobingo-bypass-deploy-token" || true

# 2. Fetch latest production commits
echo "-> Pulling latest source code from git..."
git pull origin main

# 3. Install/Optimize Composer dependencies
echo "-> Installing PHP production dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# 4. Execute database migrations
echo "-> Running database migrations..."
php artisan migrate --force

# 5. Build and optimize caches
echo "-> Optimizing application caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Install and compile frontend assets
echo "-> Compiling frontend production bundle (Vite)..."
npm ci --prefer-offline --no-audit
npm run build

# 7. Restart background queues & Reverb WebSocket daemons
echo "-> Gracefully restarting queue workers and Reverb..."
php artisan queue:restart

# Reload supervisor if supervisorctl is available
if command -v supervisorctl &> /dev/null; then
    supervisorctl reread
    supervisorctl update
    supervisorctl restart nobingo-worker:* || true
    supervisorctl restart nobingo-reverb:* || true
fi

# Reload PHP-FPM for fresh OPcache
if systemctl is-active --quiet php8.5-fpm; then
    echo "-> Reloading PHP 8.5 FPM..."
    systemctl reload php8.5-fpm
fi

# 8. Deactivate Maintenance Mode
echo "-> Bringing application back online..."
php artisan up

# 9. Verify System Health Post-Deployment
echo "-> Performing post-deployment health check..."
php artisan bingo:health

echo "=================================================="
echo " Nobingo Deployment Completed Successfully: $(date -u)"
echo "=================================================="
