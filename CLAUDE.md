## Workflow Orchestration

### 1. Plan Mode Default
- Enter plan mode for ANY non-trivial task (3+ steps or architectural decisions)
- If something goes sideways, STOP and re-plan immediately – don't keep pushing
- Use plan mode for verification steps, not just building
- Write detailed specs upfront to reduce ambiguity

### 2. Subagent Strategy
- Use subagents liberally to keep main context window clean
- Offload research, exploration, and parallel analysis to subagents
- For complex problems, throw more compute at it via subagents
- One task per subagent for focused execution

### 3. Self-Improvement Loop
- After ANY correction from the user: update `tasks/lessons.md` with the pattern
- Write rules for yourself that prevent the same mistake
- Ruthlessly iterate on these lessons until mistake rate drops
- Review lessons at session start for relevant project

### 4. Verification Before Done
- Non inventare nomi di variabili, funzioni, classi o altro, verifica sempre la loro esistenza prima di usarle
- Never mark a task complete without proving it works
- Diff behavior between main and your changes when relevant
- Ask yourself: "Would a staff engineer approve this?"
- Run tests, check logs, demonstrate correctness

### 5. Demand Elegance (Balanced)
- For non-trivial changes: pause and ask "is there a more elegant way?"
- If a fix feels hacky: "Knowing everything I know now, implement the elegant solution"
- Skip this for simple, obvious fixes – don't over-engineer
- Challenge your own work before presenting it

### 6. Autonomous Bug Fixing
- When I report a bug, don't start by trying to fix it. Instead, start by writing a test that reproduces the bug. Then, have subagents try to fix the bug and prove it with a passing test.
- Point at logs, errors, failing tests – then resolve them
- Zero context switching required from the user
- Go fix failing CI tests without being told how

## Task Management

1. **Plan First:** Write plan to `tasks/todo.md` with checkable items
2. **Verify Plan:** Check in before starting implementation
3. **Track Progress:** Mark items complete as you go
4. **Explain Changes:** High-level summary at each step
5. **Document Results:** Add review section to `tasks/todo.md`
6. **Capture Lessons:** Update `tasks/lessons.md` after corrections

## Core Principles

- **Simplicity First:** Make every change as simple as possible. Impact minimal code.
- **No Laziness:** Find root causes. No temporary fixes. Senior developer standards.
- **Minimal Impact:** Changes should only touch what's necessary. Avoid introducing bugs.

## Project Context

### What This Is
**Crurated ERP** — ERP per il trading di vini pregiati e beni di lusso. Multi-modulo, event-driven, sviluppato incrementalmente.

### Tech Stack
- **Backend:** Laravel 12, PHP 8.4+, MySQL (SQLite dev)
- **Admin UI:** Filament 3 (42 resources, 30 custom pages, 3 widgets)
- **Frontend:** Tailwind CSS 4, Vite 7
- **Integrations:** Stripe (payments), Xero (accounting), WMS (warehouse), Liv-ex (wine data)
- **Quality:** PHPStan level 5, Laravel Pint, PHPUnit

### Module Map (dependency order)
| Code | Module | Purpose | Status |
|------|--------|---------|--------|
| — | Infrastructure | Auth, roles (super_admin/admin/manager/editor/viewer), base setup | ✅ Done |
| 0 | PIM | Wine Master → Variant → SellableSku, Formats, CaseConfig, Liquid Products | ✅ Done |
| K | Customers | Party/PartyRole (multi-role), Customer, Account, Membership, Clubs, Blocks | ✅ Done |
| S | Commercial | Channels, PriceBooks, PricingPolicies, Offers, Bundles, DiscountRules, EMP | ✅ ~97% (CSV import + PIM sync TODO) |
| A | Allocations | Allocations, Vouchers (1 voucher = 1 bottiglia), CaseEntitlements, Transfers | ✅ Done |
| D | Procurement | ProcurementIntents → PurchaseOrders → Inbounds, BottlingInstructions | ✅ Done (68/68) |
| B | Inventory | Locations, InboundBatches, SerializedBottles, Cases, Movements (append-only) | ✅ Done |
| C | Fulfillment | ShippingOrders → Late Binding → Shipments, Voucher Redemption | ✅ Done |
| E | Finance | Invoices (INV0-INV4), Payments, CreditNotes, Refunds, Subscriptions, Storage | ✅ Done (132/132) |
| — | Admin Panel | Dashboards, Alert Center, Audit Viewer, System Health | 📋 PRD ready |

### Architecture Patterns
- **Domain folders:** `app/{Models,Services,Enums,Events,Listeners,Jobs,Filament}/Module/`
- **UUID PKs everywhere** via `HasUuid` trait
- **Audit trail:** Immutable `AuditLog` + `Auditable`/`AuditLoggable` traits
- **Soft deletes** on ~95% of models
- **Enums:** String-backed PHP 8.4+ with `label()`, `color()`, `icon()`, `allowedTransitions()`
- **Event-driven cross-module:** Events trigger listeners (e.g., VoucherIssued → CreateProcurementIntent)
- **Service layer:** Business logic in Services, not Controllers or Models
- **Immutability guards:** Model `boot()` with `static::updating()` for critical fields

