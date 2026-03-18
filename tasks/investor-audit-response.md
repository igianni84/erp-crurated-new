# CRURATED ERP — TECHNICAL AUDIT RESPONSE

**Date:** 2026-03-18
**Respondent:** Crurated Engineering (AI-assisted audit via Claude Code with full codebase access)
**Repository:** `github.com/igianni84/erp-crurated-new` (private)
**Production URL:** `https://crurated.giovannibroegg.it`

---

## SECTION 1: CURRENT ARCHITECTURE OVERVIEW

### 1.1 Infrastructure

| Aspect | Detail |
|--------|--------|
| **Hosting** | Ploi-managed VPS (46.224.207.175), EU datacenter |
| **Deployment model** | Single VM with PHP 8.5 FPM, MySQL, Nginx — managed via Ploi panel |
| **Orchestration** | None currently — single-server deployment; architecture is containerizable (Sail config present) |
| **CI/CD** | GitHub Actions — 4 parallel jobs on push to `main` + PRs: (1) Laravel Pint code style, (2) PHPStan level 8 static analysis, (3) PHPUnit 1,040 tests, (4) Composer security audit |
| **Environments** | Development (local SQLite), Production (Ploi/MySQL). No formal staging yet — planned |
| **Monthly infra cost** | ~€30-50/mo (Ploi VPS + domain). Pre-scale phase |

**Deploy Pipeline (automated via Ploi):**
```bash
git fetch origin main && git reset --hard origin/main
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
# Pre-migration DB backup (compressed, timestamped)
mysqldump --single-transaction --quick | gzip > /home/ploi/backups/erpcrurated_$(date).sql.gz
php artisan migrate --force
php artisan filament:optimize && php artisan optimize
php8.5-fpm reload
```

### 1.2 Backend

| Aspect | Detail |
|--------|--------|
| **Language** | PHP 8.5.2 |
| **Framework** | Laravel 12 (latest) |
| **Architecture** | Modular monolith — 9 domain modules with clear boundaries, event-driven cross-module communication |
| **API style** | REST (2 external endpoints + Stripe webhook). Internal operations via Filament admin panel |
| **API documentation** | Inline PHPDoc + typed request/response objects. No Swagger/OpenAPI yet |
| **Auth/AuthZ** | Laravel built-in session auth. 5 roles: `super_admin`, `admin`, `manager`, `editor`, `viewer`. 43 granular policies |
| **Background jobs** | 18 queued jobs via Laravel Queue (database driver, Redis-ready). 11 scheduled tasks via `routes/console.php` |

**Module Architecture (dependency order):**

| Module | Code | Models | Services | Enums | Status |
|--------|------|--------|----------|-------|--------|
| Infrastructure | — | 2 | — | 4 | ✅ Done |
| PIM (Product Info) | 0 | 16 | 3 | 10+ | ✅ Done |
| Customers | K | 11 | 2 | 2 | ✅ Done |
| Commercial | S | 13 | 4 | 8 | ✅ ~97% |
| Allocations | A | 8 | 3 | 6 | ✅ Done |
| Procurement | D | 5 | 2 | 6 | ✅ 68/68 stories |
| Inventory | B | 7 | 3 | 4 | ✅ Done |
| Fulfillment | C | 5 | 4 | 4 | ✅ Done |
| Finance | E | 11 | 8 | 8 | ✅ 132/132 stories |

**Codebase Scale:**
- 78 Eloquent models, 42 service classes, 95 string-backed enums
- 18 jobs, 9 events, 6 listeners, 3 observers, 43 policies
- 110 migrations, 48 factories, 35 seeders
- 1,040 tests passing (2,477 assertions), 86 test files
- PHPStan level 8 (strictest practical level), Laravel Pint enforced

**Scheduled Jobs (11):**

| Job | Schedule | Purpose |
|-----|----------|---------|
| ExpireReservationsJob | Every minute | Release temporary allocation holds |
| ExpireTransfersJob | Every minute | Expire pending voucher transfers |
| IdentifyOverdueInvoicesJob | 8:00 AM daily | Flag overdue invoices |
| ProcessSubscriptionBillingJob | 6:00 AM daily | Generate subscription invoices (INV0) |
| SuspendOverdueSubscriptionsJob | 9:00 AM daily | Suspend overdue subscriptions |
| AlertUnpaidImmediateInvoicesJob | Hourly | Alert on unpaid INV1/INV2/INV4 |
| GenerateStorageBillingJob | 1st of month 5:00 AM | Monthly storage billing (INV3) |
| BlockOverdueStorageBillingJob | 10:00 AM daily | Block custody on overdue storage |
| CleanupIntegrationLogsJob | 3:00 AM daily | Prune old Stripe/Xero logs (90 days) |
| ApplyBottlingDefaultsJob | Daily | Apply bottling instruction defaults |
| ArchiveAuditLogsJob | 3:30 AM daily | Archive old audit + AI logs |

