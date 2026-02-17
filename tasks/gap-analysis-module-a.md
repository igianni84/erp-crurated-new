# Gap Analysis — Module A (Allocations & Vouchers)

> Confronto tra: **ERP-FULL-DOC.md** (spec funzionale), **prd-module-a-allocations.md** (PRD UI/UX), e **codice implementato**.
> Data analisi: 2026-02-09
> **Ultima revisione: 2026-02-16** (verifica approfondita automatizzata con 7 agenti paralleli su ogni singola claim)

---

## Executive Summary

| Area | Spec funzionale (ERP-FULL-DOC) | PRD (40 user stories) | Implementato | Copertura |
|------|------|------|------|------|
| Models | 7 entità | 7 entità | 7 modelli | ✅ 100% |
| Enums | ~8 referenziati | 10 specificati | 8 implementati | ⚠️ 80% |
| Services | 4 specificati | 4 specificati | 5 implementati | ✅ 125% (extra: VoucherAnomalyService) |
| Events | VoucherIssued | VoucherIssued | 1 evento + 2 listener | ✅ 100% |
| Jobs | 2 (expire reservations + transfers) | 2 | 2 implementati | ✅ 100% |
| Filament Resources | 4 | 4 | 4 (con 10 pages) | ✅ 100% |
| Dashboard | 1 pagina | US-040 | Implementato | ✅ 100% |
| API Endpoint | Trading callback | US-033 | POST /api/vouchers/{voucher}/trading-complete | ✅ 100% |
| Policies | — | implicite | 3 (Allocation, Voucher, VoucherTransfer) | ✅ 100% |
| Tests | — | impliciti | 4 file, 43 test | ✅ Buona copertura |
| Migrations | 7+ tabelle | 7+ tabelle | 14 migration files | ✅ 100% |
| Seeders | — | — | Allocations + Vouchers | ⚠️ Parziale |

**Verdict complessivo: ~97% implementato.** L'infrastruttura core è solida e completa. Il wizard di creazione allocation è implementato con tutti i 5 step. I gap residui riguardano l'EditAllocation (stub), 2 enums mancanti come file separati (valori gestiti via JSON), e alcune feature avanzate della spec funzionale non ancora coperte.

---

## 1. GAP: Elementi mancanti rispetto al PRD

### ~~1.1 AllocationResource Form Wizard~~ — ✅ IMPLEMENTATO

> **CORREZIONE (2026-02-16):** Il wizard a 5 step è **completamente implementato** in `CreateAllocation.php` (937 righe), non nel `AllocationResource::form()`. Il form vuoto nel resource è **by design** perché il wizard è nella page `CreateAllocation` che usa il trait `CreateRecord\Concerns\HasWizard`.

**Implementazione verificata in `CreateAllocation.php`:**
- Step 1: Bottle SKU selection (Wine/Vintage/Format con autocomplete via WineMaster search)
- Step 2: Source & Capacity (source_type, supply_form, total_quantity, availability window, serialization)
- Step 3: Commercial Constraints (channels checklist 5 opzioni, geographies TagsInput, customer_types checklist 5 opzioni)
- Step 4: Advanced Constraints (liquid constraints collapsed, composition group, fungibility exception)
- Step 5: Review & Create (summary read-only + dual CTA "Create as Draft" / "Create and Activate")
- Form handling completo: `mutateFormDataBeforeCreate()`, `afterCreate()` con constraint persistence e attivazione opzionale

**User Stories US-008 → US-012: ✅ Done.**

---

### 1.1 EditAllocation Page — STUB ❌

**PRD (US-013):** Il tab Constraints deve essere "editable only if Draft".

**Implementato:** `EditAllocation.php` è una classe di 21 righe che estende `EditRecord` con solo header actions (View, Delete, Restore) e nessun form schema override. Eredita il form vuoto dal resource. Le modifiche alle allocazioni in stato Draft non sono possibili via UI.

**Nota:** Il ViewAllocation gestisce correttamente la visualizzazione dei constraints con flag "editable only if Draft" nel tab Constraints. Manca solo il form di edit effettivo.

