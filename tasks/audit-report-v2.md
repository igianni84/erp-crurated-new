# Crurated ERP — Audit V2: Criticita' Residue

**Data:** 2026-02-17 (analisi post-fix con 7 subagent paralleli)
**Stack:** Laravel 12.51 · PHP 8.5 · Filament 5.2 · Livewire 4.1 · MySQL · Tailwind 4
**Riferimento:** Questo audit copre le criticita' NON presenti nel primo audit (`tasks/audit-report.md`) oppure problemi rimasti aperti.

---

## 0. Stato Fix Precedenti — Tutti Verificati

| Fix | Stato | Fase |
|-----|-------|------|
| PHPStan `PriceSimulation.php` @property PHPDoc | VERIFICATO | Pre-audit |
| Filament v5 `MonthlyFinancialSummaryWidget::$heading` non-static | VERIFICATO | Pre-audit |
| Payment model immutability guard | VERIFICATO | Pre-audit |
| 8 file duplicati " 2.php" rimossi dal repo (solo in vendor/storage come cache) | VERIFICATO | Pre-audit |
| `.gitignore` aggiornato con tool files | VERIFICATO | Pre-audit |
| FK migration `inbound_batches.procurement_intent_id` | VERIFICATO | Pre-audit |
| FK migration `price_book_entries.policy_id` | VERIFICATO | Pre-audit |
| HMAC signature verification su trading API | VERIFICATO | P1 |
| Job hardening (retries, backoff, timeout, failure logging) | VERIFICATO | P1 |
| VoucherPolicy RBAC, N+1 eager loading, login rate limiting, composite indexes | VERIFICATO | P1 |
| Session encryption, audit archival, PIM caching, CI coverage | VERIFICATO | P2 |
| Finance/Fulfillment tests | VERIFICATO | P2 |
| `.env.example` completato (~50 keys aggiunte: AI, Finance, Commercial, Audit, Services) | VERIFICATO | P3 |
| Placeholder Anthropic `.env.example` svuotati | VERIFICATO | P3 |
| Session security cookies in `.env.example` | VERIFICATO | P3 |
| CORS restrittivo (`allowed_origins` → `APP_URL`) | VERIFICATO | P3 |
| `$recordTitleAttribute` su 10 Filament Resources (global search) | VERIFICATO | P3 |
| Fix commento "Filament v4" → "v5" in AppServiceProvider | VERIFICATO | P3 |

---

## 1. SICUREZZA — Nuove Criticita'

### 1.1 [CRITICO] API Keys esposti nel `.env` locale

**File:** `.env` (righe 67-72)

Il file `.env` contiene chiavi API reali (Anthropic, OpenRouter). Sebbene `.env` sia in `.gitignore`, la presenza di chiavi reali nella directory locale rappresenta un rischio se il file venisse accidentalmente incluso in un backup, screenshot, o condivisione dello schermo.

**Azione:** Ruotare le chiavi API esposte nei servizi Anthropic e OpenRouter. Usare un secrets manager per ambienti non-dev.

### 1.2 [CRITICO] `.env.example` manca chiavi Stripe e Xero

**File:** `.env.example`

