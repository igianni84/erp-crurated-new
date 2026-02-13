# Gap Analysis — Module C (Fulfillment & Shipping)

**Data:** 9 febbraio 2026
**Scope:** Confronto tra documentazione funzionale (ERP-FULL-DOC.md §10), PRD UI/UX (prd-module-c-fulfillment.md) e codice effettivamente implementato.

---

## 1. Sintesi Esecutiva

| Metrica | Valore |
|---------|--------|
| User Stories nel PRD | 62 (US-C001 → US-C062) |
| User Stories implementate | ~56 |
| User Stories parziali | ~4 |
| User Stories mancanti | ~2 |
| Copertura funzionale | **~93%** |
| Copertura UI/UX | **~95%** |
| Copertura invarianti | **100%** |
| Test coverage | **0%** |

**Verdetto complessivo:** Il modulo è architetturalmente solido e copre la quasi totalità delle specifiche funzionali. Le lacune sono concentrate su: integrazione Customer, API WMS, policies di autorizzazione e test automatizzati.

---

## 2. Inventario Codice Prodotto

### 2.1 Models (5)
| File | Righe chiave |
|------|-------------|
| `app/Models/Fulfillment/ShippingOrder.php` | Status machine, immutability guard su transizioni, previous_status per OnHold |
| `app/Models/Fulfillment/ShippingOrderLine.php` | **allocation_id IMMUTABILE** (boot guard), early/late binding, bound_case_id |
| `app/Models/Fulfillment/Shipment.php` | **shipped_bottle_serials IMMUTABILE** dopo conferma, status transitions |
| `app/Models/Fulfillment/ShippingOrderException.php` | Blocking/non-blocking, resolution path, line-level o order-level |
| `app/Models/Fulfillment/ShippingOrderAuditLog.php` | **NO SoftDeletes**, NO updated_at, boot guard anti-update e anti-delete |

### 2.2 Enums (8)
| Enum | Cases |
|------|-------|
| `ShippingOrderStatus` | Draft, Planned, Picking, Shipped, Completed, Cancelled, OnHold |
| `ShippingOrderLineStatus` | Pending, Validated, Picked, Shipped, Cancelled |
| `ShipmentStatus` | Preparing, Shipped, InTransit, Delivered, Failed |
| `ShippingOrderExceptionType` | SupplyInsufficient, VoucherIneligible, WmsDiscrepancy, BindingFailed, CaseIntegrityViolated, OwnershipConstraint, EarlyBindingFailed |
| `ShippingOrderExceptionStatus` | Active, Resolved |
| `PackagingPreference` | Loose, Cases, PreserveCases |
| `Incoterms` | EXW, FCA, DAP, DDP, CIF, FOB, CPT, CIP |
| `Carrier` | DHL, FedEx, UPS, TNT, DPD, GLS, USPS, Chronopost, Colissimo, Other |

### 2.3 Services (5)
| Service | Righe | Responsabilità |
|---------|-------|---------------|
| `ShippingOrderService` | ~1000 | Lifecycle SO, validazione voucher, lock/unlock, 3-checkpoint validation |
| `ShipmentService` | ~770 | Creazione/conferma shipment, redemption, ownership transfer, case breaking |
| `LateBindingService` | ~660 | Late binding voucher→bottle, early binding validation, unbinding |
| `VoucherLockService` | ~455 | Lock/unlock atomico, rollback, validazione concorrenza |
| `WmsIntegrationService` | ~845 | Picking instructions, feedback, serial validation, re-pick, discrepancy |

### 2.4 Filament Resources (3) + Pages (8) + Dashboard (1)
| Componente | Pagine |
|-----------|--------|
| `ShippingOrderResource` | List, Create (5-step wizard), View (5 tabs), Edit |
| `ShipmentResource` | List, View |
| `ShippingOrderExceptionResource` | List, View |
| `FulfillmentDashboard` | Dashboard con 4 widget sections |