**Priorità: MEDIA** — Il wizard di creazione è completo, ma l'edit di draft richiede intervento.

---

### 1.2 Enums AllowedCustomerType e AllowedChannel — MANCANTI COME FILE SEPARATI ❌

**PRD (Technical Considerations):** La sezione directory tree specifica 10 enums inclusi `AllowedCustomerType.php` e `AllowedChannel.php`.

**Nota:** US-002 nel PRD specifica solo 3 enums (AllocationSourceType, AllocationSupplyForm, AllocationStatus). I 10 enums sono elencati nella sezione Technical Considerations, non nell'acceptance criteria di US-002.

**Implementato:** Solo 8 enums in `app/Enums/Allocation/`. I valori channel e customer_type sono gestiti come array di stringhe hardcoded in `AllocationConstraint`:
- `getAllChannels()` → `['b2c', 'b2b', 'private_sales', 'wholesale', 'club']`
- `getAllCustomerTypes()` → `['retail', 'trade', 'private_client', 'club_member', 'internal']`

Il wizard Step 3 (Commercial Constraints) usa questi stessi valori come opzioni checklist.

**Impatto:** Le constraint funzionano con stringhe raw. Nessun `label()`, `color()`, `icon()` dedicato per i valori channel/customer_type. Il pattern architetturale standard del progetto (enums con label/color/icon) non è rispettato per questi due casi.

**Priorità: BASSA** — Il sistema è funzionalmente completo. La mancanza è di consistenza architetturale, non di funzionalità.

---

### 1.3 Customer Vouchers Tab — VARIANTE ARCHITETTURALE ⚠️

**PRD (US-026):** Tab dedicato in CustomerResource con: voucher_id, bottle_sku, lifecycle_state, flags; filtri per lifecycle_state; summary (total, issued, locked, redeemed).

**Implementato:** Non è un tab separato ma una sezione "Assets Summary" nel tab Overview di ViewCustomer. Mostra conteggi aggregati (total vouchers, issued, locked, redeemed) con link "View All Vouchers" che porta a VoucherResource filtrato.

**Impatto:** Funzionalmente equivalente ma con UX leggermente diversa (sezione nel tab Overview vs tab dedicato).

**Priorità: BASSA** — Il dato è accessibile, solo la navigazione differisce.

---

### 1.4 Customer Case Entitlements View — VARIANTE ARCHITETTURALE ⚠️

**PRD (US-030):** Sezione/sotto-sezione in Customer Detail con: entitlement_id, sellable_sku, status, vouchers count; indicatore visivo INTACT vs BROKEN.

**Implementato:** Integrato nella stessa sezione "Assets Summary" con conteggi (total cases, intact, broken) e link alla risorsa dedicata.

**Impatto:** Equivalente ma aggregato invece di dettagliato inline.

**Priorità: BASSA**

---

## 2. GAP: Elementi nella spec funzionale NON nel PRD e NON implementati

Questi sono concetti presenti in ERP-FULL-DOC.md sezione Module A ma assenti sia dal PRD che dall'implementazione. Tutti i riferimenti di sezione sono stati verificati nel documento originale (2026-02-16).

### 2.1 Allocation-Derived Commercial Availability (§7.3.4) — NON IMPLEMENTATO ❌

**Spec funzionale:** "From each active Allocation, the ERP derives a set of commercial availability facts, expressing: allowed channels, allowed markets/geographies, validity windows, quantity caps. These availability facts are derived, read-only projections of allocation constraints."

**Stato:** Nessun servizio bridge attivo tra Module A e Module S. Le allocation constraints esistono ma non vengono proiettate in disponibilità commerciale. Module S referenzia le allocation constraints (OfferEligibility contiene commento "Eligibility cannot override allocation constraints from Module A") ma l'integrazione è solo documentale, non enforced a runtime.

**Impatto:** Module S potrebbe esporre offerte che violano allocation constraints. La regola "If a Bottle SKU is not covered by an active allocation for a given channel or market, it is not commercially available" non è enforced.

**Priorità: ALTA** — Invariante business critico non enforced.

---

### 2.2 Active Consignment / Sell-Through (§7.8.4) — NON SUPPORTATO ❌

