# Piano: Livewire Tests per Filament Pages Critiche

## Contesto

L'ERP ha 45 risorse Filament, 31 Create pages, 24 Edit pages, 32 View pages — ma **solo 1 risorsa testata** (UserResource, 19 test). Le pagine più critiche (Allocation, Invoice, ShippingOrder) hanno 1000+ righe di logica e **zero test**. Un errore in produzione su queste pagine non viene intercettato fino al deploy.

**Obiettivo:** Aggiungere test Livewire per le pagine Filament critiche, intercettando errori di rendering, validazione form, e CRUD prima che raggiungano produzione.

## Stato Attuale

| Metrica | Valore |
|---------|--------|
| Test files | 30 (12 Feature, 18 Unit) |
| Test methods | 341 |
| Factory | 1 sola (UserFactory) |
| Risorse Filament testate | 1/45 (UserResource) |
| Pattern dati nei test | `Model::create([...])` diretto |
| DB test | SQLite in-memory |

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

### FASE 0 — Infrastruttura (prerequisito per tutto)

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

### FASE 1 — Tier 1: Risorse più critiche (5 risorse, ~60 test)

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

### FASE 2 — Tier 2: Risorse ad alta complessità (5 risorse, ~40 test)

| # | Risorsa | Pagine | Test stimati | Factory aggiuntive |
|---|---------|--------|-------------|-------------------|
| 6 | **PurchaseOrderResource** | Create, View, List | ~10 | PurchaseOrderFactory, ProcurementIntentFactory |
| 7 | **ProcurementIntentResource** | Create, View, List | ~10 | — (creata sopra) |
| 8 | **InboundResource** | Create, View, List | ~8 | InboundFactory |
| 9 | **VoucherResource** | View, List (read-only) | ~6 | VoucherFactory |
| 10 | **PaymentResource** | View, List (read-only) | ~6 | PaymentFactory |

**Factory aggiuntive (5):** ProcurementIntentFactory, PurchaseOrderFactory, InboundFactory, VoucherFactory, PaymentFactory

---

### FASE 3 — Tier 3: Risorse CRUD standard (10 risorse, ~70 test)

| # | Risorsa | Pagine | Test stimati | Factory aggiuntive |
|---|---------|--------|-------------|-------------------|
| 11 | PartyResource | Create, Edit, View, List | ~8 | — |
| 12 | ClubResource | Create, Edit, View, List | ~8 | ClubFactory |
| 13 | LocationResource | Create, Edit, View, List | ~8 | — (Fase 0) |
| 14 | InboundBatchResource | Create, View, List | ~6 | InboundBatchFactory |
| 15 | ChannelResource | Create, Edit, View, List | ~8 | ChannelFactory |
| 16 | PriceBookResource | Create, Edit, View, List | ~8 | PriceBookFactory |
| 17 | PricingPolicyResource | Create, Edit, View, List | ~8 | PricingPolicyFactory |
| 18 | OfferResource | Create, Edit, View, List | ~6 | OfferFactory |
| 19 | BundleResource | Create, Edit, View, List | ~6 | BundleFactory |
| 20 | DiscountRuleResource | Create, Edit, View, List | ~6 | DiscountRuleFactory |

**Factory aggiuntive (8):** ClubFactory, InboundBatchFactory, ChannelFactory, PriceBookFactory, PricingPolicyFactory, OfferFactory, BundleFactory, DiscountRuleFactory

---

### FASE 4 — Tier 4: Risorse read-only e semplici (~15 risorse, ~55 test)

Risorse rimanenti (Case, CaseEntitlement, CreditNote, SerializedBottle, Shipment, etc.) — mostly View/List only, 3-4 test ciascuna.

---

## Riepilogo Quantitativo

| Fase | Factory | File Test | Test Stimati |
|------|---------|-----------|-------------|
| 0 - Infrastruttura | 8 | 0 | 0 |
| 1 - Tier 1 | +3 (11 tot) | 5 | ~60 |
| 2 - Tier 2 | +5 (16 tot) | 5 | ~40 |
| 3 - Tier 3 | +8 (24 tot) | 10 | ~70 |
| 4 - Tier 4 | +2 (26 tot) | ~15 | ~55 |
| **TOTALE** | **26 factory** | **~35 file** | **~225 test** |