### 2.5 Migrations (6)
- `create_shipping_orders_table`
- `create_shipments_table`
- `create_shipping_order_lines_table`
- `create_shipping_order_exceptions_table`
- `create_shipping_order_audit_logs_table`
- `add_destination_address_to_shipping_orders_table`

### 2.6 Events & Listeners
| Componente | Path |
|-----------|------|
| `ShipmentExecuted` event | `app/Events/Finance/ShipmentExecuted.php` |
| `GenerateShippingInvoice` listener | `app/Listeners/Finance/GenerateShippingInvoice.php` |

### 2.7 Jobs (1)
| Job | Scopo |
|-----|-------|
| `UpdateProvenanceOnShipmentJob` | Aggiornamento provenance blockchain (placeholder MVP) |

### 2.8 Seeders (3)
- `ShippingOrderSeeder`, `ShipmentSeeder`, `ShippingOrderLineSeeder`

---

## 3. Confronto Documentazione Funzionale (ERP-FULL-DOC §10) vs Implementazione

### 3.1 Principi Core — TUTTI RISPETTATI ✅

| Principio | Sezione Doc | Stato |
|-----------|------------|-------|
| Shipping è customer-initiated | §10.3.1 | ✅ SO creato dall'operatore su richiesta cliente |
| Redemption avviene solo al shipment | §10.3.2 | ✅ `triggerRedemption()` chiamato SOLO da `confirmShipment()` |
| Late binding solo in Module C | §10.3.3 | ✅ `LateBindingService` unico punto di binding |
| Early binding exception (Module D) | §10.3.3 | ✅ `validateEarlyBinding()` con NO FALLBACK |
| Non-serialized inventory (case binding) | §10.3.4 | ✅ `bound_case_id` su ShippingOrderLine |

### 3.2 Entità Core

| Entità Doc | Modello Impl | Note |
|-----------|-------------|------|
| Shipping Order (§10.4.1) | `ShippingOrder` | ✅ Tutti i campi presenti. Stati: draft→planned→picking→shipped→completed (+cancelled, on_hold) |
| SO Lines (implicito) | `ShippingOrderLine` | ✅ Aggiunto per granularità voucher-level, non esplicitato in doc funzionale ma richiesto dal PRD |
| Shipment Event (§10.4.2) | `Shipment` | ✅ carrier, tracking, shipped_bottle_serials, origin/destination |
| — | `ShippingOrderException` | ✅ Non in doc funzionale ma nel PRD, gestisce discrepanze |
| — | `ShippingOrderAuditLog` | ✅ Non in doc funzionale ma nel PRD, audit immutabile |

### 3.3 Late Binding (§10.5) — COMPLETO ✅

| Requisito | Implementazione |
|-----------|----------------|
| 1 voucher → 1 serialized bottle | ✅ `bindVoucherToBottle()` in LateBindingService |
| Allocation lineage constraint | ✅ **HARD constraint** — validazione allocation_id match obbligatoria |
| No cross-allocation substitution | ✅ Eccezione se mismatch, crea BindingFailed exception |
| Binding irreversibile dopo shipment | ✅ `shipped_bottle_serials` immutabile dopo conferma |
| Binding reversibile prima di shipment | ✅ `unbindLine()` disponibile se non shipped |

### 3.4 Bottle Selection (§10.6) — COMPLETO ✅

| Requisito | Implementazione |
|-----------|----------------|
| ERP richiede inventory eligibile a Module B | ✅ `requestEligibleInventory()` in LateBindingService |
| Validazione fulfillment constraints | ✅ Ownership, custody, state, allocation checks |
| WMS esegue, ERP autorizza | ✅ `WmsIntegrationService` orchestra, non esegue direttamente |

### 3.5 Case Handling (§10.7) — COMPLETO ✅

| Requisito | Implementazione |
|-----------|----------------|
| Preservare case originali se possibile | ✅ `PackagingPreference::PreserveCases` con warning |
| Case breaking irreversibile | ✅ `breakCasesForShipment()` in ShipmentService, `Intact → Broken` |
| Decisioni auditable | ✅ Audit log per ogni case broken con reason |
| Composite SKU handling | ✅ Documentato, binding per bottle nella composizione |

