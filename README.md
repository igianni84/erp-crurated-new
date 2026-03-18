# Crurated ERP

[![CI](https://github.com/igianni84/erp-crurated-new/actions/workflows/ci.yml/badge.svg)](https://github.com/igianni84/erp-crurated-new/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/igianni84/erp-crurated-new/branch/main/graph/badge.svg)](https://codecov.io/gh/igianni84/erp-crurated-new)

Enterprise Resource Planning system for fine wine and luxury goods trading. Built with Laravel 12, Filament 5, and PHP 8.5.

> **New here?** Start with the [Onboarding Guide](docs/ONBOARDING.md).

## Requirements

- PHP 8.5+
- MySQL 8.0+
- Composer 2.x
- Node.js 22+ (for Vite asset compilation)

## Quick Start

```bash
# 1. Clone and install everything
git clone git@github.com:igianni84/erp-crurated-new.git
cd erp-crurated-new
composer setup

# 2. Configure .env (edit DB credentials)
# 3. Seed the database with test data for all modules
php artisan migrate:fresh --seed

# 4. Start the dev environment
composer run dev
```

`composer run dev` starts 4 concurrent processes: Laravel server (`localhost:8000`), queue worker, Pail log viewer, and Vite HMR.

**Login:** `admin@crurated.com` / `password`

## Project Structure

```
app/
├── Models/
│   ├── Pim/              # Wine catalog: WineMaster, WineVariant, SellableSku
│   ├── Allocation/       # Allocations, Vouchers, Transfers
│   ├── Commercial/       # Channels, PriceBooks, Offers, Bundles
│   ├── Customer/         # Party, Customer, Account, Membership
│   ├── Procurement/      # ProcurementIntent, PurchaseOrder, Inbound
│   ├── Inventory/        # Location, SerializedBottle, InventoryCase
│   ├── Fulfillment/      # ShippingOrder, Shipment
│   ├── Finance/          # Invoice, Payment, CreditNote, Subscription
│   └── AI/               # AI assistant audit logs
├── Services/{Module}/    # Business logic (41 services)
├── Enums/{Module}/       # String-backed enums (95 enums)
├── Events/{Module}/      # Domain events for cross-module communication
├── Listeners/{Module}/   # Event handlers
├── Jobs/{Module}/        # Queued and scheduled jobs
├── Filament/
│   ├── Resources/{Module}/  # 45 admin CRUD resources
│   ├── Pages/{Module}/      # 33 custom admin pages
│   └── Widgets/{Module}/    # 11 dashboard widgets
├── Traits/               # HasUuid, Auditable, AuditLoggable, etc.
└── Providers/            # EventServiceProvider, AdminPanelProvider
```

**78 models** across **10 modules**. See [Architecture Guide](docs/ARCHITECTURE.md) for the full module map and dependency diagram.

## Development Commands

```bash
composer run dev      # Start dev server + queue + logs + Vite
composer quality      # Run lint + PHPStan + tests (full pipeline)
composer lint         # Fix code style (Pint)
composer lint:test    # Check code style without fixing
composer analyse      # Run PHPStan (level 6)
composer test         # Run PHPUnit tests

# Specific test
php artisan test --compact --filter=testName

# Format only changed files
vendor/bin/pint --dirty
```

## Database Seeding

35 seeders populate all modules with realistic test data:

```bash
# Fresh database + seed
php artisan migrate:fresh --seed

# Seed only (after existing migration)
php artisan db:seed
```

Seeders run in dependency order (PIM → Customers → Allocations → ... → Finance). `fakerphp/faker` is in `require` (not `require-dev`) because seeders are available in all environments.

## Environment Configuration

Copy `.env.example` and configure at minimum:

| Variable | Dev Default | Notes |
|----------|-------------|-------|
| `DB_DATABASE` | `crurated_erp` | Your local MySQL database |
| `DB_USERNAME` | `root` | |
| `DB_PASSWORD` | *(empty)* | |
| `QUEUE_CONNECTION` | `sync` | Jobs run immediately (easier debugging) |
| `MAIL_MAILER` | `log` | Emails go to `storage/logs/` |

For external integrations (optional in dev):

| Integration | Variables |
|-------------|-----------|
| Stripe | `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` |
| Xero | `XERO_CLIENT_ID`, `XERO_CLIENT_SECRET`, `XERO_TENANT_ID` |
| Trading Platform | `TRADING_PLATFORM_HMAC_SECRET` |

See `.env.example` for all variables with inline documentation.

## Documentation

| Document | Purpose |
|----------|---------|
| [Onboarding Guide](docs/ONBOARDING.md) | First-day setup, orientation, glossary |
| [Architecture Guide](docs/ARCHITECTURE.md) | Modules, patterns, events, invariants, scheduled jobs |
| `docs/modules/*.md` | Per-module UX narratives and business logic |
| `tasks/prd-module-*.md` | Product Requirements Documents (542 user stories) |
| `CLAUDE.md` | AI agent instructions + complete project overview |

## License

Proprietary - Crurated