**Spec funzionale:** "No vouchers are created. Stock moves before sale. Sales are reported after they happen. Module A may track quantity limits but does not create customer obligations."

**Stato:** L'enum `AllocationSourceType` non include un valore per active consignment. `PassiveConsignment` esiste ma è semanticamente diverso. Nessuna logica per impedire creazione voucher su allocazioni sell-through.

**Impatto:** Il modello di business active consignment non è supportabile nel sistema attuale.

**Priorità: MEDIA** — Dipende da quando il business model verrà attivato.

---

### 2.3 Member Consignment / Agency Resale (§7.8.6) — NON IMPLEMENTATO ❌

**Spec funzionale:** "A customer who already owns bottles consigns them to Crurated for resale under an agency arrangement. Ownership remains with the consigning customer until sale. No vouchers are issued."

**Stato:** Nessun modello, enum, o logica di servizio per questo scenario.

**Impatto:** Il modello agency/secondary liquidity non è supportabile.

**Priorità: MEDIA** — Feature di fase successiva.

---

### 2.4 Voucher Binding Mode / Early Binding (§7.10) — PARZIALMENTE IMPLEMENTATO ⚠️

**Spec funzionale:** "A voucher may optionally enter an early binding mode when the customer requests personalised bottling. The voucher is bound to a specific physical unit at serialization time."

**Stato:** Nessun campo `binding_mode` su Voucher o Allocation come attributo esplicito. Tuttavia, early binding è **parzialmente implementato a livello fulfillment** (Module C):
- `ShippingOrderLine` ha un campo `early_binding_serial` per il binding anticipato
- `LateBindingService` contiene logica di validazione separata per early bindings (lines 353-414)
- Il binding mode è quindi **implicito nel contesto di spedizione**, non esplicito nella struttura allocation

**Impatto:** Il bottling personalizzato è supportabile tramite il campo early_binding_serial su ShippingOrderLine, ma manca un attributo esplicito `binding_mode` a livello Voucher/Allocation come previsto dalla spec. La logica è distribuita nel fulfillment layer anzichè dichiarata nell'allocation layer.

**Priorità: BASSA** — Funzionalmente coperto a livello fulfillment, manca solo la dichiarazione esplicita a livello allocation.

---

### 2.5 Composition Constraint Enforcement (§7.3.3) — SOLO SCHEMA ⚠️

**Spec funzionale:** "Allocations may carry composition constraints that restrict how they may be consumed. One or more Bottle SKU allocations must be consumed together as an atomic group."

**Stato:** Il campo `composition_constraint_group` esiste su `AllocationConstraint` (migration + model fillable + display in `getSummary()`), ma:
- Nessuna logica in `AllocationService.consumeAllocation()` che verifichi atomicità (verificato: il metodo usa `DB::transaction()` con `lockForUpdate()` per singola allocation, ma non controlla il gruppo)
- Nessuna validazione che allocazioni nello stesso gruppo vengano consumate insieme
- Nessun servizio che raggruppi allocazioni per composition group

**Impatto:** I vertical cases non sono enforced a livello allocation.

**Priorità: MEDIA** — Schema pronto, manca business logic.

---

### 2.6 Temporary Reservation in Checkout/Negotiation Flow — DEAD CODE ⚠️

**Spec funzionale (§7.3.5):** "To prevent overselling during checkout, negotiation, or manual deal workflows, Module A supports temporary allocation reservations."

**Stato:** Modello `TemporaryReservation` con metodi lifecycle completi (`isActive()`, `hasExpired()`, `shouldExpire()`, `expire()`, `cancel()`, `convert()`), scopes (`active()`, `needsExpiration()`), e job `ExpireReservationsJob` implementati. `AllocationService.getRemainingAvailable()` (line 151-159) tiene correttamente conto delle reservation attive nel calcolo disponibilità. Ma:
- **Nessun codice nell'intera applicazione crea TemporaryReservation** al di fuori di test/seeders
- Nessun punto di integrazione con Module S (checkout flow)
- Nessun trigger automatico durante negotiazione
- L'intera feature è **implementata ma orfana** — codice lifecycle completo che non viene mai invocato in produzione