### 3.6 Multi-warehouse (§10.8) — PARZIALE ⚠️

| Requisito | Stato | Note |
|-----------|-------|------|
| Fulfillment da France main warehouse | ✅ | `source_warehouse_id` su SO |
| Fulfillment da satellite warehouses | ✅ | Selezione warehouse nel wizard |
| Fulfillment da consignee locations | ⚠️ | Campo presente ma logica consignment non differenziata |
| Internal transfer prima di shipment | ❌ | **NON IMPLEMENTATO** — nessuna orchestrazione transfer pre-shipment |
| Warehouse selection logic | ⚠️ | Selezione manuale, no automated optimization |

### 3.7 WMS Integration (§10.9) — BACKEND COMPLETO, API MANCANTI ⚠️

| Requisito | Stato | Note |
|-----------|-------|------|
| ERP invia SO a WMS | ✅ | `sendPickingInstructions()` |
| WMS esegue picking | ✅ | `receivePickingFeedback()` |
| WMS conferma seriali | ✅ | `validateSerials()` |
| ERP valida selezioni | ✅ | Allocation lineage check su ogni serial |
| Shipment eseguito e confermato | ✅ | `confirmShipment()` in WmsIntegrationService |
| Discrepancies gestite esplicitamente | ✅ | `handleDiscrepancy()` crea exceptions |
| **API endpoints per comunicazione WMS** | ❌ | **routes/api.php NON contiene endpoints WMS** |

### 3.8 Special Scenarios (§10.10)

| Scenario | Stato | Note |
|----------|-------|------|
| Active consignment — NO redemption | ✅ | Documentazione chiara in `checkVoucherEligibility()` |
| Third-party stock restrictions | ⚠️ | Ownership check presente ma non granulare per third-party custody |

### 3.9 Governance & Invarianti (§10.11) — TUTTI IMPLEMENTATI ✅

| Invariante | Enforcement |
|-----------|-------------|
| No shipment without SO | ✅ `shipping_order_id` FK required + `canCreate()=false` su ShipmentResource |
| Redemption solo al shipment | ✅ `triggerRedemption()` SOLO in `confirmShipment()` |
| Late binding solo in Module C | ✅ `LateBindingService` unico punto |
| 1 voucher = 1 bottle | ✅ Enforced in binding logic |
| ERP authorizes, WMS executes | ✅ Authority model nel WmsIntegrationService |

---

## 4. Confronto PRD UI/UX (prd-module-c-fulfillment.md) vs Implementazione

### 4.1 Sezione 1: Infrastructure Base (US-C001 → US-C010) — COMPLETO ✅

| US | Descrizione | Stato |
|----|------------|-------|
| US-C001 | ShippingOrder model | ✅ |
| US-C002 | Shipment model | ✅ |
| US-C003 | ShippingOrderLine model | ✅ |
| US-C004 | ShippingOrderStatus enum | ✅ con `allowedTransitions()` |
| US-C005 | ShipmentStatus enum | ✅ |
| US-C006 | PackagingPreference enum | ✅ con `description()`, `mayDelayShipment()` |
| US-C007 | ShippingOrderLineStatus enum | ✅ |
| US-C008 | ShippingOrderExceptionType enum | ✅ con `isBlocking()`, `canAutoResolve()` |
| US-C009 | ShippingOrderException model | ✅ |
| US-C010 | ShippingOrderAuditLog model | ✅ immutabile (NO SoftDeletes, NO update, NO delete) |

### 4.2 Sezione 2: Core Services (US-C011 → US-C015) — COMPLETO ✅

| US | Descrizione | Stato |
|----|------------|-------|
| US-C011 | ShippingOrderService | ✅ ~1000 righe, lifecycle completo |
| US-C012 | LateBindingService | ✅ ~660 righe, allocation hard constraint |
| US-C013 | ShipmentService | ✅ ~770 righe, redemption + ownership |
| US-C014 | VoucherLockService | ✅ ~455 righe, atomic lock/unlock |
| US-C015 | WmsIntegrationService | ✅ ~845 righe, bidirezionale |

