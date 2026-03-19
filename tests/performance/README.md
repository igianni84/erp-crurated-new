# Performance Testing — k6

Baseline performance tests for Crurated ERP API endpoints.

## Prerequisites

```bash
# macOS
brew install k6

# Linux
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D68
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update && sudo apt-get install k6
```

## Quick Start

```bash
# Start the application
php artisan serve

# Run smoke test (all endpoints, 1 VU, ~5 seconds)
k6 run tests/performance/smoke-test.js

# Run health check load test
k6 run tests/performance/health-check.js

# Run against staging
k6 run -e BASE_URL=https://cruratedstaging.giovannibroegg.it tests/performance/smoke-test.js
```

## Available Scripts

| Script | Target | Threshold | Profiles |
|--------|--------|-----------|----------|
| `smoke-test.js` | All endpoints | Sanity check | 1 VU, 1 iteration |
| `health-check.js` | `GET /up`, `GET /api/health` | p95 < 200ms | smoke, load, stress |
| `voucher-trading.js` | `POST /api/vouchers/{id}/trading-complete` | p95 < 1000ms | smoke, load |
| `stripe-webhook.js` | `POST /api/webhooks/stripe` | p95 < 300ms | smoke, load |
| `admin-login.js` | `POST /admin/login` | p95 < 500ms | smoke, load |

## Load Profiles

Each script (except smoke-test) includes these profiles:

| Profile | VUs | Duration | Purpose |
|---------|-----|----------|---------|
| **Smoke** | 1 | 30s | Sanity check — does it work? |
| **Load** | 10 | 5 min | Normal expected traffic |
| **Stress** | 50 | 10 min | Beyond expected traffic (health-check only) |

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `BASE_URL` | `http://localhost:8000` | Target server URL |
| `HMAC_SECRET` | `test-secret` | HMAC secret for trading endpoint |
| `VOUCHER_ID` | `00000000-...` | Test voucher UUID |
| `ADMIN_EMAIL` | `admin@crurated.com` | Admin login email |
| `ADMIN_PASSWORD` | `password` | Admin login password |

## Running Individual Profiles

```bash
# Run only the smoke scenario
k6 run --scenario smoke tests/performance/health-check.js

# Run only the load scenario
k6 run --scenario load tests/performance/health-check.js
```

## Interpreting Results

k6 outputs a summary including:

- **http_req_duration** — Request latency (p50, p90, p95, p99)
- **http_req_failed** — Percentage of failed requests
- **http_reqs** — Total requests per second
- **iterations** — Completed test iterations

Key metrics to watch:
- **p95 < threshold** — 95% of requests must be faster than the threshold
- **http_req_failed < 1%** — Less than 1% of requests should fail
- **http_reqs** — Requests per second (throughput)

## Baseline Results

_Fill in after first run against staging:_

| Endpoint | p50 | p95 | p99 | RPS | Date |
|----------|-----|-----|-----|-----|------|
| `GET /up` | — | — | — | — | — |
| `GET /api/health` | — | — | — | — | — |
| `POST /api/webhooks/stripe` | — | — | — | — | — |
| `POST /admin/login` | — | — | — | — | — |