**Impatto:** L'infrastruttura anti-overselling è completa ma è dead code. Il calcolo disponibilità le contempla (`getRemainingAvailable()`), ma nulla le crea in produzione.

**Priorità: MEDIA** — Dipende dall'implementazione dei flussi checkout in Module S. Il codice è pronto, serve solo il punto di integrazione.

---

## 3. Elementi implementati OLTRE la specifica

### 3.1 VoucherAnomalyService (5° servizio) ✅

Non specificato nel PRD ma implementato. Gestisce:
- Quarantena voucher anomali (`quarantine()`, `unquarantine()`, `updateReason()`)
- Validazione dati (`validateVoucher()`, `validateVoucherData()`)
- Scan batch per anomalie (`scanForAnomalies()`, `autoQuarantineIfNeeded()`)
- Query: `getQuarantinedVouchers()`, `getQuarantinedCount()`
- Costanti: `REASON_MISSING_ALLOCATION`, `REASON_MISSING_CUSTOMER`, `REASON_MISSING_BOTTLE_SKU`, `REASON_DATA_IMPORT_FAILURE`, `REASON_MANUAL_REVIEW`

**Valore:** Supporta US-037 (quarantine handling) con un servizio dedicato e robusto (9 metodi pubblici + 5 costanti).

### 3.2 ViewVoucher con 8 sezioni (vs 6 nel PRD) ✅

Il PRD specifica 6 sezioni. L'implementazione ne ha 8:
- Header + Quarantine Warning (aggiunte)
- What Was Sold, Allocation Lineage, Lifecycle State, Behavioral Flags, Transfer Context, Event History

### 3.3 Race Condition Handling su Transfer ✅

Gestione sofisticata della concorrenza transfer/lock:
- `isAcceptanceBlockedByLock()` su VoucherTransfer (lines 281-288)
- `hasPendingTransferBlockedByLock()` su Voucher (lines 487-496)
- `canCurrentlyBeAccepted()` su VoucherTransfer (lines 296-329) con check completi: lock, suspension, expiry
- Messaggi espliciti per l'utente

### 3.4 Idempotency Protection ✅

`sale_reference` unique constraint per prevenire duplicazione voucher. `VoucherService.issueVouchers()` verifica tramite `findExistingVouchers()` (allocation_id + customer_id + sale_reference) e restituisce voucher esistenti se già creati, con log warning per tentativi duplicati.

### 3.5 Wizard di Creazione con Dual Submit ✅

`CreateAllocation.php` implementa un wizard a 5 step con due CTA distinti:
- "Create as Draft" (comportamento default)
- "Create and Activate" (role-based, richiede policy `activate`)

Questo pattern non è esplicitamente richiesto nel PRD ma migliora il workflow operativo.

---

## 4. Confronto Enums: PRD vs Implementazione

| Enum | PRD (Technical Considerations) | Implementato | Match |
|------|-----|-------------|-------|
| AllocationSourceType | 4 cases | 4 cases | ✅ Identico |
| AllocationSupplyForm | 2 cases | 2 cases | ✅ Identico |
| AllocationStatus | 4 cases | 4 cases | ✅ Identico |
| ReservationContextType | 3 cases | 3 cases | ✅ Identico |
| ReservationStatus | 4 cases | 4 cases | ✅ Identico |
| VoucherLifecycleState | 4 cases | 4 cases | ✅ Identico |
| CaseEntitlementStatus | 2 cases | 2 cases | ✅ Identico |
| VoucherTransferStatus | 4 cases | 4 cases | ✅ Identico |
| AllowedCustomerType | 5 cases | **Non esiste come enum** | ⚠️ Valori hardcoded in AllocationConstraint |
| AllowedChannel | 5 cases | **Non esiste come enum** | ⚠️ Valori hardcoded in AllocationConstraint |

Tutti gli 8 enums implementati hanno i metodi UI standard: `label()`, `color()`, `icon()`. Solo 4 dei 8 enums hanno anche `allowedTransitions()` e `isTerminal()` (quelli con lifecycle a stati): AllocationStatus, ReservationStatus, VoucherLifecycleState, VoucherTransferStatus. I restanti 4 (AllocationSourceType, AllocationSupplyForm, CaseEntitlementStatus, ReservationContextType) sono enums di tipo/classificazione senza transizioni di stato.