### 1.3 Frontend

| Aspect | Detail |
|--------|--------|
| **Admin UI** | Filament v5.3.5 (TALL stack: Tailwind + Alpine.js + Livewire 4 + Laravel) |
| **Admin scope** | 45 resources, 33 custom pages, 11 widgets, 12 relation managers |
| **Styling** | Tailwind CSS 4 via Vite 7 |
| **Customer-facing frontend** | Not part of ERP scope — ERP is the backend operations platform. Customer-facing apps are separate |
| **Mobile apps** | Not applicable to ERP. Crurated's consumer mobile app is a separate project |
| **URL** | `https://crurated.giovannibroegg.it` (production ERP) |

**Filament Panel Features:**
- 10 navigation groups (PIM, Allocations, Fulfillment, Inventory, Finance, Vouchers, Commercial, Procurement, Customers, System)
- Executive dashboard with 8 KPI widgets (Revenue chart, Order Pipeline, Inventory Position, Customer Engagement, Procurement Pipeline, Voucher Lifecycle, Operational Alerts, Monthly Financial Summary)
- AI Assistant page (Claude-powered ERP query interface with 8 domain-specific tools)
- Finance reporting suite: 8 dedicated report pages (Aging, Outstanding Exposure, Reconciliation, Revenue by Type, FX Impact, Event-to-Invoice Traceability, Storage Billing Preview, Customer Finance)
- Audit Viewer, Integration Health dashboard, Xero Sync monitor

### 1.4 Database

