# Disaster Recovery Plan — Crurated ERP

**Last updated:** 2026-03-19
**Owner:** Engineering
**Review cadence:** Quarterly

---

## 1. Infrastructure Overview

| Component | Details |
|-----------|---------|
| **Server** | Single VPS via Ploi (46.224.207.175) |
| **PHP** | 8.5 (FPM) |
| **Database** | MySQL 8, host 127.0.0.1:3306 |
| **Production DB** | `erpcrurated` |
| **Staging DB** | `erpcrurated_staging` |
| **Production URL** | https://crurated.giovannibroegg.it |
| **Staging URL** | https://cruratedstaging.giovannibroegg.it |
| **Deploy** | Ploi auto-deploy from `main` branch |
| **CI** | GitHub Actions (Pint, PHPStan, Tests, Security Audit) |

---

## 2. Recovery Targets

| Metric | Target | Rationale |
|--------|--------|-----------|
| **RPO** (Recovery Point Objective) | 1 hour | Pre-deploy mysqldump + recommended hourly cron |
| **RTO** (Recovery Time Objective) | 30 minutes | Single-server MySQL, Ploi re-provision capability |

---

## 3. Backup Strategy

### 3.1 Pre-Deploy Backup (Active)

Every deploy automatically creates a compressed, timestamped MySQL backup:

```bash
BACKUP_FILE="/home/ploi/backups/erpcrurated_$(date +%Y%m%d_%H%M%S).sql.gz"
mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" -h 127.0.0.1 erpcrurated \
  --single-transaction --quick | gzip > "$BACKUP_FILE"
```

Location: `/home/ploi/backups/`

### 3.2 Recommended: Hourly Cron Backup

Add to Ploi scheduled tasks:

```bash
# Every hour — compressed backup with 48h retention
BACKUP_DIR="/home/ploi/backups/hourly"
mkdir -p "$BACKUP_DIR"
BACKUP_FILE="$BACKUP_DIR/erpcrurated_$(date +%Y%m%d_%H%M%S).sql.gz"
mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" -h 127.0.0.1 erpcrurated \
  --single-transaction --quick | gzip > "$BACKUP_FILE"
# Cleanup backups older than 48 hours
find "$BACKUP_DIR" -name "*.sql.gz" -mmin +2880 -delete
```

### 3.3 Recommended: Off-Site Backup

For production-grade RPO, configure daily backups to external storage:
- S3 bucket via `aws s3 cp`
- Or Ploi's built-in backup integration

---

## 4. Recovery Runbooks

### 4.1 Database Restore from Backup

**When:** Data corruption, accidental deletion, failed migration

```bash
# SSH into server
ssh ploi@46.224.207.175

# List available backups (most recent first)
ls -lt /home/ploi/backups/*.sql.gz | head -10

# Restore from backup (DESTRUCTIVE — drops and recreates all tables)
gunzip -c /home/ploi/backups/erpcrurated_YYYYMMDD_HHMMSS.sql.gz \
  | mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" -h 127.0.0.1 erpcrurated

# Verify
cd /home/ploi/crurated.giovannibroegg.it
php artisan migrate:status
php artisan tinker --execute="echo App\Models\User::count().' users found';"
```

### 4.2 Failed Deploy Rollback

**When:** Deploy introduces a bug, broken migration, or application error

```bash
ssh ploi@46.224.207.175
cd /home/ploi/crurated.giovannibroegg.it

# Check current commit
git log --oneline -5

# Rollback to previous commit
git reset --hard HEAD~1

# If migration was the issue, restore DB first (see 4.1), then:
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
php artisan migrate --force
php artisan filament:optimize
php artisan optimize
echo "" | sudo -S service php8.5-fpm reload
```

**Alternative:** Revert the commit on GitHub and trigger a new deploy via Ploi.

### 4.3 Queue Failure Recovery

**When:** Jobs stuck in `failed_jobs` table, processing backlog

```bash
ssh ploi@46.224.207.175
cd /home/ploi/crurated.giovannibroegg.it

# View failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Retry a specific job
php artisan queue:retry <job-id>

# Flush all failed jobs (if they are no longer relevant)
php artisan queue:flush

# Check queue health
php artisan queue:monitor redis:default --max=100
```

### 4.4 Cache/Config Corruption Clear

**When:** Application shows stale data, config changes not reflected, Filament errors

```bash
ssh ploi@46.224.207.175
cd /home/ploi/crurated.giovannibroegg.it

# Clear all caches (config, route, view, events, Filament)
php artisan filament:optimize-clear
php artisan optimize:clear

# Rebuild caches
php artisan filament:optimize
php artisan optimize

# Reload PHP-FPM (clears OPcache)
echo "" | sudo -S service php8.5-fpm reload
```