---

## 5. Confronto User Stories: PRD vs Implementazione

### Sezione 1: Infrastruttura Allocations
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-001 | Setup modello Allocation | ✅ Done | Tutti i campi, relazioni, invarianti. Traits: Auditable, HasFactory, HasUuid, SoftDeletes |
| US-002 | Setup enums | ✅ Done | 8/8 enums richiesti da acceptance criteria (i 2 "mancanti" sono nella sezione Technical Considerations, non in US-002) |
| US-003 | Setup AllocationConstraint | ✅ Done | Auto-create, editable solo in Draft. Traits: Auditable, HasFactory, HasUuid |
| US-004 | Setup LiquidAllocationConstraint | ✅ Done | Solo per supply_form=liquid. Traits: Auditable, HasFactory, HasUuid |
| US-005 | Setup TemporaryReservation | ✅ Done | Modello con lifecycle completo + scopes + job expiration |
| US-006 | AllocationService | ✅ Done | activate, close, consumeAllocation (con DB::transaction + lockForUpdate), checkAvailability, getRemainingAvailable, markAsExhausted |

### Sezione 2: Allocation CRUD & UI
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-007 | Allocation List | ✅ Done | 11 colonne, 6 filtri, ordinamento, eager loading |
| US-008 | Wizard Step 1 (Bottle SKU) | ✅ Done | WineMaster search, vintage cascading, format selection, Bottle SKU preview |
| US-009 | Wizard Step 2 (Source & Capacity) | ✅ Done | Enum-backed selects, quantity validation, availability window, serialization toggle |
| US-010 | Wizard Step 3 (Commercial Constraints) | ✅ Done | Channels checklist (5), geographies TagsInput, customer_types checklist (5), warning banner |
| US-011 | Wizard Step 4 (Advanced/Liquid) | ✅ Done | Liquid constraints collapsed, bottling formats, case configs, composition group, fungibility |
| US-012 | Wizard Step 5 (Review & Create) | ✅ Done | Summary read-only, dual CTA "Create as Draft" / "Create and Activate" |
| US-013 | Allocation Detail 6 tabs | ✅ Done | Overview, Constraints, Capacity & Consumption, Reservations, Vouchers, Audit |
| US-014 | Status transitions | ✅ Done | Con auto-exhaustion quando remaining=0 |

### Sezione 3: Infrastruttura Vouchers
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-015 | Setup modello Voucher | ✅ Done | Tutti i campi + extra (quarantine: requires_attention, attention_reason). Traits: Auditable, HasFactory, HasUuid, SoftDeletes |
| US-016 | Setup VoucherLifecycleState | ✅ Done | 4 stati con transizioni, isTerminal(), label/color/icon |
| US-017 | Setup CaseEntitlement | ✅ Done | Breakability irreversibile con canBeBroken(). Traits: Auditable, HasFactory, HasUuid |
| US-018 | Setup VoucherTransfer | ✅ Done | Con unique constraint pending. Traits: Auditable, HasFactory, HasUuid |
| US-019 | VoucherService | ✅ Done | issueVouchers (idempotent), lockForFulfillment/unlock, redeem, cancel, suspend, reactivate, suspendForTrading, completeTrading + 20 metodi pubblici totali (inclusi import, fulfillment validation, flag management) |
| US-020 | CaseEntitlementService | ✅ Done | Auto-break su transfer/trade/redemption con costanti REASON_* |
| US-021 | VoucherTransferService | ✅ Done | Con race condition handling (lock check in acceptTransfer) |

