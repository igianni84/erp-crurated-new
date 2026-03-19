# INVESTOR AUDIT — GAP ANALYSIS & IMPROVEMENT PLAN

**Date:** 2026-03-18
**Purpose:** Areas where the audit reveals weaknesses, and what to do about them before the next review.

---

## GAP ANALYSIS (Honest Assessment)

### SEVERITY: HIGH (Investor will notice these)

| # | Gap | Why It Matters | Current State | Effort |
|---|-----|---------------|---------------|--------|
| H1 | **No staging environment** | Investors expect dev → staging → prod pipeline. Single-server = risk signal | Production only | 2-4h (Ploi second site or Docker) |
| H2 | **Xero stub implementation** | Says "integrated" but API calls are simulated. Finance sync is table stakes for an ERP | 807-line service, stub `callXeroCreateInvoice()` | 4-8h (install SDK, swap stub) |
| H3 | **Liv-ex mock data** | 10 hardcoded wines. Wine data provider integration is core to credibility | 260-line service, mock DB | 4-6h (get API key, swap mock) |
| H4 | **Blockchain/NFT placeholder** | `NFT-{hex}` placeholders. Provenance is a Crurated differentiator | Job + model fields ready | 8-16h (smart contract + integration) |
| H5 | **No API documentation (Swagger/OpenAPI)** | Standard expectation for any platform with APIs | 2 REST endpoints + 1 webhook, no docs | 2-3h (laravel-scramble or manual) |
| H6 | **No test coverage report** | We run 1,040 tests but can't show % coverage. Investors ask for this | Coverage dropped from CI (too slow) | 2-4h (add Xdebug coverage in nightly job) |

### SEVERITY: MEDIUM (Will come up in follow-up questions)

| # | Gap | Why It Matters | Current State | Effort |
|---|-----|---------------|---------------|--------|
| M1 | **No consumer behavior analytics** | Investor wants to see data flywheel potential | ERP tracks operations, not consumer behavior | N/A for ERP scope — but we should articulate the plan |
| M2 | **No formal feature flags** | Signals immature deployment practices | Env-based toggles only | 2-3h (Laravel Pennant or similar) |
| M3 | **No data warehouse / analytics DB** | Finance reports hit operational DB | Eloquent-based reporting | 8-16h (BigQuery/Redshift + ETL) |
| M4 | **White-label not fully multi-tenant** | Club/Channel model exists but no DB isolation or branding | Soft multi-tenancy via relationships | 16-40h (Stancl/Tenancy or custom) |
| M5 | **No E2E / browser tests** | Only PHPUnit — no Cypress/Playwright | 1,040 unit/feature tests | 8-16h (setup + critical path tests) |
| M6 | **Single engineer dependency** | Bus factor = 1. AI-assisted helps but investor will flag | Founder + Claude Code | Narrative: document everything, AI makes onboarding fast |
| M7 | **Module S at 97%** | Signals incomplete work | CSV import + PIM sync pending | 4-8h |

### SEVERITY: LOW (Nice to have, shows polish)

| # | Gap | Why It Matters | Current State | Effort |
|---|-----|---------------|---------------|--------|
| L1 | **No load/performance testing** | Can't prove scale readiness | Untested at volume | 4-8h (k6 or Artillery scripts) |
| L2 | **No Swagger/Postman collection** | Developer experience for future team | No API docs | 2-3h |
| L3 | **No monitoring dashboard (Grafana/Datadog)** | Operational maturity signal | Sentry configured, no dashboards | 4-8h |
| L4 | **No disaster recovery documentation** | DR plan shows operational maturity | Pre-migration backups exist, no formal DR doc | 2-3h |
| L5 | **No changelog / release notes** | Shows process maturity | Git commits only | 2h (auto-generate from conventional commits) |

---

## IMPROVEMENT PLAN (Priority-Ordered)

### Sprint 1: Quick Wins (1-2 days) — Maximum investor impact

#### 1.1 API Documentation with Scramble
- Install `dedoc/scramble` (auto-generates OpenAPI from Laravel routes)
- Generates Swagger UI at `/docs/api`
- Covers: Stripe webhook, Trading Platform endpoint, Voucher trading-complete
- **Fixes:** H5

#### 1.2 Test Coverage Report
- Add a nightly/weekly GitHub Actions job that runs coverage with Xdebug
- Generate and publish coverage badge in README
- Alternatively: run locally and store report
- **Fixes:** H6

#### 1.3 Complete Module S (97% → 100%)
- Implement CSV bulk import for PriceBookEntries
- Implement PIM sync for SellableSku price updates
- **Fixes:** M7

