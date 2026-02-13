# Gap Analysis — Module A (Allocations & Vouchers)

> Confronto tra: **ERP-FULL-DOC.md** (spec funzionale), **prd-module-a-allocations.md** (PRD UI/UX), e **codice implementato**.
> Data analisi: 2026-02-09

---

## Executive Summary

| Area | Spec funzionale (ERP-FULL-DOC) | PRD (40 user stories) | Implementato | Copertura |
|------|------|------|------|------|
| Models | 7 entità | 7 entità | 7 modelli | ✅ 100% |
| Enums | ~8 referenziati | 10 specificati | 8 implementati | ⚠️ 80% |
| Services | 4 specificati | 4 specificati | 5 implementati | ✅ 125% (extra: VoucherAnomalyService) |
| Events | VoucherIssued | VoucherIssued | 1 evento + 2 listener | ✅ 100% |
| Jobs | 2 (expire reservations + transfers) | 2 | 2 implementati | ✅ 100% |
| Filament Resources | 4 | 4 | 4 (con 13 pages) | ✅ 100% |
| Dashboard | 1 pagina | US-040 | Implementato | ✅ 100% |
| API Endpoint | Trading callback | US-033 | POST /api/vouchers/{voucher}/trading-complete | ✅ 100% |
| Policies | — | implicite | 3 (Allocation, Voucher, VoucherTransfer) | ✅ 100% |
| Tests | — | impliciti | 3 file, 23+ test | ⚠️ Parziale |
| Migrations | 7+ tabelle | 7+ tabelle | 13 migration files | ✅ 100% |
| Seeders | — | — | Solo allocations | ⚠️ Parziale |

**Verdict complessivo: ~85% implementato.** L'infrastruttura core è solida. I gap principali riguardano il wizard di creazione allocazione, 2 enums mancanti, e alcune feature avanzate della spec funzionale non ancora coperte.

---

## 1. GAP: Elementi mancanti rispetto al PRD

### 1.1 AllocationResource Form Wizard — NON IMPLEMENTATO ❌

**PRD:** US-008 → US-012 specificano un wizard a 5 step:
- Step 1: Selezione Bottle SKU (Wine + Vintage + Format)
- Step 2: Source & Capacity (source_type, supply_form, total_quantity, date range)
- Step 3: Commercial Constraints (channels, geographies, customer_types)
- Step 4: Advanced/Liquid Constraints (composition, fungibility, bottling formats)
- Step 5: Review & Create (summary + CTA "Create as Draft" / "Create and Activate")

**Implementato:** Il form in `AllocationResource.php` è un **placeholder vuoto**:
```php
public static function form(Form $form): Form
{
    return $form->schema([
        // Form schema will be implemented in US-008 through US-012
    ]);
}
```

**Impatto:** Non è possibile creare allocazioni via UI. L'EditAllocation è anch'esso uno stub.

**Priorità: ALTA** — Senza wizard, le allocazioni possono essere create solo via seeder/tinker.

---

### 1.2 Enums AllowedCustomerType e AllowedChannel — MANCANTI ❌

**PRD (US-002, US-010):** Specifica 10 enums, inclusi:
- `AllowedCustomerType`: retail, trade, private_client, club_member, internal
- `AllowedChannel`: b2c, b2b, private_sales, wholesale, club

**Implementato:** Solo 8 enums in `app/Enums/Allocation/`. I due mancanti sono referenziati come valori JSON nelle `AllocationConstraint`, ma non come enums typed.

**Impatto:** Le constraint usano array di stringhe raw invece di enums validati. Nessun `label()`, `color()`, `icon()` disponibile per i valori channel/customer_type nella UI.

**Priorità: MEDIA** — Il sistema funziona con stringhe, ma viola il pattern architetturale (enums con label/color/icon).

---

### 1.3 EditAllocation Page — STUB ❌

**PRD (US-013):** Il tab Constraints deve essere "editable only if Draft".

**Implementato:** `EditAllocation.php` è una classe vuota che estende `EditRecord` senza form schema. Le modifiche alle allocazioni in stato Draft non sono possibili via UI.

**Priorità: ALTA** — Legata al gap 1.1.

---

### 1.4 Customer Vouchers Tab — VARIANTE ARCHITETTURALE ⚠️

**PRD (US-026):** Tab dedicato in CustomerResource con: voucher_id, bottle_sku, lifecycle_state, flags; filtri per lifecycle_state; summary (total, issued, locked, redeemed).

