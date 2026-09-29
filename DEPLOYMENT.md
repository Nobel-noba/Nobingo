# Nobingo Production Deployment Guide

This guide details the step-by-step procedures for deploying and maintaining the **Nobingo 75-Ball Multi-Tenant SaaS Platform** in production environments.

---

## 1. System Requirements & Infrastructure Sizing

### 1.1 Minimum Server Specifications (Single-Node VPS / Cloud Instance)
- **CPU**: 4 vCPUs (2.5 GHz+)
- **Memory**: 8 GB RAM (Minimum 4 GB dedicated to PHP-FPM and Reverb)
- **Storage**: 50 GB NVMe SSD with automated snapshots
- **Operating System**: Ubuntu 24.04 LTS or Debian 12
- **Network**: 1 Gbps port with static IPv4 and SSL termination

### 1.2 Multi-Node High Availability Sizing (Enterprise Cluster)
- **Web Nodes (2+)**: 4 vCPUs, 8 GB RAM behind AWS ALB / Cloudflare
- **Database Node**: Managed MySQL 8.0 (AWS RDS Multi-AZ / Google Cloud SQL) with 8 vCPUs, 32 GB RAM, and Provisioned IOPS
- **Cache & Queue Node**: Managed Redis Cluster (AWS ElastiCache / Redis Cloud) with replication
- **Real-Time WebSocket Node**: Dedicated Reverb instance with Redis horizontal scaling

### 1.3 Required Runtime & Extensions
- **PHP 8.5** CLI & FPM
  - Required PHP Extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcntl`, `pcre`, `pdo_mysql`, `redis`, `session`, `tokenizer`, `xml`.
- **MySQL 8.0** (`innodb_buffer_pool_size` sized to 70% of available DB RAM)
- **Redis 7.x** (Configured with `maxmemory-policy allkeys-lru` for cache, `noeviction` for queue/session)
- **Node.js 20.x LTS** & NPM for frontend asset building
- **Nginx 1.24+** with HTTP/2 and WebSocket proxy support

---

## 2. Server Provisioning & Initial Setup

### 2.1 Install System Dependencies (Ubuntu 24.04 LTS)
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip supervisor nginx

# Add PHP 8.5 PPA
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.5-fpm php8.5-cli php8.5-mysql php8.5-redis php8.5-mbstring \
    php8.5-xml php8.5-curl php8.5-bcmath php8.5-zip php8.5-intl php8.5-pcntl

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2.2 Clone Codebase & Configure Permissions
```bash
sudo mkdir -p /var/www/nobingo
sudo chown -R $USER:www-data /var/www/nobingo
git clone git@github.com:your-org/nobingo.git /var/www/nobingo
cd /var/www/nobingo

# Set proper writable storage and bootstrap cache permissions
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

### 2.3 Environment Configuration
```bash
cp .env.production.example .env
nano .env # Configure DB credentials, Redis passwords, Reverb keys, and APP_URL
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
```

### 2.4 Pre-Generate Fixed Card Inventory
```bash
# Generate baseline 5,000 cards for primary tenants
php artisan bingo:cards:generate --company=acme-bingo --count=5000
php artisan bingo:cards:generate --company=lucky-star --count=5000
```

---

## 3. Web Server & WebSocket Configuration

### 3.1 Nginx Setup
Copy the production configuration template:
```bash
sudo cp deployment/nginx/nobingo.conf /etc/nginx/sites-available/nobingo.conf
sudo ln -s /etc/nginx/sites-available/nobingo.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

### 3.2 SSL Certificate via Let's Encrypt (Certbot)
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d nobingo.yourdomain.com
```

---

## 4. Background Process Management (Supervisor)

Nobingo requires two critical persistent daemon processes:
1. **Laravel Reverb WebSocket Server**: Dispatches real-time game numbers, card daubs, and winner notifications.
2. **Queue Workers**: Process payouts, balance transactions, audit logs, and asynchronous notifications.

### 4.1 Install Supervisor Configurations
```bash
sudo cp deployment/supervisor/nobingo-worker.conf /etc/supervisor/conf.d/
sudo cp deployment/supervisor/nobingo-reverb.conf /etc/supervisor/conf.d/

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Expected output:
```text
nobingo-reverb                   RUNNING   pid 14201, uptime 0:15:32
nobingo-worker:nobingo-worker_00 RUNNING   pid 14202, uptime 0:15:32
nobingo-worker:nobingo-worker_01 RUNNING   pid 14203, uptime 0:15:32
nobingo-worker:nobingo-worker_02 RUNNING   pid 14204, uptime 0:15:32
nobingo-worker:nobingo-worker_03 RUNNING   pid 14205, uptime 0:15:32
```

---

## 5. Scheduled Tasks (Cron)

Edit the server's crontab for user `www-data`:
```bash
sudo crontab -u www-data -e
```
Add the standard Laravel scheduler entry:
```cron
* * * * * cd /var/www/nobingo && php artisan schedule:run >> /dev/null 2>&1
```

---

## 6. Automated Zero-Downtime Deployment

For ongoing production deployments, run the pre-configured automation script:
```bash
chmod +x /var/www/nobingo/deployment/deploy.sh
/var/www/nobingo/deployment/deploy.sh
```

### Deployment Pipeline Stages:
1. **Maintenance Mode**: Activates 503 response with bypass token.
2. **Code Synchronization**: Pulls latest verified git commits.
3. **Optimized Dependencies**: Runs `composer install --no-dev -o`.
4. **Database Migrations**: Atomic schema updates via `php artisan migrate --force`.
5. **Cache Optimization**: Generates route, config, and view cache files.
6. **Frontend Bundling**: Executes `npm run build` with Vite.
7. **Daemon Replay**: Restarts queue workers and Reverb daemons gracefully.
8. **Health Diagnostic Verification**: Executes `php artisan bingo:health` to confirm nominal status.

---

## 7. Emergency Rollback Playbook

If a critical defect or migration issue occurs:
```bash
# 1. Engage maintenance mode
php artisan down --render="errors::503"

# 2. Revert code to previous release tag or git commit
git checkout v1.0.0 # or git revert HEAD

# 3. Rollback the last migration batch if needed
php artisan migrate:rollback --step=1 --force

# 4. Re-optimize and reload
php artisan optimize
php artisan queue:restart
supervisorctl restart nobingo-reverb:*

# 5. Bring application back online
php artisan up
```
