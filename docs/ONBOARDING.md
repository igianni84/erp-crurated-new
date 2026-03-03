# Developer Onboarding Guide

Welcome to Crurated ERP. This guide gets you from zero to productive in under 30 minutes.

## 1. Prerequisites

| Tool | Version | Check |
|------|---------|-------|
| PHP | 8.5+ | `php -v` |
| Composer | 2.x | `composer -V` |
| MySQL | 8.0+ | `mysql --version` |
| Node.js | 22+ | `node -v` |
| npm | 10+ | `npm -v` |

> SQLite is **not** supported — the app uses MySQL-specific features (JSON columns, full-text search).

## 2. Setup Locale

```bash
# 1. Clone the repo
git clone git@github.com:igianni84/erp-crurated-new.git
cd erp-crurated-new

# 2. Install everything + generate key + run migrations + build frontend
composer setup

# 3. Configure your .env
#    Edit DB_DATABASE, DB_USERNAME, DB_PASSWORD for your local MySQL.
#    All other defaults are fine for development.

# 4. Seed the database with realistic test data (all 10 modules)
php artisan migrate:fresh --seed

# 5. Start the dev environment (4 concurrent processes)
composer run dev
```

`composer run dev` starts:
- **Laravel server** on `http://localhost:8000` (blue)
- **Queue worker** for async jobs (purple)
- **Pail** log viewer in terminal (pink)
- **Vite** for hot-reload frontend (orange)

### First Login

Open `http://localhost:8000/admin` and log in with:
- **Email:** `admin@crurated.com`
- **Password:** `password`

## 3. Codebase Orientation

### First Files to Read (in order)

| # | File | Why |
|---|------|-----|
| 1 | `CLAUDE.md` → "Module Map" section | Overview of all 10 modules, their purpose, and status |
| 2 | `docs/ARCHITECTURE.md` | Data flow, patterns, event chains, invariants |
| 3 | `app/Models/Allocation/Voucher.php` | Best example of a well-documented model (invariants, PHPDoc, immutability guards) |
| 4 | `app/Services/Finance/InvoiceService.php` | Service layer pattern with full PHPDoc and domain context |
| 5 | `app/Enums/Finance/InvoiceType.php` | Standard enum pattern (`label()`, `color()`, `icon()`, `description()`) |
| 6 | `app/Filament/Resources/Finance/InvoiceResource.php` | Filament resource pattern with form/table/infolist |

### Where Things Live

```
app/
├── Models/{Module}/          # Eloquent models, grouped by domain
├── Services/{Module}/        # Business logic (never in controllers/models)
├── Enums/{Module}/           # String-backed enums with label/color/icon
├── Events/{Module}/          # Domain events (cross-module communication)
├── Listeners/{Module}/       # Event handlers
├── Jobs/{Module}/            # Queued + scheduled jobs
├── Filament/
│   ├── Resources/{Module}/   # Admin CRUD resources
│   ├── Pages/{Module}/       # Custom admin pages (dashboards, tools)
│   └── Widgets/{Module}/     # Dashboard widgets
├── Http/
│   ├── Controllers/          # Minimal — only API endpoints
│   └── Middleware/            # SecurityHeaders, VerifyHmacSignature
├── Policies/                 # Authorization policies
├── Traits/                   # HasUuid, Auditable, AuditLoggable, etc.
└── Providers/                # EventServiceProvider (event→listener map)
```

Modules: `Pim`, `Allocation`, `Commercial`, `Customer`, `Procurement`, `Inventory`, `Fulfillment`, `Finance`, `AI`.

## 4. Domain Glossary

| Term | Meaning |
|------|---------|
| **WineMaster** | A wine identity (producer + name), independent of vintage |
| **WineVariant** | A specific vintage of a WineMaster (e.g., Barolo 2019) |
| **SellableSku** | A purchasable unit: variant + format (e.g., Barolo 2019, 750ml) |
| **LiquidProduct** | The liquid content of a wine, decoupled from its packaging format |
| **Voucher** | A right to receive exactly 1 bottle (quantity is always 1) |
| **Late Binding** | Deferred assignment of a physical bottle to a voucher — happens only at shipment |
| **Allocation Lineage** | The immutable chain: Allocation → Voucher → SerializedBottle |
| **Party / PartyRole** | A legal entity (Party) that can hold multiple roles (customer, supplier, producer) |
| **ProcurementIntent** | An auto-generated purchase request, created when vouchers are issued |
| **INV0–INV4** | Five invoice types, each triggered by a different business event (see Architecture doc) |
| **EMP** | Estimated Market Price — reference price for wine trading |
| **CaseEntitlement** | Right to receive a full case (vs individual bottles) |
| **InboundBatch** | A group of bottles arriving from a supplier, linked to a PurchaseOrder |
| **SerializedBottle** | A physical bottle with a unique serial number, tracked individually |

## 5. Daily Commands

```bash
# Start everything
composer run dev

# Run all quality checks (lint + PHPStan + tests)
composer quality

# Run a specific test
php artisan test --compact --filter=testInvoiceTypeIsImmutable

# Run all tests in a file
php artisan test --compact tests/Unit/Models/Finance/InvoiceImmutabilityTest.php

# Format only changed files
vendor/bin/pint --dirty

# Static analysis
composer analyse

# Laravel REPL (tinker)
php artisan tinker

# Fresh database with seed data
php artisan migrate:fresh --seed
```

## 6. Integrations

The ERP integrates with three external systems. In development, all are optional — the app runs fine without them.

| Integration | Purpose | Required Env Vars |
|-------------|---------|-------------------|
| **Stripe** | Payment processing, webhooks | `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` |
| **Xero** | Accounting sync (invoices, contacts) | `XERO_CLIENT_ID`, `XERO_CLIENT_SECRET`, `XERO_TENANT_ID`, `XERO_REDIRECT_URI` |
| **Trading Platform** | External wine trading API (HMAC auth) | `TRADING_PLATFORM_HMAC_SECRET` |

Check integration status in the admin panel: **Finance → Integration Configuration**.

## 7. Further Reading

| Document | Purpose |
|----------|---------|
| `docs/ARCHITECTURE.md` | Technical architecture, patterns, event flows, invariants |
| `docs/modules/*.md` | Per-module UX narratives and business logic details |
| `tasks/prd-module-*.md` | Product Requirements Documents (542 user stories across all modules) |
| `CLAUDE.md` | AI agent instructions — but also the most complete project overview |
| `_ide_helper_models.php` | Auto-generated: all model properties, casts, and relationships |
