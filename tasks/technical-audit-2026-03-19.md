# CRURATED ERP — TECHNICAL AUDIT REPORT
**Date:** 2026-03-19
**Scope:** Full codebase audit per investor due-diligence template

---

## SECTION 1: CURRENT ARCHITECTURE OVERVIEW

### 1.1 Infrastructure

**Hosting:**
- **Provider:** Ploi-managed VPS (IP: `46.224.207.175`)
- **Site Path:** `/home/ploi/crurated.giovannibroegg.it`
- **Production URL:** `https://crurated.giovannibroegg.it`
- **Database:** MySQL 5.7+ on localhost (`127.0.0.1:3306`), database `erpcrurated`, charset `utf8mb4_unicode_ci`
- **PHP:** 8.5 (FPM)

**Deployment Model:** Traditional VPS with git-based deploys. Docker Compose dev environment via Laravel Sail (`compose.yaml` — PHP 8.4, MySQL 8.4, Redis, Meilisearch + Horizon/Scheduler worker profiles). Production containerization-ready.

**Orchestration:** None. Scheduled tasks via Laravel Scheduler (`routes/console.php`), 11 jobs with cron runner.

**CI/CD Pipeline:** GitHub Actions (`.github/workflows/`)
| Workflow | Trigger | Jobs |
|----------|---------|------|
| `ci.yml` | Push to main/develop + PRs | Pint (code style), PHPStan L8, Tests (PHPUnit+PCOV), Security Audit |
| `deploy-staging.yml` | Push to develop | SSH deploy to staging (git reset, composer, migrate, npm build, optimize) |
| `coverage.yml` | Separate | Codecov coverage reporting |

**Environments:**
| Env | DB | Cache | Queue | Mail | URL |
|-----|----|-------|-------|------|-----|
| Local Dev | SQLite | file | sync | log | localhost |
| Staging | MySQL (`erpcrurated_staging`) | database | database | SMTP | `https://cruratedstaging.giovannibroegg.it` |
| Production | MySQL (`erpcrurated`) | Redis (recommended) | Redis/database | SMTP/SES | `https://crurated.giovannibroegg.it` |

**Monthly Infrastructure Cost:** Unknown — single VPS + Ploi subscription (estimated low; no cloud-scale spend).

---

### 1.2 Backend

**Language & Framework:**
- PHP 8.4/8.5, Laravel 12, Filament 5.3.5+, Livewire 4, Tailwind CSS 4, Vite 7

**Architecture:** **Monolith with modular domain design** (not microservices). 10 business modules with domain-driven folder structure: `app/{Models,Services,Enums,Events,Listeners,Jobs,Filament}/Module/`

| Code | Module | Models | Services | Status |
|------|--------|--------|----------|--------|
| — | Infrastructure | Auth, Roles, Base | — | Done |
| 0 | PIM | 10 | 3 | Done |
| K | Customers | 12 | 2 | Done |
| S | Commercial | 11 | 5 | ~97% |
| A | Allocations | 4 | 4 | Done |
| D | Procurement | 6 | 4 | Done |
| B | Inventory | 7 | 5 | Done |
| C | Fulfillment | 5 | 5 | Done |
| E | Finance | 15 | 15 | Done |
| 9 | AI Assistant | 2 | 1 | In Dev |

**API Style:** REST (minimal). Endpoints:
- `GET /api/health` — Health check
- `POST /api/vouchers/{voucher}/trading-complete` — HMAC-signed trading webhook
- `POST /api/webhooks/stripe` — Stripe webhook
- **API Documentation:** Auto-generated OpenAPI 3.0 via Dedoc Scramble at `/docs/api` + export at `/api.json`

**Authentication & Authorization:**
- Session-based auth (web guard, Eloquent provider)
- RBAC: 5 roles (`super_admin`, `admin`, `manager`, `editor`, `viewer`)
- 16 Policy classes for resource-level authorization
- Custom `VerifyHmacSignature` middleware for trading platform API
- Stripe webhook signature verification
- Session encryption enabled (`SESSION_ENCRYPT=true`)

