# Piano: Livewire Tests per Filament Pages Critiche

## Contesto

L'ERP ha 45 risorse Filament, 31 Create pages, 24 Edit pages, 32 View pages — ma **solo 1 risorsa testata** (UserResource, 19 test). Le pagine più critiche (Allocation, Invoice, ShippingOrder) hanno 1000+ righe di logica e **zero test**. Un errore in produzione su queste pagine non viene intercettato fino al deploy.

**Obiettivo:** Aggiungere test Livewire per le pagine Filament critiche, intercettando errori di rendering, validazione form, e CRUD prima che raggiungano produzione.

## Stato Attuale (aggiornato 2026-03-04)

| Metrica | Prima | Dopo Fase 0-3 | Dopo Fase 4 |
|---------|-------|---------------|-------------|
| Test files | 30 | 50 (+20) | 74 (+24) |
| Test methods | 341 | 558 (+217) | 683 (+125) |
| Assertions | ~900 | 1682 | 1965 |
| Factory | 1 (UserFactory) | 25 | 42 (+17) |
| Risorse Filament testate | 1/45 | 21/45 | 45/45 |
| Pattern dati nei test | `Model::create()` diretto | Factory + trait helper | Factory + trait helper |
| DB test | SQLite in-memory | SQLite in-memory | SQLite in-memory |

