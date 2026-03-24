# Piano Upgrade Filament v3 → v4 — Crurated ERP

**Data:** 2026-02-16 (Reviewed & verified con analisi codebase completa + docs ufficiali Filament v4)
**Stato attuale:** Filament 3.3.47 → Target: Filament 4.x
**Rischio stimato:** MEDIO-ALTO (progetto ben strutturato, nessun plugin di terze parti, ma 220 file Filament PHP + 46 Blade views + 372 occorrenze `fi-*` CSS classes)

---

## 1. Compatibility Check — SUPERATO

| Requisito Filament v4 | Valore Attuale | Stato |
|------------------------|---------------|-------|
| PHP 8.2+ | ^8.4 (composer.json) / 8.5.2 (installato) | ✅ |
| Laravel 11.28+ | ^12.0 (composer.json, v12.49.0 installato) | ✅ |
| Tailwind CSS 4.1+ | ^4.0.0 (package.json) / 4.1.18 installato | ✅ |
| Vite | ^7.0.7 (package.json) | ✅ |
| Larastan v3+ / PHPStan v2+ | Larastan ^3.0 / PHPStan 2.1.38 | ✅ |
| No tailwind.config.js (Tailwind v4 native) | Assente nel progetto (corretto) | ✅ |
| No custom Filament theme CSS | Solo app.css con `@import tailwindcss` + `@theme` | ✅ |
| No published vendor views | `resources/views/vendor/` non esiste | ✅ |
| No third-party Filament plugins | Solo `filament/filament:^3.0` | ✅ |
| No Spatie Translatable plugin | Non presente | ✅ |
| No RichEditor usage | Non presente | ✅ |

**Verdetto:** Il progetto soddisfa tutti i prerequisiti.

---

## 2. Inventario Componenti Coinvolti

| Tipo | Conteggio | Verificato |
|------|-----------|------------|
| Filament Resources | 45 | ✅ |
| Custom Pages | 32 (20 root + 12 Finance) | ✅ |
| Widgets | 3 | ✅ |
| RelationManagers | 3 | ✅ |
| Custom Blade Views | 46 | ✅ |
| Navigation Groups | 10 | ✅ |
| Files con InteractsWithForms/InteractsWithTable | 14 (11 Pages + 3 Resource Pages) | ✅ |
| Files Blade con classi `fi-*` | 29 (372 occorrenze totali) | ✅ |
| Exporter (Export/Import) | 1 (OperationalBlockExporter + ExportAction) | ✅ |
| Total Filament PHP files | 220 | ✅ |

---

## 3. Breaking Changes Rilevati nel Progetto

### 3.1 ALTA PRIORITA

#### 3.1.1 Tailwind `@source` per Filament v4 (CRITICO — NON nel piano originale)
In v4, Filament sposta le utility classes da Blade HTML in CSS con `@apply`. Le classi `fi-*` usate nei nostri template Blade (372 occorrenze in 29 file) potrebbero non compilarsi senza la corretta configurazione `@source`.

**Stato attuale `app.css`:**
```css
@source '../**/*.blade.php';  /* copre Blade views */
@source '../**/*.js';
```

**Mancano:**
```css
@source '../../app/Filament/**/*.php';
@source '../../vendor/filament/**/*.blade.php';
```

**Azione:** Dopo l'upgrade, eseguire `php artisan make:filament-theme` OPPURE aggiungere manualmente le direttive `@source` per i file PHP Filament. Verificare che tutte le 372 occorrenze `fi-*` nei 29 Blade template compilino correttamente.

#### 3.1.2 Section/Grid — columnSpanFull (79 file con Section/Grid, 29 senza columnSpanFull)
In v4, `Section::make()`, `Grid::make()` **NON fanno piu full-width di default** in form multi-colonna. Serve `->columnSpanFull()`.

- Files con `Section::make()` o `Grid::make()`: **79 file**
- Files che hanno GIA `columnSpanFull()`: **50 file**
- **29 file richiedono l'aggiunta** di `columnSpanFull()`
- `Fieldset::make()`: **0 file** (non usato)

**I 29 file che necessitano columnSpanFull:**
- Pages: CommittedInventoryOverride, CreateConsignmentPlacement, CreateInternalTransfer, EventConsumption
- Resources: BundleResource, ChannelResource, UserResource, CustomerResource, PartyResource, OfferResource, PriceBookResource, PricingPolicyResource
- Resource Pages: CreateBundle (7 Section senza columnSpanFull)
- PIM Resources: SellableSkuResource, ProducerResource, AppellationResource, RegionResource, CountryResource, CaseConfigurationResource, FormatResource, LiquidProductResource
- PIM Resource Pages: CreateManualBottle, ViewWineVariant
- Inventory: LocationResource
- Finance: ListStorageBilling
- Allocation: ViewCaseEntitlement
- RelationManagers: EntriesRelationManager, SellableSkusRelationManager
- Customer: EditSupplierConfig