| Aspect | Detail |
|--------|--------|
| **Database** | MySQL 8+ (production), SQLite (development/testing) |
| **ORM** | Eloquent (Laravel's ORM) with explicit relationship typing |
| **Tables** | 87 tables (from 110 migrations) |
| **Approximate size** | Pre-production. Seeded data for development/demo. Production data TBD |
| **Caching** | Database cache store (Redis-ready via config). Filament caching for optimized panel |
| **Data warehouse** | Not yet. Finance reporting currently via Eloquent queries + Filament dashboard |
| **Search engine** | Filament global search on 45 resources (Eloquent-based). No Elasticsearch/Algolia yet |

**Database Design Principles:**
- UUID primary keys on all 78 models (via `HasUuid` trait) — collision-free, merge-safe
- Soft deletes on ~95% of models — data never physically lost
- Immutable audit log table — append-only, no updates/deletes
- Foreign key constraints with cascade rules
- Strict mode enabled (utf8mb4_unicode_ci)
- Decimal math via `DecimalMath` wrapper class (bcmath) — never float arithmetic for money

### 1.5 Third-Party Services

| Service | Provider | Status | Auth Method |
|---------|----------|--------|-------------|
| **Payment processing** | Stripe (stripe-php v19.3) | ✅ Production-ready | API keys + webhook HMAC-SHA256 |
| **Accounting sync** | Xero (OAuth2) | ✅ Architecture complete, stub API calls | OAuth2 client credentials |
| **Warehouse (WMS)** | Custom integration service (848 lines) | ✅ API-ready, message-based | Bidirectional message protocol |
| **Wine data** | Liv-ex (LWIN codes) | ✅ Architecture complete, mock data | API key (pending production key) |
| **External trading** | Trading platform webhook | ✅ Production-ready | HMAC-SHA256 (5-min timestamp tolerance) |
| **Email** | SMTP/SES/Mailgun (configurable) | ✅ Production-ready | SMTP credentials or SES |
| **Error monitoring** | Sentry (optional) | ✅ Configured | DSN |
| **AI assistant** | Anthropic Claude (laravel/ai v0.1.5) | ✅ Integrated | API key, rate-limited (60/hr, 500/day) |
| **File storage** | Local (S3-ready via config) | ✅ Configured | AWS credentials when needed |
| **Blockchain/NFT** | Provenance framework (MintProvenanceNftJob) | ✅ Framework ready, stub minting | TBD (Ethereum/Polygon planned) |
| **PDF generation** | DomPDF (barryvdh/laravel-dompdf v3.1) | ✅ Production-ready | N/A |

**Integration Architecture:**
- All webhook handlers: signature verification + idempotency + async processing
- Integration logs retained 90 days with automatic cleanup
- Retry logic: max 3 retries with exponential backoff (Stripe webhooks, Xero sync)
- All external API calls wrapped in service classes with audit logging

---

## SECTION 2: DATA MODEL

### 2.1 Core Entities

**Users/Members:**
- Model: `User` — name, email, password, role (UserRole enum: super_admin/admin/manager/editor/viewer)
- ERP users are operations staff, not end customers
- Customer records are separate: `Party` → `Customer` → `Account` with multi-role support via `PartyRole`
- Fields: stripe_customer_id, membership tier, geographic data (via Address), club affiliations

**Producers/Wineries:**
- Model: `Producer` (Module PIM) — name, country, region, appellation
- Linked to wines via `WineMaster.producer_id`
- Supplier config via `ProducerSupplierConfig` (procurement terms)

**Products/Wines:**
- Three-tier hierarchy: `WineMaster` → `WineVariant` → `SellableSku`
- **WineMaster:** name, producer, region, appellation, country, wine_type, varietal
- **WineVariant:** vintage, tasting notes, lifecycle_status, lwin_code (Liv-ex), data_source, locked_fields
- **SellableSku:** variant + format + case_configuration = purchasable unit
- **Format:** bottle sizes (750ml, 375ml, 1500ml, etc.)
- **CaseConfiguration:** fixed case layouts (6-bottle, 12-bottle, etc.)
- **LiquidProduct:** unfinished wine in bulk (barrel/tank)
- **ProductMedia:** images and media per wine
- **AttributeSet/Definition/Group/Value:** flexible attribute system for wine metadata

**Inventory/Warehouse:**
- **Per-bottle tracking:** `SerializedBottle` — individual bottle with serial number, state (BottleState enum), location, custody chain, nft_reference, nft_minted_at
- **Case tracking:** `InventoryCase` — grouped bottles, status (Intact/Broken — breaking is irreversible)
- **Locations:** `Location` — physical warehouse locations
- **Movements:** `InventoryMovement` + `MovementItem` — append-only ledger (never modified/deleted)
- **Inbound:** `InboundBatch` — batch of received inventory linked to procurement
- **Exceptions:** `InventoryException` — discrepancies and anomalies
- **Blockchain link:** `SerializedBottle.nft_reference` links to provenance record. `MintProvenanceNftJob` handles async minting

**Orders/Transactions:**
- ERP uses **allocation-based model**, not traditional cart/order:
  1. `Allocation` — supply allocation at WineVariant + Format level (total/sold/remaining)
  2. `Voucher` — 1 voucher = 1 bottle (immutable allocation_id, lifecycle_state tracking)
  3. `CaseEntitlement` — groups vouchers when customer buys a case
  4. `ShippingOrder` → `ShippingOrderLine` → `Shipment` — fulfillment pipeline
- Status management via typed enums with `allowedTransitions()`

**CruTrade (Secondary Marketplace):**
- Modeled via `VoucherTransfer` — customer-to-customer transfer with timestamps (initiated_at, expires_at, accepted_at)
- External trading platform integration via HMAC-authenticated webhook callback
- `Voucher.external_trading_reference` links to external trading platform record

**Cellars:**
- A customer's "cellar" = all `Voucher` records owned by that customer with lifecycle_state = Active
- Each voucher represents ownership of 1 bottle, may or may not be bound to a `SerializedBottle`
- Late binding (voucher → physical bottle) occurs ONLY at shipment confirmation in Fulfillment module

**NFC/Provenance:**
- `SerializedBottle.nft_reference` — blockchain token reference
- `SerializedBottle.nft_minted_at` — minting timestamp
- `MintProvenanceNftJob` — async NFT minting with 3 retries, exponential backoff (10s, 60s, 300s)
- `UpdateProvenanceOnShipmentJob` — updates provenance on shipment events
- `UpdateProvenanceOnMovementJob` — updates provenance on inventory movements
- Currently framework-ready with placeholder minting; production requires blockchain service integration

### 2.2 Data Gaps

| Question | Status | Detail |
|----------|--------|--------|
| **Member purchase history queryable?** | ✅ Yes | Via Voucher → Customer → SellableSku chain. Fully queryable with Eloquent relationships |
| **Tasting/review data?** | ❌ Not yet | No review or tasting data model in ERP. Could be added as a PIM extension |
| **Wine views/searches/wishlists?** | ❌ Not yet | ERP tracks operations, not consumer behavior. Consumer-facing analytics would be in the frontend app |
| **Geographic data on members?** | ✅ Yes | `Address` model (polymorphic to Party) — full address including city, country, postal code |
| **Event attendance data?** | ❌ Not yet | Events referenced in finance (EventConsumption page) but no event attendance tracking model |
| **Member-to-member interactions?** | ⚠️ Partial | `VoucherTransfer` tracks peer transfers. No social/messaging features in ERP scope |

---

## SECTION 3: CURRENT PRODUCT CAPABILITIES

### 3.1 What's Live Today

**ERP Backend Capabilities (this project):**

- [x] **Admin/operations dashboard** — LIVE (8-widget executive dashboard + 33 custom pages)
- [x] **Product catalog management** — LIVE (WineMaster → Variant → SellableSku, Liv-ex import)
- [x] **Primary wine allocation & voucher management** — LIVE (full allocation → voucher → fulfillment pipeline)
- [x] **"My Cellar" data model** — LIVE (voucher ownership = cellar, queryable per customer)
- [x] **CruTrade secondary marketplace backend** — LIVE (VoucherTransfer + external trading webhook)
- [x] **Shipping request and fulfillment** — LIVE (ShippingOrder → Late Binding → Shipment)
- [x] **NFC/blockchain provenance framework** — IN DEVELOPMENT (framework + jobs ready, needs blockchain integration)
- [x] **Warehouse management integration** — LIVE (WMS integration service, bidirectional protocol)
- [x] **Invoice & payment management** — LIVE (5 invoice types INV0-INV4, Stripe integration, subscription billing)
- [x] **Procurement pipeline** — LIVE (ProcurementIntent → PurchaseOrder → Inbound)
- [x] **Subscription & storage billing** — LIVE (automated monthly/recurring billing)
- [x] **Xero accounting sync** — IN DEVELOPMENT (architecture complete, stub API calls)
- [x] **Analytics/reporting dashboard** — LIVE (8 finance reports, KPI widgets, audit viewer)
- [x] **AI-powered operations assistant** — LIVE (Claude-powered with 8 domain tools)
- [x] **Email notifications** — LIVE (invoice emails with PDF attachment, queued)
- [x] **Role-based access control** — LIVE (5 roles, 43 policies, comprehensive audit trail)

**Consumer-Facing Features (separate from ERP scope):**

- [ ] Member registration and authentication — N/A for ERP (consumer app scope)
- [ ] Product catalog browsing (consumer) — N/A for ERP
- [ ] Fractional barrel sales — LIVE in ERP (LiquidProduct + LiquidAllocationConstraint models)
- [ ] Cellar value tracking / market value display — PLANNED (EstimatedMarketPrice model exists in Commercial module)
- [ ] B2B platform for restaurants — NOT STARTED
- [ ] Push notifications — NOT STARTED (ERP has email notifications)

### 3.2 What's in the Social Commerce Proposal But Not Built Yet

| Feature | Status | Notes |
|---------|--------|-------|
| Member public profiles | NOT STARTED | Consumer app feature, not ERP |
| Social cellar discovery | NOT STARTED | Consumer app feature |
| NFC-triggered review system | NOT STARTED | Provenance framework exists, review system TBD |
| Review gamification | NOT STARTED | |
| 1% referral credit system | NOT STARTED | CustomerCredit model exists in Finance, referral logic TBD |
| Member-led tasting events | NOT STARTED | EventConsumption page in Finance exists for event billing |
| Event logistics coordination | PARTIAL | ShippingOrder supports event-based shipping |
| Collector badges | NOT STARTED | |
| Native iOS app | NOT STARTED | Separate project |
| Native Android app | NOT STARTED | Separate project |

---

## SECTION 4: ENGINEERING TEAM & PROCESS

### 4.1 Team

| Aspect | Detail |
|--------|--------|
| **Engineers total** | 1 founder-engineer + Claude Code AI (this is the unique aspect — entire ERP built AI-first) |
| **Roles** | Founder/CEO acts as product owner, architect, and QA. Claude Code handles implementation, testing, static analysis |
| **Location** | Italy (CET timezone) |
| **External contractors** | None. AI-native development model |
| **Technical decision-maker** | Founder (Giovanni) — architecture decisions documented in PRDs and CLAUDE.md |
| **Product prioritization** | Founder with structured PRDs (542 user stories across 10 modules, priority-ordered) |

### 4.2 Process

| Aspect | Detail |
|--------|--------|
| **Project management** | PRD-driven with `tasks/` directory: `project-plan.md`, `todo.md`, per-module PRDs, progress files |
| **Sprint cadence** | Module-based sprints. One module at a time, dependency-ordered |
| **Code review** | AI self-review (PHPStan L8 + Pint + test suite). Manual review by founder |
| **Testing approach** | PHPUnit feature + unit tests. 1,040 tests, 2,477 assertions. CI enforced. SQLite in-memory for speed |
| **Database migrations** | Version-controlled, module-numbered (PIM: 200000+, Finance: 300000+, etc.). Pre-migration backups in deploy |
| **Deployment frequency** | Multiple times per week during active development. Automated via Ploi on push to main |
| **Feature flags** | No formal feature flag system yet. Environment-based toggles (e.g., XERO_SYNC_ENABLED) |
| **Documented architecture** | Yes — `CLAUDE.md` (coding conventions, invariants, module map), `tasks/project-plan.md` (strategic plan), per-module PRDs |

**Quality Gates (CI must pass before merge):**
1. Laravel Pint — zero code style violations
2. PHPStan level 8 — zero static analysis errors
3. 1,040 tests — zero failures
4. `composer audit` — zero known vulnerabilities

### 4.3 Technical Debt & Known Issues

**Top 5 Technical Debt Items:**

1. **Xero integration is stub** — Architecture and service complete (807 lines), but API calls return simulated responses. Needs `xeroapi/xero-php-oauth2` package integration for production
2. **Liv-ex integration is mock** — 260-line service with 10 hardcoded wines. Needs actual Liv-ex API credentials and integration
3. **Blockchain/NFT minting is placeholder** — `MintProvenanceNftJob` generates `NFT-{hex}` placeholders. Needs Ethereum/Polygon smart contract integration
4. **No formal staging environment** — Development → Production only. Staging planned
5. **Module S (Commercial) at 97%** — CSV bulk import and PIM sync features pending

**Performance bottlenecks:**
- Minimal at current scale. Recent optimization: ShippingOrder creation reduced from ~12 to ~4 queries (commit `6c2c34d`)
- N+1 queries addressed systematically in audit P3b (eager loading added to all relation managers)

**Security:**
- All OWASP concerns addressed: CORS restrictive, session encryption, file upload validation, HMAC signature verification
- No pending compliance work
- Security audit passes in CI (`composer audit`)

**Pending infrastructure migrations:**
- Staging environment setup
- Potential move to containerized deployment (Docker/Sail config already present)
- Redis for cache/queue (currently database-backed, Redis config ready)

---

## SECTION 5: DATA & ANALYTICS CURRENT STATE

### 5.1 What Data Do You Currently Collect

| Aspect | Status | Detail |
|--------|--------|--------|
| **User behavior events** | ❌ Not in ERP | ERP tracks operational events (allocations, shipments, payments), not consumer behavior |
| **Frontend event tracking** | N/A | ERP admin panel only. Consumer analytics would be in the consumer app |
| **API request logging** | ✅ Yes | Stripe webhooks logged (StripeWebhook model), Xero sync logged (XeroSyncLog), WMS events logged (ShippingOrderAuditLog), AI interactions logged (AiAuditLog) |
| **ML models/recommendations** | ⚠️ AI Assistant | Claude-powered AI assistant with 8 domain tools for operational queries. No recommendation engine yet |
| **Reports/dashboards** | ✅ Yes | Executive dashboard (8 widgets), 8 finance reports (Aging, Exposure, Reconciliation, Revenue, FX, Traceability, Storage Preview, Customer Finance), Audit Viewer |

### 5.2 Data Access

| Query | Can Do? | How |
|-------|---------|-----|
| "All purchases by member X, sorted by producer" | ✅ Yes | `Customer → vouchers → sellableSku → wineVariant → wineMaster → producer` (Eloquent chain) |
| "Top 20 most-purchased wines in Hong Kong last 6 months" | ✅ Yes | `Voucher::whereHas('customer.party.addresses', fn($q) => $q->where('country', 'HK'))->whereBetween('created_at', ...)->groupBy('sellable_sku_id')->orderByDesc(count)` |
| "All members who purchased from Producer Y" | ✅ Yes | `Customer::whereHas('vouchers.sellableSku.wineVariant.wineMaster', fn($q) => $q->where('producer_id', $producerY))` |
| "Member's taste profile from purchase history" | ⚠️ Possible but not built | Data exists (purchases → wines → regions/varietals/producers). Profile generation service not yet implemented |

**What prevents more analytics:**
- No consumer behavior tracking (views, searches, wishlists) — that's consumer app scope
- No dedicated analytics warehouse — all queries hit operational DB
- No pre-computed aggregations — real-time Eloquent queries only
- AI Assistant can answer ad-hoc operational queries today

---

## SECTION 6: ERP & OPERATIONS

### 6.1 Current Operations Stack

| Aspect | Detail |
|--------|--------|
| **ERP system** | This project — custom-built Crurated ERP (Laravel 12 + Filament v5) |
| **Inventory management** | Fully automated: Location → InboundBatch → SerializedBottle → InventoryCase → Movement (append-only ledger) |
| **Purchase orders** | Fully automated: ProcurementIntent → PurchaseOrder → Inbound. Every PO requires a ProcurementIntent (invariant enforced) |
| **Shipping/fulfillment** | ShippingOrder → WMS picking → Late Binding → Shipment confirmation. Bidirectional WMS integration |
| **Compliance** | Multi-currency support (Currency enum). Tax/duty management via invoice line items. 25+ country operations TBD |
| **Producer relationships** | `ProducerSupplierConfig` model + `Allocation` model. Allocation constraints track geographic/channel/customer type restrictions |
| **Accounting** | Xero integration (architecture complete). 5 invoice types: Subscription (INV0), Wine Purchase (INV1), Shipping (INV2), Storage (INV3), Service/Event (INV4) |

### 6.2 Pain Points

**Top 3 Operational Bottlenecks (current):**
1. **Xero sync is stub** — Financial data doesn't flow to accounting automatically yet. Manual reconciliation needed
2. **Liv-ex data is mock** — Wine masters need to be entered manually or imported from mock data
3. **No staging environment** — Production testing requires extra caution

**Manual Processes That Should Be Automated:**
- Xero invoice sync (architecture ready, needs API activation)
- Liv-ex wine data import (architecture ready, needs API key)
- NFT minting (framework ready, needs blockchain integration)
- CSV bulk import for Commercial module (planned)

**Data Silos:**
- Consumer-facing app data and ERP data are in separate systems. Integration points defined but not all connected
- Xero accounting data not yet synced bidirectionally

**Missing Reporting:**
- Profitability per producer/region/vintage analysis
- Cellar valuation trending (EstimatedMarketPrice model exists, tracking not active)
- Customer lifetime value computation
- Cross-module operational KPIs (partially addressed by dashboard)

---

## SECTION 7: WHITE-LABEL & PARTNERSHIPS

### 7.1 Current White-Label Architecture

| Aspect | Detail |
|--------|--------|
| **Architecture** | Multi-tenant foundations via Club → CustomerClub → Channel model. Clubs can have distinct channels, pricing, and allocations |
| **New club without code?** | ⚠️ Partially — Club + Channel + PriceBook can be created via admin panel, but no full white-label skin/branding system |
| **Inventory partitioning** | Via Allocation constraints — each allocation can be restricted by channel, geography, customer type. Operational blocks exist for inventory grouping |
| **Member data separation** | Not multi-tenant at DB level — all customers in same tables, separated by Club/Account relationships |
| **Partner customization** | Channel-specific pricing (PriceBook per Channel), allocation constraints, club membership tiers |

### 7.2 Partnership Pipeline

| Aspect | Detail |
|--------|--------|
| **Live partnerships** | Need to verify — Club model and infrastructure present |
| **In pipeline** | Need to verify with business team |
| **Undeliverable requests** | Full white-label branding (separate UI skin per partner), self-service partner portal, partner-specific analytics |

---

## SECTION 8: OPEN QUESTIONS FOR STRATEGIC ALIGNMENT

### 8.1 Highest-Priority Engineering Initiative

The **ERP completeness** — getting the remaining 3% of Module S (Commercial CSV import + PIM sync) and activating the three stub integrations (Xero, Liv-ex, Blockchain) for production use. The architecture is complete; these are API activation tasks, not architectural work.

### 8.2 Biggest Technical Risk (12 months)

**Scaling from single-server to multi-server** as transaction volume grows. The codebase is ready (stateless services, queue-based async, Redis-ready config), but the infrastructure hasn't been tested at scale. Additionally, the AI-first development model depends on LLM quality continuing to improve.

### 8.3 What Would You Rebuild From Scratch?

Nothing major — the system was designed from scratch with clean architecture 2 months ago. If forced to pick: the **white-label/multi-tenancy layer** could benefit from a more formal multi-tenant architecture (e.g., Stancl/Tenancy) if partner volume justifies it.

### 8.4 Data as Strategic Asset

Data is designed as a **strategic asset from day one**:
- UUID PKs enable cross-system data merging without conflicts
- Immutable audit log preserves complete operational history
- Append-only inventory movements create a complete provenance chain
- Every entity has created_by/updated_by tracking
- Finance is modeled as consequence of operations — the audit trail tells the complete story

The gap is in **consumer analytics** (behavior, preferences, engagement) which lives outside ERP scope.

### 8.5 Current AI/ML Approach

**In production:**
- Claude-powered AI assistant integrated into admin panel (8 domain-specific tools)
- Token tracking, cost monitoring, rate limiting (60/hr, 500/day)
- AI audit log for compliance

**Unique aspect:**
- The **entire ERP was built using AI-assisted development** (Claude Code). This is both the development methodology and a product feature
- PRDs → AI implementation → AI testing → AI code review pipeline

**Planned:**
- Taste profile generation from purchase data
- Wine recommendation based on allocation/purchase patterns
- Demand forecasting from allocation burn rate

### 8.6 What "ERP Optimization" Means

Specifically:
1. **Eliminating manual operations** — automate invoice-to-accounting (Xero), wine-data-import (Liv-ex), provenance-tracking (blockchain)
2. **Single source of truth** — all wine trading operations (allocation → fulfillment → finance) in one system instead of spreadsheets
3. **Real-time visibility** — dashboard KPIs, alert center, aging reports instead of end-of-month reconciliation
4. **Compliance by design** — immutable audit trail, role-based access, append-only ledger

### 8.7 Social Commerce ↔ Core Commerce Relationship

Designed as **loosely coupled**:
- ERP provides the data backbone (allocations, vouchers, inventory, finance)
- Consumer app calls ERP APIs for operations (the 2 REST endpoints + webhook pattern)
- Social features (profiles, reviews, badges) would live in the consumer app layer
- Customer data flows back to ERP via webhooks/events for operational tracking
- The `VoucherTransfer` and trading platform webhook are examples of this pattern already working

---

## APPENDIX: KEY ARCHITECTURAL INVARIANTS

These are enforced in code (model boot guards, service validation, database constraints):

1. **1 voucher = 1 bottle** — quantity is always exactly 1
2. **Allocation lineage immutable** — allocation_id never changes after creation
3. **Late Binding ONLY in Fulfillment** — voucher → bottle binding happens only at shipment confirmation
4. **Voucher redemption ONLY at shipment** — never before
5. **Case breaking is IRREVERSIBLE** — Intact → Broken, never back
6. **Finance is consequence, not cause** — events from other modules generate invoices
7. **Invoice type immutable** — INV0-INV4 set at creation, never changed
8. **Every PurchaseOrder requires a ProcurementIntent** — no orphan POs
9. **ERP authorizes, WMS executes** — WMS never initiates operations
10. **Append-only movements** — inventory movement ledger is never modified or deleted
11. **Audit log immutable** — no updates or deletes on audit records

---

## APPENDIX: TECHNOLOGY VERSIONS

| Component | Version |
|-----------|---------|
| PHP | 8.5.2 |
| Laravel | 12.x |
| Filament | 5.3.5 |
| Livewire | 4.x |
| Tailwind CSS | 4.0 |
| Vite | 7.0.7 |
| PHPUnit | 11.5.3 |
| PHPStan (Larastan) | Level 8 |
| Stripe PHP | 19.3 |
| DomPDF | 3.1 |
| Laravel AI | 0.1.5 |
| MySQL | 8+ (production) |
| Node.js | 22 (CI) |