**Background Jobs (18 total):**
| Module | Job | Schedule |
|--------|-----|----------|
| Allocation | ExpireReservationsJob | Every minute |
| Allocation | ExpireTransfersJob | Every minute |
| Finance | ProcessSubscriptionBillingJob | Daily 06:00 |
| Finance | IdentifyOverdueInvoicesJob | Daily 08:00 |
| Finance | SuspendOverdueSubscriptionsJob | Daily 09:00 |
| Finance | BlockOverdueStorageBillingJob | Daily 10:00 |
| Finance | AlertUnpaidImmediateInvoicesJob | Hourly |
| Finance | GenerateStorageBillingJob | Monthly 1st 05:00 |
| Finance | CleanupIntegrationLogsJob | Daily 03:00 |
| Finance | ProcessStripeWebhookJob | On event (queued) |
| Inventory | MintProvenanceNftJob | On event |
| Inventory | SyncCommittedInventoryJob | On event |
| Inventory | UpdateProvenanceOnMovementJob | On event |
| Fulfillment | UpdateProvenanceOnShipmentJob | On event |
| Commercial | ExecuteScheduledPricingPoliciesJob | On schedule |
| Commercial | ExpireOffersJob | On schedule |
| Procurement | ApplyBottlingDefaultsJob | Daily |
| Audit | ArchiveAuditLogsJob | Daily 03:30 |

---

### 1.3 Frontend