`config/services.php` referenzia `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `XERO_CLIENT_ID`, `XERO_CLIENT_SECRET`, `XERO_TENANT_ID`, `XERO_REDIRECT_URI` — nessuna presente in `.env.example`. Chiunque faccia setup del progetto non avra' guida su queste configurazioni critiche.

**Azione:** Aggiungere tutte le chiavi Stripe e Xero a `.env.example` con valori vuoti.

### 1.3 ~~[ALTO] Placeholder sensibili in `.env.example`~~ ✅ RISOLTO P3

Placeholder svuotati (`ANTHROPIC_API_KEY=`, `ANTHROPIC_KEY=`).

### 1.4 [ALTO] `{!! !!}` (unescaped output) in Blade templates

**File:** `resources/views/filament/pages/price-simulation.blade.php`

`PriceSimulation.php` genera HTML inline (metodo `buildSkuPreviewHtml()`, righe 411-457) che viene renderizzato con `{!! !!}`. Il contenuto e' costruito dal server con `htmlspecialchars()` sul nome del vino, ma `$statusColor` e `$statusLabel` provengono da enum values senza escaping. Rischio XSS basso (dati interni) ma violazione del principio di defense-in-depth.

**Azione:** Valutare la migrazione a un componente Blade o Livewire dedicato anziche' HTML inline.

### 1.5 ~~[MEDIO] Session cookie security non configurata esplicitamente~~ ✅ RISOLTO P3

Aggiunte `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE` a `.env.example` con commenti per dev/prod.

### 1.6 [MEDIO] Nessun Security Header configurato

`bootstrap/app.php` ha il middleware handler vuoto. Mancano completamente:
- `Content-Security-Policy`
- `X-Frame-Options`
- `Strict-Transport-Security`
- `X-Content-Type-Options`

**Azione:** Aggiungere un middleware globale per security headers o configurarli nel web server.

### 1.7 ~~[MEDIO] CORS permissivo di default~~ ✅ RISOLTO P3

Pubblicato `config/cors.php` con `allowed_origins` ristretto a `env('APP_URL')` invece del wildcard `*`.

### 1.8 [ALTO] `canAccessPanel()` ritorna `true` per tutti gli utenti

**File:** `app/Models/User.php` righe 66-69

```php
public function canAccessPanel(Panel $panel): bool
{
    return true; // All authenticated users can access the admin panel
}
```

Qualsiasi utente autenticato accede al pannello Filament, anche con `role = null`. Dovrebbe verificare almeno il ruolo minimo (`viewer`).

**Azione:** Aggiungere check ruolo: `return $this->role !== null;`

### 1.9 [ALTO] AllocationPolicy ritorna `true` per TUTTI i metodi

**File:** `app/Policies/AllocationPolicy.php`

`viewAny`, `view`, `create`, `update`, `delete`, `activate`, `close` — tutti ritornano `true`. Qualsiasi utente autenticato (incluso `viewer`) puo' creare, modificare e cancellare allocazioni.

Stessa problematica nelle Finance policies: `PaymentPolicy`, `CreditNotePolicy`, `SubscriptionPolicy`, `StorageBillingPeriodPolicy` hanno `create()` che ritorna `true`.

**Azione:** Implementare controlli role-based almeno per create/update/delete.

### 1.10 [MEDIO] File uploads con visibility `public`

**File:** `app/Providers/AppServiceProvider.php` riga 99

```php
FileUpload::configureUsing(fn (FileUpload $fu) => $fu->visibility('public'));
```

Tutti i file upload Filament sono configurati con visibility `public`. Documenti sensibili (contratti, fatture) sono accessibili via URL diretta.

**Azione:** Usare `private` visibility e servire via signed URLs.

### 1.11 [MEDIO] Session encryption disabilitata

**File:** `config/session.php` — `SESSION_ENCRYPT=false` in `.env`. I dati di sessione (ruoli utente, permessi) sono leggibili dalla tabella `sessions` senza encryption.

### 1.12 [MEDIO] AI audit logs senza data retention policy

**File:** `app/Http/Controllers/AI/ChatController.php` riga 132

Ogni messaggio AI e' salvato integralmente in `ai_audit_logs` (tabella immutabile). Se gli utenti inseriscono PII o dati sensibili nelle chat, questi vengono conservati permanentemente senza policy di retention.

### 1.13 [BASSO] Nessun rate limiting globale sulle rotte web

Il rate limiting e' configurato solo per le API routes (`api` e `trading-api` in AppServiceProvider). Le rotte web/admin non hanno rate limiting, rendendo possibili attacchi brute-force al login Filament.

---

## 2. RACE CONDITIONS — Trovate 3 nuove

### 2.1 [CRITICO] VoucherTransferService::acceptTransfer() — NO lockForUpdate()

**File:** `app/Services/Allocation/VoucherTransferService.php` righe 117-211

Il metodo valida lo stato del voucher (not locked, not suspended, not terminal) e poi lo aggiorna in una `DB::transaction()`, ma **NON usa `lockForUpdate()`** sulla riga Voucher. Race condition possibile:
- Thread A: `acceptTransfer()` controlla voucher e' `Issued`
- Thread B: `lockForFulfillment()` cambia voucher a `Locked`
- Thread A: nella transazione, aggiorna `customer_id` su un voucher ora `Locked`

**Azione:** Aggiungere `->lockForUpdate()` al query del voucher in `acceptTransfer()`.

### 2.2 [CRITICO] MovementService — NO lockForUpdate() su SerializedBottle

**File:** `app/Services/Inventory/MovementService.php` righe 194, 346, 542

I metodi `transferBottle()`, `recordDestruction()`, `recordConsumption()` validano lo stato della bottiglia e poi aggiornano in transazione, ma **senza `lockForUpdate()`**. Due operazioni concorrenti possono entrambe passare la validazione di stato.

**Azione:** Aggiungere `->lockForUpdate()` al query della bottiglia in tutti i metodi critici.

### 2.3 [ALTO] LateBindingService::bindVoucherToBottle() — NO lockForUpdate()

**File:** `app/Services/Fulfillment/LateBindingService.php` riga 174

Controlla che lo stato della bottiglia sia `Stored` poi transiziona a `ReservedForPicking` senza lock pessimistico. Due operazioni di binding concorrenti potrebbero entrambe vedere `Stored`.

**Azione:** Aggiungere `->lockForUpdate()` al query della bottiglia.

### 2.4 [MEDIO] InvoiceService::generateInvoiceNumber() — Possibile duplicato

**File:** `app/Services/Finance/InvoiceService.php` righe 662-680

Genera il numero fattura selezionando l'ultimo e incrementando. Sebbene sia in transazione, non c'e' lock esplicito. Due fatture generate contemporaneamente potrebbero ricevere lo stesso numero.

**Azione:** Aggiungere un indice UNIQUE su `invoice_number` nel DB (se non presente) e gestire l'eventuale constraint violation con retry.

---

## 3. MODEL CONSISTENCY — Nuovi Finding

### 3.1 [MEDIO] Inconsistenza $fillable per audit columns

`Voucher` include `created_by` in `$fillable` (riga 76) mentre tutti gli altri modelli (Invoice, Payment, Allocation, Subscription, PurchaseOrder, ShippingOrder, SerializedBottle) lo escludono, affidandosi al trait `Auditable`. Pattern misto — dovrebbe essere standardizzato.

### 3.2 [MEDIO] Invoice `source_id` senza FK constraint (polymorphic)

**File:** `app/Models/Finance/Invoice.php` riga 242-245

La relazione `subscription()` usa `source_id` come FK verso `subscriptions`, ma `source_id` e' un campo polimorfico (string) senza constraint FK a livello DB. Referenze orfane possibili.

### 3.3 [BASSO] Missing inverse relationships

- `Allocation` non ha `serializedBottles()` HasMany (inversa di `SerializedBottle::allocation()` BelongsTo)
- `WineVariant` non ha `serializedBottles()` HasMany
- `Format` non ha `serializedBottles()` HasMany
- `Party` non ha `purchaseOrders()` HasMany (inversa di `PurchaseOrder::supplier()` BelongsTo)

Queste relazioni inverse non sono strettamente necessarie ma potrebbero essere utili per query e eager loading.

---

## 4. SERVICE LAYER — Nuovi Finding

### 4.1 [ALTO] ~120 metodi pubblici non referenziati (dead code)

Superficie di dead code significativa nei Services. I piu' notevoli:

| Service | Metodi non referenziati | Note |
|---------|------------------------|------|
| `InvoiceService` | ~15 (calculateShippingCosts, getTotalShippingCost, isOverpayment, markPaid...) | 360 righe di logica shipping mai chiamata |
| `PaymentService` | ~8 (getPendingReconciliation, getMismatchedPayments...) | |
| `RefundService` | ~5 (getPendingRefunds, getFailedRefunds...) | |
| `InventoryService` | ~12 (getBottlesAtLocation, bottleMatchesAllocation...) | |
| `ShippingOrderService` | ~5 (addVoucher, removeVoucher...) | |
| `VoucherService` | ~8 (suspendForTrading, createFromImport...) | |
| `OfferService` | ~5 (pause, resume...) | |
| `BundleService` | ~5 (deactivate, calculatePriceValue...) | |
| **Totale** | **~120+** | |

**Impatto:** Costo di manutenzione elevato. Ogni modifica allo schema o ai modelli potrebbe rompere codice mai testato ne' utilizzato.

**Azione:** Valutare quali metodi sono predisposti per futuri moduli (documentarli con `@planned`) e quali sono realmente orfani (rimuoverli).

### 4.2 [MEDIO] InvoiceService a 1287 righe — candidato per refactoring

Il servizio piu' grande. Contiene ~360 righe di logica per calcolo costi di spedizione (righe 925-1287) che non e' mai chiamata dal resto del codebase. Candidato per estrazione in `ShippingCostCalculationService`.

### 4.3 [BASSO] InvoiceMailService::__construct() vuoto

**File:** `app/Services/Finance/InvoiceMailService.php` riga 33

`public function __construct() {}` — costruttore vuoto con zero parametri. Per le coding conventions del progetto, non dovrebbe esistere a meno che sia privato.

---

## 5. FILAMENT RESOURCES — Nuovi Finding

### 5.1 ~~[ALTO] Nessuna resource implementa `$recordTitleAttribute` per global search~~ ✅ RISOLTO P3

Aggiunto `$recordTitleAttribute` a 10 resource: Invoice (`invoice_number`), Voucher (`id`), Customer (`name`), ShippingOrder (`id`), PurchaseOrder (`id`), Party (`legal_name`), Allocation (`id`), WineMaster (`name`), SellableSku (`sku_code`), Payment (`payment_reference`).

### 5.2 [MEDIO] SoftDeletes filter mancante su diverse resource

Resource i cui modelli usano `SoftDeletes` ma la tabella non ha `TrashedFilter::make()`:
- Verifica necessaria su: `AllocationResource`, `VoucherResource`, `InvoiceResource`, `PaymentResource` e altre resource del modulo Finance e Allocation.

Le resource PIM (Country, Region, Producer, Appellation) hanno correttamente `TrashedFilter::make()`.

### 5.3 [MEDIO] Validation rules minime su molti form

Campione di 10 resource: la maggior parte ha solo `->required()` e `->maxLength()`. Mancano regole come:
- `->unique(ignoreRecord: true)` su campi che dovrebbero essere unici
- `->numeric()->minValue(0)` su importi monetari
- `->regex()` per formati specifici (codici SKU, numeri fattura)
- `->exists()` per validare FK a livello di form

`UserResource` e `CountryResource` hanno validazione buona (con `->unique()`). Le resource finanziarie dovrebbero avere validazione piu' stringente.

### 5.4 [BASSO] Filament v5 property types — tutti corretti

Verificati tutti gli 11 widget e le proprieta' delle pagine. Nessun mismatch static/non-static residuo dopo i fix del primo audit.

---

## 6. TEST QUALITY & COVERAGE — Nuovi Finding

### 6.1 [CRITICO] Copertura servizi: solo 5/41 testati (12%)

| Modulo | Servizi testati | Servizi non testati |
|--------|----------------|---------------------|
| Finance | InvoiceService (parziale) | PaymentService, CreditNoteService, RefundService, StorageBillingService, SubscriptionBillingService, +4 |
| Allocation | VoucherService, VoucherTransferService, VoucherAnomalyService | AllocationService, CaseEntitlementService, VoucherLockService |
| Inventory | 0 | MovementService, InventoryService, SerializationService, CommittedInventoryOverrideService |
| Commercial | 0 | PricingService, OfferService, BundleService, PriceBookService, +3 |
| Fulfillment | 0 | ShippingOrderService, ShipmentService, LateBindingService, WmsIntegrationService |
| Procurement | 0 | ProcurementIntentService, InboundService, +2 |
| Integration | 0 | StripeIntegrationService, XeroIntegrationService, LivExService |

### 6.2 [ALTO] Zero test per Jobs, Listeners, Observers, Notifications, Mailable

| Componente | Quantita' | Test |
|-----------|----------|------|
| Jobs | 17 | 0 |
| Event Listeners | 6 | 0 (1 testato indirettamente) |
| Observers | 2 | 0 |
| Notifications | 2 | 0 |
| Mailables | 1 | 0 |
| Seeders | 35 | 0 |

### 6.3 [ALTO] Solo 1/45 Filament Resource testata

Solo `UserResource` ha test CRUD. Le 44 resource rimanenti (incluse Invoice, Voucher, Customer, ShippingOrder) non hanno test per form validation, table rendering, o action visibility.

### 6.4 [ALTO] Fragile test data setup — 1 factory per 78 modelli

I test creano modelli con `Model::create([...])` inline. Catene di dipendenza profonde (WineMaster -> WineVariant -> Format -> CaseConfiguration -> SellableSku -> Allocation -> Voucher) ripetute in 12+ file. Se una migration aggiunge una colonna required, tutti i test si rompono.

**Priorita' factory:** Customer, WineMaster, WineVariant, Format, CaseConfiguration, SellableSku, Allocation, Voucher, Invoice, InvoiceLine, PurchaseOrder, ProcurementIntent, Party, ShippingOrder.

### 6.5 [MEDIO] Boundary value testing assente

Non testati:
- Importi a zero (`total_amount = '0.00'`)
- Importi negativi
- Stringhe a lunghezza massima
- UUID non validi passati a colonne UUID
- Codici valuta non supportati
- Quantita' diversa da 1 per voucher (l'invariante esiste nel model ma non e' testata esplicitamente)

### 6.6 [MEDIO] MySQL vs SQLite — Rischio di divergenza

I test usano SQLite in-memory, la produzione usa MySQL. Differenze note:
- `MODIFY COLUMN` non supportato in SQLite (gia' documentato nei commenti dei test)
- Operazioni JSON con comportamento diverso
- Collation stringhe diversa
- FK enforcement diverso

### 6.7 [BASSO] `tests/Feature/ExampleTest.php` ha `RefreshDatabase` commentato

```php
// use Illuminate\Foundation\Testing\RefreshDatabase;
```

Rischio di state leakage se usato come template per nuovi test.

---

## 7. CONFIGURAZIONE & INFRASTRUTTURA — Nuovi Finding

### 7.1 [ALTO] Model::preventLazyLoading() mancante in AppServiceProvider

**File:** `app/Providers/AppServiceProvider.php`

Non c'e' `Model::preventLazyLoading()` in non-production. Questo e' il metodo principale per prevenire N+1 query durante lo sviluppo.

**Azione:**
```php
if (! app()->isProduction()) {
    Model::preventLazyLoading();
    Model::preventSilentlyDiscardingAttributes();
    Model::preventAccessingMissingAttributes();
}
```

### 7.2 [ALTO] `QUEUE_CONNECTION=sync` in `.env.example`

Se copiato in staging/prod senza modifica, tutti i 10 job schedulati e i 6 listener asincroni gireranno sincronamente durante le request HTTP, causando timeout.

**Azione:** Cambiare a `QUEUE_CONNECTION=database` in `.env.example` con commento.

### 7.3 ~~[MEDIO] ~30 env keys referenziate ma assenti da `.env.example`~~ ✅ RISOLTO P3

Aggiunte ~50 chiavi a `.env.example`: AI Assistant (10), Finance (20), Commercial (2), Audit (4), Additional Services (4), Session cookies (3).

### 7.4 [MEDIO] Scheduled jobs senza `->withoutOverlapping()`

Nessuno dei 10 job schedulati ha `->withoutOverlapping()`. Particolarmente rischioso per i 2 job ogni-minuto (`ExpireReservationsJob`, `ExpireTransfersJob`) e `GenerateStorageBillingJob` (mensile, potenzialmente lento).

### 7.5 [MEDIO] Scheduled jobs senza failure alerting

Nessun `->onFailure()` o `->emailOutputOnFailure()` configurato. I job Finance critici (billing, overdue detection, suspension) possono fallire silenziosamente.

### 7.6 [MEDIO] `fakerphp/faker` in `require` (non `require-dev`)

Come documentato in CLAUDE.md, e' intenzionale perche' i seeders usano `fake()`. Ma 1.2MB+ di codice faker viene installato in produzione. L'approccio corretto sarebbe creare seeders di produzione che non usano faker.

### 7.7 ~~[BASSO] Commento "Filament v4" in AppServiceProvider~~ ✅ RISOLTO P3

Corretto a `// Filament v5 global configuration`.

