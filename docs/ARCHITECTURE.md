# Architecture Guide

Technical reference for the Crurated ERP architecture. Read `docs/ONBOARDING.md` first for setup and orientation.

## 1. Module Map

| Code | Module | Key Models | Depends On |
|------|--------|------------|------------|
| — | **Infrastructure** | `User`, `AuditLog` | — |
| 0 | **PIM** (Product Information) | `WineMaster`, `WineVariant`, `SellableSku`, `LiquidProduct`, `Format`, `CaseConfiguration` | Infrastructure |
| K | **Customers** | `Party`, `PartyRole`, `Customer`, `Account`, `Membership`, `Club`, `Address` | Infrastructure |
| A | **Allocations** | `Allocation`, `Voucher`, `VoucherTransfer`, `CaseEntitlement`, `TemporaryReservation` | PIM, Customers |
| S | **Commercial** | `Channel`, `PriceBook`, `PricingPolicy`, `Offer`, `Bundle`, `DiscountRule`, `EstimatedMarketPrice` | PIM, Allocations, Customers |
| D | **Procurement** | `ProcurementIntent`, `PurchaseOrder`, `Inbound`, `BottlingInstruction` | PIM, Allocations |
| B | **Inventory** | `Location`, `InboundBatch`, `SerializedBottle`, `InventoryCase`, `InventoryMovement` | PIM, Allocations, Procurement |
| C | **Fulfillment** | `ShippingOrder`, `Shipment`, `ShippingOrderLine`, `ShippingOrderException` | PIM, Allocations, Inventory, Commercial, Customers |
| E | **Finance** | `Invoice`, `InvoiceLine`, `Payment`, `CreditNote`, `Refund`, `Subscription`, `StorageBillingPeriod` | Allocations, Fulfillment, Customers |

### Dependency Diagram

```
Infrastructure
├── Module 0 (PIM)
└── Module K (Customers)
         │
    Module A (Allocations & Vouchers)
    ├── Module S (Commercial)
    ├── Module D (Procurement)
    │       │
    │   Module B (Inventory)
    │       │
    └────► Module C (Fulfillment)
                 │
           Module E (Finance)
```

**Rule:** Dependencies flow top-to-bottom only. A module never depends on a module below it.

## 2. Architecture Patterns

### Domain Folder Structure

Every domain concept follows the same folder layout:

```
app/{Layer}/{Module}/{ClassName}.php
```

Layers: `Models`, `Services`, `Enums`, `Events`, `Listeners`, `Jobs`, `Filament/Resources`, `Filament/Pages`, `Filament/Widgets`.

### UUID Primary Keys

All models use UUID primary keys via the `HasUuid` trait. Never use auto-increment IDs.

```php
use App\Traits\HasUuid;

class MyModel extends Model
{
    use HasUuid;
    // Migration: $table->uuid('id')->primary();
}
```

### Audit Trail

Two complementary mechanisms:

1. **`Auditable` trait** — tracks `created_by` / `updated_by` on the model itself
2. **`AuditLoggable` trait** — writes to the immutable `audit_logs` table for full change history

### Soft Deletes

~95% of models use `SoftDeletes`. Always include it unless there's a specific reason not to.

### Immutability Guards

Critical fields are protected in the model's `boot()` method:

```php
protected static function boot(): void
{
    parent::boot();

    static::updating(function (self $model) {
        if ($model->isDirty('invoice_type')) {
            throw new \RuntimeException('Invoice type cannot be modified after creation.');
        }
    });
}
```

This pattern is used for: invoice type, allocation lineage, voucher quantity, serial numbers.

### Decimal Math

**Never use float arithmetic.** Always use BC Math functions:

```php
$total = bcadd($subtotal, $tax, 2);      // Addition
$net = bcsub($gross, $discount, 2);       // Subtraction
$lineTotal = bcmul($price, $qty, 2);      // Multiplication
```

### Enum Standard

All enums are string-backed and implement four standard methods:

```php
enum InvoiceType: string
{
    case Subscription = 'subscription';       // INV0
    case VoucherSale = 'voucher_sale';        // INV1

    public function label(): string { /* Human-readable name */ }
    public function color(): string { /* Filament badge color */ }
    public function icon(): string  { /* Heroicon name */ }

    // State-machine enums also implement:
    public function allowedTransitions(): array { /* Valid next states */ }
}
```

### Service Layer

Business logic lives in Services, **never** in Controllers or Models.

- Controllers: receive HTTP requests, delegate to services, return responses
- Models: define structure, relationships, scopes, and immutability guards
- Services: orchestrate operations, enforce business rules, fire events

## 3. Event-Driven Cross-Module Flow

Modules communicate via Laravel Events. The Finance module is **always a consequence, never a cause** — other modules fire events, Finance generates invoices.

### Event → Listener Map

| Event | Listener | Flow | Result |
|-------|----------|------|--------|
| `VoucherIssued` | `CreateProcurementIntentOnVoucherIssued` | A → D | Creates a draft ProcurementIntent for ops review |
| `SubscriptionBillingDue` | `GenerateSubscriptionInvoice` | → E | Generates INV0 (subscription invoice) |
| `VoucherSaleConfirmed` | `GenerateVoucherSaleInvoice` | → E | Generates INV1 (voucher sale invoice, auto-issued) |
| `ShipmentExecuted` | `GenerateShippingInvoice` | → E | Generates INV2 (shipping/redemption invoice) |
| `EventBookingConfirmed` | `GenerateEventServiceInvoice` | → E | Generates INV4 (event/service invoice) |
| `StripePaymentFailed` | `HandleStripePaymentFailure` | E | Handles Stripe webhook payment failures |

All event→listener mappings are registered in `app/Providers/EventServiceProvider.php`.

### Invoice Types

| Code | Type | Trigger | Payment |
|------|------|---------|---------|
| INV0 | Subscription | Monthly billing cycle (scheduled job) | Deferred (due date) |
| INV1 | Voucher Sale | Customer purchases allocation vouchers | Immediate (auto-issued) |
| INV2 | Shipping | Shipment confirmed / voucher redeemed | Immediate |
| INV3 | Storage | Monthly storage billing (scheduled job) | Deferred (due date) |
| INV4 | Event/Service | Event booking confirmed | Immediate |

## 4. Key Invariants

These rules are enforced in code and must **never** be violated:

| # | Invariant | Enforced In |
|---|-----------|-------------|
| 1 | **1 voucher = 1 bottle** (quantity is always 1) | `Voucher` model boot(), `VoucherService` |
| 2 | **Allocation lineage is immutable** (allocation_id never changes) | `Voucher` model boot(), `SerializedBottle` model boot() |
| 3 | **Late Binding only in Fulfillment** (voucher→bottle binding happens at shipment) | `LateBindingService` |
| 4 | **Voucher redemption only at shipment confirmation** | `ShipmentService` |
| 5 | **Case breaking is irreversible** (Intact → Broken, never back) | `InventoryCase` model, `CaseBreakingService` |
| 6 | **Finance is consequence, not cause** (events from other modules trigger invoices) | `EventServiceProvider`, Finance listeners |
| 7 | **Invoice type is immutable** after creation | `Invoice` model boot() |
| 8 | **Every PurchaseOrder requires a ProcurementIntent** | `PurchaseOrder` model validation |
| 9 | **ERP authorizes, WMS executes** (ERP never receives commands from warehouse) | API design, `VerifyHmacSignature` middleware |

## 5. External APIs