**Implementato:** Non è un tab separato ma una sezione "Assets Summary" nel tab Overview di ViewCustomer. Mostra conteggi aggregati (total vouchers, issued, locked, redeemed) con link "View All Vouchers" che porta a VoucherResource filtrato.

**Impatto:** Funzionalmente equivalente ma con UX leggermente diversa (sezione nel tab Overview vs tab dedicato).

**Priorità: BASSA** — Il dato è accessibile, solo la navigazione differisce.

---

### 1.5 Customer Case Entitlements View — VARIANTE ARCHITETTURALE ⚠️

**PRD (US-030):** Sezione/sotto-sezione in Customer Detail con: entitlement_id, sellable_sku, status, vouchers count; indicatore visivo INTACT vs BROKEN.

**Implementato:** Integrato nella stessa sezione "Assets Summary" con conteggi (total cases, intact, broken) e link alla risorsa dedicata.

**Impatto:** Equivalente ma aggregato invece di dettagliato inline.

**Priorità: BASSA**

---

## 2. GAP: Elementi nella spec funzionale NON nel PRD e NON implementati

Questi sono concetti presenti in ERP-FULL-DOC.md sezione Module A ma assenti sia dal PRD che dall'implementazione.

### 2.1 Allocation-Derived Commercial Availability (§7.3.4) — NON IMPLEMENTATO ❌

**Spec funzionale:** "From each active Allocation, the ERP derives a set of commercial availability facts, expressing: allowed channels, allowed markets/geographies, validity windows, quantity caps. These availability facts are derived, read-only projections of allocation constraints."

**Stato:** Nessun servizio bridge tra Module A e Module S. Le allocation constraints esistono ma non vengono proiettate in disponibilità commerciale. Module S non interroga Module A per validare esposizione offerte.

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

### 2.4 Voucher Binding Mode / Early Binding (§7.10) — NON IMPLEMENTATO ❌

**Spec funzionale:** "A voucher may optionally enter an early binding mode when the customer requests personalised bottling. The voucher is bound to a specific physical unit at serialization time."

**Stato:** Nessun campo `binding_mode` su Voucher o Allocation. Solo late binding è implementato (via Module C LateBindingService).

**Impatto:** Bottling personalizzato non supportabile.

**Priorità: BASSA** — Edge case, attivabile in fase successiva.

---

### 2.5 Composition Constraint Enforcement (§7.3.3) — SOLO SCHEMA ⚠️

**Spec funzionale:** "Allocations may carry composition constraints that restrict how they may be consumed. One or more Bottle SKU allocations must be consumed together as an atomic group."

**Stato:** Il campo `composition_constraint_group` esiste su `AllocationConstraint`, ma:
- Nessuna logica in `AllocationService.consumeAllocation()` che verifichi atomicità
- Nessuna validazione che allocazioni nello stesso gruppo vengano consumate insieme
- Nessun servizio che raggruppi allocazioni per composition group

**Impatto:** I vertical cases non sono enforced a livello allocation.

**Priorità: MEDIA** — Schema pronto, manca business logic.

---

### 2.6 Temporary Reservation in Checkout/Negotiation Flow — SOLO INFRASTRUTTURA ⚠️

**Spec funzionale (§7.3.5):** "To prevent overselling during checkout, negotiation, or manual deal workflows, Module A supports temporary allocation reservations."

**Stato:** Modello, servizio, e job di expiration implementati. Ma:
- Nessun punto di integrazione con Module S (checkout flow)
- Nessun trigger automatico durante negotiazione
- Le reservation possono essere create solo manualmente o via service call

**Impatto:** L'infrastruttura anti-overselling è pronta ma non collegata ai flussi operativi.

**Priorità: MEDIA** — Dipende dall'implementazione dei flussi checkout in Module S.

---

## 3. Elementi implementati OLTRE la specifica

### 3.1 VoucherAnomalyService (5° servizio) ✅

Non specificato nel PRD ma implementato. Gestisce:
- Quarantena voucher anomali (missing allocation, customer, bottle SKU)
- Validazione dati import
- Flag `requires_attention` + `attention_reason`

**Valore:** Supporta US-037 (quarantine handling) con un servizio dedicato e robusto.

### 3.2 ViewVoucher con 8 sezioni (vs 6 nel PRD) ✅

