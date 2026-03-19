# Production Deployment Checklist

## 1. Server Prerequisites

- [ ] PHP 8.5 with extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `redis`
- [ ] MySQL 8.0+
- [ ] Redis 7.0+ (required for Horizon, cache, queue, session)
- [ ] Composer 2.x
- [ ] Node.js 20+ / npm (for asset compilation)
- [ ] Supervisor (for Horizon process management)

## 2. Environment Configuration

- [ ] Copy `.env.production.example` to `.env`
- [ ] Generate app key: `php artisan key:generate`
- [ ] Set `APP_DEBUG=false` (never `true` in production)
- [ ] Set `APP_URL` to production domain with `https://`
- [ ] Configure database credentials (`DB_*`)
- [ ] Configure Redis credentials (`REDIS_*`)
- [ ] Set `QUEUE_CONNECTION=redis`
- [ ] Set `CACHE_STORE=redis`
- [ ] Set `SESSION_DRIVER=redis`
- [ ] Set `SESSION_SECURE_COOKIE=true`
- [ ] Set `BCRYPT_ROUNDS=14`
- [ ] Configure Stripe keys (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`)
- [ ] Configure Xero credentials (`XERO_*`)
- [ ] Configure Sentry DSN (`SENTRY_LARAVEL_DSN`)

## 3. Security Hardening

- [ ] `APP_DEBUG=false` confirmed
- [ ] `SESSION_SECURE_COOKIE=true` (HTTPS only)
- [ ] `SESSION_ENCRYPT=true`
- [ ] `SESSION_HTTP_ONLY=true`
- [ ] Strong `REDIS_PASSWORD` set
- [ ] Strong `DB_PASSWORD` set
- [ ] SecurityHeaders middleware active (HSTS, X-Frame-Options, CSP — already in app)
- [ ] `.env` file permissions: `chmod 600 .env`
- [ ] Storage directory permissions: `chmod -R 775 storage bootstrap/cache`
- [ ] Debug routes disabled (Telescope, Debugbar not installed in prod)

## 4. Queue & Horizon Setup

### Supervisor Configuration

Create `/etc/supervisor/conf.d/crurated-horizon.conf`:

```ini
[program:crurated-horizon]
process_name=%(program_name)s
command=php /home/ploi/crurated.giovannibroegg.it/artisan horizon
autostart=true
autorestart=true
user=ploi
redirect_stderr=true
stdout_logfile=/home/ploi/crurated.giovannibroegg.it/storage/logs/horizon.log
stopwaitsecs=3600
```

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start crurated-horizon
```

### Queue Configuration

- [ ] `QUEUE_CONNECTION=redis` set
- [ ] `HORIZON_PREFIX=crurated_erp_horizon:` set
- [ ] Supervisor config installed and running
- [ ] `php artisan horizon:status` returns "running"
- [ ] Horizon dashboard accessible at `/horizon` (SuperAdmin only)
- [ ] `horizon:snapshot` scheduled every 5 minutes (in `routes/console.php`)

### Queue Monitoring

- Finance jobs (`finance` queue) — highest priority, 30s wait threshold
- Default jobs (`default` queue) — general processing
- Notification jobs (`notifications` queue) — lowest priority

## 5. Monitoring & Alerting

### Health Check

- [ ] `GET /api/health` returns 200 with all checks passing
  - Database connectivity
  - Cache read/write
  - Storage read/write
  - Queue status (pending + failed jobs)
  - Redis connectivity (when configured)

### Metrics

- [ ] `GET /api/metrics` returns queue, database, storage, and runtime metrics
- [ ] External monitoring tool configured to poll `/api/health` (recommended: every 60s)
- [ ] Alert on `/api/health` returning 503 (degraded)

### Error Tracking

- [ ] Sentry DSN configured
- [ ] Slack webhook configured for `LOG_STACK=daily,slack`
- [ ] Verify errors reach Sentry: `php artisan tinker` → `throw new \Exception('Sentry test')`

## 6. Backup Strategy

See [Disaster Recovery Documentation](disaster-recovery.md) for full details.

- [ ] Automated MySQL backups (pre-deploy backup in Ploi deploy script)
- [ ] Backup retention policy configured
- [ ] Backup restoration tested

## 7. Deployment Process

Deploy script is configured in Ploi (see CLAUDE.md for full script). Key steps:

1. `git fetch origin main && git reset --hard origin/main`
2. `composer install --no-dev --optimize-autoloader`
3. PHP-FPM reload
4. Pre-migration database backup
5. `php artisan migrate --force`
6. `php artisan filament:optimize`
7. `php artisan optimize`

### Post-Deploy (automatic via Supervisor)

- Horizon auto-restarts on deploy (Supervisor `autorestart=true`)
- If manual restart needed: `php artisan horizon:terminate` (graceful)

## 8. Post-Deployment Verification

- [ ] `curl -s https://YOUR_DOMAIN/api/health | jq .status` → `"healthy"`
- [ ] `curl -s https://YOUR_DOMAIN/api/metrics | jq .runtime.environment` → `"production"`
- [ ] `php artisan horizon:status` → running
- [ ] Login to admin panel works
- [ ] Scheduled tasks visible: `php artisan schedule:list`
- [ ] No errors in `storage/logs/laravel.log` after deploy
- [ ] Sentry receiving events (if configured)

## 9. Scheduled Tasks

The following jobs run on schedule (see `routes/console.php`):

| Schedule | Job | Queue |
|----------|-----|-------|
| Every minute | ExpireReservationsJob | default |
| Every minute | ExpireTransfersJob | default |
| Daily 05:00 | GenerateStorageBillingJob (1st of month) | finance |
| Daily 06:00 | ProcessSubscriptionBillingJob | finance |
| Daily 08:00 | IdentifyOverdueInvoicesJob | finance |
| Daily 09:00 | SuspendOverdueSubscriptionsJob | finance |
| Daily 10:00 | BlockOverdueStorageBillingJob | finance |
| Hourly | AlertUnpaidImmediateInvoicesJob | default |
| Daily 03:00 | CleanupIntegrationLogsJob | default |
| Daily 03:30 | ArchiveAuditLogsJob | default |
| Daily | ApplyBottlingDefaultsJob | default |
| Every 5 min | horizon:snapshot | — |

Cron entry (Ploi manages this):

```
* * * * * cd /home/ploi/crurated.giovannibroegg.it && php artisan schedule:run >> /dev/null 2>&1
```