The ERP exposes only 2 API endpoints:

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/vouchers/{voucher}/trading-complete` | POST | HMAC-SHA256 | Trading platform notifies voucher trade completion |
| `/api/webhooks/stripe` | POST | Stripe signature | Stripe payment webhooks |

All other interaction happens through the Filament admin panel at `/admin`.

### Authentication

- **Admin panel:** Laravel session auth via Filament (email + password)
- **Trading API:** HMAC-SHA256 signature in `X-Signature` header, with `X-Timestamp` (5-minute tolerance)
- **Stripe webhooks:** Stripe signature verification in controller

## 6. Scheduled Jobs

| Schedule | Job | Module | Purpose |
|----------|-----|--------|---------|
| Every minute | `ExpireReservationsJob` | A | Expire temporary voucher reservations |
| Every minute | `ExpireTransfersJob` | A | Expire pending voucher transfers |
| Daily 03:00 | `CleanupIntegrationLogsJob` | E | Clean Stripe/Xero logs > 90 days |
| Daily 06:00 | `ProcessSubscriptionBillingJob` | E | Generate INV0 subscription invoices |
| Daily 08:00 | `IdentifyOverdueInvoicesJob` | E | Mark overdue invoices |
| Daily 09:00 | `SuspendOverdueSubscriptionsJob` | E | Suspend subscriptions with overdue INV0 |
| Daily 10:00 | `BlockOverdueStorageBillingJob` | E | Block custody operations for unpaid storage |
| Hourly | `AlertUnpaidImmediateInvoicesJob` | E | Alert on unpaid INV1/INV2/INV4 |
| Monthly 1st 05:00 | `GenerateStorageBillingJob` | E | Generate INV3 storage invoices |
| Daily | `ApplyBottlingDefaultsJob` | D | Apply bottling defaults when deadline expires |

Registered in `routes/console.php`.

## 7. How to Create New...

### New Model

```bash
php artisan make:model {Module}/{Name} -mfs --no-interaction
```

Then add to the model:
- `use HasUuid, SoftDeletes, Auditable;`
- UUID primary key in migration: `$table->uuid('id')->primary()`
- PHPDoc with `@property` annotations for all columns
- Typed relationships with generic PHPDoc: `@return BelongsTo<ParentModel, $this>`
- Immutability guards in `boot()` for critical fields

### New Service

Create `app/Services/{Module}/{Name}Service.php` manually. Add:
- Class-level PHPDoc explaining the service's responsibility and architectural context
- Full PHPDoc on public methods with `@param`, `@return`, `@throws`
- Constructor injection for dependencies

### New Enum

```bash
php artisan make:enum {Module}/{Name} --no-interaction
```

Implement: `label()`, `color()`, `icon()`. For state machines, add `allowedTransitions()` and `description()`.

### New Filament Resource

```bash
php artisan make:filament-resource {Module}/{Name} --no-interaction
```

Add `->columns(1)` to the top-level schema in both `form()` and `infolist()` methods (Filament v5 defaults to 2 columns, which breaks layout).

### New Event + Listener

1. Create event in `app/Events/{Module}/`
2. Create listener in `app/Listeners/{Module}/`
3. Register the mapping in `app/Providers/EventServiceProvider.php`
4. If the listener should be async, implement `ShouldQueue`

## 8. Testing

```bash
# Run all tests
php artisan test --compact

# Run a specific test file
php artisan test --compact tests/Feature/ExampleTest.php

# Filter by test name
php artisan test --compact --filter=testInvoiceTypeIsImmutable

# Full quality pipeline (Pint + PHPStan + tests)
composer quality
```

Tests use PHPUnit (not Pest). Feature tests go in `tests/Feature/`, unit tests in `tests/Unit/`. Use factories when available, and check for existing factory states before manually setting up models.

## 9. Admin Panel Navigation

The Filament admin panel groups resources into these navigation sections:

| Group | Icon | Modules |
|-------|------|---------|
| PIM | `heroicon-o-cube` | Wine masters, variants, SKUs, formats |
| Allocations | `heroicon-o-rectangle-stack` | Allocations, case entitlements |
| Vouchers | `heroicon-o-ticket` | Vouchers, transfers, reservations |
| Commercial | `heroicon-o-currency-dollar` | Channels, price books, offers, bundles |
| Customers | `heroicon-o-user-group` | Parties, customers, accounts, memberships |
| Procurement | `heroicon-o-clipboard-document-list` | Intents, purchase orders, inbounds |
| Inventory | `heroicon-o-archive-box` | Locations, bottles, cases, movements |
| Fulfillment | `heroicon-o-truck` | Shipping orders, shipments |
| Finance | `heroicon-o-banknotes` | Invoices, payments, credit notes, subscriptions |
| System | `heroicon-o-cog-6-tooth` | Users, audit logs, system health |

Configuration: `app/Providers/Filament/AdminPanelProvider.php`