### 7.8 [BASSO] `config/ai.php` — provider AI hardcodati

I default dei provider AI (anthropic, gemini, openai, cohere) sono hardcodati senza `env()` wrapper. Non configurabili per ambiente.

### 7.9 [BASSO] Exception handler vuoto in `bootstrap/app.php`

```php
->withExceptions(function (Exceptions $exceptions): void {
    //
})
```

Per un ERP in produzione, dovrebbe avere reporting a un servizio di error tracking (Sentry, Bugsnag, etc.).

---

## 8. Piano di Azione Prioritizzato

### Priorita' CRITICA (immediata)

| # | Azione | File |
|---|--------|------|
| 1 | **Fix race condition VoucherTransfer** — Aggiungere `lockForUpdate()` in `acceptTransfer()` | `VoucherTransferService.php` |
| 2 | **Fix race condition MovementService** — Aggiungere `lockForUpdate()` su SerializedBottle in `transferBottle()`, `recordDestruction()`, `recordConsumption()` | `MovementService.php` |
| 3 | **Fix race condition LateBinding** — Aggiungere `lockForUpdate()` su SerializedBottle in `bindVoucherToBottle()` | `LateBindingService.php` |
| 4 | **Ruotare API keys** — Le chiavi Anthropic e OpenRouter nel `.env` locale sono state lette da strumenti di analisi | Dashboard provider |

