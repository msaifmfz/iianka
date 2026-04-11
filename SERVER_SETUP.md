# SERVER_SETUP

This guide explains how to deploy this Laravel 13 + Inertia React application on AlmaLinux 10 for production use with:

- PHP 8.5
- Node.js
- Redis
- SQLite
- Nginx
- Multiple environments on subdomains
- Always-running queue workers
- Always-running scheduler

The examples below use these subdomains:

- Production: `app.example.com`
- Staging: `staging.example.com`

Adjust names and paths to match your real domain.

## 1. Architecture

This setup uses:

- One AlmaLinux 10 server
- One deploy user: `iianka`
- One codebase per environment under `/var/www/iianka/<environment>/current`
- One shared `.env` and SQLite database per environment
- Redis for cache, queue, and sessions
- SQLite for the primary application database
- One PHP-FPM pool per environment
- One Nginx server block per subdomain
- One systemd service per environment for the queue worker
- One systemd service per environment for the scheduler

Recommended layout:

```text
/var/www/iianka/
├── production/
│   ├── current -> /var/www/iianka/production/releases/20260410-120000
│   ├── releases/
│   └── shared/
│       ├── .env
│       ├── database/
│       │   └── database.sqlite
│       └── storage/
├── staging/
│   ├── current -> /var/www/iianka/staging/releases/20260410-120000
│   ├── releases/
│   └── shared/
│       ├── .env
│       ├── database/
│       │   └── database.sqlite
│       └── storage/
```

## 2. Server Preparation

Update the server and install base packages:

```bash
sudo dnf update -y
sudo dnf install -y epel-release
sudo dnf install -y \
  curl git unzip tar sqlite nginx redis \
  policycoreutils-python-utils firewalld
```

Enable firewall and allow web traffic:

```bash
sudo systemctl enable --now firewalld
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

Create the deploy user:

```bash
sudo useradd --create-home --shell /bin/bash iianka
sudo passwd -l iianka
```

Create the application directories:

```bash
sudo mkdir -p /var/www/iianka/{production,staging}/{releases,shared/{database,storage/app/public,storage/framework/{cache/data,sessions,testing,views},storage/logs}}
sudo chown -R iianka:nginx /var/www/iianka
sudo chmod -R 775 /var/www/iianka
```

## 3. Install PHP 8.5

On AlmaLinux, PHP 8.5 is typically installed from Remi packages:

```bash
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-10.rpm
sudo dnf module reset php -y
sudo dnf module enable php:remi-8.5 -y
sudo dnf install -y \
  php php-cli php-common php-fpm php-opcache \
  php-mbstring php-xml php-bcmath php-intl php-pdo php-sqlite3 \
  php-process php-gd php-curl php-zip php-redis
```

Verify:

```bash
php -v
php -m | grep -E 'redis|sqlite'
```

## 4. Install Composer

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm -f composer-setup.php
composer --version
```

## 5. Install Node.js

Use Node.js 22 LTS for the frontend build:

```bash
curl -fsSL https://rpm.nodesource.com/setup_22.x | sudo bash -
sudo dnf install -y nodejs
node -v
npm -v
```

## 6. Enable Redis, PHP-FPM, and Nginx

```bash
sudo systemctl enable --now redis
sudo systemctl enable --now php-fpm
sudo systemctl enable --now nginx
```

Quick checks:

```bash
sudo systemctl status redis --no-pager
sudo systemctl status php-fpm --no-pager
sudo systemctl status nginx --no-pager
```

## 7. Deploy the Code

Switch to the deploy user:

```bash
sudo -iu iianka
```

Clone the repository separately for each environment release. Example for production:

```bash
export RELEASE_ID="$(date +%Y%m%d-%H%M%S)"
mkdir -p /var/www/iianka/production/releases/$RELEASE_ID
git clone <YOUR_GIT_REMOTE> /var/www/iianka/production/releases/$RELEASE_ID
cd /var/www/iianka/production/releases/$RELEASE_ID
git checkout <YOUR_PRODUCTION_BRANCH_OR_TAG>
```

Repeat the same pattern for staging under `/var/www/iianka/staging/releases/...`.

## 8. Install Dependencies and Build Assets

Inside each new release directory:

```bash
cd /var/www/iianka/production/releases/$RELEASE_ID
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
```

If you also use SSR in the future, build with:

```bash
npm run build:ssr
```

This repository currently only needs the standard production frontend build.

## 9. Create Shared Environment Files

Create a separate `.env` file for each environment:

```bash
mkdir -p /var/www/iianka/production/shared/database
mkdir -p /var/www/iianka/staging/shared/database
mkdir -p /var/www/iianka/production/shared/storage/{app/public,framework/{cache/data,sessions,testing,views},logs}
mkdir -p /var/www/iianka/staging/shared/storage/{app/public,framework/{cache/data,sessions,testing,views},logs}
touch /var/www/iianka/production/shared/database/database.sqlite
touch /var/www/iianka/staging/shared/database/database.sqlite
chmod 664 /var/www/iianka/production/shared/database/database.sqlite
chmod 664 /var/www/iianka/staging/shared/database/database.sqlite
```

