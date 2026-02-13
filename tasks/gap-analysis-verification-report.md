# Gap Analysis Verification Report — Crurated ERP

> Data: 9 Febbraio 2026
> Metodo: Verifica diretta del codice sorgente per ogni claim di ogni gap analysis
> Agenti: 8 agenti paralleli, ciascuno dedicato a un modulo

---

## Executive Summary

| Modulo | Claims | ✅ Accurate | ❌ Inaccurate | ⚠️ Partial | Accuracy |
|--------|:------:|:-----------:|:-------------:|:----------:|:--------:|
| **0 — PIM** | 125 | 119 | 3 | 3 | **95.2%** |
| **K — Customers** | 34 | 30 | 3 | 1 | **88.2%** |
| **S — Commercial** | 77 | 74 | 1 | 2 | **96.1%** |
| **A — Allocations** | 30 | 18 | 10 | 2 | **60.0%** |
| **D — Procurement** | 38 | 36 | 2 | 0 | **94.7%** |
| **B — Inventory** | 77 | 71 | 6 | 0 | **92.2%** |
| **C — Fulfillment** | 153 | 146 | 1 | 6 | **95.4%** |
| **E — Finance** | 46 | 30 | 8 | 8 | **65.2%** |
| **TOTALE** | **580** | **524** | **34** | **22** | **90.3%** |

---

## Classifica Affidabilità Report

1. 🥇 **Module S (Commercial)** — 96.1% — Report eccellente, 1 solo errore numerico (conteggio channel seed)
2. 🥈 **Module C (Fulfillment)** — 95.4% — Molto accurato, 153 claim verificati
3. 🥉 **Module 0 (PIM)** — 95.2% — Errori solo nella sezione statistiche
4. **Module D (Procurement)** — 94.7% — Unico problema: un gap inesistente (auto-refresh)
5. **Module B (Inventory)** — 92.2% — Tutti gli errori da un'unica causa radice
6. **Module K (Customers)** — 88.2% — AccountPolicy dichiarato mancante ma esiste
7. **Module E (Finance)** — 65.2% — Molti gap dichiarati che in realtà sono implementati
8. **Module A (Allocations)** — 60.0% — Errore critico: wizard dichiarato mancante ma pienamente implementato

---

## Errori Critici per Modulo

### 🔴 Module A — Allocations (Accuracy: 60%)
**ERRORE GRAVE: Il wizard di creazione allocazioni è dichiarato "NON IMPLEMENTATO" quando è COMPLETAMENTE IMPLEMENTATO**

Il report analizza solo `AllocationResource::form()` (che è vuoto) senza verificare `CreateAllocation.php` che contiene un **wizard a 5 step completo** (937 righe):
- Step 1: Selezione Bottle SKU (Wine + Vintage + Format) con cascading selects
- Step 2: Source & Capacity (source_type, supply_form, total_quantity, date range)
- Step 3: Commercial Constraints (channels, geographies, customer_types)
- Step 4: Advanced/Liquid Constraints
- Step 5: Review & Create con attivazione diretta

**Impatto**: 5 user stories (US-008 → US-012) erroneamente marcate come "Missing". La copertura reale è **~97%**, non ~85%.

Altre inesattezze:
- Listener: report dice 2 ma sono 1 (per VoucherIssued)
- Migrations: report dice 13 ma sono 14
- Seeders: report dice "Solo allocations" ma esiste anche VoucherSeeder
- Verdetto complessivo 85% → in realtà ~97%

---

### 🔴 Module E — Finance (Accuracy: 65.2%)
**ERRORE GRAVE: CreateInvoice dichiarato "STUBBED" (P1 critico) ma è un wizard completo di 907 righe**

Il report vede il commento stub in `InvoiceResource::form()` senza verificare che `CreateInvoice.php` override con wizard multi-step completo (selezione cliente, righe fattura con repeater, totali real-time, calcoli bcmath).