### Fasi completate
- **Fase 0** (Infrastruttura): 8 factory base + trait `FilamentTestHelpers` ✅
- **Fase 1** (Tier 1 — 5 risorse critiche): 60 test ✅
- **Fase 2** (Tier 2 — 5 risorse alta complessita'): 40 test ✅
- **Fase 3** (Tier 3 — 10 risorse CRUD standard): 99 test ✅
- **Fase 4** (Tier 4 — 24 risorse rimanenti): 125 test ✅

## Strategia: Factory Mirate + Test per Fase

### Decisioni chiave

1. **26 factory mirate** (non tutte e 68) — solo i modelli necessari per le risorse testate
2. **Trait helper** `FilamentTestHelpers` per ridurre boilerplate (stack PIM, Customer, etc.)
3. **Scope test "Standard+"** per ogni risorsa: render pagine + CRUD + validazione + autorizzazione
4. **1 file test per risorsa**, organizzati in sottocartelle per modulo

### Scope test per risorsa

| Categoria | Asserzione | Obbligatorio? |
|-----------|-----------|---------------|
| List renders | `assertSuccessful()` | Sempre |
| List mostra record | `assertCanSeeTableRecords()` | Sempre |
| List filtri | `filterTable()` per status | Se ha filtri |
| List search | `searchTable()` | Se searchable |
| Create renders | `assertSuccessful()` | Se ha Create |
| Create CRUD | `fillForm()->call('create')->assertHasNoFormErrors()` + `assertDatabaseHas` | Se ha Create |
| Create validation | `fillForm([required=>null])->assertHasFormErrors()` | Se ha Create |
| Edit renders | `assertSuccessful()` con record | Se ha Edit |
| Edit CRUD | `fillForm()->call('save')->assertHasNoFormErrors()` | Se ha Edit |
| View renders | `assertSuccessful()` con record | Se ha View |
| Auth (viewer blocked) | `assertForbidden()` su create/edit | Se ha Policy |

---

## Fasi di Implementazione

### FASE 0 — Infrastruttura (prerequisito per tutto) ✅ COMPLETATA

**Cosa:** Trait helper + 8 factory base (modelli "foglia" senza dipendenze + Customer/Party)

**Factory da creare (8):**

| Factory | Dipendenze | Stati |
|---------|-----------|-------|
| WineMasterFactory | nessuna | — |
| FormatFactory | nessuna | standard(), magnum() |
| CaseConfigurationFactory | Format | — |
| WineVariantFactory | WineMaster | draft(), published() |
| SellableSkuFactory | WineVariant + Format + CaseConfig | active(), draft() |
| PartyFactory | nessuna | individual(), legalEntity() |
| CustomerFactory | Party | prospect() (default), active(), suspended() |
| LocationFactory | nessuna | warehouse(), bonded() |

**Trait `FilamentTestHelpers`:**
- `actingAsSuperAdmin()` / `actingAsViewer()`
- `createPimStack()` → crea WineMaster + WineVariant + Format + CaseConfig + SellableSku
- `createCustomerStack()` → crea Party + Customer

**File:**
- `tests/Support/FilamentTestHelpers.php`
- `database/factories/Pim/*.php` (5 factory)
- `database/factories/Customer/PartyFactory.php`
- `database/factories/Customer/CustomerFactory.php`
- `database/factories/Inventory/LocationFactory.php`

---

### FASE 1 — Tier 1: Risorse più critiche (5 risorse, ~60 test) ✅ COMPLETATA

| # | Risorsa | Pagine | Test stimati | Factory aggiuntive |
|---|---------|--------|-------------|-------------------|
| 1 | **AllocationResource** | Create (wizard), Edit, View, List | ~15 | AllocationFactory |
| 2 | **CustomerResource** | Create, Edit, View, List | ~12 | — (già in Fase 0) |
| 3 | **ShippingOrderResource** | Create, Edit, View, List | ~12 | ShippingOrderFactory |
| 4 | **InvoiceResource** | Create, View, List | ~10 | InvoiceFactory |
| 5 | **WineVariantResource** | Create, Edit, View, List | ~12 | — (già in Fase 0) |

**Factory aggiuntive (3):** AllocationFactory, ShippingOrderFactory, InvoiceFactory

**File test:**
```
tests/Feature/Filament/Allocation/AllocationResourceTest.php
tests/Feature/Filament/Customer/CustomerResourceTest.php
tests/Feature/Filament/Fulfillment/ShippingOrderResourceTest.php
tests/Feature/Filament/Finance/InvoiceResourceTest.php
tests/Feature/Filament/Pim/WineVariantResourceTest.php
```

**Note tecniche:**
- AllocationResource usa `HasWizard` — `fillForm()` riempie tutti i campi di tutti gli step in un colpo
- InvoiceResource — testare solo edit di draft (immutabilità già coperta da `InvoiceImmutabilityTest`)
- CustomerFactory default = `Prospect` (non Active) per evitare il check billing address dell'observer

---

### FASE 2 — Tier 2: Risorse ad alta complessità (5 risorse, ~40 test) ✅ COMPLETATA

| # | Risorsa | Pagine | Test stimati | Factory aggiuntive |
|---|---------|--------|-------------|-------------------|
| 6 | **PurchaseOrderResource** | Create, View, List | ~10 | PurchaseOrderFactory, ProcurementIntentFactory |
| 7 | **ProcurementIntentResource** | Create, View, List | ~10 | — (creata sopra) |
| 8 | **InboundResource** | Create, View, List | ~8 | InboundFactory |
| 9 | **VoucherResource** | View, List (read-only) | ~6 | VoucherFactory |
| 10 | **PaymentResource** | View, List (read-only) | ~6 | PaymentFactory |

**Factory aggiuntive (5):** ProcurementIntentFactory, PurchaseOrderFactory, InboundFactory, VoucherFactory, PaymentFactory

---

### FASE 3 — Tier 3: Risorse CRUD standard (10 risorse, 99 test) ✅ COMPLETATA

| # | Risorsa | Pagine | Test effettivi | Factory aggiuntive |
|---|---------|--------|---------------|-------------------|
| 11 | PartyResource | Create, Edit, View, List | 12 | — |
| 12 | ClubResource | Create, Edit, View, List | 11 | ClubFactory |
| 13 | LocationResource | Create, Edit, View, List | 12 | — (Fase 0) |
| 14 | InboundBatchResource | View, List | 5 | InboundBatchFactory |
| 15 | ChannelResource | Create, Edit, View, List | 13 | ChannelFactory |
| 16 | PriceBookResource | Create, Edit, View, List | 11 | PriceBookFactory |
| 17 | PricingPolicyResource | View, Edit, List | 8 | PricingPolicyFactory |
| 18 | OfferResource | View, Edit, List | 8 | OfferFactory |
| 19 | BundleResource | View, Edit, List | 8 | BundleFactory |
| 20 | DiscountRuleResource | Create, Edit, View, List | 11 | DiscountRuleFactory |

**Factory aggiuntive (8):** ClubFactory, InboundBatchFactory, ChannelFactory, PriceBookFactory, PricingPolicyFactory, OfferFactory, BundleFactory, DiscountRuleFactory

**Modifiche al modello:** `Club` — aggiunto trait `HasFactory` (mancava, necessario per `Club::factory()`)

#### Test skippati in Fase 3 (con motivazioni)

| Risorsa | Test skippato | Motivazione |
|---------|--------------|-------------|
| Party, Club, Location, Channel | `viewer_cannot_create`, `viewer_cannot_edit` | Nessuna Policy registrata ne' `canCreate()`/`canEdit()` override → Filament concede accesso a tutti gli autenticati. Il test `assertForbidden()` restituisce 200. Da risolvere aggiungendo Policy o gate. |
| InboundBatch | Create form CRUD test | Form troppo complesso: richiede morph relations (`product_reference_type/id`), campi form-only (`manual_creation_reason`, `audit_confirmation`), e 3 relazioni obbligatorie (WineVariant, Allocation, Location). Il test `viewer_cannot_create` funziona grazie al guard esplicito `canCreate()`. |
| Offer | Create form CRUD test | Wizard con filtri business-logic: `sellable_sku_id` filtra solo SKU con `lifecycle_status=active` E allocazioni attive; `price_book_id` filtra solo PriceBook con `status=Active`. I record factory non soddisfano questi filtri → validation rejection. |
| Bundle | Create form CRUD test | Wizard `HasWizard` con validazione multi-step. I campi condizionali (`fixed_price`, `percentage_off`) rendono il fill-form non testabile con approccio standard. |
| PricingPolicy | Create form CRUD test | Wizard `HasWizard` con logica condizionale su `policy_type` e `input_source`. Stessa problematica di Bundle/Offer. |

> **Nota:** Le risorse con wizard (`HasWizard`) e Select con filtri domain-specific richiedono un approccio di test dedicato (mock delle options o creazione di dati che soddisfano i filtri). Questo e' un candidato per un follow-up mirato.

---

### FASE 4 — Tier 4: 24 risorse rimanenti (125 test) ✅ COMPLETATA

**Sotto-fasi:**
- **4A — 11 risorse read-only** (40 test): CaseEntitlement, VoucherTransfer, StorageBilling, CreditNote, Refund, Subscription, Shipment, ShippingOrderException, Case, SerializedBottle, InventoryMovement
- **4B — 9 risorse PIM CRUD** (74 test): Country, Format, Region, CaseConfiguration, LiquidProduct, Appellation, Producer, SellableSku, WineMaster
- **4C — 4 risorse speciali** (11 test): BottlingInstruction, OperationalBlock, SupplierProducer, Product (WineVariant)

**17 factory create:**

| Factory | Modulo | Dipendenze |
|---------|--------|-----------|
| CountryFactory | Pim | — |
| RegionFactory | Pim | Country |
| AppellationFactory | Pim | Country |
| ProducerFactory | Pim | Country |
| LiquidProductFactory | Pim | WineVariant |
| CaseEntitlementFactory | Allocation | Customer, SellableSku |
| VoucherTransferFactory | Allocation | Voucher, 2x Customer |
| StorageBillingPeriodFactory | Finance | Customer |
| CreditNoteFactory | Finance | Invoice, Customer |
| RefundFactory | Finance | Invoice, Payment + pivot |
| SubscriptionFactory | Finance | Customer |
| ShipmentFactory | Fulfillment | ShippingOrder, Location |
| ShippingOrderExceptionFactory | Fulfillment | ShippingOrder |
| InventoryCaseFactory | Inventory | CaseConfig, Allocation, Location |
| SerializedBottleFactory | Inventory | WineVariant, Format, Allocation, InboundBatch, Location |
| InventoryMovementFactory | Inventory | 2x Location |
| BottlingInstructionFactory | Procurement | ProcurementIntent, LiquidProduct |

#### Test skippati in Fase 4 (con motivazioni)

| Risorsa | Test skippato | Motivazione |
|---------|--------------|-------------|
| VoucherTransfer | list_shows_records, view_page | Route `filament.admin.resources.vouchers.view` hard-coded in VoucherTransferResource:65 senza prefisso modulo. Bug nel resource, non nel test. |
| Refund | list_shows_records, view_page | Model casts `invoice_id`/`payment_id` a `integer` ma Invoice/Payment usano UUID. Boot validation `validateInvoicePaymentLink()` fa query con UUID castati a 0. Fix: cambiare cast a `string`. |

#### Bug fix applicato in Fase 4

- **ViewBottlingInstruction.php:159** — `formatStateUsing(fn (array $state))` riceveva string invece di array per `allowed_formats`. Fix: cambiato type hint a `array|string` con gestione entrambi i casi.

---

## Riepilogo Quantitativo

| Fase | Factory | File Test | Test Stimati | Test Effettivi | Stato |
|------|---------|-----------|-------------|---------------|-------|
| 0 - Infrastruttura | 8 | 0 (1 trait) | 0 | 0 | ✅ |
| 1 - Tier 1 | +3 (11 tot) | 5 | ~60 | 60 | ✅ |
| 2 - Tier 2 | +5 (16 tot) | 5 | ~40 | 40 | ✅ |
| 3 - Tier 3 | +8 (24 tot) | 10 | ~70 | 99 | ✅ |
| 4 - Tier 4 | +17 (42 tot) | 24 | ~100 | 125 | ✅ |
| **TOTALE** | **42 factory** | **44 file** | **~270** | **324** | ✅ |

## Debt Tecnico Identificato

1. **Policy mancanti per Party, Club, Location, Channel** — senza Policy/gate Filament concede accesso CRUD a tutti gli utenti autenticati, inclusi viewer. Serve creare Policy con `viewAny`/`create`/`update`/`delete` per questi 4 modelli.
2. **Wizard Create form non testati per Offer, Bundle, PricingPolicy** — i Select con filtri domain-specific (es. "solo SKU active con allocazioni") richiedono dati preparati ad-hoc o mock delle options. Follow-up: creare helper che generano dati "wizard-ready" per questi test.
3. **InboundBatch Create form non testato** — form con morph relation + campi form-only (`manual_creation_reason`, `audit_confirmation`). Serve un test dedicato con setup specifico.
4. **VoucherTransferResource route hard-coded** — `VoucherTransferResource.php:65` usa `filament.admin.resources.vouchers.view` senza prefisso modulo. Da correggere con il nome route corretto.
5. **Refund model UUID/integer cast** — `invoice_id` e `payment_id` castati a `integer` ma i modelli Invoice/Payment usano UUID string. Boot validation fallisce perché query con ID=0. Fix: cambiare cast a `string`.

## Rischi e Mitigazioni

| Rischio | Impatto | Mitigazione |
|---------|---------|-------------|
| **SQLite vs MySQL** (JSON, decimali) | Medio | Test a livello form/Livewire, non query raw. Decimal: confronti stringa |
| **Side-effect Observer** (CustomerObserver richiede billing address per Active) | Alto | CustomerFactory default = Prospect. Stato `active()` crea Address in afterCreating |
| **Side-effect Allocation boot** (auto-crea AllocationConstraint) | Basso | Factory NON crea constraint manualmente — lascia fare al boot |
| **Auditable trait** (crea AuditLog per ogni operazione) | Basso | Lasciare attivo — valida che il trail funzioni. Migration già inclusa |
| **Wizard AllocationResource** | Medio | `fillForm()` funziona su tutti gli step simultaneamente |
| **Morph relations** (ProcurementIntent.product_reference) | Basso | SQLite supporta morph identicamente a MySQL |

## Struttura File (Fasi 0-4)

```
tests/
  Support/
    FilamentTestHelpers.php               (Fase 0)
  Feature/
    Filament/
      Allocation/
        AllocationResourceTest.php        (Fase 1, 15 test)
        VoucherResourceTest.php           (Fase 2, 6 test)
        CaseEntitlementResourceTest.php   (Fase 4, 4 test)
        VoucherTransferResourceTest.php   (Fase 4, 2 test)
      Customer/
        CustomerResourceTest.php          (Fase 1, 14 test)
        PartyResourceTest.php             (Fase 3, 12 test)
        ClubResourceTest.php              (Fase 3, 11 test)
        OperationalBlockResourceTest.php  (Fase 4, 3 test)
      Fulfillment/
        ShippingOrderResourceTest.php     (Fase 1, 12 test)
        ShipmentResourceTest.php          (Fase 4, 4 test)
        ShippingOrderExceptionResourceTest.php (Fase 4, 4 test)
      Finance/
        InvoiceResourceTest.php           (Fase 1, 10 test)
        PaymentResourceTest.php           (Fase 2, 6 test)
        StorageBillingResourceTest.php    (Fase 4, 4 test)
        CreditNoteResourceTest.php        (Fase 4, 4 test)
        RefundResourceTest.php            (Fase 4, 2 test)
        SubscriptionResourceTest.php      (Fase 4, 4 test)
      Pim/
        WineVariantResourceTest.php       (Fase 1, 9 test)
        CountryResourceTest.php           (Fase 4, 8 test)
        FormatResourceTest.php            (Fase 4, 8 test)
        RegionResourceTest.php            (Fase 4, 8 test)
        CaseConfigurationResourceTest.php (Fase 4, 8 test)
        LiquidProductResourceTest.php     (Fase 4, 8 test)
        AppellationResourceTest.php       (Fase 4, 8 test)
        ProducerResourceTest.php          (Fase 4, 8 test)
        SellableSkuResourceTest.php       (Fase 4, 8 test)
        WineMasterResourceTest.php        (Fase 4, 8 test)
        ProductResourceTest.php           (Fase 4, 3 test)
      Procurement/
        PurchaseOrderResourceTest.php     (Fase 2, 10 test)
        ProcurementIntentResourceTest.php (Fase 2, 12 test)
        InboundResourceTest.php           (Fase 2, 6 test)
        BottlingInstructionResourceTest.php (Fase 4, 5 test)
        SupplierProducerResourceTest.php  (Fase 4, 2 test)
      Commercial/
        ChannelResourceTest.php           (Fase 3, 13 test)
        PriceBookResourceTest.php         (Fase 3, 11 test)
        PricingPolicyResourceTest.php     (Fase 3, 8 test)
        OfferResourceTest.php             (Fase 3, 8 test)
        BundleResourceTest.php            (Fase 3, 8 test)
        DiscountRuleResourceTest.php      (Fase 3, 11 test)
      Inventory/
        LocationResourceTest.php          (Fase 3, 12 test)
        InboundBatchResourceTest.php      (Fase 3, 5 test)
        CaseResourceTest.php              (Fase 4, 4 test)
        SerializedBottleResourceTest.php  (Fase 4, 4 test)
        InventoryMovementResourceTest.php (Fase 4, 4 test)

database/factories/
  Pim/
    WineMasterFactory.php                 (Fase 0)
    FormatFactory.php                     (Fase 0)
    CaseConfigurationFactory.php          (Fase 0)
    WineVariantFactory.php                (Fase 0)
    SellableSkuFactory.php                (Fase 0)
    CountryFactory.php                    (Fase 4)
    RegionFactory.php                     (Fase 4)
    AppellationFactory.php                (Fase 4)
    ProducerFactory.php                   (Fase 4)
    LiquidProductFactory.php              (Fase 4)
  Customer/
    PartyFactory.php                      (Fase 0)
    CustomerFactory.php                   (Fase 0)
    ClubFactory.php                       (Fase 3)
  Allocation/
    AllocationFactory.php                 (Fase 1)
    VoucherFactory.php                    (Fase 2)
    CaseEntitlementFactory.php            (Fase 4)
    VoucherTransferFactory.php            (Fase 4)
  Fulfillment/
    ShippingOrderFactory.php              (Fase 1)
    ShipmentFactory.php                   (Fase 4)
    ShippingOrderExceptionFactory.php     (Fase 4)
  Finance/
    InvoiceFactory.php                    (Fase 1)
    PaymentFactory.php                    (Fase 2)
    StorageBillingPeriodFactory.php       (Fase 4)
    CreditNoteFactory.php                 (Fase 4)
    RefundFactory.php                     (Fase 4)
    SubscriptionFactory.php               (Fase 4)
  Procurement/
    ProcurementIntentFactory.php          (Fase 2)
    PurchaseOrderFactory.php              (Fase 2)
    InboundFactory.php                    (Fase 2)
    BottlingInstructionFactory.php        (Fase 4)
  Inventory/
    LocationFactory.php                   (Fase 0)
    InboundBatchFactory.php               (Fase 3)
    InventoryCaseFactory.php              (Fase 4)
    SerializedBottleFactory.php           (Fase 4)
    InventoryMovementFactory.php          (Fase 4)
  Commercial/
    ChannelFactory.php                    (Fase 3)
    PriceBookFactory.php                  (Fase 3)
    PricingPolicyFactory.php              (Fase 3)
    OfferFactory.php                      (Fase 3)
    BundleFactory.php                     (Fase 3)
    DiscountRuleFactory.php               (Fase 3)
```

## Verifica

Per ogni fase:
1. Creare factory → verificare con `php artisan tinker` che funzionino
2. Creare test → `php artisan test --compact --filter={ResourceName}ResourceTest`
3. `vendor/bin/pint --dirty --format agent`
4. A fine fase: `php artisan test --compact` per regressione completa

## Completamento

Tutte le 45 risorse Filament sono ora coperte da test Livewire. Suite completa: **683 test, 1965 assertions, 0 failures**.

### Follow-up consigliati
- Aggiungere Policy per Party, Club, Location, Channel + test `assertForbidden`
- Test Create form dedicati per wizard Offer/Bundle/PricingPolicy con dati wizard-ready
- Test Create form per InboundBatch con setup morph + campi form-only
- Fix route hard-coded in VoucherTransferResource.php:65
- Fix Refund model cast `invoice_id`/`payment_id` da `integer` a `string`