Production `.env` example:

```dotenv
APP_NAME="Iianka"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://app.example.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=info

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/iianka/production/shared/database/database.sqlite

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

SESSION_LIFETIME=120
SESSION_PATH=/
SESSION_DOMAIN=app.example.com
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Staging `.env` example:

```dotenv
APP_NAME="Iianka Staging"
APP_ENV=staging
APP_KEY=
APP_DEBUG=false
APP_URL=https://staging.example.com

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/iianka/staging/shared/database/database.sqlite

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=2
REDIS_CACHE_DB=3

SESSION_PATH=/
SESSION_DOMAIN=staging.example.com
SESSION_SECURE_COOKIE=true
```

Notes:

- Generate a different `APP_KEY` for each environment.
- Keep `APP_DEBUG=false` in production and staging.
- Use different Redis databases for each environment.
- Use exact `SESSION_DOMAIN` values to keep staging and production isolated.
- Only use `.example.com` as the session domain if you intentionally want shared cookies across subdomains.

Generate keys:

```bash
cd /var/www/iianka/production/releases/$RELEASE_ID
ln -sfn /var/www/iianka/production/shared/.env .env
php artisan key:generate --force

cd /var/www/iianka/staging/releases/<STAGING_RELEASE_ID>
ln -sfn /var/www/iianka/staging/shared/.env .env
php artisan key:generate --force
```

## 10. Link Shared Storage and Environment

For each environment, link the shared `.env` and `storage` directory into the release:

```bash
cd /var/www/iianka/production/releases/$RELEASE_ID
rm -rf storage
ln -sfn /var/www/iianka/production/shared/.env .env
ln -sfn /var/www/iianka/production/shared/storage storage

cd /var/www/iianka/staging/releases/<STAGING_RELEASE_ID>
rm -rf storage
ln -sfn /var/www/iianka/staging/shared/.env .env
ln -sfn /var/www/iianka/staging/shared/storage storage
```

## 11. Run Database Setup and Laravel Optimization

For each environment:

```bash
cd /var/www/iianka/production/releases/$RELEASE_ID
php artisan migrate --force
# php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Then point the `current` symlink at the new release:

```bash
ln -sfn /var/www/iianka/production/releases/$RELEASE_ID /var/www/iianka/production/current
ln -sfn /var/www/iianka/staging/releases/<STAGING_RELEASE_ID> /var/www/iianka/staging/current
```

## 12. Configure PHP-FPM Pools

Create one pool per environment.

Production pool at `/etc/php-fpm.d/iianka-production.conf`:

```ini
[iianka-production]
user = iianka
group = nginx

listen = /run/php-fpm/iianka-production.sock
listen.owner = nginx
listen.group = nginx
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500

chdir = /var/www/iianka/production/current
php_admin_value[error_log] = /var/log/php-fpm/iianka-production-error.log
php_admin_flag[log_errors] = on
```

Staging pool at `/etc/php-fpm.d/iianka-staging.conf`:

```ini
[iianka-staging]
user = iianka
group = nginx

listen = /run/php-fpm/iianka-staging.sock
listen.owner = nginx
listen.group = nginx
listen.mode = 0660

pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

chdir = /var/www/iianka/staging/current
php_admin_value[error_log] = /var/log/php-fpm/iianka-staging-error.log
php_admin_flag[log_errors] = on
```

Check and restart PHP-FPM:

```bash
sudo php-fpm -t
sudo systemctl restart php-fpm
```

## 13. Configure Nginx for Multiple Subdomains

Production vhost at `/etc/nginx/conf.d/iianka-production.conf`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name app.example.com;
    root /var/www/iianka/production/current/public;

    index index.php;
    charset utf-8;

    access_log /var/log/nginx/iianka-production-access.log;
    error_log /var/log/nginx/iianka-production-error.log warn;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/iianka-production.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Staging vhost at `/etc/nginx/conf.d/iianka-staging.conf`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name staging.example.com;
    root /var/www/iianka/staging/current/public;

    index index.php;
    charset utf-8;

    access_log /var/log/nginx/iianka-staging-access.log;
    error_log /var/log/nginx/iianka-staging-error.log warn;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/iianka-staging.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Validate and reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 14. Add TLS Certificates

Install Certbot if it is available from your enabled repositories:

```bash
sudo dnf install -y certbot python3-certbot-nginx
```

Request certificates:

```bash
sudo certbot --nginx -d app.example.com -d staging.example.com
```

Verify renewal:

```bash
sudo systemctl status certbot-renew.timer --no-pager
sudo certbot renew --dry-run
```

If your AlmaLinux repositories do not provide those packages, use your preferred ACME client and update the Nginx server blocks for HTTPS manually.

## 15. Configure Always-Running Queue Workers

Create a systemd template unit at `/etc/systemd/system/iianka-queue@.service`:
Replace %i with production/staging accordingly.