**Altri errori significativi:**
- **ViewInvoice header actions**: report le marca "da verificare" ma sono TUTTE implementate (7 azioni: Download PDF, Send Email, Issue, Record Bank Payment, Create Credit Note, Create Refund, Cancel)
- **ViewPayment actions**: report dice "da verificare" ma ci sono 7 azioni complete (Apply to Invoice, Apply to Multiple, Force Match, Create Exception, Mark for Refund, Confirm Not Duplicate, Mark As Duplicate)
- **ViewRefund**: dichiarato incompleto, in realtà ha tabs completi
- **Invoice Status lifecycle**: 2 errori nelle transizioni:
  - `Issued → Cancelled` non esiste nel codice (solo `Draft → Cancelled`)
  - `Paid → Credited` non esiste (Paid è terminale)
- **Policies**: report dice 7, in realtà sono 6

**Copertura reale: ~95-96%**, non ~92%.

---

### 🟡 Module B — Inventory (Accuracy: 92.2%)
**ERRORE SINGOLO CON EFFETTO CASCATA: SerializationQueue dichiarata "NON IMPLEMENTATA" ma esiste ed è completa**

Il file `/app/Filament/Pages/SerializationQueue.php` (330 righe) è pienamente implementato con:
- Query pre-filtrata per location autorizzate
- Colonne, filtri, azione Start Serialization
- Polling a 30s, statistiche coda
- Blade view dedicata

Questo singolo errore genera 6 claim errati (page count, user story count, gap claim, recommendation, completion %, conclusione).

**Copertura reale: 100%** (58/58 user stories), non 98.3%.

---

### 🟡 Module K — Customers (Accuracy: 88.2%)
**AccountPolicy dichiarata mancante ma ESISTE**

`/app/Policies/AccountPolicy.php` è presente con tutti i metodi richiesti: `view()`, `update()`, `delete()`, `manageUsers()` + `create()`, `restore()`, `forceDelete()`, `suspend()`, `activate()`, `manageBlocks()`.

**Altri errori:**
- **SegmentEngine**: report dice 14 segmenti, in realtà sono 13
- **Test Module K**: report dice "nessun test" ma `CustomerPolicyTest.php` esiste con 10 test methods

**Issue non scoperto dal report**: colonna `has_active_blocks` nella lista Customer hardcoded a `false` (stesso pattern del bug `membership_tier` = "N/A" che il report segnala).

---

### 🟢 Module D — Procurement (Accuracy: 94.7%)
**Un solo gap inesistente (GAP-3)**

Il report dice che il default auto-refresh della dashboard è "Off" ma nel codice `$autoRefreshMinutes = 5` che produce un polling di 5 minuti — esattamente come da specifica.

**Copertura reale: 66/68** user stories (97.1%), non 65/68 (95.6%).

---

### 🟢 Module 0 — PIM (Accuracy: 95.2%)
**Errori concentrati nella sezione statistiche**

- Filament Resources: report dice 8, in realtà 11 (mancano CountryResource, RegionResource, AppellationResource, ProducerResource nel conteggio)
- Custom Pages: report dice 4, in realtà 5
- Seeders: report dice 9, in realtà 12
- Enum namespace: report dice "3 Enum PIM" ma solo AppellationSystem è nel namespace Pim/

Tutte le analisi architetturali, entity, gap e invarianti sono corrette.

---

### 🟢 Module S — Commercial (Accuracy: 96.1%)
**Un solo errore fattuale**

- Channel seed count: report dice 6 canali, in realtà sono 9 (mancano Crurated Hospitality B2B, Crurated Trade B2B, Crurated APAC Trade)
- PricingIntelligenceDetail page non contata (7 pagine custom, non 6)

Tutti i 10 GAP claims, tutte le 13 user stories e tutte le 6 verifiche invarianti sono corrette.

---

### 🟢 Module C — Fulfillment (Accuracy: 95.4%)
**Molto accurato su 153 claim**

Unico errore: `ShipmentExecuted` event dichiarato come feature "Beyond-Spec" per multi-SO, ma in realtà l'evento non viene mai dispatched da nessun service di Module C (il dispatch call manca da `ShipmentService::confirmShipment()`).

6 claim parzialmente accurati per dettagli minori (warehouse nel wizard vs View page, consignment check generico, CSV export su audit tab non trovato).

---

## Pattern Ricorrenti negli Errori