### Key Invariants (NEVER violate)
1. **1 voucher = 1 bottiglia** (quantity always 1)
2. **Allocation lineage immutable** (allocation_id never changes after creation)
3. **Late Binding ONLY in Module C** (voucher→bottle binding)
4. **Voucher redemption ONLY at shipment confirmation**
5. **Case breaking is IRREVERSIBLE** (Intact → Broken, never back)
6. **Finance is consequence, not cause** (events from other modules generate invoices)
7. **Invoice type immutable** after creation (INV0-INV4)
8. **Every PurchaseOrder requires a ProcurementIntent**
9. **ERP authorizes, WMS executes** (never the reverse)

### Codebase Numbers
- 73 Models, 40 Services, 101 Enums, 18 Jobs, 9 Events, 12 Policies
- 98 migrations, 42 Filament Resources, 30 custom pages
- PRDs totali: 542 user stories across 10 modules

### Migration Numbering
- Module 0 (PIM): 200000+
- Module E (Finance): 300000+
- Module S (Commercial): 380000+
- Module K (Customers): 390000+
- Module D (Procurement): 400000+

### Coding Conventions
- Model: `app/Models/{Module}/ModelName.php`
- Enum: `app/Enums/{Module}/EnumName.php` — string-backed, with `label()`/`color()`/`icon()`
- Service: `app/Services/{Module}/ServiceName.php`
- Filament: `app/Filament/Resources/{Module}/ResourceName.php`
- Traits: `HasUuid`, `Auditable`, `AuditLoggable`, `HasLifecycleStatus`, `HasProductLifecycle`
- Decimal math: use `bcadd()`, `bcsub()`, `bcmul()` — never float arithmetic
- Immutability: enforce in model `boot()` with `static::updating()`
- PHPStan: explicit null checks, no nullsafe + null coalescing combos

### PRD & Task Files
- PRDs: `tasks/prd-{module}.md` (detailed specs with user stories)
- Progress: `tasks/progress-{module}.txt` (implementation logs)
- Ralph JSONs: `tasks/prd-{module}.json` (task runner format)
- Project plan: `tasks/project-plan.md`

## Server & Infrastructure

### Production Server (Ploi)
- **Host:** `46.224.207.175`
- **SSH:** `ssh ploi@46.224.207.175` (key `~/.ssh/id_ed25519` già registrata)
- **Site path:** `/home/ploi/crurated.giovannibroegg.it`
- **URL:** `https://crurated.giovannibroegg.it`
- **PHP:** 8.5 (FPM: `sudo -S service php8.5-fpm reload`)
- **DB:** MySQL, host `127.0.0.1:3306`, database `erpcrurated`
- **Ploi panel:** `ploi.io/panel/servers/106731/sites/342059`

### Git Repos
- **Locale (dev):** `github.com/igianni84/erp-crurated-new` ← repo attivo
- **Vecchio (deprecato):** `github.com/igianni84/erpcrurated` ← NON usare
- Il server Ploi DEVE puntare a `erp-crurated-new`

### Deploy Script (Ploi)
```bash
cd /home/ploi/crurated.giovannibroegg.it
git fetch origin main
git reset --hard origin/main
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
echo "" | sudo -S service php8.5-fpm reload
php artisan migrate --force
php artisan filament:optimize
php artisan optimize
```

### Quick SSH Commands
```bash
# Logs
ssh ploi@46.224.207.175 "tail -50 /home/ploi/crurated.giovannibroegg.it/storage/logs/laravel.log"

# Tinker
ssh ploi@46.224.207.175 "cd /home/ploi/crurated.giovannibroegg.it && php artisan tinker"

# Migration status
ssh ploi@46.224.207.175 "cd /home/ploi/crurated.giovannibroegg.it && php artisan migrate:status"

# Fresh seed (DESTRUCTIVE — solo staging/dev)
ssh ploi@46.224.207.175 "cd /home/ploi/crurated.giovannibroegg.it && php artisan migrate:fresh --force --seed"

# Clear all caches (including Filament)
ssh ploi@46.224.207.175 "cd /home/ploi/crurated.giovannibroegg.it && php artisan filament:optimize-clear && php artisan optimize:clear"
```

### Known Gotchas
- Seeders usano `fake()` → `fakerphp/faker` è in `require` (non `require-dev`)
- Deploy Ploi usa la sua config di repo, non il git remote del server — cambiare repo va fatto dal pannello Ploi
- Dopo cambio Filament version: sempre `php artisan filament:upgrade` + `php8.5-fpm reload`