### Sezione 4: Voucher CRUD & UI
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-022 | Voucher List | ✅ Done | 9 colonne, 6 filtri, search, NO create action (canCreate=false) |
| US-023 | Voucher Detail | ✅ Done | 8 sezioni (più del PRD: +Header, +Quarantine Warning) |
| US-024 | Behavioral flags | ✅ Done | Toggle con conferma e audit, 6 header actions |
| US-025 | Transfer UI | ✅ Done | Initiate Transfer (form con customer select + expiry) + Cancel Transfer |
| US-026 | Customer Vouchers tab | ⚠️ Variante | Sezione "Assets Summary" in Overview, non tab dedicato. Link a VoucherResource filtrato |
| US-027 | Allocation Vouchers tab | ✅ Done | Tab read-only in ViewAllocation con summary (4 metriche) + lista RepeatableEntry |

### Sezione 5: Case Entitlements
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-028 | CaseEntitlement management | ✅ Done | Resource con list (7 colonne, 2 filtri) + detail (5 sezioni). canCreate=false |
| US-029 | Breakability rules | ✅ Done | Auto-break su transfer/trade/redemption. Irreversibile |
| US-030 | Customer Case view | ⚠️ Variante | Aggregato in Assets Summary con conteggi (total, intact, broken) |

### Sezione 6: Transfer & Trading
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-031 | Transfer List globale | ✅ Done | VoucherTransferResource completo: 8 colonne, 5 filtri, Cancel action con confirmation |
| US-032 | External trading suspension | ✅ Done | suspendForTrading/completeTrading + external_trading_reference |
| US-033 | Trading completion callback | ✅ Done | POST /api/vouchers/{voucher}/trading-complete (routes/api.php:26) |

### Sezione 7: Edge Cases & Invariants
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-034 | Lineage enforcement | ✅ Done | allocation_id immutabile (model boot), quantity=1 invariant + 8 test (VoucherLineageTest) |
| US-035 | Concurrent transfer/lock | ✅ Done | Race condition handling + 8 test (VoucherTransferConcurrencyTest) |
| US-036 | Duplicate prevention | ✅ Done | sale_reference unique + idempotency in issueVouchers() |
| US-037 | Quarantine handling | ✅ Done | VoucherAnomalyService + 12 test (VoucherQuarantineTest) |

### Sezione 8: Audit & Governance
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-038 | Audit log Allocations | ✅ Done | Trait Auditable su tutti i 7 modelli |
| US-039 | Audit log Vouchers | ✅ Done | Event History in ViewVoucher con filtri e clear |
| US-040 | Dashboard | ✅ Done | AllocationVoucherDashboard: 10 stat cards (3 righe), allocations by status, vouchers by state, near exhaustion list, expired transfers, 10 metriche totali |

---

## 6. Riepilogo quantitativo

| Categoria | Totale | ✅ Done | ⚠️ Parziale/Variante | ❌ Mancante |
|-----------|--------|---------|----------------------|------------|
| User Stories PRD | 40 | 37 | 3 | 0 |
| Enums PRD (Technical Considerations) | 10 | 8 | 2 (gestiti come JSON) | 0 |
| Feature spec funzionale (extra PRD) | 6 | 0 | 3 | 3 |

### User Stories variante (⚠️):
1. **US-026** — Vouchers in sezione Overview invece di tab dedicato
2. **US-030** — Case Entitlements aggregati invece di lista dettagliata
3. **Enums AllowedCustomerType/AllowedChannel** — Valori gestiti come JSON hardcoded in AllocationConstraint, non come enum files separati

### Feature spec funzionale non coperte o parziali:
1. **Allocation-derived commercial availability (§7.3.4)** — Bridge A→S mancante (integrazione solo documentale, `OfferEligibility.allocation_constraint_id` dichiarativo ma non enforced)
2. **Active consignment / sell-through (§7.8.4)** — Business model non supportato (enum case mancante)
3. **Member consignment / agency resale (§7.8.6)** — Business model non supportato
4. **Early binding / Personalised bottling (§7.10)** — ⚠️ Parzialmente implementato a livello fulfillment (Module C `ShippingOrderLine.early_binding_serial` + `LateBindingService` validation), manca attributo esplicito su Allocation/Voucher
5. **Composition constraint enforcement (§7.3.3)** — Solo schema, no business logic (campo esiste, `consumeAllocation()` non lo verifica)
6. **Temporary reservation integration (§7.3.5)** — Dead code: infrastruttura completa (modello + lifecycle + job + integrazione in `getRemainingAvailable()`), ma nessun codice in produzione crea reservation