```ini
[Unit]
Description=Iianka queue worker for %i
After=network.target
# Redis not needed, so remove these if queue is only using database
# After=network.target redis.service
# Requires=redis.service

[Service]
Type=simple
User=iianka
Group=nginx
WorkingDirectory=/var/www/iianka/%i/current
ExecStart=/usr/bin/php artisan queue:work --sleep=1 --tries=3 --timeout=120 --max-time=3600
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=180

[Install]
WantedBy=multi-user.target
```

Enable one worker for each environment:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now iianka-queue@production
sudo systemctl enable --now iianka-queue@staging
```

To run multiple workers for production, create more instances:

```bash
sudo cp /etc/systemd/system/iianka-queue@.service /etc/systemd/system/iianka-queue-production-2.service
```

A cleaner alternative is to keep the template and create separate named units that point to the production path if you want more concurrency.

Check logs:

```bash
sudo journalctl -u iianka-queue@production -f
sudo journalctl -u iianka-queue@staging -f
```

## 16. Configure an Always-Running Scheduler

Create `/etc/systemd/system/iianka-scheduler@.service`:
Replace %i with production/staging accordingly.

```ini
[Unit]
Description=iianka scheduler for %i
After=network.target

[Service]
Type=simple
User=iianka
Group=nginx
WorkingDirectory=/var/www/iianka/%i/current
ExecStart=/usr/bin/php artisan schedule:work
Restart=always
RestartSec=5
KillSignal=SIGTERM

[Install]
WantedBy=multi-user.target
```

Enable it for both environments:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now iianka-scheduler@production
sudo systemctl enable --now iianka-scheduler@staging
```

Check logs:

```bash
sudo journalctl -u iianka-scheduler@production -f
sudo journalctl -u iianka-scheduler@staging -f
```

Laravel’s default production approach is a cron entry running `php artisan schedule:run` every minute. This guide uses `schedule:work` because you explicitly asked for an always-running scheduler.

## 17. SELinux

If SELinux is enforcing, label the project so Nginx and PHP-FPM can read it and Laravel can write to the required directories.

Production:

```bash
sudo semanage fcontext -a -t httpd_sys_content_t '/var/www/iianka/production/current(/.*)?'
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/iianka/production/shared/storage(/.*)?'
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/iianka/production/current/bootstrap/cache(/.*)?'
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/iianka/production/shared/database(/.*)?'
sudo restorecon -Rv /var/www/iianka/production
```

Staging:

```bash
sudo semanage fcontext -a -t httpd_sys_content_t '/var/www/iianka/staging/current(/.*)?'
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/iianka/staging/shared/storage(/.*)?'
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/iianka/staging/current/bootstrap/cache(/.*)?'
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/iianka/staging/shared/database(/.*)?'
sudo restorecon -Rv /var/www/iianka/staging
```

If you serve user-uploaded files from storage through Nginx, review additional SELinux rules for that path.

## 18. First Deploy Checklist

Run these once the files and services are in place:

```bash
sudo systemctl restart redis
sudo systemctl restart php-fpm
sudo systemctl restart nginx
sudo systemctl restart iianka-queue@production
sudo systemctl restart iianka-scheduler@production
sudo systemctl restart iianka-queue@staging
sudo systemctl restart iianka-scheduler@staging
```

Then verify:

```bash
curl -I https://app.example.com
curl -I https://staging.example.com
sudo systemctl status iianka-queue@production --no-pager
sudo systemctl status iianka-scheduler@production --no-pager
```

## 19. Future Deploy Procedure

For each environment:

1. Create a new release directory.
2. Clone or update the code into that release.
3. Run `composer install --no-dev --prefer-dist --optimize-autoloader`.
4. Run `npm ci && npm run build`.
5. Link the shared `.env` and `storage`.
6. Run `php artisan migrate --force`.
7. Run:

```bash
php artisan optimize
```

8. Update the `current` symlink.
9. Restart long-running processes:

```bash
php artisan queue:restart && \
php artisan reload || true && \
sudo systemctl restart iianka-queue@production && \
sudo systemctl restart iianka-scheduler@production
```

If you add sub-minute scheduled tasks later, also run:

```bash
php artisan schedule:interrupt
```

## 20. Operational Notes

- SQLite is acceptable for low to moderate write volume on a single server.
- Do not place the SQLite file on network storage.
- Keep one SQLite database file per environment.
- Redis is strongly preferred for cache, sessions, and queues in production.
- This application already trusts reverse proxy headers, so Nginx-to-PHP-FPM behind TLS termination is fine.
- If you later add host restrictions in Laravel, configure trusted hosts for `app.example.com` and `staging.example.com`.

## 21. Useful Commands

Application health:

```bash
curl -I https://app.example.com/up
curl -I https://staging.example.com/up
```

Laravel maintenance:

```bash
php artisan about
php artisan migrate:status
php artisan queue:restart
php artisan optimize:clear
```

Logs:

```bash
sudo journalctl -u php-fpm -f
sudo journalctl -u nginx -f
sudo journalctl -u iianka-queue@production -f
sudo journalctl -u iianka-scheduler@production -f
tail -f /var/log/nginx/iianka-production-error.log
```