### 4.3 Sezione 3: SO CRUD & UI (US-C016 → US-C023) — COMPLETO ✅

| US | Descrizione | Stato | Note |
|----|------------|-------|------|
| US-C016 | ShippingOrderResource | ✅ | Nav icon, group, sort, label corretti |
| US-C017 | List view con filtri | ✅ | Status, customer, warehouse, date range, carrier filters |
| US-C018 | Wizard Step 1: Customer | ✅ | Autocomplete, eligibility check, address |
| US-C019 | Wizard Step 2: Vouchers | ✅ | Multi-select, eligibility filtering, wine/allocation filters |
| US-C020 | Wizard Step 3: Shipping | ✅ | Carrier enum, incoterms, date picker |
| US-C021 | Wizard Step 4: Packaging | ✅ | Radio buttons, visual breakdown, warning |
| US-C022 | Wizard Step 5: Review | ✅ | Summary di tutti i dati, CTA "Create Draft" |
| US-C023 | Bulk Actions | ✅ | Export CSV, Cancel (con reason) |

### 4.4 Sezione 4: SO Detail View (US-C024 → US-C029) — COMPLETO ✅

| US | Descrizione | Stato | Note |
|----|------------|-------|------|
| US-C024 | Tab Overview | ✅ | Customer, destination, method, packaging, voucher summary |
| US-C025 | Tab Vouchers & Eligibility | ✅ | Per-voucher checks, blocking banner, badge count |
| US-C026 | Tab Planning | ✅ | Warehouse selection, inventory availability, allocation constraint |
| US-C027 | Tab Picking & Binding | ✅ | Bound serials, early binding, discrepancy handling |
| US-C028 | Tab Audit & Timeline | ✅ | Timeline immutabile, per-event detail, export CSV |
| US-C029 | Contextual Actions | ✅ | Status-based actions con confirmation dialogs |

### 4.5 Sezione 5: Late Binding & Picking (US-C030 → US-C035) — COMPLETO ✅

| US | Descrizione | Stato |
|----|------------|-------|
| US-C030 | Voucher eligibility validation (3 checkpoints) | ✅ Creation, planning, pre-picking |
| US-C031 | Allocation lineage constraint | ✅ **HARD** — no cross-allocation |
| US-C032 | Inventory availability request | ✅ Con cache 5 min |
| US-C033 | Late binding execution | ✅ voucher → serialized bottle |
| US-C034 | Early binding validation | ✅ No fallback se fallisce |
| US-C035 | Case integrity handling | ✅ PreserveCases preference, warnings |

### 4.6 Sezione 6: Shipment Management (US-C036 → US-C042) — COMPLETO ✅

| US | Descrizione | Stato | Note |
|----|------------|-------|------|
| US-C036 | ShipmentResource | ✅ | Read-only, nav correct |
| US-C037 | Shipment detail view | ✅ | Carrier, tracking URL, shipped items table |
| US-C038 | Shipment creation from SO | ✅ | `createFromOrder()` richiede status=Picking |
| US-C039 | Shipment confirmation | ✅ | Tracking required, case break confirmation |
| US-C040 | Voucher redemption trigger | ✅ | **SOLO** a confirmation |
| US-C041 | Ownership transfer trigger | ✅ | Bottle state → Shipped |
| US-C042 | Provenance update trigger | ✅ | `UpdateProvenanceOnShipmentJob` (placeholder) |

### 4.7 Sezione 7: Exception Handling (US-C043 → US-C047) — COMPLETO ✅