---

## 7. Dettaglio tecnico implementazione

### 7.1 Filament Pages (10 totali)

| Resource | Pages | Dettaglio |
|----------|-------|-----------|
| AllocationResource | 4 | ListAllocations, CreateAllocation (wizard 5 step), ViewAllocation (6 tabs), EditAllocation (stub) |
| VoucherResource | 2 | ListVouchers, ViewVoucher (8 sezioni) |
| CaseEntitlementResource | 2 | ListCaseEntitlements, ViewCaseEntitlement (5 sezioni) |
| VoucherTransferResource | 2 | ListVoucherTransfers, ViewVoucherTransfer (6 sezioni) |

### 7.2 Test Coverage (4 file, 43 test)

| File | Test | Focus |
|------|------|-------|
| VoucherQuarantineTest.php | 12 | Anomaly detection, quarantine/unquarantine, requires_attention |
| VoucherLineageTest.php | 8 | Immutabilità allocation_id, fulfillment lineage, constraint messages |
| VoucherTransferConcurrencyTest.php | 8 | Race conditions transfer/lock, blocked states, timestamps |
| AllocationToolsTest.php | 15 | AI tools: allocation overview, bottles by producer, voucher counts |

### 7.3 Migrations (14 file)

1. `250000_create_allocations_table` — Tabella allocations con UUID PK
2. `260000_create_allocation_constraints_table` — One-to-one con allocation
3. `270000_create_liquid_allocation_constraints_table` — Per supply_form=liquid
4. `280000_create_temporary_reservations_table` — Con UUID PK
5. `300000_create_vouchers_table` — Con UUID PK, soft deletes
6. `310000_create_case_entitlements_table` — Con UUID PK
7. `320000_add_case_entitlement_id_to_vouchers_table` — FK nullable
8. `330000_create_voucher_transfers_table` — Con UUID PK
9. `340000_add_external_trading_reference_to_vouchers_table`
10. `350000_add_unique_sale_reference_constraint_to_vouchers_table`
11. `360000_add_requires_attention_to_vouchers_table` — Quarantine fields
12. `370000_add_updated_by_to_vouchers_table`
13. `370001_add_audit_columns_to_case_entitlements_table`
14. `370002_add_audit_columns_to_voucher_transfers_table`

### 7.4 Seeders

| Seeder | Stato | Note |
|--------|-------|------|
| AllocationSeeder | ✅ Implementato | 300+ allocations, tutti gli status e source types, include liquid, 7 categorie vino (Ultra Premium, Piedmont, Tuscany, Bordeaux, Burgundy, Champagne, Rhone) |
| VoucherSeeder | ✅ Implementato | Usa VoucherService.issueVouchers() con business logic |
| CaseEntitlementSeeder | ❌ Mancante | |
| VoucherTransferSeeder | ❌ Mancante | |
| TemporaryReservationSeeder | ❌ Mancante | |

---

## 8. Raccomandazioni prioritizzate

### P0 — Critiche (bloccano operatività)
1. **Implementare form EditAllocation** — Necessario per modificare allocazioni in stato Draft via UI. Il ViewAllocation mostra i constraints come editabili in Draft, ma il form di edit non ha schema

### P1 — Importanti (gap funzionali)
2. **Implementare bridge Allocation→Commercial availability (§7.3.4)** — Prevenire violazione constraints in Module S. Attualmente l'integrazione è solo documentale
3. **Enforcement composition constraints (§7.3.3)** — Logica atomicità per vertical cases in `consumeAllocation()`

### P2 — Medio termine
4. **Creare enums AllowedCustomerType e AllowedChannel** — Allineamento al pattern architetturale (opzionale, funzionalmente completo)
5. **Integrare temporary reservations nei flussi checkout (§7.3.5)** — Collegare a Module S
6. **Arricchire seeder** — Aggiungere CaseEntitlementSeeder, VoucherTransferSeeder, TemporaryReservationSeeder