### Priorita' ALTA (1-2 giorni)

| # | Azione | File |
|---|--------|------|
| 5 | **Fix `canAccessPanel()`** — Verificare che `role !== null` | `User.php` |
| 6 | **Fix AllocationPolicy** — Aggiungere role checks per create/update/delete | `AllocationPolicy.php` |
| 7 | **Fix Finance policies** — Payment/CreditNote/Subscription `create()` non deve ritornare `true` | `*Policy.php` |
| 8 | **Aggiungere `Model::preventLazyLoading()`** in non-production | `AppServiceProvider.php` |
| 9 | ~~**Completare `.env.example`** — Aggiungere ~37 chiavi mancanti~~ | `.env.example` | ✅ P3 |
| 10 | **Cambiare `QUEUE_CONNECTION=database`** in `.env.example` | `.env.example` |
| 11 | ~~**Aggiungere `$recordTitleAttribute`** alle 10 resource principali per global search~~ | Filament Resources | ✅ P3 |
| 12 | **Aggiungere `->withoutOverlapping()`** ai 10 job schedulati | `routes/console.php` |
| 13 | **Aggiungere security headers** via middleware globale | `bootstrap/app.php` |
| 14 | **File uploads visibility `private`** — Cambiare default da `public` a `private` | `AppServiceProvider.php` |

