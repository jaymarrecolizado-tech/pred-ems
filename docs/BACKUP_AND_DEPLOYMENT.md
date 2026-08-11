# Backup & Deployment Procedures

> **DICT RO2 HRIS** — operational documentation for system administrators.

---

## 1. Production Deployment

### Prerequisites

- Ubuntu 22.04/24.04 server with root or sudo access
- PHP 8.2+ with extensions: mysql, xml, mbstring, curl, zip, gd, bcmath
- MySQL 8.0+ or MariaDB 10.6+
- Nginx (recommended) or Apache
- Composer 2.x
- Let's Encrypt (certbot) for HTTPS

### Initial Deployment

```bash
# 1. Clone the repository
cd /var/www
git clone <repo-url> hris
cd hris

# 2. Install dependencies (no dev packages in production)
composer install --no-dev --optimize-autoloader

# 3. Configure environment
cp .env.example .env
php artisan key:generate
# Edit .env with production values:
#   APP_ENV=production
#   APP_DEBUG=false
#   APP_URL=https://hris.dictro2.gov.ph
#   DB_DATABASE=hris
#   DB_USERNAME=hris_user
#   DB_PASSWORD=<strong-password>

# 4. Run migrations and seeders
php artisan migrate --force
# To seed the real directory (without dev seeders):
php artisan db:seed --class=RealDirectorySeeder --force

# 5. Cache configuration for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Set up the storage symlink
php artisan storage:link

# 7. Set permissions
chown -R www-data:www-data /var/www/hris
chmod -R 775 storage bootstrap/cache
```

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name hris.dictro2.gov.ph;
    root /var/www/hris/public;
    index index.php;

    # Redirect to HTTPS
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name hris.dictro2.gov.ph;
    root /var/www/hris/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/hris.dictro2.gov.ph/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/hris.dictro2.gov.ph/privkey.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Schedule & Queue Workers

```bash
# Add to crontab (runs Laravel scheduler every minute)
* * * * * cd /var/www/hris && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs:
- `leave:accrue` on the 1st of each month (VL/SL accruals)
- `sms:send` every minute (SMS queue delivery)

### Updating Production

```bash
cd /var/www/hris
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart PHP-FPM (if using OPcache)
sudo systemctl restart php8.2-fpm
```

---

## 2. Backup Strategy

> ⚠️ **Government HR data is irreplaceable.** A tested backup is not optional.

### 2.1 Database Backups ( nightly )

Create `/var/www/hris/scripts/backup-db.sh`:

```bash
#!/bin/bash
set -euo pipefail

BACKUP_DIR="/var/backups/hris/db"
RETENTION_DAYS=30
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="hris"
DB_USER="hris_backup"   # read-only user, not the app user

mkdir -p "$BACKUP_DIR"

# Dump with single-transaction for consistency (no lock)
mysqldump \
  --single-transaction \
  --routines \
  --triggers \
  --quick \
  -u "$DB_USER" -p"$DB_BACKUP_PASSWORD" \
  "$DB_NAME" | gzip > "$BACKUP_DIR/hris_${DATE}.sql.gz"

# Prune old backups
find "$BACKUP_DIR" -name "hris_*.sql.gz" -mtime +$RETENTION_DAYS -delete

echo "[$DATE] Backup completed: hris_${DATE}.sql.gz"
```

```bash
# Schedule nightly at 2:00 AM
echo "0 2 * * * /var/www/hris/scripts/backup-db.sh >> /var/log/hris-backup.log 2>&1" | crontab -
```

### 2.2 File Backups ( weekly )

Back up uploaded files and generated PDFs:

```bash
#!/bin/bash
set -euo pipefail

BACKUP_DIR="/var/backups/hris/files"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p "$BACKUP_DIR"

# Storage: profile photos, payslips, vouchers, generated PDFs
tar czf "$BACKUP_DIR/storage_${DATE}.tar.gz" -C /var/www/hris storage/app

# Config (contains .env with secrets — encrypt or restrict permissions)
tar czf "$BACKUP_DIR/config_${DATE}.tar.gz" -C /var/www/hris .env

# Prune (keep 12 weeks)
find "$BACKUP_DIR" -name "*.tar.gz" -mtime +84 -delete

echo "[$DATE] File backup completed"
```

### 2.3 Off-Site Replication

Copy backups to off-server storage (prevents total loss if the server fails):

```bash
# Option A: rsync to a NAS or secondary server
rsync -avz --delete /var/backups/hris/ backup@nas.local:/backups/hris/

# Option B: rclone to cloud storage (Google Drive, S3, etc.)
rclone sync /var/backups/hris/ remote:hris-backups/ --progress
```

### 2.4 Restore Procedure

**Test a restore at least once a month.**

```bash
# 1. Stop the app (maintenance mode)
cd /var/www/hris
php artisan down

# 2. Restore the database
gunzip < /var/backups/hris/db/hris_YYYYMMDD_HHMMSS.sql.gz | mysql -u hris_user -p hris

# 3. Restore files (if needed)
cd /var/www/hris
tar xzf /var/backups/hris/files/storage_YYYYMMDD_HHMMSS.tar.gz

# 4. Bring the app back up
php artisan up
```

### 2.5 Backup Verification Checklist

- [ ] Backup script runs without errors (check `/var/log/hris-backup.log`)
- [ ] Backup files exist and are non-zero size
- [ ] Database restore produces expected table count
- [ ] Storage restore restores photos and PDFs
- [ ] Off-site replication succeeds
- [ ] Retention policy is pruning old backups

---

## 3. Health Monitoring

### Application Health

- Laravel's `/up` endpoint returns 200 when the app is running
- Monitor: Nginx access logs for 500 errors
- Monitor: `storage/logs/laravel.log` for exceptions

### Database Health

```bash
# Check database size
mysql -u hris_user -p -e "SELECT table_schema 'hris', ROUND(SUM(data_length+index_length)/1024/1024, 1) 'MB' FROM information_schema.tables GROUP BY table_schema;"

# Check for slow queries
mysql -u hris_user -p -e "SHOW VARIABLES LIKE 'slow_query%';"
```

### SMS Gateway

- Check `sms_queue` table for stuck messages (status=pending with high attempts)
- Monitor the Android gateway device (it must be online with a signal)
- `php artisan sms:send --limit=50` processes the queue

---

## 4. Security Checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Strong `DB_PASSWORD` (not the dev default)
- [ ] Firewall (UFW): allow only 22, 80, 443
- [ ] fail2ban installed for SSH brute-force protection
- [ ] Let's Encrypt SSL certificate auto-renewing (`certbot renew`)
- [ ] `config:cache` run (so `env()` calls don't leak in production)
- [ ] `storage/` and `bootstrap/cache/` writable by `www-data`
- [ ] `.env` file permissions: `chmod 600 .env`
- [ ] MySQL backup user has read-only access (`SELECT, LOCK TABLES`)
- [ ] Account lockout active (5 failed attempts → 15-minute lockout)
- [ ] Session cookies: `secure`, `http_only`, `same_site=lax`
- [ ] Security headers middleware active (CSP, HSTS, X-Frame-Options)

---

## 5. Disaster Recovery

**RPO (Recovery Point Objective):** 24 hours (nightly DB backup)
**RTO (Recovery Time Objective):** 2 hours (server rebuild + restore)

### Emergency Contacts

| Role | Name | Contact |
|------|------|---------|
| System Admin | _(fill in)_ | _(fill in)_ |
| HR Data Owner | _(fill in)_ | _(fill in)_ |
| Hosting Provider | Hostinger | _(support ticket)_ |