**Nota:** InboundBatchResource ha GIA `columnSpanFull()` (rimosso dalla lista). EntriesRelationManager AGGIUNTO (mancava nel piano originale).

**Azione:** Lo script Rector aggiunge `->columnSpanFull()` automaticamente. Verifica manuale post-script sui **29 file**.

#### 3.1.3 `bulkActions()` rinominato in `toolbarActions()` (52 file)
In v4, `$table->bulkActions([...])` diventa `$table->toolbarActions([...])`.

**Impatto:** **52 file** verificati.
**Azione:** Script Rector gestisce la rinomina. Verificare post-script.

#### 3.1.4 `actions()` rinominato in `recordActions()` (62 occorrenze in 61 file)
In v4, `$table->actions([...])` per azioni a livello di riga diventa `$table->recordActions([...])`.

**ATTENZIONE CRITICA — Notification Actions (7 occorrenze in 6 file da NON toccare):**

1. `ViewInvoice.php` (2 Notification actions, righe 1659 e 1913)
2. `ViewSerializedBottle.php` (1 Notification, riga 1485)
3. `CreateConsignmentPlacement.php` (1 Notification, riga 646)
4. `CreateInternalTransfer.php` (1 Notification, riga 620)
5. `EventConsumption.php` (1 Notification, riga 765)
6. `CommittedInventoryOverride.php` (1 Notification, riga 582)

**I restanti 55 occorrenze sono TABLE actions** da rinominare in `recordActions()`.

**Azione:** Script Rector gestisce la rinomina. Verificare post-script che:
1. **55 table actions** aggiornati
2. **7 Notification actions in 6 file** NON toccati

#### 3.1.5 URL Parameter Renaming (42 occorrenze `tableFilters` + 3 URL `activeTab`)
Filament v4 rinomina i parametri URL:
- `activeTab` → `tab`
- `tableFilters` → `filters`
- `tableSearch` → `search` (0 nel progetto)
- `tableSort` → `sort` (0 nel progetto)
- `tableGrouping` → `grouping` (0 nel progetto)
- `tableGroupingDirection` → `groupingDirection` (0 nel progetto)
- `activeRelationManager` → `relation` (0 nel progetto)
- `isTableReordering` → `reordering` (0 nel progetto)

**File con `tableFilters` hardcoded (42 occorrenze in 12 file) — verificati:**
| File | Occorrenze |
|------|-----------|
| ProcurementDashboard.php | 18 |
| AllocationVoucherDashboard.php | 8 |
| InventoryOverview.php | 5 |
| FulfillmentDashboard.php | 2 |
| Finance/FinanceOverview.php | 2 |
| CommercialOverview.php | 1 |
| InventoryAudit.php | 1 |
| ViewAllocation.php | 1 |
| ViewCaseEntitlement.php | 1 |
| ViewCustomer.php | 1 |
| finance-overview.blade.php | 1 |
| integrations-health.blade.php | 1 |

**File con `activeTab` — DISTINZIONE CRITICA:**

*URL Parameters (DA RINOMINARE a `tab`) — 3 file:*
- `PimDashboard.php:183` — `$url .= '?activeTab='.$tab`
- `Finance/FinanceOverview.php:416` — `'activeTab' => 'overdue'`
- `EditSupplierConfig.php:198` — `'activeTab' => 'supplier-config'`

*Proprieta Livewire custom (RINOMINARE PREVENTIVAMENTE a `$currentTab`) — 2 file + 2 Blade:*
- `Finance/ReconciliationStatusReport.php:54` — `public string $activeTab = 'summary'` (Blade: 6 ref)
- `Finance/CustomerFinance.php:54` — `public string $activeTab = 'open-invoices'` (Blade: 11 ref)

*Parametri URL custom `?customerId=` (NON Filament — verificare compatibilita):*
File che generano: FinanceOverview, OutstandingExposureReport, EventToInvoiceTraceabilityReport, InvoiceAgingReport
File che riceve: CustomerFinance.php

**Azione:** Rinominare TUTTI i parametri URL. Lo script automatico **NON** gestisce questi.

#### 3.1.6 FileUpload — Visibility Default (2 file)
Default cambiato da `public` a `private` per dischi non-local.