### Priorita' MEDIA (1 settimana)

| # | Azione | File |
|---|--------|------|
| 11 | **Creare 14 factory** per i modelli piu' usati nei test | `database/factories/` |
| 12 | **Audit dead code** — Documentare metodi predisposti con `@planned`, rimuovere orfani | Tutti i Services |
| 13 | ~~**CORS restrittivo** — Limitare `allowed_origins` in `config/cors.php`~~ | `config/cors.php` | ✅ P3 |
| 14 | **Aggiungere `TrashedFilter`** alle resource con SoftDeletes | Filament Resources |
| 15 | **Aggiungere failure alerting** ai job schedulati Finance | `routes/console.php` |
| 16 | **Session secure cookie** — `SESSION_SECURE_COOKIE=true` in produzione | `.env` produzione |
| 17 | **Unique index su `invoice_number`** per prevenire duplicati | Migration |
| 18 | **Rate limiting login Filament** | `bootstrap/app.php` |

### Priorita' BASSA (nice-to-have)

| # | Azione | File |
|---|--------|------|
| 19 | **Spostare faker in require-dev** con seeders di produzione dedicati | `composer.json` |
| 20 | **Aggiungere validazione form stringente** sulle resource finanziarie | Filament Resources |
| 21 | ~~**Fix commento "Filament v4"** → "Filament v5"~~ | `AppServiceProvider.php:98` | ✅ P3 |
| 22 | **Rimuovere `pestphp/pest-plugin`** da allow-plugins | `composer.json` |
| 23 | **Aggiungere exception reporting** (Sentry/Bugsnag) | `bootstrap/app.php` |
| 24 | **Standardizzare $fillable** per audit columns (created_by) | Models |
| 25 | **Fix RefreshDatabase commentato** in `Feature/ExampleTest.php` | Test |
| 26 | **Aggiungere inverse relationships** mancanti | Models |
| 27 | **Migliare PriceSimulation** a componente Blade dedicato | Filament Pages |