## Rischi e Mitigazioni

| Rischio | Impatto | Mitigazione |
|---------|---------|-------------|
| **SQLite vs MySQL** (JSON, decimali) | Medio | Test a livello form/Livewire, non query raw. Decimal: confronti stringa |
| **Side-effect Observer** (CustomerObserver richiede billing address per Active) | Alto | CustomerFactory default = Prospect. Stato `active()` crea Address in afterCreating |
| **Side-effect Allocation boot** (auto-crea AllocationConstraint) | Basso | Factory NON crea constraint manualmente — lascia fare al boot |
| **Auditable trait** (crea AuditLog per ogni operazione) | Basso | Lasciare attivo — valida che il trail funzioni. Migration già inclusa |
| **Wizard AllocationResource** | Medio | `fillForm()` funziona su tutti gli step simultaneamente |
| **Morph relations** (ProcurementIntent.product_reference) | Basso | SQLite supporta morph identicamente a MySQL |

## Struttura File Finale

```
tests/
  Support/
    FilamentTestHelpers.php          ← NEW (trait helper)
  Feature/
    Filament/
      Allocation/
        AllocationResourceTest.php   ← NEW
        VoucherResourceTest.php      ← NEW
      Customer/
        CustomerResourceTest.php     ← NEW
        PartyResourceTest.php        ← NEW
        ClubResourceTest.php         ← NEW
      Fulfillment/
        ShippingOrderResourceTest.php ← NEW
      Finance/
        InvoiceResourceTest.php      ← NEW
        PaymentResourceTest.php      ← NEW
      Pim/
        WineVariantResourceTest.php  ← NEW
        WineMasterResourceTest.php   ← NEW
        SellableSkuResourceTest.php  ← NEW
      Procurement/
        PurchaseOrderResourceTest.php ← NEW
        ProcurementIntentResourceTest.php ← NEW
        InboundResourceTest.php      ← NEW
      Commercial/
        ChannelResourceTest.php      ← NEW
        PriceBookResourceTest.php    ← NEW
        ...
      Inventory/
        LocationResourceTest.php     ← NEW
        InboundBatchResourceTest.php ← NEW

database/factories/
  Pim/
    WineMasterFactory.php            ← NEW
    FormatFactory.php                ← NEW
    CaseConfigurationFactory.php     ← NEW
    WineVariantFactory.php           ← NEW
    SellableSkuFactory.php           ← NEW
  Customer/
    PartyFactory.php                 ← NEW
    CustomerFactory.php              ← NEW
  Allocation/
    AllocationFactory.php            ← NEW
    VoucherFactory.php               ← NEW
  Fulfillment/
    ShippingOrderFactory.php         ← NEW
  Finance/
    InvoiceFactory.php               ← NEW
    PaymentFactory.php               ← NEW
  Procurement/
    ProcurementIntentFactory.php     ← NEW
    PurchaseOrderFactory.php         ← NEW
    InboundFactory.php               ← NEW
  Inventory/
    LocationFactory.php              ← NEW
  Commercial/
    ChannelFactory.php               ← NEW
    ...
```

## Verifica

Per ogni fase:
1. Creare factory → verificare con `php artisan tinker` che funzionino
2. Creare test → `php artisan test --compact --filter={ResourceName}ResourceTest`
3. `vendor/bin/pint --dirty --format agent`
4. A fine fase: `php artisan test --compact` per regressione completa

## Approccio Consigliato

**Iniziare da Fase 0 + Fase 1** come blocco unico. Le 8 factory base + 5 risorse Tier 1 coprono i punti più critici del sistema (Allocation, Customer, Invoice, ShippingOrder, WineVariant) e stabiliscono il pattern per tutto il resto.

Le fasi successive (2-4) sono incrementali e indipendenti — possono essere fatte in sessioni separate senza bloccare nulla.