### 4.5 Full Server Recovery

**When:** Server hardware failure, complete data loss

1. **Provision new server** via Ploi panel → New Server
2. **Install stack:** PHP 8.5, MySQL 8, Nginx, Redis
3. **Create site** in Ploi pointing to `github.com/igianni84/erp-crurated-new`
4. **Configure DNS** for `crurated.giovannibroegg.it`
5. **Set environment variables** from `.env.example` + production secrets
6. **Restore database** from most recent off-site backup
7. **Deploy** via Ploi panel
8. **Verify:**
   ```bash
   curl -s https://crurated.giovannibroegg.it/up
   curl -s https://crurated.giovannibroegg.it/api/health | jq .
   ```

---

## 5. Scheduled Jobs Inventory

All 11 scheduled tasks registered in `routes/console.php`:

| Job | Schedule | Purpose |
|-----|----------|---------|
| `ExpireReservationsJob` | Every minute | Expire temporary allocation reservations |
| `ExpireTransfersJob` | Every minute | Expire pending voucher transfers |
| `IdentifyOverdueInvoicesJob` | Daily 08:00 | Mark invoices as overdue |
| `ProcessSubscriptionBillingJob` | Daily 06:00 | Generate subscription invoices |
| `SuspendOverdueSubscriptionsJob` | Daily 09:00 | Suspend subscriptions with overdue INV0 |
| `AlertUnpaidImmediateInvoicesJob` | Hourly | Alert on unpaid INV1/INV2/INV4 |
| `GenerateStorageBillingJob` | Monthly 1st 05:00 | Generate storage billing (INV3) |
| `BlockOverdueStorageBillingJob` | Daily 10:00 | Block custody ops for overdue storage |
| `CleanupIntegrationLogsJob` | Daily 03:00 | Remove old Stripe/Xero logs (90d retention) |
| `ApplyBottlingDefaultsJob` | Daily | Apply bottling defaults after deadline |
| `ArchiveAuditLogsJob` | Daily 03:30 | Archive audit logs (365d) + AI logs (180d) |

Verify scheduler is running: `php artisan schedule:list`

---

## 6. Monitoring

### 6.1 Health Endpoints

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `GET /up` | GET | Public | Laravel built-in health check (200 = app running) |
| `GET /api/health` | GET | Public | Detailed health: DB, cache, storage with latency |

### 6.2 Application Monitoring

- **Sentry** — Error tracking and alerting (configured via `SENTRY_LARAVEL_DSN`)
- **Laravel Telescope** — Debug tool (disabled in production via `TELESCOPE_ENABLED=false`)
- **Server logs** — `storage/logs/laravel.log` (daily rotation)
- **Finance logs** — Dedicated `finance` channel for Stripe/Xero events

### 6.3 Recommended Monitoring Setup

- **Uptime monitoring:** Point UptimeRobot/Better Uptime at `GET /api/health`
- **Alert on:** HTTP status != 200, response time > 2s
- **Dashboard:** Grafana with MySQL metrics (connections, slow queries, replication lag)

---

## 7. Data Integrity

### 7.1 Audit Trail

- **Audit logs:** Immutable `AuditLog` model, 365-day archival via `ArchiveAuditLogsJob`
- **AI audit logs:** 180-day retention
- **Integration logs:** 90-day retention (Stripe webhooks, Xero sync logs)

### 7.2 Key Invariants

These invariants are enforced at the application level and must be verified after any data restore:

1. Every voucher has `quantity = 1` (1 voucher = 1 bottle)
2. Allocation lineage (`allocation_id`) is immutable after creation
3. Case breaking is irreversible (Intact → Broken, never back)
4. Invoice type is immutable after creation
5. Every PurchaseOrder has a ProcurementIntent

### 7.3 Post-Restore Verification

```bash
cd /home/ploi/crurated.giovannibroegg.it

# Run integrity checks
php artisan tinker --execute="
echo 'Vouchers with qty != 1: ' . App\Models\Allocation\Voucher::where('quantity', '!=', 1)->count();
echo 'Invoices: ' . App\Models\Finance\Invoice::count();
echo 'Users: ' . App\Models\User::count();
echo 'Allocations: ' . App\Models\Allocation\Allocation::count();
"

# Run full test suite (staging only)
php artisan test --compact
```

---

## 8. Contacts & Escalation

| Role | Contact | Responsibility |
|------|---------|----------------|
| Engineering Lead | Giovanni | All technical decisions, deploy, DR |
| Ploi Support | support@ploi.io | Server provisioning, DNS, SSL |
| Stripe Support | dashboard.stripe.com | Payment processing issues |
| Xero Support | developer.xero.com | Accounting sync issues |