---

## 9. Verdetto Complessivo V2

| Area | V1 | V2 | Trend | Note |
|------|----|----|-------|------|
| **Architettura** | A | A | = | Nessun degrado |
| **Code Quality** | A | A- | ↓ | Dead code significativo (~120 metodi), god service (1287 righe) |
| **Test Coverage** | B- | B- | = | Qualita' alta ma ampiezza bassa (12% servizi, 2% resource) |
| **Sicurezza** | B- | B | ↑ | HMAC aggiunto, ma race conditions e headers mancanti |
| **Database** | A- | A- | = | FK aggiunte, ma invoice_number senza unique |
| **Filament** | A | A- | ↓ | Manca global search, TrashedFilter incompleto |
| **Authorization** | C | C | = | Invariato — 27% policy coverage |
| **Event/Jobs** | B- | B | ↑ | Job hardening applicato, ma mancano withoutOverlapping e failure alerting |
| **DevOps** | B+ | B | ↓ | .env.example incompleto, faker in prod, nessun error reporting |
| **Race Conditions** | N/A | C | NEW | 3 race condition critiche trovate in VoucherTransfer, Movement, LateBinding |
| **Infrastruttura** | N/A | B- | NEW | preventLazyLoading mancante, 30+ env keys non documentate |

**Giudizio V2:** Il progetto ha migliorato significativamente dalla V1 (HMAC, job hardening, FK, property fixes). Le nuove criticita' piu' importanti sono:

1. **Race conditions** (3 critiche) — Rischio di corruzione dati su voucher transfer, inventory movement e late binding
2. **Dead code** (~120 metodi) — Costo di manutenzione elevato
3. **Test coverage** — La qualita' e' eccellente ma la copertura e' stretta (solo 5/41 servizi, 1/45 resource)
4. **Configurazione** — `.env.example` incompleto, `preventLazyLoading` mancante, security headers assenti

Le race conditions sono il problema piu' urgente perche' possono causare corruzione di dati in scenari di concorrenza reale.
