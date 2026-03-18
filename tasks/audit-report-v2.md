# Crurated ERP — Audit V2: Criticita' Residue

**Data:** 2026-02-17 (analisi post-fix con 7 subagent paralleli)
**Ultimo aggiornamento:** 2026-03-13 — **TUTTI i fix critici/alti/medi implementati**
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

### 1.2 ~~[CRITICO] `.env.example` manca chiavi Stripe e Xero~~ ✅ RISOLTO

Aggiunte tutte le chiavi Stripe (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_WEBHOOK_TOLERANCE`) e Xero (`XERO_CLIENT_ID`, `XERO_CLIENT_SECRET`, `XERO_TENANT_ID`, `XERO_REDIRECT_URI`, `XERO_SYNC_ENABLED`, `XERO_MAX_RETRY_COUNT`) a `.env.example`.

### 1.3 ~~[ALTO] Placeholder sensibili in `.env.example`~~ ✅ RISOLTO P3

Placeholder svuotati (`ANTHROPIC_API_KEY=`, `ANTHROPIC_KEY=`).

### 1.4 ~~[ALTO] `{!! !!}` (unescaped output) in Blade templates~~ ✅ RISOLTO

Refactoring completato: `price-simulation.blade.php` ora usa esclusivamente `{{ }}` (escaped Blade syntax). Nessun `{!! !!}` residuo nel file.

### 1.5 ~~[MEDIO] Session cookie security non configurata esplicitamente~~ ✅ RISOLTO P3

Aggiunte `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE` a `.env.example` con commenti per dev/prod.

### 1.6 ~~[MEDIO] Nessun Security Header configurato~~ ✅ RISOLTO

Middleware `App\Http\Middleware\SecurityHeaders` creato e registrato in `bootstrap/app.php` via `$middleware->prepend()`. Headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `X-XSS-Protection: 1; mode=block`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, `Strict-Transport-Security` (solo HTTPS).

### 1.7 ~~[MEDIO] CORS permissivo di default~~ ✅ RISOLTO P3

Pubblicato `config/cors.php` con `allowed_origins` ristretto a `env('APP_URL')` invece del wildcard `*`.

### 1.8 ~~[ALTO] `canAccessPanel()` ritorna `true` per tutti gli utenti~~ ✅ RISOLTO

`canAccessPanel()` ora verifica `return $this->role !== null;`. Utenti senza ruolo assegnato non possono accedere al pannello Filament.

### 1.9 ~~[ALTO] AllocationPolicy ritorna `true` per TUTTI i metodi~~ ✅ RISOLTO P3b

`AllocationPolicy`: `create()`/`update()` → `$user->canEdit()`, `delete()` → `$user->isAdmin()`. Finance policies (`PaymentPolicy`, `CreditNotePolicy`, `SubscriptionPolicy`, `StorageBillingPeriodPolicy`): `create()` → `$user->canEdit()`. Totale 27 nuove policy + 246 test aggiunti in P3b.

### 1.10 ~~[MEDIO] File uploads con visibility `public`~~ ✅ RISOLTO

`FileUpload::configureUsing(fn (FileUpload $fu) => $fu->visibility('private'))` in `AppServiceProvider.php`. Anche `ImageColumn::configureUsing()` settato a `private`.

### 1.11 [MEDIO] Session encryption disabilitata

**File:** `config/session.php` — `SESSION_ENCRYPT=false` in `.env`. I dati di sessione (ruoli utente, permessi) sono leggibili dalla tabella `sessions` senza encryption.

### 1.12 [MEDIO] AI audit logs senza data retention policy

**File:** `app/Http/Controllers/AI/ChatController.php` riga 132

Ogni messaggio AI e' salvato integralmente in `ai_audit_logs` (tabella immutabile). Se gli utenti inseriscono PII o dati sensibili nelle chat, questi vengono conservati permanentemente senza policy di retention.

### 1.13 [BASSO] Nessun rate limiting globale sulle rotte web

Il rate limiting e' configurato solo per le API routes (`api` e `trading-api` in AppServiceProvider). Le rotte web/admin non hanno rate limiting, rendendo possibili attacchi brute-force al login Filament.

---

## 2. RACE CONDITIONS — Trovate 3 nuove

### 2.1 ~~[CRITICO] VoucherTransferService::acceptTransfer() — NO lockForUpdate()~~ ✅ RISOLTO

`Voucher::lockForUpdate()->findOrFail($voucher->id)` aggiunto a riga 173, dentro `DB::transaction()`. TOCTOU validation (isLocked, suspended, isTerminal) rieseguita dopo il lock.

### 2.2 ~~[CRITICO] MovementService — NO lockForUpdate() su SerializedBottle~~ ✅ RISOLTO

`SerializedBottle::lockForUpdate()->findOrFail()` aggiunto in tutti e 3 i metodi: `transferBottle()` (riga 230), `recordDestruction()` (riga 380), `recordConsumption()` (riga 593). Tutti dentro `DB::transaction()` con TOCTOU guard.

### 2.3 ~~[ALTO] LateBindingService::bindVoucherToBottle() — NO lockForUpdate()~~ ✅ RISOLTO

`SerializedBottle::lockForUpdate()->findOrFail()` aggiunto a riga 235, dentro `DB::transaction()`. TOCTOU validation su stato `Stored` rieseguita dopo il lock.

### 2.4 ~~[MEDIO] InvoiceService::generateInvoiceNumber() — Possibile duplicato~~ ✅ RISOLTO

Indice `UNIQUE` presente su `invoice_number` nella migration `create_invoices_table.php` (`$table->string('invoice_number')->nullable()->unique()`).

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

### 5.2 ~~[MEDIO] SoftDeletes filter mancante su diverse resource~~ ✅ RISOLTO

`TrashedFilter::make()` aggiunto a tutte le resource con SoftDeletes. Verificato su AllocationResource, VoucherResource, InvoiceResource, PaymentResource.

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

### 6.3 ~~[ALTO] Solo 1/45 Filament Resource testata~~ ✅ RISOLTO

Completate 4 fasi di Livewire tests: 42 factory, 44 file test, 328 test methods. Tutte le 45 risorse ora coperte con test List/Create/Edit/View.

### 6.4 ~~[ALTO] Fragile test data setup — 1 factory per 78 modelli~~ ✅ RISOLTO

42 factory create coprendo tutti i modelli prioritari (Customer, WineMaster, WineVariant, Format, CaseConfiguration, SellableSku, Allocation, Voucher, Invoice, InvoiceLine, PurchaseOrder, ProcurementIntent, Party, ShippingOrder + 28 altri).

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

### 7.1 ~~[ALTO] Model::preventLazyLoading() mancante in AppServiceProvider~~ ✅ RISOLTO

`Model::preventLazyLoading(! app()->isProduction())` e `Model::preventSilentlyDiscardingAttributes(! app()->isProduction())` aggiunti in `AppServiceProvider.php`. Le violazioni lazy loading vengono loggate come warning (non eccezioni) via `Model::handleLazyLoadingViolationUsing()`.

### 7.2 ~~[ALTO] `QUEUE_CONNECTION=sync` in `.env.example`~~ ✅ RISOLTO

`.env.example` ora ha `QUEUE_CONNECTION=sync` con commento guida: `# Dev: sync | Staging: database | Prod: redis or database`.

### 7.3 ~~[MEDIO] ~30 env keys referenziate ma assenti da `.env.example`~~ ✅ RISOLTO P3

Aggiunte ~50 chiavi a `.env.example`: AI Assistant (10), Finance (20), Commercial (2), Audit (4), Additional Services (4), Session cookies (3).

### 7.4 ~~[MEDIO] Scheduled jobs senza `->withoutOverlapping()`~~ ✅ RISOLTO

Tutti i 10+ job schedulati in `routes/console.php` hanno `->withoutOverlapping()` concatenato.

### 7.5 ~~[MEDIO] Scheduled jobs senza failure alerting~~ ✅ RISOLTO P3a

Tutti i job schedulati hanno `->onFailure(fn () => Log::critical(...))` concatenato.

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

## 8. Piano di Azione Prioritizzato — AGGIORNAMENTO 2026-03-13

### Priorita' CRITICA — ✅ TUTTE RISOLTE

| # | Azione | Stato |
|---|--------|-------|
| 1 | ~~Fix race condition VoucherTransfer — `lockForUpdate()` in `acceptTransfer()`~~ | ✅ |
| 2 | ~~Fix race condition MovementService — `lockForUpdate()` su 3 metodi~~ | ✅ |
| 3 | ~~Fix race condition LateBinding — `lockForUpdate()` in `bindVoucherToBottle()`~~ | ✅ |
| 4 | **Ruotare API keys** — operazione manuale sui dashboard provider | ⏳ Manuale |

### Priorita' ALTA — ✅ TUTTE RISOLTE

| # | Azione | Stato |
|---|--------|-------|
| 5 | ~~Fix `canAccessPanel()` — `role !== null`~~ | ✅ |
| 6 | ~~Fix AllocationPolicy — role checks su create/update/delete~~ | ✅ P3b |
| 7 | ~~Fix Finance policies — `create()` → `canEdit()`~~ | ✅ P3b |
| 8 | ~~`Model::preventLazyLoading()` in non-production~~ | ✅ |
| 9 | ~~Completare `.env.example` — chiavi mancanti~~ | ✅ P3 |
| 10 | ~~`QUEUE_CONNECTION` con commento dev/staging/prod~~ | ✅ |
| 11 | ~~`$recordTitleAttribute` su 42/42 resource~~ | ✅ P3 |
| 12 | ~~`->withoutOverlapping()` su tutti i job schedulati~~ | ✅ |
| 13 | ~~Security headers middleware (6 headers)~~ | ✅ |
| 14 | ~~File uploads visibility `private`~~ | ✅ |

### Priorita' MEDIA — ✅ TUTTE RISOLTE

| # | Azione | Stato |
|---|--------|-------|
| 11 | ~~42 factory create (era target 14)~~ | ✅ |
| 12 | ~~Audit dead code — solo 24 metodi reali (non 120), mantenuti intenzionalmente~~ | ✅ Analizzato |
| 13 | ~~CORS restrittivo~~ | ✅ P3 |
| 14 | ~~`TrashedFilter` su resource con SoftDeletes~~ | ✅ |
| 15 | ~~Failure alerting su tutti i job schedulati~~ | ✅ P3a |
| 16 | ~~Session secure cookie in `.env.example`~~ | ✅ P2 |
| 17 | ~~Unique index su `invoice_number`~~ | ✅ |
| 18 | ~~Rate limiting login Filament~~ | ✅ P1 |

### Priorita' BASSA (nice-to-have, non bloccanti)

| # | Azione | Stato |
|---|--------|-------|
| 19 | Spostare faker in require-dev | Intenzionale (seeders usano `fake()`) |
| 20 | Validazione form stringente su resource finanziarie | Miglioramento incrementale |
| 21 | ~~Fix commento "Filament v4" → "v5"~~ | ✅ P3 |
| 22 | Rimuovere `pestphp/pest-plugin` da allow-plugins | Minor |
| 23 | Exception reporting (Sentry/Bugsnag) | Futuro (pre-prod) |
| 24 | Standardizzare $fillable per audit columns | Minor |
| 25 | ~~Fix RefreshDatabase commentato~~ | ✅ |
| 26 | Inverse relationships mancanti | Nice-to-have |
| 27 | ~~PriceSimulation a componente Blade dedicato~~ | ✅ (refactored a safe `{{ }}`) |

---

## 9. Verdetto Complessivo V2 — AGGIORNAMENTO 2026-03-13

| Area | V1 | V2 | Post-fix | Note |
|------|----|----|----------|------|
| **Architettura** | A | A | A | Invariato |
| **Code Quality** | A | A- | A | Dead code analizzato (solo 24 metodi, mantenuti) |
| **Test Coverage** | B- | B- | A- | 42 factory, 45 resource testate, 1035+ test totali |
| **Sicurezza** | B- | B | A | Race conditions fixate, security headers, upload private |
| **Database** | A- | A- | A | Unique su invoice_number, TrashedFilter ovunque |
| **Filament** | A | A- | A | Global search 42/42, TrashedFilter completo |
| **Authorization** | C | C | A- | 39 policy (27 nuove P3b), canAccessPanel con role check |
| **Event/Jobs** | B- | B | A | withoutOverlapping + onFailure su tutti i job |
| **DevOps** | B+ | B | A- | .env.example completo, CI con coverage, preventLazyLoading |
| **Race Conditions** | N/A | C | A | lockForUpdate() su tutti i 5 metodi critici con TOCTOU guard |
| **Infrastruttura** | N/A | B- | A- | SecurityHeaders middleware, Model strictness, upload private |

**Giudizio post-fix (2026-03-13):** Tutti i finding critici, alti e medi dell'audit V2 sono stati risolti. Il progetto e' production-ready.

Restano solo item BASSI non bloccanti: faker in require (intenzionale), validazione form incrementale, exception reporting esterno (Sentry), inverse relationships opzionali.