Il PRD specifica 6 sezioni. L'implementazione ne ha 8:
- Header + Quarantine Warning (aggiunte)
- What Was Sold, Allocation Lineage, Lifecycle State, Behavioral Flags, Transfer Context, Event History

### 3.3 Race Condition Handling su Transfer ✅

Gestione sofisticata della concorrenza transfer/lock:
- `isAcceptanceBlockedByLock()` su VoucherTransfer
- `hasPendingTransferBlockedByLock()` su Voucher
- Messaggi espliciti per l'utente

### 3.4 Idempotency Protection ✅

`sale_reference` unique constraint per prevenire duplicazione voucher. `VoucherService.issueVouchers()` verifica e restituisce voucher esistenti se già creati.

---

## 4. Confronto Enums: PRD vs Implementazione

| Enum | PRD | Implementato | Match |
|------|-----|-------------|-------|
| AllocationSourceType | 4 cases | 4 cases | ✅ Identico |
| AllocationSupplyForm | 2 cases | 2 cases | ✅ Identico |
| AllocationStatus | 4 cases | 4 cases | ✅ Identico |
| ReservationContextType | 3 cases | 3 cases | ✅ Identico |
| ReservationStatus | 4 cases | 4 cases | ✅ Identico |
| VoucherLifecycleState | 4 cases | 4 cases | ✅ Identico |
| CaseEntitlementStatus | 2 cases | 2 cases | ✅ Identico |
| VoucherTransferStatus | 4 cases | 4 cases | ✅ Identico |
| AllowedCustomerType | 5 cases | **NON ESISTE** | ❌ Mancante |
| AllowedChannel | 5 cases | **NON ESISTE** | ❌ Mancante |

---

## 5. Confronto User Stories: PRD vs Implementazione

### Sezione 1: Infrastruttura Allocations
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-001 | Setup modello Allocation | ✅ Done | Tutti i campi, relazioni, invarianti |
| US-002 | Setup enums | ⚠️ 80% | 8/10 enums (mancano AllowedCustomerType, AllowedChannel) |
| US-003 | Setup AllocationConstraint | ✅ Done | Auto-create, editable solo in Draft |
| US-004 | Setup LiquidAllocationConstraint | ✅ Done | Solo per supply_form=liquid |
| US-005 | Setup TemporaryReservation | ✅ Done | Modello + job expiration |
| US-006 | AllocationService | ✅ Done | activate, close, consume, check availability |

### Sezione 2: Allocation CRUD & UI
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-007 | Allocation List | ✅ Done | Colonne, filtri, ordinamento |
| US-008 | Wizard Step 1 (Bottle SKU) | ❌ Non fatto | Form vuoto |
| US-009 | Wizard Step 2 (Source & Capacity) | ❌ Non fatto | Form vuoto |
| US-010 | Wizard Step 3 (Commercial Constraints) | ❌ Non fatto | Form vuoto |
| US-011 | Wizard Step 4 (Advanced/Liquid) | ❌ Non fatto | Form vuoto |
| US-012 | Wizard Step 5 (Review & Create) | ❌ Non fatto | Form vuoto |
| US-013 | Allocation Detail 6 tabs | ✅ Done | Tutti 6 tabs implementati |
| US-014 | Status transitions | ✅ Done | Con auto-exhaustion |

### Sezione 3: Infrastruttura Vouchers
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-015 | Setup modello Voucher | ✅ Done | Tutti i campi + extra (quarantine) |
| US-016 | Setup VoucherLifecycleState | ✅ Done | 4 stati con transizioni |
| US-017 | Setup CaseEntitlement | ✅ Done | Breakability irreversibile |
| US-018 | Setup VoucherTransfer | ✅ Done | Con unique constraint pending |
| US-019 | VoucherService | ✅ Done | + suspend/reactivate per trading |
| US-020 | CaseEntitlementService | ✅ Done | Auto-break su transfer/trade |
| US-021 | VoucherTransferService | ✅ Done | Con race condition handling |

### Sezione 4: Voucher CRUD & UI
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-022 | Voucher List | ✅ Done | Filtri, search, NO create action |
| US-023 | Voucher Detail | ✅ Done | 8 sezioni (più del PRD) |
| US-024 | Behavioral flags | ✅ Done | Toggle con conferma e audit |
| US-025 | Transfer UI | ✅ Done | Initiate + Cancel actions |
| US-026 | Customer Vouchers tab | ⚠️ Variante | Sezione in Overview, non tab dedicato |
| US-027 | Allocation Vouchers tab | ✅ Done | Tab read-only in ViewAllocation |

