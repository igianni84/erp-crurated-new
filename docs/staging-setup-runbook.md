# Staging Environment Setup Runbook

## Prerequisites

- Ploi panel access: `ploi.io/panel/servers/106731`
- Server: `46.224.207.175`
- DNS management access for `giovannibroegg.it`

## 1. DNS Configuration

Add an A record pointing to the server:

```
cruratedstaging.giovannibroegg.it  →  46.224.207.175
```

## 2. Ploi Panel — Create Site

1. Go to Ploi panel → Server `106731` → **Add Site**
2. Domain: `cruratedstaging.giovannibroegg.it`
3. PHP version: **8.5**
4. Web directory: `/public`
5. Click **Create**

## 3. SSL Certificate

1. In the site panel → **SSL**
2. Click **Request Certificate** (Let's Encrypt)
3. Wait for provisioning to complete

## 4. Database

The staging database has already been created:

| Setting  | Value                  |
|----------|------------------------|
| Host     | `127.0.0.1`           |
| Port     | `3306`                |
| Database | `erpcrurated_staging` |
| Username | `erpcrurated_staging` |
| Password | *(stored in Ploi)*    |

## 5. Git Repository

1. In the site panel → **Repository**
2. Repository: `igianni84/erp-crurated-new`
3. Branch: `develop`
4. Enable **Auto Deploy** on push (or rely on GitHub Actions)

## 6. Environment Variables

1. In the site panel → **Environment**
2. Copy contents of `.env.staging.example` from the repo
3. Fill in the secrets:
   - `APP_KEY` — generate with `php artisan key:generate --show`
   - `DB_PASSWORD` — `IPsIYB5HsLWkThtXtqRa`
   - Any integration keys (Stripe test, Xero, Liv-ex) as they become available

## 7. Deploy Script

Set the deploy script in Ploi (site → **Deploy Script**):

```bash
cd /home/ploi/cruratedstaging.giovannibroegg.it
git fetch origin develop
git reset --hard origin/develop
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
echo "" | sudo -S service php8.5-fpm reload

# Pre-migration backup
BACKUP_FILE="/home/ploi/backups/staging_$(date +%Y%m%d_%H%M%S).sql.gz"
mkdir -p /home/ploi/backups
mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" -h 127.0.0.1 erpcrurated_staging \
  --single-transaction --quick | gzip > "$BACKUP_FILE"

php artisan migrate --force
npm ci && npm run build
php artisan filament:optimize
php artisan optimize
```

## 8. GitHub Actions Secrets

In the repository settings → **Settings → Secrets and Variables → Actions**, create an environment called `staging` with:

| Secret             | Value                                                |
|--------------------|------------------------------------------------------|
| `STAGING_HOST`     | `46.224.207.175`                                     |
| `STAGING_USER`     | `ploi`                                               |
| `STAGING_SSH_KEY`  | Contents of `~/.ssh/id_ed25519` (private key)        |
| `STAGING_PATH`     | `/home/ploi/cruratedstaging.giovannibroegg.it`       |

## 9. Initial Deploy

```bash
# SSH into the server
ssh ploi@46.224.207.175

# Navigate to site
cd /home/ploi/cruratedstaging.giovannibroegg.it

# Run initial setup
composer install --no-interaction --prefer-dist --optimize-autoloader
cp .env.staging.example .env
# Edit .env with correct values
php artisan key:generate
php artisan migrate --force --seed
npm ci && npm run build
php artisan filament:optimize
php artisan optimize
```

## 10. Verification

After deploy, verify:

1. Site loads: `https://cruratedstaging.giovannibroegg.it`
2. Admin panel: `https://cruratedstaging.giovannibroegg.it/admin`
3. Login works with seeded credentials
4. Database migrations ran successfully: `php artisan migrate:status`

## Quick SSH Commands

```bash
# Logs
ssh ploi@46.224.207.175 "tail -50 /home/ploi/cruratedstaging.giovannibroegg.it/storage/logs/laravel.log"

# Tinker
ssh ploi@46.224.207.175 "cd /home/ploi/cruratedstaging.giovannibroegg.it && php artisan tinker"

# Fresh seed (safe on staging)
ssh ploi@46.224.207.175 "cd /home/ploi/cruratedstaging.giovannibroegg.it && php artisan migrate:fresh --force --seed"

# Clear caches
ssh ploi@46.224.207.175 "cd /home/ploi/cruratedstaging.giovannibroegg.it && php artisan filament:optimize-clear && php artisan optimize:clear"
```