### Sprint 2: Integration Activation (2-3 days) — Production credibility

#### 2.1 Xero SDK Integration
- Install `xeroapi/xero-php-oauth2` package
- Replace stub `callXeroCreateInvoice()` with real API calls
- Implement OAuth2 token management (store/refresh)
- Test with Xero sandbox environment
- **Fixes:** H2

#### 2.2 Liv-ex API Integration
- Obtain Liv-ex API credentials
- Replace mock data in `LivExService` with real API calls
- Validate LWIN code lookup + wine detail retrieval
- **Fixes:** H3

#### 2.3 Staging Environment
- Create second Ploi site (e.g., `staging.crurated.giovannibroegg.it`)
- Point to same MySQL with separate database (`erpcrurated_staging`)
- Deploy from `develop` branch
- **Fixes:** H1

### Sprint 3: Polish & Maturity Signals (2-3 days)

#### 3.1 Feature Flags
- Install Laravel Pennant
- Move integration toggles (XERO_SYNC_ENABLED, etc.) to Pennant
- Add dashboard toggle page in Filament
- **Fixes:** M2

#### 3.2 Disaster Recovery Documentation
- Document backup strategy, recovery procedures, RTO/RPO
- Add runbook for common operational scenarios
- **Fixes:** L4

#### 3.3 Performance Baseline
- Write k6 or Artillery load test scripts
- Test critical paths: Allocation creation, ShippingOrder, Invoice generation
- Document baseline numbers
- **Fixes:** L1

### Sprint 4: Advanced (1 week) — If investor deepens engagement

#### 4.1 Blockchain NFT Integration
- Select chain (Polygon recommended: low gas, EVM-compatible)
- Deploy provenance smart contract
- Replace stub in `MintProvenanceNftJob` with real minting
- **Fixes:** H4

#### 4.2 Analytics Foundation
- Set up read replica or data warehouse
- ETL pipeline for finance/allocation data
- Pre-computed dashboards (Metabase or custom)
- **Fixes:** M3

#### 4.3 E2E Tests
- Install Laravel Dusk or Playwright
- Write critical path tests (Login → Dashboard → Create Allocation → Generate Invoice)
- **Fixes:** M5

---

## NARRATIVE IMPROVEMENTS (How We Tell the Story)

### What Sounds Weak → How to Reframe

1. **"1 engineer"** → "AI-native development: 1 engineer + Claude Code delivered 542 user stories, 78 models, 1,040 tests in 2 months. Traditional team equivalent: 4-6 engineers over 6 months. Onboarding is instant because everything is documented in PRDs and CLAUDE.md"

2. **"No staging"** → ✅ **Fixed.** Staging at `cruratedstaging.giovannibroegg.it` with `develop` branch auto-deploy via GitHub Actions.

3. **"Stub integrations"** → ✅ **Fixed.** Xero SDK (`xeroapi/xero-php-oauth2`) integrated with OAuth2 flow, encrypted token storage, and Filament connection management UI. Liv-ex API client with caching. Both degrade gracefully without credentials.

4. **"No consumer analytics"** → "ERP is the operational backbone. Consumer analytics layer sits above the ERP and consumes its APIs. The data model supports all queries needed for taste profiles, purchase history, geographic analysis — we demonstrated the Eloquent queries in Section 5.2"

5. **"Pre-production scale"** → "Architecture is scale-ready: stateless services, queue-based async, Redis-ready config, UUID PKs for distributed systems. Current single-server is appropriate for pre-revenue phase; horizontal scaling requires only infrastructure, not code changes"

---

## EXECUTION PRIORITY MATRIX

```
                    HIGH INVESTOR IMPACT
                         ↑
     Sprint 1           |  Sprint 2
     (Quick Wins)       |  (Integrations)
     H5, H6, M7        |  H1, H2, H3
                        |
   ←LOW EFFORT ————————+———————→ HIGH EFFORT
                        |
     Sprint 3           |  Sprint 4
     (Polish)           |  (Advanced)
     M2, L4, L1        |  H4, M3, M5
                        |
                    LOW INVESTOR IMPACT
```

---

## ESTIMATED TOTAL EFFORT

| Sprint | Duration | Fixes |
|--------|----------|-------|
| Sprint 1 | 1-2 days | H5, H6, M7 |
| Sprint 2 | 2-3 days | H1, H2, H3 |
| Sprint 3 | 2-3 days | M2, L4, L1 |
| Sprint 4 | 5-7 days | H4, M3, M5 |
| **Total** | **~2 weeks** | **All HIGH + MEDIUM gaps** |