| US | Descrizione | Stato | Note |
|----|------------|-------|------|
| US-C043 | Exceptions list page | ✅ | Badge count, active-default filter, red highlighting |
| US-C044 | Supply exception | ✅ | SupplyInsufficient type |
| US-C045 | WMS discrepancy | ✅ | WmsDiscrepancy type + resolution |
| US-C046 | Voucher ineligible | ✅ | VoucherIneligible type + blocking |
| US-C047 | Shipment failure recovery | ✅ | `markFailed()` NON triggera redemption |

### 4.8 Sezione 8: WMS Integration (US-C048 → US-C051) — BACKEND ✅, API ❌

| US | Descrizione | Stato | Note |
|----|------------|-------|------|
| US-C048 | WMS outbound message | ✅ | `sendPickingInstructions()` con payload strutturato |
| US-C049 | WMS picking feedback | ✅ | `receivePickingFeedback()` con serial validation |
| US-C050 | WMS serial validation | ✅ | Allocation lineage check per serial |
| US-C051 | WMS shipment confirmation | ✅ | `confirmShipment()` in WmsIntegrationService |
| — | **API endpoints HTTP** | ❌ | **Nessun endpoint in routes/api.php** |

### 4.9 Sezione 9: Audit & Governance (US-C052 → US-C054) — COMPLETO ✅

| US | Descrizione | Stato |
|----|------------|-------|
| US-C052 | Audit logs SO | ✅ ShippingOrderAuditLog immutabile |
| US-C053 | Audit logs Shipment | ✅ Morfici via AuditLog |
| US-C054 | Late binding audit | ✅ Event tracking con voucher_id, bottle_serial, allocation_id |

### 4.10 Sezione 10: Dashboard (US-C055 → US-C056) — COMPLETO ✅

| US | Descrizione | Stato | Note |
|----|------------|-------|------|
| US-C055 | Fulfillment Dashboard | ✅ | 4 widget sections, links, metrics |
| US-C056 | SO Workflow visualization | ✅ | Step indicator su ViewShippingOrder |

### 4.11 Sezione 11: Customer Integration (US-C057 → US-C058) — NON IMPLEMENTATO ❌

| US | Descrizione | Stato | Note |
|----|------------|-------|------|
| US-C057 | Customer → Shipping Orders tab | ❌ | **CustomerResource non ha relation managers per SO** |
| US-C058 | Customer → Shipment history | ❌ | **CustomerResource non ha tab per Shipments** |

### 4.12 Sezione 12: Edge Cases & Invariants (US-C059 → US-C062) — COMPLETO ✅

| US | Descrizione | Stato | Note |
|----|------------|-------|------|
| US-C059 | Concurrent SO prevention | ✅ | `isVoucherInActiveShippingOrder()` in VoucherLockService |
| US-C060 | SO cancellation unlock | ✅ | `cancel()` sblocca voucher e unbinda lines |
| US-C061 | Shipment without SO prevention | ✅ | `canCreate()=false` + FK required |
| US-C062 | Redemption timing enforcement | ✅ | SOLO in `confirmShipment()` |

---

## 5. Gap Critici e Raccomandazioni

### 5.1 GAP CRITICI (Bloccanti per produzione)

#### GAP-C01: Nessun Test Automatizzato
- **Severità:** 🔴 CRITICA
- **Descrizione:** Zero test per Module C — nessun unit test, feature test o integration test
- **Impatto:** Impossibile verificare correttezza dei workflow critici (late binding, redemption, case breaking)
- **Raccomandazione:** Scrivere test per:
  - ShippingOrderService (lifecycle, voucher validation, lock/unlock)
  - LateBindingService (binding, allocation constraint, early binding)
  - ShipmentService (confirmation, redemption, case breaking)
  - VoucherLockService (atomic lock, rollback, concurrent SO prevention)
  - WmsIntegrationService (picking feedback, serial validation)
- **Effort stimato:** 3-4 giorni