### P3 — Futuro
7. **Active consignment business model (§7.8.4)** — Quando il business lo richiede
8. **Member consignment / Agency resale (§7.8.6)** — Quando il business lo richiede
9. **Early binding come attributo esplicito (§7.10)** — Già funzionante a livello fulfillment (Module C), serve solo campo dichiarativo su Allocation/Voucher per allineamento spec
10. **Customer Vouchers come tab dedicato** — Refinement UX (US-026)

---

## 9. Log di verifica (2026-02-16)

> Verifica automatizzata condotta con 7 agenti paralleli che hanno letto e analizzato ogni singolo file del Module A.

### Correzioni applicate

| # | Sezione | Errore originale | Correzione | Gravità |
|---|---------|------------------|------------|---------|
| 1 | §4 (Enums) | "Tutti gli 8 enums hanno `allowedTransitions()`, `isTerminal()`" | Solo 4/8 li hanno (AllocationStatus, ReservationStatus, VoucherLifecycleState, VoucherTransferStatus). Gli altri 4 hanno solo `label()`, `color()`, `icon()` | **ALTA** — Claim fattualmente errata |
| 2 | §7.2 (Tests) | Test distribution 12+12+12+7=43 | Distribuzione reale: 12+8+8+15=43 (totale corretto, per-file errato) | **MEDIA** — Numeri individuali sbagliati |
| 3 | §5 US-040 (Dashboard) | "4 stat cards, 11+ metriche" | 10 stat cards su 3 righe, 10 metriche totali | **MEDIA** — Numeri sottostimati |
| 4 | §5 US-022 (Voucher List) | "11 colonne" | 9 colonne | **BASSA** — Conteggio errato |
| 5 | §5 US-019 (VoucherService) | "lock/unlock" | Metodo si chiama `lockForFulfillment()` non `lock()`. Servizio ha 20 metodi pubblici totali | **BASSA** — Nome metodo impreciso |
| 6 | §3.1 (VoucherAnomalyService) | 3 costanti e 6 metodi | 5 costanti (+REASON_MISSING_BOTTLE_SKU, +REASON_MANUAL_REVIEW) e 9 metodi pubblici (+updateReason, +getQuarantinedVouchers, +getQuarantinedCount) | **BASSA** — Sottostimato |
| 7 | §2.4 (Early Binding) | "NON IMPLEMENTATO ❌" | Parzialmente implementato ⚠️ a livello fulfillment (Module C ShippingOrderLine.early_binding_serial + LateBindingService) | **MEDIA** — Claim troppo categorica |
| 8 | §2.6 (Temporary Reservations) | "SOLO INFRASTRUTTURA ⚠️" | Dead code: implementazione completa ma nessun codice in produzione crea reservation | **BASSA** — Severità sottostimata |
| 9 | §1.1 (CreateAllocation) | "935 righe" | 937 righe | Trascurabile |
| 10 | §1.1 (EditAllocation) | "22 righe" | 21 righe | Trascurabile |

### Claim confermate senza errori
- 7 modelli con tutti i campi, trait e relazioni ✅
- Invarianti (quantity=1, allocation_id immutable, case breaking irreversibile) ✅
- 5 servizi con tutte le responsabilità ✅
- 4 Filament Resources con 10 Pages totali ✅
- 14 migration files con numerazione corretta ✅
- 1 evento + 2 listener + 2 jobs + 3 policies ✅
- API route POST /api/vouchers/{voucher}/trading-complete ✅
- Seeders: 2 implementati + 3 mancanti ✅
- Customer integration (US-026, US-030): varianti architetturali corrette ✅
- Gap funzionali §7.3.3, §7.3.4, §7.8.4, §7.8.6: tutti confermati ✅
- SoftDeletes solo su Allocation e Voucher (non sugli altri 5 modelli) ✅

### Distribuzione SoftDeletes (dettaglio verificato)
| Modello | SoftDeletes |
|---------|:-----------:|
| Allocation | ✅ |
| Voucher | ✅ |
| VoucherTransfer | ✗ |
| AllocationConstraint | ✗ |
| CaseEntitlement | ✗ |
| LiquidAllocationConstraint | ✗ |
| TemporaryReservation | ✗ |