**Web Frontend:** Filament 5 admin panel only. No customer-facing SPA or website.
- **Styling:** Tailwind CSS v4 via `@tailwindcss/vite` plugin
- **JavaScript:** Minimal — Axios (HTTP) + Alpine.js (Filament's reactivity)
- **State Management:** None (Livewire 4 handles component state)
- **Build:** Vite 7 + Laravel Vite Plugin
- **`package.json` deps:** Vite, Tailwind, Axios, Concurrently — that's it

**URL Structure:**
- `/` → redirects to `/admin`
- `/admin/*` — entire application (Filament panel)
- `/api/*` — minimal REST endpoints (health, webhooks)
- `/docs/api` — OpenAPI documentation

**Mobile Apps:** None. No native iOS/Android. No PWA.

**Customer-Facing UI:** **Does not exist.** The entire application is an internal admin ERP. No public catalog, no member portal, no checkout flow.

---

### 1.4 Database

**Primary Database:** MySQL (production/staging), SQLite (development)
- **ORM:** Eloquent (Laravel native)
- **Migrations:** 112+ timestamped, module-numbered
- **Models:** 79 unique Eloquent models across 10 modules
- **Estimated Tables:** ~95 (models + pivots + audit)
- **Relationships:** 233+ defined (HasMany, BelongsTo, BelongsToMany, MorphMany, etc.)

**Caching Layer:**
- Dev: file | Staging: database | Production: Redis (recommended)
- Prefix: `crurated_erp`
- PimCacheService with Observer-based invalidation

**Data Warehouse:** None. No BigQuery, Redshift, or analytics database.

**Search Engine:** None. No Scout, Meilisearch, Algolia, or Elasticsearch. All queries are direct Eloquent/SQL.

---

### 1.5 Third-Party Services

| Service | Package/Config | Purpose | Status |
|---------|---------------|---------|--------|
| **Stripe** | `stripe/stripe-php ^19.3` | Payments, webhooks | Live |
| **Xero** | `xeroapi/xero-php-oauth2 ^11.0` | Accounting sync (invoices, contacts) | Live |
| **Liv-ex** | Custom config (`services.livex`) | Wine market data & pricing | Configured |
| **WMS** | Custom service (`WmsIntegrationService`) | Warehouse fulfillment (outbound) | Live |
| **Anthropic Claude** | `laravel/ai 0.1.5` | AI assistant (27 tools) | In Dev |
| **Laravel Pennant** | `laravel/pennant ^1.22` | Feature flags | Live |
| **DomPDF** | `barryvdh/laravel-dompdf ^3.1` | PDF invoice generation | Live |
| **Scramble** | `dedoc/scramble ^0.13.16` | Auto-generated OpenAPI docs | Live |
| **AWS S3** | Config only | File storage (production) | Configured |
| **Sentry** | Config only (optional) | Error monitoring | Optional |
| **SMTP/SES/Mailgun** | Config only | Transactional email | Configured |
| **Slack** | Config only | Alert notifications | Optional |

**Not Integrated:** No Blockchain provider, no NFC system, no analytics (Mixpanel/GA4/Segment), no CRM, no shipping carriers API.

---

## SECTION 2: DATA MODEL

### 2.1 Core Entities

#### Users (Internal Operators)
- **Model:** `app/Models/User.php`
- **Fields:** name, email, password, role (UserRole enum: SuperAdmin/Admin/Manager/Editor/Viewer)
- **Relations:** BelongsToMany Account (via `account_users` pivot)
- **Scope:** Internal staff only. No customer self-service accounts.

#### Parties & Customers
- **Party** (`app/Models/Customer/Party.php`): Legal entity base. Fields: legal_name, party_type (Individual|LegalEntity), tax_id, vat_number, jurisdiction, status
- **Customer** (`app/Models/Customer/Customer.php`): Commercial profile. Fields: party_id, customer_type (B2C|B2B|Partner), status, stripe_customer_id
- **Relations:** Customer → Vouchers, CaseEntitlements, ShippingOrders, Invoices, Subscriptions, Memberships, Clubs, Addresses, PaymentPermission, OperationalBlocks
- **Membership:** Tier-based (Bronze/Silver/Gold/Platinum), lifecycle (Pending/Approved/Rejected/Cancelled)
- **Club:** BelongsToMany via pivot (affiliation_status, start_date, end_date)

#### Products & Wine (PIM Module)
- **WineMaster** → **WineVariant** (vintage) → **SellableSku** (variant + format)
- **Format:** bottle sizes (750ml, 375ml, magnums, etc.)
- **Key fields:** producer, appellation, country, region, classification, liv_ex_code, vintage_year, tasting_notes, alcohol_percentage, lifecycle_status
- **Scale:** ~500-2,000 wines, ~5,000-10,000 SKUs

#### Allocations & Vouchers
- **Allocation:** Supply bucket. Fields: wine_variant_id, total_quantity, sold_quantity, remaining_quantity, status
- **Voucher:** 1 voucher = 1 bottle (immutable invariant). Fields: customer_id, allocation_id (immutable), lifecycle_state (Issued|Locked|Redeemed|Cancelled), tradable, giftable
- **VoucherTransfer:** P2P trading. Fields: from_customer_id, to_customer_id, status
- **CaseEntitlement:** Case-level allocations with case_status (intact|broken — irreversible)

#### Inventory (Serialized)
- **SerializedBottle:** Physical bottle with unique serial_number. Fields: allocation_id (immutable), current_location_id, case_id, ownership_type, custody_holder, state, nft_reference
- **InventoryMovement:** Append-only location history. 11 movement types.
- **Location:** Warehouses + customer vaults (climate_controlled, security_level)

#### Fulfillment
- **ShippingOrder** → **ShippingOrderLine** → **Shipment**
- **Late Binding:** Voucher → SerializedBottle binding happens ONLY at shipment, not at sale
- **WMS Integration:** ERP sends picking instructions, WMS executes

#### Procurement
- **ProcurementIntent** → **PurchaseOrder** → **Inbound**
- Every PO requires a ProcurementIntent (enforced in boot())
- Supplier managed via Party + PartyRole + ProducerSupplierConfig

#### Finance
- **Invoice:** 5 types (INV0=Membership, INV1=Wine, INV2=Shipping, INV3=Storage, INV4=Events). Type immutable.
- **Payment:** Stripe-linked, multi-method (card, bank_transfer, credit)
- **Subscription:** Recurring billing (membership, storage)
- **CustomerCredit:** Store credit with expiration
- **CreditNote:** Invoice adjustments
- **StorageBillingPeriod:** Monthly bottle-day fee calculation
- **Xero Sync:** XeroSyncLog + XeroToken for accounting integration

### 2.2 Data Gaps

| Data Point | Available? | Notes |
|-----------|-----------|-------|
| Purchase history (queryable) | **YES** | Customer → Invoices → InvoiceLines; full voucher lineage |
| Tasting/review data | **NO** | No Review, Rating, or TastingNote models |
| User behavior (views, searches) | **NO** | No analytics events; only operational AuditLog |
| Wishlist/watchlist | **NO** | No Wishlist model |
| Geographic data | **MINIMAL** | Address model has country field, no aggregation |
| Event attendance | **NO** | No Event model or attendance tracking |
| Member-to-member interactions | **MINIMAL** | VoucherTransfer only; no messaging/social |
| Taste profiles | **NO** | No preference modeling or ML |
| Referral tracking | **NO** | No referral model or credit system |

---

## SECTION 3: CURRENT PRODUCT CAPABILITIES

### 3.1 What's Live Today

| Capability | Status | Detail |
|-----------|--------|--------|
| Member registration & auth | **NOT STARTED** | Users are internal operators only; no customer self-service |
| Product catalog browsing | **ADMIN ONLY** | PIM complete; no customer-facing catalog UI |
| Primary wine purchasing | **ADMIN ONLY** | Commercial module ~97%; no shopping cart/checkout |
| Fractional barrel sales | **NOT STARTED** | Not in any PRD |
| "My Cellar" (member's digital cellar) | **PLANNED** | Vouchers exist; no customer UI |
| Cellar value tracking | **PLANNED** | EMP data exists; no customer dashboard |
| CruTrade secondary marketplace | **PLANNED** | VoucherTransfer model exists; no marketplace UI |
| Shipping request & fulfillment | **ADMIN ONLY** | Module C complete with WMS; no customer ordering |
| NFC bottle scanning | **NOT STARTED** | No NFC models or device integration |
| Blockchain provenance | **NOT STARTED** | Jobs exist (MintProvenanceNftJob) but no blockchain provider |
| B2B platform | **NOT STARTED** | Customer types exist; no B2B-specific features |
| White-label club platform | **NOT STARTED** | Club model exists; no multi-tenant infrastructure |
| Push notifications | **NOT STARTED** | No mobile app; no push service |
| Email marketing campaigns | **NOT STARTED** | Basic Mailable classes only |
| Admin/operations dashboard | **LIVE** | 9 dashboards, 35 custom pages, 11 widgets |
| Warehouse management | **LIVE** | Inventory module complete; WMS integration operational |
| Analytics/reporting dashboard | **LIVE** | 8+ operational reports; no self-service BI |

### 3.2 Social Commerce Features

| Feature | Status |
|---------|--------|
| Member public profiles | NOT STARTED |
| Social cellar discovery | NOT STARTED |
| NFC-triggered review system | NOT STARTED |
| Review gamification | NOT STARTED |
| 1% referral credit system | NOT STARTED |
| Member-led tasting events | NOT STARTED |
| Event logistics coordination | NOT STARTED |
| Collector badges | NOT STARTED |
| Native iOS app | NOT STARTED |
| Native Android app | NOT STARTED |

---

## SECTION 4: ENGINEERING TEAM & PROCESS

### 4.1 Team

- **Engineers:** 1 developer + AI pair (Claude Code)
- **Roles:** Solo full-stack (PHP/Laravel backend, Filament admin UI)
- **Location:** Italy (CET timezone)
- **External contractors:** None currently
- **Technical decision-maker:** Solo developer
- **Product prioritization:** Solo developer (PRD-driven)

### 4.2 Process

- **Project management:** PRD files in `tasks/` directory, todo tracking in `tasks/todo.md`
- **Sprint cadence:** Incremental, PRD-driven (not formal sprints)
- **Code review:** AI-assisted (Claude Code), no peer review process
- **Testing:**
  - **Framework:** PHPUnit 11
  - **Test files:** 97
  - **Total tests:** 1,035+ (expanded from 764 after audit remediation)
  - **Structure:** Unit (models, middleware, AI tools) + Feature (resources, policies, services, API)
  - **Factories:** 48 (organized by module)
  - **Coverage:** PCOV-based, CI artifact generation
  - **Notable:** Concurrency tests for voucher transfers and PO invariants
- **Database migrations:** Sequential, module-numbered (112+). Pre-migration mysqldump backup on deploy.
- **Deployment frequency:** Ad-hoc (push to main triggers CI; staging auto-deploys from develop)
- **Feature flags:** Laravel Pennant (database-backed). Active flags: XeroSync, StripeWebhooks, LivExIntegration
- **Technical documentation:** Comprehensive — `tasks/ERP-FULL-DOC.md` (300+ pages), 10 module PRDs

### 4.3 Technical Debt & Known Issues

**Static Analysis:** PHPStan Level 8 (strict). 3 minor baseline patterns (trait.unused, Filament return types, Policy test reflection). Clean.

**Top Technical Debt Items:**
1. **No customer-facing UI** — entire platform is admin-only; needs separate frontend for members
2. **No blockchain provider** — MintProvenanceNftJob exists but has no actual blockchain connection
3. **No search engine** — all queries are direct SQL; will not scale for catalog browsing
4. **Xero sync failures require manual intervention** — no automatic retry fallback
5. **Queue runs in `sync` mode in dev** — production readiness of async jobs not fully battle-tested

**Performance:** Optimized. N+1 queries fixed across all Resources and Services (Phase 1 audit). Composite indexes on lifecycle/status+deleted_at. Row-level locking on critical sections (VoucherService, AllocationService, LateBindingService).

**Security:** Strong. Session encryption, CSRF, rate limiting (login 5/min, AI 60/hr + 500/day), HMAC webhooks, Stripe signature verification, no hardcoded secrets. CVE-2026-30838 patched.

**Pending Migrations:** None. Queue strategy (sync → database → Redis) needs explicit production configuration.

**Libraries to Replace:** None. All dependencies current (Laravel 12, Filament 5, PHP 8.5).

---

## SECTION 5: DATA & ANALYTICS CURRENT STATE

### 5.1 What Data Is Currently Collected

| Data Type | Collected? | How |
|-----------|-----------|-----|
| User behavior events (views, clicks) | **NO** | No analytics SDK |
| Frontend event tracking | **NO** | Admin-only panel; no tracking instrumented |
| API request logging | **PARTIAL** | Stripe webhooks + Xero sync logs only |
| ML models / recommendations | **NO** | AI assistant uses LLM, not ML |
| Reports / dashboards | **YES** | 9 dashboards, 11 widgets, 8 finance reports, AI tools |

**Audit Trail:** Immutable AuditLog with 22+ event types. All 79 models use Auditable trait. Retention: 365 days (audit), 180 days (AI), 90 days (integration logs).

**AI Assistant:** 27 tools across all modules. Anthropic Claude Sonnet 4.5. Rate-limited per user. Read-only queries — does not mutate data.

### 5.2 Data Access

| Query | Possible? | Notes |
|-------|-----------|-------|
| All purchases by member X, sorted by producer | **YES** | Customer → Invoices → InvoiceLines; allocation lineage |
| Top 20 wines in Hong Kong last 6 months | **PARTIAL** | Need to join Address.country + Invoice.issued_at; no pre-built query |
| Members who purchased from Producer Y | **YES** | Allocation → WineVariant → WineMaster → Producer; then Voucher → Customer |
| Member taste profile from history | **NO** | No taste modeling; raw purchase data exists |

---

## SECTION 6: ERP & OPERATIONS

### 6.1 Current Operations Stack

| Area | System | Detail |
|------|--------|--------|
| ERP | **Custom (this system)** | Laravel-based, 10 modules, 79 models |
| Inventory | **Module B + WMS** | Serialized bottles, append-only movements, case tracking |
| Purchase Orders | **Module D** | ProcurementIntent → PurchaseOrder → Inbound pipeline |
| Shipping/Fulfillment | **Module C + WMS** | Late binding, ShippingOrder → Shipment with WMS execution |
| Compliance | **OperationalBlock model** | 5 block types (Payment, Shipment, Redemption, Trading, Compliance) |
| Producer Relations | **Party + PartyRole** | ProducerSupplierConfig; no dedicated CRM |
| Accounting | **Xero** | OAuth2 sync (invoices, contacts); account code mapping (INV0→200, etc.) |

### 6.2 Pain Points (Architecture-Derived)

1. **No customer-facing platform** — all operations require staff to mediate; members cannot self-serve
2. **Late binding complexity** — voucher-to-bottle binding at shipment requires careful WMS coordination
3. **Storage billing batch process** — monthly INV3 generation via batch job; scale concern at 100K+ bottles
4. **Xero sync failure handling** — failed syncs logged but not auto-retried; requires manual review
5. **Multi-currency pricing** — FX rate captured at invoice time; no real-time hedging or rate alerts
6. **Serialization bottleneck** — manual serial assignment via SerializationQueue page; no batch import from WMS
7. **No real-time inventory dashboard for customers** — cellar value requires admin lookup

---

## SECTION 7: WHITE-LABEL & PARTNERSHIPS

### 7.1 Current White-Label Architecture

- **Architecture:** Club model exists in Module K (Customers) with BelongsToMany pivot
- **Multi-tenancy:** Not implemented. Single-tenant codebase.
- **Can spin up new partner without code changes?** No
- **Inventory partitioning:** Not implemented (shared inventory pool)
- **Member data separation:** Not implemented (shared database)
- **Partner customization:** Not possible currently (no branding, theming, or config-based customization)

### 7.2 Partnership Pipeline

- **Live white-label partnerships:** 0
- **In pipeline:** Unknown (business context, not in codebase)
- **Delivery gaps:** Entire white-label infrastructure needs to be built (multi-tenancy, branding, data isolation)

---

## SECTION 8: STRATEGIC ALIGNMENT

### 8.1 Highest-Priority Initiative
The ERP backend is essentially **complete** (8/10 modules done, Module S at 97%). The critical gap is **customer-facing UI** — without it, the platform cannot generate revenue from member interactions. Everything from catalog browsing to cellar management to marketplace trading requires a frontend.

### 8.2 Biggest Technical Risk (12 months)
**Scaling from admin tool to customer platform.** The monolith handles internal operations well but has zero infrastructure for:
- Customer authentication (separate from admin)
- Public API (REST/GraphQL for mobile/web clients)
- Real-time features (cellar updates, trading, notifications)
- Search at scale (no search engine)
- Mobile apps (no native code, no PWA)

### 8.3 What to Rebuild
**Nothing.** The backend architecture is solid — modular, well-tested (1,035+ tests), PHPStan L8 clean, proper domain separation. The need is to **extend** (add customer-facing layer on top) rather than **rebuild**.

### 8.4 Data as Strategic Asset
Purchase history, allocation lineage, and provenance tracking create a unique dataset. Currently underutilized — no analytics, no ML, no taste profiling. This data becomes extremely valuable with:
- Recommendation engine (purchase history → taste profile → personalized offers)
- Market intelligence (EMP data + Liv-ex integration)
- Provenance verification (serialized bottle history)

### 8.5 AI/ML Approach
- **Current:** LLM-based AI assistant (Claude Sonnet 4.5) with 27 read-only tools across all modules. Rate-limited, audit-logged.
- **No ML models** in production. No recommendation engine. No predictive analytics.
- **Opportunity:** Purchase data + wine metadata → collaborative filtering for recommendations.

### 8.6 ERP Optimization
The ERP solves: wine trading operations (allocations, vouchers, procurement, fulfillment, finance) with full audit trail and domain invariants. Current optimization focus: hardening for production scale, Xero/Stripe reliability, feature flag rollout.

### 8.7 Social Commerce Integration
Should be **loosely coupled** — separate frontend application consuming ERP APIs, with the ERP remaining the system of record for inventory, allocations, and finance. Social features (profiles, reviews, badges) should live in the customer platform, not the ERP.

---

## CODEBASE METRICS SUMMARY

| Metric | Count |
|--------|-------|
| Models | 79 |
| Services | 44 |
| Enums | 96 |
| Jobs | 18 |
| Events | 9 |
| Listeners | 6 |
| Policies | 16 |
| Observers | 3 |
| Migrations | 112+ |
| Filament Resources | 45 |
| Custom Pages | 35 |
| Widgets | 11 |
| Factories | 48 |
| Seeders | 35 |
| Test Files | 97 |
| Total Tests | 1,035+ |
| Scheduled Tasks | 11 |
| AI Tools | 27 |
| PRD User Stories | 542 |
| PHPStan Level | 8 |