#### GAP-C02: API Endpoints WMS Mancanti
- **Severità:** 🔴 CRITICA (per go-live con WMS reale)
- **Descrizione:** `routes/api.php` non contiene endpoint per comunicazione WMS
- **Impatto:** WmsIntegrationService ha tutta la logica ma nessun punto di ingresso HTTP
- **Raccomandazione:** Creare:
  - `POST /api/wms/picking-instructions` — invio istruzioni
  - `POST /api/wms/picking-feedback` — ricezione feedback
  - `POST /api/wms/shipment-confirmation` — conferma spedizione
  - Autenticazione API key o token per WMS
- **Effort stimato:** 1-2 giorni

### 5.2 GAP ALTI (Necessari pre-produzione)

#### GAP-C03: Customer Integration Tabs (US-C057/US-C058)
- **Severità:** 🟠 ALTA
- **Descrizione:** CustomerResource non ha tab/relation managers per Shipping Orders e Shipments
- **Impatto:** Operatori non possono vedere lo storico fulfillment di un cliente dalla sua scheda
- **Raccomandazione:** Aggiungere RelationManagers a CustomerResource:
  - `ShippingOrdersRelationManager` — lista SO del cliente con status/filters
  - `ShipmentsRelationManager` — storico spedizioni del cliente
- **Effort stimato:** 0.5-1 giorno

#### GAP-C04: Authorization Policies Mancanti
- **Severità:** 🟠 ALTA
- **Descrizione:** Nessuna Policy per ShippingOrder, Shipment, ShippingOrderException
- **Impatto:** Accesso controllato solo a livello Filament Resource, non enforcement globale
- **Raccomandazione:** Creare ShippingOrderPolicy e ShipmentPolicy con:
  - `viewAny`, `view`, `create`, `update`, `delete` basati su ruoli
  - Vincoli specifici (es. solo draft editabili)
- **Effort stimato:** 0.5 giorno

### 5.3 GAP MEDI (Miglioramenti pre-produzione)

#### GAP-C05: Internal Transfer Pre-Shipment
- **Severità:** 🟡 MEDIA
- **Descrizione:** Doc funzionale §10.8.2 prevede transfer interni prima di shipment se stock non al warehouse ottimale — non implementato
- **Impatto:** Scenari multi-warehouse limitati a selezione manuale
- **Raccomandazione:** Implementare orchestrazione transfer via Module B prima di picking
- **Effort stimato:** 2-3 giorni

#### GAP-C06: Blockchain Provenance (Placeholder)
- **Severità:** 🟡 MEDIA
- **Descrizione:** `UpdateProvenanceOnShipmentJob` è solo un placeholder con log
- **Impatto:** Nessun aggiornamento provenance reale — accettabile per MVP
- **Raccomandazione:** Implementare integrazione reale quando infrastruttura blockchain disponibile
- **Effort stimato:** Dipende da provider blockchain

#### GAP-C07: Address Model Integration
- **Severità:** 🟡 MEDIA
- **Descrizione:** `destination_address_id` presente ma nessun Address model — fallback su campo text
- **Impatto:** Nessun riuso indirizzi salvati, nessuna validazione strutturata
- **Raccomandazione:** Implementare Address model in Module K e integrare nel wizard SO
- **Effort stimato:** 1-2 giorni

#### GAP-C08: Automated Warehouse Selection
- **Severità:** 🟡 MEDIA
- **Descrizione:** Doc §10.8.1 prevede logica di selezione automatica warehouse — attualmente manuale
- **Impatto:** Operatori devono scegliere manualmente il warehouse ottimale
- **Raccomandazione:** Aggiungere suggerimento automatico basato su: stock availability, proximity, cost
- **Effort stimato:** 2-3 giorni

### 5.4 GAP BASSI (Nice-to-have)

#### GAP-C09: Third-Party Custody Granularity
- **Severità:** 🟢 BASSA
- **Descrizione:** Ownership check generico, non differenzia tra custody types
- **Impatto:** Edge case raro nel business model attuale
- **Effort stimato:** 1 giorno

#### GAP-C10: Partial Shipment Support
- **Severità:** 🟢 BASSA
- **Descrizione:** WmsIntegrationService commenta "partial shipment not supported in MVP"
- **Impatto:** Tutti i bottle serials attesi devono essere inclusi nella conferma
- **Raccomandazione:** Valutare se necessario per fase 2
- **Effort stimato:** 2-3 giorni