### 1. **Stub in Resource::form() ma implementazione in CreateXxx.php** (3 moduli)
I report controllano `XxxResource::form()` e vedono un commento `// will be implemented` senza verificare che la page `CreateXxx.php` override il form con wizard completi. Questo pattern ha causato gli errori più gravi in:
- Module A (CreateAllocation — 937 righe)
- Module E (CreateInvoice — 907 righe)
- Module E (ViewInvoice/ViewPayment actions)

### 2. **Conteggi numerici imprecisi** (5 moduli)
Errori di conteggio su risorse, pagine, seeders, migrazioni. Tipicamente off-by-1 o off-by-3/4 quando vengono dimenticate entità secondarie (lookup tables, etc.).

### 3. **File non trovati ma esistenti** (2 moduli)
- SerializationQueue (Module B)
- AccountPolicy (Module K)

### 4. **"Da verificare" trattato come gap** (Module E)
Diversi claims marcati "da verificare" vengono conteggiati come gap quando in realtà la funzionalità è implementata.

---

## Gap Reali Confermati (dopo verifica)

### Modulo 0 — PIM
- ❌ Nessun Service Layer PIM (logica nei modelli e pagine Filament)
- ❌ Zero test
- ❌ Zero Events/Listeners
- ❌ Zero Policies per modelli PIM
- ❌ SkuLifecycleStatus usa costanti string anziché PHP Enum
- ❌ Nessun CRUD admin per AttributeSet/AttributeGroup/AttributeDefinition
- ❌ Dashboard filters non collegati alle query

### Modulo K — Customers
- ❌ CustomerSeeder legacy (non usa flusso Party → PartyRole → Observer)
- ❌ `membership_tier` hardcoded a "N/A" nella lista Customer
- ❌ `has_active_blocks` hardcoded a `false` nella lista Customer (**non segnalato dal report**)
- ❌ Filtro `removed_at` mancante nella lista blocchi
- ❌ Test mancanti per EligibilityEngine, SegmentEngine, MembershipStatus transitions

### Modulo S — Commercial
- ❌ PriceBookEntry referenzia SKU non Offer (architetturale)
- ❌ Campaign entity mancante
- ❌ Customer segment mancante nelle pricing policies
- ❌ Zero test

### Modulo A — Allocations
- ❌ Enum AllowedCustomerType e AllowedChannel mancanti (valori hardcoded)
- ❌ EditAllocation form vuoto (workaround via Constraints tab in View)

### Modulo D — Procurement
- ❌ PurchaseOrderService mancante (logica in ViewPurchaseOrder header actions)
- ❌ CheckOverdueInboundsJob mancante

### Modulo B — Inventory
- ❌ Typo `CururatedOwned` in OwnershipType enum
- ❌ Widget inline in InventoryOverview anziché classi Widget separate
- ❌ Zero Events/Listeners per comunicazione cross-module
- ❌ Zero Policies
- ❌ NFT placeholder

### Modulo C — Fulfillment
- ❌ Zero test
- ❌ API endpoints WMS mancanti (backend completo, no routes)
- ❌ Customer integration tabs mancanti in CustomerResource
- ❌ Zero Policies
- ❌ ShipmentExecuted event mai dispatched (wired ma non usato)
- ❌ Partial shipment non supportato (MVP)

### Modulo E — Finance
- ❌ CreditNote creation form mancante
- ❌ Refund creation form mancante
- ❌ StorageBilling View page stub
- ❌ Subscription form stub
- ❌ Retry Xero Sync TODO placeholder

---

## Conclusioni

I report di gap analysis sono **complessivamente affidabili** con un'accuracy media del **90.3%** su 580 claims verificati.

**I report più affidabili** sono Module S (96.1%), Module C (95.4%) e PIM (95.2%).

**I report meno affidabili** sono Module A (60%) e Module E (65.2%), entrambi afflitti dallo stesso pattern: controllare `Resource::form()` senza verificare le pagine `CreateXxx.php` che implementano wizard completi.

**Raccomandazione**: Prima di agire sui gap segnalati, verificare sempre che la funzionalità non sia implementata nella page dedicata anziché nel Resource base class. I gap "P0/P1 critico" nei moduli A ed E sono in gran parte falsi positivi.