**File verificati:**
- `WineVariantResource.php`: 2 FileUpload con `->disk('public')` ma senza `->visibility('public')`
- `ProductResource.php`: 1 ImageColumn con `->disk('public')` senza visibility

**Azione:** Aggiungere globalmente in `AppServiceProvider::boot()`:
```php
FileUpload::configureUsing(fn (FileUpload $fu) => $fu->visibility('public'));
ImageColumn::configureUsing(fn (ImageColumn $ic) => $ic->visibility('public'));
```

#### 3.1.7 Table Filters — Deferred by Default
In v4, i filtri tabella richiedono click su "Apply".

**Azione:** Per mantenere comportamento v3 (filtri istantanei), aggiungere in `AppServiceProvider::boot()`:
```php
Table::configureUsing(fn (Table $table) => $table->deferFilters(false));
```

#### 3.1.8 Namespace Migration Massiva (gestita da Rector)
| Da | A | Occorrenze verificate |
|----|---|----------------------|
| `Filament\Forms\Form` | `Filament\Schemas\Schema` | 65 file |
| `Filament\Forms\Components\*` | `Filament\Schemas\Components\*` | ~88 occorrenze in 43 file |
| `Filament\Forms\Get` / `Set` | `Filament\Schemas\Components\Utilities\Get` / `Set` | 39 occorrenze in 26 file |
| `Filament\Tables\Actions\*` | `Filament\Actions\*` | 2 file (InventoryAudit, OperationalBlockResource) |
| `Filament\Infolists\*` | `Filament\Schemas\*` (vedi nota sotto) | ~223 occorrenze in 32 file |

**⚠️ NOTA CRITICA su Infolists:** Le **Entry classes** (TextEntry, ImageEntry, IconEntry, etc.) **restano in `Filament\Infolists\Components\*`** e NON migrano a Schemas. Solo l'oggetto `Infolist` e i layout components (Section, Grid, Tabs, Group) migrano a `Filament\Schemas\*`. Delle ~223 occorrenze, **~190 sono Entry imports che NON devono migrare**. Il Rector script gestisce questa distinzione automaticamente, ma verificare post-script che gli Entry imports non siano stati migrati erroneamente.

**Azione:** Script gestisce tutto. Grep post-script per namespace vecchi residui E per Entry imports migrati erroneamente:
```bash
grep -r "Filament\\\\Schemas\\\\Components\\\\TextEntry" app/Filament/ --include="*.php"
```
Se trova risultati, ripristinare a `Filament\Infolists\Components\TextEntry`.

#### 3.1.9 Cambio tipo proprietà e rimozione `static` selettiva
In v4, alcune proprietà cambiano **tipo** e/o perdono il modificatore `static`. **NON tutte perdono `static`.**

**Proprietà che PERDONO `static`:**
- `protected static string $view` → `protected string $view`: **~41 file**

**Proprietà che RESTANO `static` ma cambiano TIPO:**
- `protected static ?string $navigationIcon` → `protected static string|BackedEnum|null $navigationIcon`: **78 file**
- `protected static ?string $navigationGroup` → `protected static string|UnitEnum|null $navigationGroup`: **77 file**

**Verifiche aggiuntive:**
- **0 file** accedono a queste via `static::$propertyName` (upgrade sicuro)

**Azione:** Script Rector gestisce entrambe le modifiche automaticamente (rimozione `static` da `$view`, cambio tipo union per `$navigationIcon`/`$navigationGroup`).

#### 3.1.10 `HasActions` interface (14 file)
Classi con `InteractsWithForms`/`InteractsWithTable` devono implementare `HasActions`.

**14 file verificati (11 Pages + 3 Resource Pages):**
- Pages: AiAuditViewer, PriceSimulation, ProcurementAudit, InventoryAudit, CommercialAudit, PricingIntelligence, CreateConsignmentPlacement, CreateInternalTransfer, EventConsumption, CommittedInventoryOverride, SerializationQueue
- Resource Pages: AggregatedProcurementIntents, EditSupplierConfig, BulkCreateOffers

**Azione:** Script Rector aggiunge l'interface. Verificare post-script.

#### 3.1.11 `MaxWidth` rinominato in `Width` — 9 pagine
L'enum `MaxWidth` diventa `Width` (`Filament\Support\Enums\Width`).

**9 pagine verificate:** AiAssistant, AiAuditViewer, AllocationVoucherDashboard, CommercialOverview, FinanceOverview, FulfillmentDashboard, InventoryOverview, PimDashboard, ProcurementDashboard