---

## 6. Matrice di Conformità Invarianti

| # | Invariante | Doc §10.11 | PRD | Codice | Note |
|---|-----------|-----------|-----|--------|------|
| 1 | No shipment senza SO | ✅ | ✅ | ✅ | FK required + `canCreate()=false` |
| 2 | Redemption solo al shipment | ✅ | ✅ | ✅ | SOLO in `confirmShipment()` |
| 3 | Late binding solo in Module C | ✅ | ✅ | ✅ | `LateBindingService` unico punto |
| 4 | 1 voucher = 1 bottle | ✅ | ✅ | ✅ | Enforced in binding |
| 5 | Allocation lineage immutabile | ✅ | ✅ | ✅ | Boot guard su `allocation_id` |
| 6 | ERP authorizes, WMS executes | ✅ | ✅ | ✅ | Authority model in WmsIntegrationService |
| 7 | Early binding da Module D è vincolante | ✅ | ✅ | ✅ | NO fallback |
| 8 | Case breaking irreversibile | ✅ | ✅ | ✅ | Intact → Broken, mai il contrario |
| 9 | Binding reversibile fino a conferma | — | ✅ | ✅ | `unbindLine()` prima di shipped |
| 10 | Exceptions visibili (no override) | — | ✅ | ✅ | Escalation, no auto-resolve |

**Risultato: 10/10 invarianti rispettati** ✅

---

## 7. Elementi Implementati OLTRE le Specifiche

| Feature | Presente in Doc? | Presente in PRD? | Note |
|---------|-----------------|-----------------|------|
| OnHold status con previous_status recovery | ❌ | ✅ | Implementato correttamente |
| Incoterms enum con seller/buyer responsibility | ❌ | ✅ | 8 casi con metodi helper |
| Carrier enum con tracking URL templates | ❌ | ✅ | 10 carrier con URL generation |
| Multi-shipment aggregation (INV2) | ❌ | Parziale | ShipmentExecuted supporta multi-SO |
| Wizard 5-step con validazione inter-step | ❌ | ✅ | Implementazione completa |
| Dashboard con near-ship-date alerting | ❌ | ✅ | Warning per SO entro 3 giorni |
| Navigation badge exception count | ❌ | ✅ | Badge danger sul menu |

---

## 8. Riepilogo Effort Raccomandato

| Priorità | Gap | Effort |
|----------|-----|--------|
| 🔴 Critico | GAP-C01: Test automatizzati | 3-4 giorni |
| 🔴 Critico | GAP-C02: API WMS | 1-2 giorni |
| 🟠 Alto | GAP-C03: Customer integration tabs | 0.5-1 giorno |
| 🟠 Alto | GAP-C04: Authorization policies | 0.5 giorno |
| 🟡 Medio | GAP-C05: Internal transfer orchestration | 2-3 giorni |
| 🟡 Medio | GAP-C07: Address model | 1-2 giorni |
| 🟡 Medio | GAP-C08: Auto warehouse selection | 2-3 giorni |
| **Totale stimato** | | **~11-16 giorni** |

---

## 9. Conclusione

Module C è **architetturalmente maturo e ben implementato**. La copertura funzionale è del ~93% con tutti i 10 invarianti di business rispettati al 100%. I service layer sono robusti (~3,730 righe di business logic) con pattern coerenti di audit, immutability e validation.

Le lacune principali sono:
1. **Nessun test** — rischio significativo per un modulo che gestisce redemption irreversibili e ownership transfer
2. **API WMS mancanti** — la logica c'è tutta, mancano gli endpoint HTTP
3. **Customer integration** — tab mancanti nel CustomerResource

La qualità del codice è alta con pattern coerenti (enum-driven state machines, boot guards, audit logging, allocation lineage enforcement). L'implementazione riflette fedelmente sia il documento funzionale che il PRD UI/UX.