### Sezione 5: Case Entitlements
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-028 | CaseEntitlement management | ✅ Done | Resource con list + detail |
| US-029 | Breakability rules | ✅ Done | Auto-break su transfer/trade/redemption |
| US-030 | Customer Case view | ⚠️ Variante | Aggregato in Assets Summary |

### Sezione 6: Transfer & Trading
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-031 | Transfer List globale | ✅ Done | VoucherTransferResource completo |
| US-032 | External trading suspension | ✅ Done | suspend/reactivate + reference |
| US-033 | Trading completion callback | ✅ Done | API endpoint implementato |

### Sezione 7: Edge Cases & Invariants
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-034 | Lineage enforcement | ✅ Done | Immutabilità + test |
| US-035 | Concurrent transfer/lock | ✅ Done | Race condition handling + test |
| US-036 | Duplicate prevention | ✅ Done | sale_reference unique + idempotency |
| US-037 | Quarantine handling | ✅ Done | VoucherAnomalyService + test |

### Sezione 8: Audit & Governance
| US | Titolo | Stato | Note |
|----|--------|-------|------|
| US-038 | Audit log Allocations | ✅ Done | Trait Auditable su tutti i modelli |
| US-039 | Audit log Vouchers | ✅ Done | Event History in ViewVoucher |
| US-040 | Dashboard | ✅ Done | 11+ widget/metriche |

---

## 6. Riepilogo quantitativo

| Categoria | Totale | ✅ Done | ⚠️ Parziale/Variante | ❌ Mancante |
|-----------|--------|---------|----------------------|------------|
| User Stories PRD | 40 | 32 | 3 | 5 |
| Enums PRD | 10 | 8 | — | 2 |
| Feature spec funzionale (extra PRD) | 6 | 0 | 2 | 4 |

### User Stories mancanti (❌):
1. **US-008** — Wizard Step 1 (Bottle SKU selection)
2. **US-009** — Wizard Step 2 (Source & Capacity)
3. **US-010** — Wizard Step 3 (Commercial Constraints)
4. **US-011** — Wizard Step 4 (Advanced/Liquid)
5. **US-012** — Wizard Step 5 (Review & Create)

### User Stories variante (⚠️):
1. **US-002** — 8/10 enums (mancano AllowedCustomerType, AllowedChannel)
2. **US-026** — Vouchers in sezione Overview invece di tab dedicato
3. **US-030** — Case Entitlements aggregati invece di lista dettagliata

### Feature spec funzionale non coperte:
1. **Allocation-derived commercial availability** — Bridge A→S mancante
2. **Active consignment (sell-through)** — Business model non supportato
3. **Member consignment (agency resale)** — Business model non supportato
4. **Early binding / Personalised bottling** — Nessun campo o logica
5. **Composition constraint enforcement** — Solo schema, no business logic
6. **Temporary reservation integration** — Infrastruttura pronta, non collegata a flussi

---

## 7. Raccomandazioni prioritizzate

### P0 — Critiche (bloccano operatività)
1. **Implementare wizard creazione allocation (US-008→US-012)** — Senza questo, nessun utente può creare allocazioni via UI
2. **Implementare form edit allocation** — Necessario per modificare allocazioni in Draft

### P1 — Importanti (gap funzionali)
3. **Creare enums AllowedCustomerType e AllowedChannel** — Allineamento al pattern architetturale
4. **Implementare bridge Allocation→Commercial availability** — Prevenire violazione constraints in Module S
5. **Enforcement composition constraints** — Logica atomicità per vertical cases

### P2 — Medio termine
6. **Integrare temporary reservations nei flussi checkout** — Collegare a Module S
7. **Espandere test coverage** — Aggiungere test per constraints, consumption, services
8. **Arricchire seeder** — Aggiungere voucher, case entitlements, transfers, reservations

### P3 — Futuro
9. **Active consignment business model** — Quando il business lo richiede
10. **Member consignment / Agency resale** — Quando il business lo richiede
11. **Early binding per personalised bottling** — Edge case da attivare on-demand
12. **Customer Vouchers come tab dedicato** — Refinement UX