Tutte hanno `protected ?string $maxContentWidth = 'full';`

**Stato attuale:**
```php
protected ?string $maxContentWidth = 'full';
```

**Il Rector aggiorna il tipo della proprietà a `Width|string|null`.** Poiché il valore `'full'` è una stringa accettata dal tipo union, **probabilmente non serve alcuna modifica manuale**. Verificare post-script che le 9 pagine funzionino correttamente.

**Fallback (solo se la stringa non funziona dopo l'upgrade):**
```php
public function getMaxContentWidth(): \Filament\Support\Enums\Width
{
    return \Filament\Support\Enums\Width::Full;
}
```

#### 3.1.12 `columnSpan()` — Breakpoint Default cambiato a `>= lg` (32 file, 148 occorrenze)
In v3, `columnSpan(2)` si applicava a tutti i breakpoint. In v4, solo da `lg` in su.

**Impatto verificato:** 32 file con 148 occorrenze di `->columnSpan(`. **0 file** usano gia la sintassi breakpoint array.

**Azione:** Verificare post-upgrade il layout su schermi tablet/mobile. Se serve il comportamento v3, usare `->columnSpan(['default' => 2, 'lg' => 2])`.

#### 3.1.13 `callable $get` / `callable $set` — Type Hint deprecati (2 file)
In v4, i callable `$get`/`$set` nei callback cambiano tipo.

**File verificati:**
- `ViewPayment.php`: 4 occorrenze di `callable $set` (righe 781, 928, 1648, 1777)
- `ViewInbound.php`: 1 occorrenza di `callable $set` (riga 766)

**Nota:** ViewPayment.php usa GIA `Get $get` correttamente in alcuni punti, ma `callable $set` e ancora il vecchio pattern.

**Azione:** Sostituire `callable $set` con `Set $set` (da `Filament\Schemas\Components\Utilities\Set`).

### 3.2 MEDIA PRIORITA

#### 3.2.1 unique() con ignoreRecord (8 file)
In v4, `ignoreRecord` default cambia a `true`. I nostri 8 file GIA specificano `ignoreRecord: true`.

**File verificati:** BundleResource, UserResource, CustomerResource, LocationResource, CountryResource, WineMasterResource, LiquidProductResource, CreateBundle

**Azione:** Nessuna azione richiesta. Il parametro diventa ridondante ma non rompente.

#### 3.2.2 can*() Method Signatures (14 file, di cui 3 con Policy)
In v4, se esiste una Policy Laravel per il model, Filament la usa ignorando i `can*()` override.

**File con can*() override verificati (14):**
- Resources (10): VoucherResource, CaseEntitlementResource, VoucherTransferResource, CaseResource, InventoryMovementResource, InboundBatchResource, SupplierProducerResource, ShippingOrderExceptionResource, ShippingOrderResource, ShipmentResource
- Widgets (2): CustomerPreferenceCollectionWidget, XeroSyncPendingWidget
- Pages (2): AiAuditViewer (`canAccess()`), CommittedInventoryOverride (`canAccess()`)

**Policy esistenti nel progetto (12):** UserPolicy, AllocationPolicy, VoucherPolicy, VoucherTransferPolicy, AccountPolicy, CustomerPolicy + InvoicePolicy, PaymentPolicy, CreditNotePolicy, RefundPolicy, SubscriptionPolicy, StorageBillingPeriodPolicy

**Conflitti potenziali (3 Resources con sia can*() che Policy):**
- VoucherResource ↔ VoucherPolicy
- VoucherTransferResource ↔ VoucherTransferPolicy
- CaseEntitlementResource ↔ AllocationPolicy

**Azione:** Verificare che le Policy coprano la stessa logica dei `can*()` override per questi 3 Resources.

#### 3.2.3 Table Default Key Sort
In v4, le tabelle ordinano automaticamente per PK. Con UUID, risulta in ordini non intuitivi.

**Azione:** Verificare post-upgrade. Se necessario, `->defaultKeySort(false)` globalmente o per-table.

#### 3.2.4 Enum Field State — Restituisce istanza, non stringa
Quando si usano Select con opzioni enum, lo state restituisce ora l'istanza enum.

**File con confronti stringa su state verificati (6):**
- BundleResource/Pages/ViewBundle.php — `$state === 'Yes'` (stato computato, rischio BASSO)
- BundleResource/Pages/CreateBundle.php — `$state === null` (null check, rischio BASSO)
- PriceBookResource.php — `$state === 0` (conteggio numerico, rischio BASSO)
- PriceBookResource/Pages/ViewPriceBook.php — `$state === 0` (conteggio numerico, rischio BASSO)
- ProductResource.php — `$state === 'liquid'` (stringa computata, rischio BASSO)
- InboundResource/Pages/ViewInbound.php — `$state === 'sellable_skus'` (stringa diretta, rischio BASSO)

**Nota post-verifica:** Nessuno dei 6 file usa Select con opzioni enum che restituirebbe istanze in v4. Tutti usano stati computati o confronti numerici. **Rischio reale BASSO.**

**Azione:** Audit post-upgrade per sicurezza, ma impatto minimo previsto.

#### 3.2.5 Form/Infolist Schema Namespace Unification
Forms e Infolists condividono `Filament\Schemas\Schema`. I type-hint cambiano da `Form $form` a `Schema $schema`.

**Impatto:** 14 file con InteractsWithForms/InteractsWithTable (vedi 3.1.10).

**Azione:** Script automatico gestisce. Verifica post-script.

#### 3.2.6 Pagination "all" rimossa dai default
L'opzione `'all'` non e piu nelle opzioni di paginazione di default.

**Azione:** Se serve, aggiungere globalmente in `AppServiceProvider` o per-table.

#### 3.2.7 Radio::inline() behavior change — N/A
In v4, `Radio::inline()` rende i radio button inline tra loro, ma NON inline con la label.

**Verifica codebase:** I Radio nel progetto usano `->gridDirection('row')`, **NON** `->inline()`. Grep conferma **0 file** con `->inline()`. Questo breaking change **non impatta il progetto**.

**8 file con Radio::make()** (per riferimento, nessuna azione richiesta): CreateShippingOrder, CreateProcurementIntent, CreatePricingPolicy, CreatePriceBook, CreateOffer, BulkCreateOffers, DiscountRuleResource, CreateBundle

**Azione:** Nessuna azione richiesta.

#### 3.2.8 ExportAction namespace (1 file — aggiunto)
`OperationalBlockResource.php` importa `Filament\Tables\Actions\ExportAction` che in v4 diventa `Filament\Actions\ExportAction`.

**Azione:** Script Rector dovrebbe gestire. Verificare post-script.

#### 3.2.9 Import/Export Job Retry Strategy
In v4, i job import/export riprovano max 3 volte con 60s di backoff (non piu 24h continue). Esiste un `OperationalBlockExporter.php`.

**Azione:** Verificare che il comportamento retry sia accettabile.

### 3.3 BASSA PRIORITA

#### 3.3.1 InventoryAudit — $this->tableFilters
`app/Filament/Pages/InventoryAudit.php:496` accede a `$this->tableFilters` direttamente.

**Azione:** Verificare se la property interna Livewire diventa `$this->filters`.

#### 3.3.2 Blade Templates con $activeTab
2 template Blade usano `$activeTab` come variabile Livewire:
- `reconciliation-status-report.blade.php` (6 riferimenti)
- `customer-finance.blade.php` (11 riferimenti)

**Azione:** Rinominare a `$currentTab` insieme alle proprieta PHP (vedi 3.1.5).

#### 3.3.3 doctrine/dbal non piu bundled
Presente come dipendenza transitiva (v4.4.1 nel lockfile) ma NON nel `require` diretto di composer.json.

**Azione:** Se il progetto usa DBAL per migrazioni/schema, aggiungere a composer.json. Altrimenti nessuna azione.

---

## 4. Piano Step-by-Step

### FASE 0: Preparazione (PRIMA di toccare codice)

- [ ] **0.1** Creare branch dedicato: `git checkout -b feature/filament-v4-upgrade`
- [ ] **0.2** Verificare che tutti i test passino su main: `php artisan test`
- [ ] **0.3** Eseguire PHPStan: `vendor/bin/phpstan analyse --level=5`
- [ ] **0.4** Commit di tutti i file non committati (pulire working tree)
- [ ] **0.5** Documentare versione corrente: `composer show filament/filament` → 3.3.47

### FASE 1: Upgrade Automatico

- [ ] **1.1** Installare tool di upgrade:
  ```bash
  composer require filament/upgrade:"^4.0" -W --dev
  ```
- [ ] **1.2** Eseguire script automatico:
  ```bash
  vendor/bin/filament-v4
  ```
  Lo script (Rector-based) gestisce:
  - Namespace e import (Forms→Schemas, Actions unificato, Get/Set, Infolists→Schemas)
  - `columnSpanFull()` su Section/Grid
  - `actions()` → `recordActions()` nelle tabelle
  - `bulkActions()` → `toolbarActions()`
  - Rimozione `static` da `$view`; cambio tipo `$navigationIcon`/`$navigationGroup`
  - Aggiunta `HasActions` interface
  - Type hint `$maxContentWidth` a `Width|string|null`
  - Firme metodi e type hints
- [ ] **1.3** Eseguire i comandi generati dallo script
- [ ] **1.4** Aggiornare Filament:
  ```bash
  composer require filament/filament:"^4.0" -W
  ```
- [ ] **1.5** Pubblicare nuova configurazione:
  ```bash
  php artisan vendor:publish --tag=filament-config
  ```
- [ ] **1.6** Rimuovere il tool di upgrade:
  ```bash
  composer remove filament/upgrade --dev
  ```

### FASE 2: Fix Manuali Post-Script

- [ ] **2.1** Fix Tailwind `@source` — Aggiungere in `resources/css/app.css`:
  ```css
  @source '../../app/Filament/**/*.php';
  @source '../../vendor/filament/**/*.blade.php';
  ```
  Oppure eseguire `php artisan make:filament-theme` e configurare.

- [ ] **2.2** Fix URL parameters — Search & replace:
  - `'tableFilters'` → `'filters'` (42 occorrenze in 12 file)
  - `'activeTab'` → `'tab'` (SOLO nei query string URL — 3 file)
  - **NON** rinominare le proprieta Livewire `$activeTab` (gestite al 2.10)

- [ ] **2.3** Fix `$this->tableFilters` in InventoryAudit.php → verificare se diventa `$this->filters`

- [ ] **2.4** Fix File Visibility — In `AppServiceProvider::boot()`:
  ```php
  FileUpload::configureUsing(fn (FileUpload $fu) => $fu->visibility('public'));
  ImageColumn::configureUsing(fn (ImageColumn $ic) => $ic->visibility('public'));
  ```

- [ ] **2.5** Fix Deferred Filters — In `AppServiceProvider::boot()`:
  ```php
  Table::configureUsing(fn (Table $table) => $table->deferFilters(false));
  ```

- [ ] **2.6** Fix `$maxContentWidth` nelle 9 pagine — verificare se Rector ha gestito. Se no, convertire a getter:
  ```php
  public function getMaxContentWidth(): \Filament\Support\Enums\Width
  {
      return \Filament\Support\Enums\Width::Full;
  }
  ```

- [ ] **2.7** Verificare `columnSpanFull()` aggiunto sui 29 file.

- [ ] **2.8** Verificare `recordActions()`:
  - 55 table actions aggiornati
  - 7 Notification actions in 6 file NON toccati

- [ ] **2.9** Verificare `toolbarActions()` — 52 file con `bulkActions()` aggiornati.

- [ ] **2.10** Rinominare proprieta Livewire `$activeTab` → `$currentTab` in:
  - `Finance/CustomerFinance.php` + `customer-finance.blade.php` (11 ref)
  - `Finance/ReconciliationStatusReport.php` + `reconciliation-status-report.blade.php` (6 ref)

- [ ] **2.11** Verificare namespace unificati (grep per namespace vecchi residui):
  ```bash
  grep -r "Filament\\\\Forms\\\\" app/Filament/ --include="*.php"
  grep -r "Filament\\\\Infolists\\\\Infolist" app/Filament/ --include="*.php"
  ```
  **⚠️ ATTENZIONE:** `Filament\Infolists\Components\TextEntry` (e altri Entry) devono RESTARE — NON sono errori. Verificare che non siano stati migrati erroneamente a Schemas:
  ```bash
  grep -r "Filament\\\\Schemas\\\\Components\\\\TextEntry" app/Filament/ --include="*.php"
  ```

- [ ] **2.12** Verificare cambio tipo proprietà ($navigationIcon: `string|BackedEnum|null` in 78 file, $navigationGroup: `string|UnitEnum|null` in 77 file) e rimozione `static` da `$view` (~41 file).

- [ ] **2.13** Verificare `HasActions` interface aggiunta alle 14 pages con traits.

- [ ] **2.14** Fix `callable $set` — Sostituire con type-hint `Set $set` in:
  - `ViewPayment.php` (4 occorrenze)
  - `ViewInbound.php` (1 occorrenza)

- [ ] **2.15** Verificare `default_filesystem_disk` nel file config Filament pubblicato.

- [ ] **2.16** Verificare authorization: 3 Resources con sia can*() che Policy.

- [ ] **2.17** ~~Radio layout~~ — N/A: nessun file usa `->inline()`, usano `->gridDirection('row')`. Nessuna azione.

- [ ] **2.18** Verificare layout `columnSpan()` su schermi tablet/mobile (32 file, 148 occorrenze).

- [ ] **2.19** Audit classi `fi-*` nei 29 Blade template (372 occorrenze) — verificare che compilino dopo l'upgrade.

### FASE 3: Build & Cache

- [ ] **3.1** Pulire cache e rigenerare:
  ```bash
  php artisan optimize:clear
  php artisan filament:upgrade
  ```
- [ ] **3.2** Rebuild frontend assets:
  ```bash
  npm install
  npm run build
  ```
- [ ] **3.3** Verificare che `resources/css/app.css` abbia le direttive `@source` corrette
- [ ] **3.4** Verificare che le classi `fi-*` nei Blade compilino (no errori CSS/Tailwind)

### FASE 4: Test Completi

- [ ] **4.1** `php artisan test`
- [ ] **4.2** `vendor/bin/phpstan analyse --level=5`
- [ ] **4.3** `vendor/bin/pint --test`
- [ ] **4.4** `php artisan migrate:fresh --seed`
- [ ] **4.5** Verificare che le 46 Blade views compilino correttamente
- [ ] **4.6** Test manuale:
  - [ ] Login admin panel
  - [ ] Tutte le 10 navigation groups visibili
  - [ ] Almeno 1 Resource per modulo: create, view, edit, delete
  - [ ] Dashboard pages: PIM, Procurement, Inventory, Fulfillment, Allocation, Finance, Commercial
  - [ ] Filtri tabella funzionanti (deferred vs instant)
  - [ ] Bulk actions funzionanti (toolbar rename)
  - [ ] FileUpload su WineVariantResource (media_images, media_documents)
  - [ ] RelationManagers: BundleResource, PriceBookResource, WineVariantResource
  - [ ] Custom pages con InteractsWithTable: InventoryAudit, EventConsumption, etc.
  - [ ] AI Assistant page
  - [ ] Finance report pages (tutte e 12)
  - [ ] Link con filtri pre-applicati dalle dashboard
  - [ ] Link con `?customerId=` dalle finance pages
  - [ ] ~~Radio button layout~~ — N/A (nessun file usa `->inline()`)
  - [ ] Layout form su viewport mobile/tablet (columnSpan breakpoint)
  - [ ] Export OperationalBlock (ExportAction funzionante)
  - [ ] Classi `fi-*` nei Blade (buttons, sections, etc. visualizzati correttamente)

### FASE 5: Deploy (solo se FASE 4 completamente OK)

- [ ] **5.1** Commit e push
- [ ] **5.2** Test su staging (server Ploi)
- [ ] **5.3** Merge in main dopo validazione staging

---

## 5. Piano di Rollback

### Rollback Immediato (< 5 min)
```bash
git checkout main
composer install
npm install && npm run build
php artisan optimize:clear
php artisan test
```

### Rollback da Staging/Produzione
```bash
ssh ploi@46.224.207.175
cd /home/ploi/crurated.giovannibroegg.it
git log --oneline -5
git checkout <commit-hash-pre-upgrade>
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
echo "" | sudo -S service php8.5-fpm reload
php artisan optimize:clear
php artisan filament:upgrade
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Rollback DB
Filament v4 NON richiede migration DB. Se per qualche ragione ce ne sono:
```bash
php artisan migrate:rollback --step=<N>
```

---

## 6. Rischi e Mitigazioni

| Rischio | Probabilita | Impatto | Mitigazione |
|---------|------------|---------|-------------|
| **Classi `fi-*` non compilano (29 Blade, 372 occ.)** | **Alta** | **Alto** | **Aggiungere `@source` in app.css + audit visuale** |
| Layout rotto (columnSpanFull) | Alta | Medio | Script Rector + verifica 29 file |
| `actions()` non rinominato (55 table actions) | Media | Alto | Script gestisce — verifica post-script |
| Notification `actions()` rinominato per errore (6 file) | Media | Critico | Verifica manuale post-script |
| `bulkActions()` non rinominato (52 file) | Media | Alto | Script gestisce — verifica post-script |
| Link dashboard rotti (URL params) | Alta | Alto | Find & replace sistematico (42+3 occ.) |
| Namespace migration incompleta | Media | Alto | Script gestisce, grep post-script |
| Cambio tipo proprietà (78+77 file) + rimozione `static` da `$view` (~41 file) | Media | Medio | Script gestisce — nessun `static::$prop` trovato |
| Layout mobile rotto (columnSpan >= lg) | Media | Medio | Verifica 32 file post-upgrade |
| File non visibili (visibility) | Media | Alto | Config globale in AppServiceProvider |
| Filtri UX diversa (deferred) | Certa | Basso | Config globale per tornare a v3 behavior |
| Conflitto `$activeTab` property | Media | Medio | Rinominare preventivamente a `$currentTab` |
| Pagination "all" scomparsa | Certa | Basso | Config globale o per-table |
| `MaxWidth` → `Width` enum change | Certa | Alto | Script gestisce tipo, verificare 9 pagine |
| Sort UUID inaspettato | Bassa | Basso | `defaultKeySort(false)` se serve |
| `can*()` ignorato se Policy esiste | Media | Alto | Verificare 3 Resources con conflitto |
| ~~Radio layout cambiato~~ | N/A | N/A | `->inline()` non usato nel progetto (usano `->gridDirection('row')`) |
| `callable $set` rotti | Media | Medio | Fix manuale 2 file (5 occorrenze) |
| ExportAction namespace | Bassa | Basso | Script gestisce — verificare |

---

## 7. Checklist Pre-Merge Finale

- [ ] Tutti i test passano (`php artisan test`)
- [ ] PHPStan level 5 senza errori
- [ ] Pint senza violazioni
- [ ] `migrate:fresh --seed` funziona
- [ ] Login admin funziona
- [ ] Tutte le dashboard caricano
- [ ] Classi `fi-*` nei Blade template renderizzano correttamente
- [ ] Link con filtri pre-applicati funzionano (`filters` non `tableFilters`)
- [ ] FileUpload funziona (upload + visualizzazione pubblica)
- [ ] Record actions funzionano su almeno 5 Resources
- [ ] Toolbar/Bulk actions funzionano su almeno 5 Resources
- [ ] Notification actions intatte sui 6 file critici
- [ ] Layout form multi-colonna corretto (columnSpanFull)
- [ ] Layout tablet/mobile verificato (columnSpan breakpoint)
- [ ] `$maxContentWidth` funziona sulle 9 pagine
- [ ] Blade views custom (46 file) renderizzano correttamente
- [ ] Nessun errore JavaScript in console browser
- [ ] Link `?customerId=` funzionano tra le finance pages
- [ ] `$currentTab` (ex `$activeTab`) funziona nelle 2 finance pages
- [ ] Navigation icons e groups funzionano (post cambio tipo proprietà)
- [ ] ~~Radio button layout~~ — N/A (`->inline()` non usato, solo `->gridDirection('row')`)
- [ ] Nessun namespace `Filament\Forms\` residuo; `Filament\Infolists\Components\*Entry` DEVE restare (solo `Infolist` oggetto migra a Schemas)
- [ ] `callable $set` sostituiti con type-hint `Set`
- [ ] Export OperationalBlock funzionante

---

## 8. Note Finali

### Cosa lo Script Rector FA
1. Aggiunge `->columnSpanFull()` a Section/Grid
2. Rinomina `actions()` → `recordActions()` nelle tabelle
3. Rinomina `bulkActions()` → `toolbarActions()`
4. Aggiorna TUTTI i namespace (Forms→Schemas, Actions unificato, Get/Set, Infolists→Schemas)
5. Rimuove `static` da `$view`; aggiorna tipo di `$navigationIcon` (`string|BackedEnum|null`) e `$navigationGroup` (`string|UnitEnum|null`)
6. Aggiunge interface `HasActions` dove servono i traits
7. Cambia tipo di `$maxContentWidth` a `Width|string|null`
8. Aggiorna firme metodi e type hints
9. Genera lista comandi post-upgrade

### Cosa lo Script Rector NON Fa
1. Non configura `@source` per Tailwind (classi `fi-*` nei Blade)
2. Non aggiorna i query string hardcoded (`tableFilters` → `filters`, `activeTab` → `tab`)
3. Non aggiunge `->visibility('public')` ai FileUpload
4. Non configura `deferFilters(false)` globalmente
5. Non rinomina proprieta Livewire custom (`$activeTab` → `$currentTab`)
6. Non corregge il breakpoint `columnSpan()` da default `>= lg`
7. Non corregge confronti enum state nei callback
8. Non ripristina l'opzione pagination "all"
9. Non configura `default_filesystem_disk`
10. Non verifica classi Tailwind `fi-*` cambiate nelle 46 Blade views
11. ~~Non aggiunge `->inlineLabel()` ai Radio con `->inline()`~~ — N/A: progetto non usa `->inline()`
12. Non corregge `callable $set` type hints