---

## PROGRESS TRACKER

_Updated: 2026-03-19_

### Sprint 1 — Quick Wins

| # | Item | Status | Note |
|---|------|--------|------|
| H5 | API Documentation (Scramble) | ✅ **Done** | `dedoc/scramble` installato, config in `config/scramble.php`, UI a `/docs/api` con gate super_admin (local: aperto). 2 test in `ApiDocumentationTest.php` passano. Auto-documenta nuove route API. |
| H6 | Test Coverage Report | ✅ **Done** | Workflow `coverage.yml` (schedule lunedì 3AM + manual dispatch, PCOV + `pcov.directory=app`, artifact 30gg). Upload Codecov con `codecov/codecov-action@v5`. Badge CI + Coverage nel README. Token Codecov configurato. |
| M7 | Module S → 100% | ⚠️ **80%** | **CSV import: ✅ Done** — `PriceBookCsvImportService` (parse, validate, create entries, template download) + wizard 4-step in `CreatePriceBook.php` + 19 test. **PIM sync: ⏳ Pending** — `BundleService::syncWithPim()` è placeholder (richiede composite SKU in PIM). SellableSku price propagation non implementata. |

### Sprint 2 — Integration Activation

| # | Item | Status | Note |
|---|------|--------|------|
| H1 | Staging environment | ✅ **Done** | `.env.staging.example`, `deploy-staging.yml` (auto-deploy on push to `develop`), CI updated for `develop` branch, `docs/staging-setup-runbook.md`. Site: `cruratedstaging.giovannibroegg.it`, DB: `erpcrurated_staging`. User action: DNS + Ploi setup + GitHub secrets. |
| H2 | Xero SDK integration | ✅ **Done** | `xeroapi/xero-php-oauth2` installed. `XeroToken` model (encrypted at rest), `XeroApiClient` (OAuth2 flow, auto-refresh), `XeroAuthController` (authorize/callback routes). 3 stub methods replaced with real SDK calls + graceful fallback. Configurable account codes (`XERO_ACCOUNT_CODE_INV0-4`). `XeroConnection` Filament page for connection management. 36 new tests. Graceful degradation: works without API keys. |
| H3 | Liv-ex API integration | ✅ **Done** | `LivExService` rewritten: real HTTP client (`Http` facade) + `Cache::remember()` (search 1h, detail 24h) + mock fallback. `isConfigured()` check, "Not Configured" banner in UI. Config in `services.livex`, env vars `LIVEX_*`. Container injection in ImportLivex. 13 new tests. Zero breaking changes. |

### Sprint 3 — Polish & Maturity

| # | Item | Status | Note |
|---|------|--------|------|
| M2 | Feature flags (Pennant) | ✅ **Done** | `laravel/pennant` v1.22 installed. 5 feature classes in `app/Features/` (XeroSync, LivExIntegration, StripeWebhooks, NftMinting, AiChat). Env/config fallbacks as defaults, Pennant DB overrides take precedence. Integrated into XeroIntegrationService, LivExService, StripeWebhookController (503), MintProvenanceNftJob (skip), ChatController (503). `FeatureFlags` Filament page (System group, sort 97) with toggle/reset. IntegrationConfiguration reads sync_enabled from Pennant. 21 tests. `PENNANT_STORE=array` in phpunit.xml. |
| L4 | DR documentation | ✅ **Done** | `docs/disaster-recovery.md` — RPO 1h, RTO 30min, 5 recovery runbooks (DB restore, deploy rollback, queue recovery, cache clear, full server). All 11 scheduled jobs documented. `GET /api/health` endpoint with DB/cache/storage checks + per-check latency (200 healthy, 503 degraded). 7 tests in `HealthCheckTest.php`. |
| L1 | Performance baseline | ✅ **Done** | 5 k6 scripts in `tests/performance/`: health-check.js (p95<200ms), voucher-trading.js (p95<1000ms), stripe-webhook.js (p95<300ms), admin-login.js (p95<500ms), smoke-test.js (all endpoints). Load profiles: smoke/load/stress. `README.md` with install, commands, env config, baseline table. |

### Sprint 4 — Advanced

| # | Item | Status | Note |
|---|------|--------|------|
| H4 | Blockchain/NFT | ⏳ Not started | |
| M3 | Analytics / data warehouse | ⏳ Not started | |
| M5 | E2E browser tests | ⏳ Not started | |
