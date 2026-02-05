# PRD: Module A — Allocations & Vouchers

## Introduction

Module A è il sistema autoritativo per la gestione della supply vendibile e delle obbligazioni verso i clienti (entitlements) nell'ERP Crurated. Risponde alle domande: "Quanto possiamo vendere indipendentemente dallo stock fisico?" e "Quale obbligo creiamo quando un cliente compra?"

Module A **governa**:
- La definizione di supply vendibile (Allocations) indipendente dall'inventario fisico
- I vincoli commerciali autoritativi (canali, geografie, tipi cliente)
- Le obbligazioni cliente (Vouchers) come diritti di redenzione
- La prevenzione dell'overselling tramite reservations
- Il late binding tra voucher e bottiglie fisiche

Module A **non decide**:
- Quali prodotti esistono (Module 0 - PIM)
- A quali prezzi vendere (Module S - Commercial)
- Se l'inventario fisico esiste (Module B - Inventory)
- Chi può comprare (Module K - Customers)
- Come evadere gli ordini (Module C - Fulfillment)

**Nessuna vendita può procedere senza allocation attiva. Nessun voucher esiste senza conferma esplicita di vendita.**

---

## Goals

- Creare un sistema di allocazioni che permetta di vendere prima che lo stock esista
- Implementare vincoli commerciali autoritativi che Module S deve rispettare
- Gestire vouchers come entitlements atomici (1 voucher = 1 bottiglia o bottle-equivalent)
- Supportare liquid allocations (pre-bottling) con vincoli di condizionamento
- Prevenire overselling tramite temporary reservations
- Gestire Case Entitlements con breakability rules
- Supportare voucher transfers (gifting) e trading suspension
- Preservare allocation lineage come invariante non negoziabile
- Garantire audit trail completo per compliance e governance

---

## User Stories

### Sezione 1: Infrastruttura Allocations

#### US-001: Setup modello Allocation
**Description:** Come Admin, voglio definire il modello base per le allocazioni di supply vendibile a livello Bottle SKU.

**Acceptance Criteria:**
- [ ] Tabella `allocations` con campi: id, uuid, bottle_sku_id (FK), source_type (enum), supply_form (enum), total_quantity, sold_quantity, remaining_quantity, expected_availability_start, expected_availability_end, serialization_required (boolean, default true), status (enum)
- [ ] Soft deletes abilitati
- [ ] remaining_quantity calcolato come total_quantity - sold_quantity
- [ ] Relazione: Allocation belongsTo BottleSku (WineVariant + Format)
- [ ] Vincolo: total_quantity >= sold_quantity sempre
- [ ] Typecheck e lint passano

---

#### US-002: Setup enums per Allocation
**Description:** Come Developer, voglio enums ben definiti per i tipi e stati delle allocazioni.

**Acceptance Criteria:**
- [ ] Enum `AllocationSourceType`: producer_allocation, owned_stock, passive_consignment, third_party_custody
- [ ] Enum `AllocationSupplyForm`: bottled, liquid
- [ ] Enum `AllocationStatus`: draft, active, exhausted, closed
- [ ] Enums in `app/Enums/Allocation/`
- [ ] Typecheck e lint passano

---

#### US-003: Setup modello AllocationConstraint
**Description:** Come Admin, voglio definire i vincoli commerciali autoritativi di un'allocation.

**Acceptance Criteria:**
- [ ] Tabella `allocation_constraints` con: id, allocation_id (FK unique), allowed_channels (JSON array), allowed_geographies (JSON array), allowed_customer_types (JSON array), composition_constraint_group (string nullable), fungibility_exception (boolean default false)
- [ ] Relazione: Allocation hasOne AllocationConstraint
- [ ] AllocationConstraint creato automaticamente con Allocation
- [ ] Vincoli sono editabili solo se Allocation è in Draft
- [ ] Typecheck e lint passano

---

#### US-004: Setup modello LiquidAllocationConstraint
**Description:** Come Admin, voglio vincoli aggiuntivi per le liquid allocations.

**Acceptance Criteria:**
- [ ] Tabella `liquid_allocation_constraints` con: id, allocation_id (FK unique), allowed_bottling_formats (JSON array), allowed_case_configurations (JSON array), bottling_confirmation_deadline (date nullable)
- [ ] Relazione: Allocation hasOne LiquidAllocationConstraint (solo se supply_form = liquid)
- [ ] Validazione: allocation.supply_form deve essere 'liquid'
- [ ] Typecheck e lint passano

---

#### US-005: Setup modello TemporaryReservation
**Description:** Come Developer, voglio un sistema di reservation temporanee per prevenire overselling durante checkout o negoziazioni.

**Acceptance Criteria:**
- [ ] Tabella `temporary_reservations` con: id, uuid, allocation_id (FK), quantity, context_type (enum), context_reference (string nullable), status (enum), expires_at, created_by (FK users nullable)
- [ ] Enum `ReservationContextType`: checkout, negotiation, manual_hold
- [ ] Enum `ReservationStatus`: active, expired, cancelled, converted
- [ ] Job schedulato per espirare reservations oltre expires_at
- [ ] Reservation non consuma allocation, solo "blocca" temporaneamente
- [ ] Typecheck e lint passano

---

#### US-006: AllocationService per gestione allocation
**Description:** Come Developer, voglio un service per centralizzare la logica di allocation.

**Acceptance Criteria:**
- [ ] Service class `AllocationService` in `app/Services/Allocation/`
- [ ] Metodo `activate(Allocation)`: transizione draft → active
- [ ] Metodo `close(Allocation)`: transizione active/exhausted → closed
- [ ] Metodo `consumeAllocation(Allocation, quantity)`: decrementa remaining, incrementa sold
- [ ] Metodo `checkAvailability(Allocation, quantity)`: verifica disponibilità (remaining - active_reservations >= quantity)
- [ ] Metodo `getRemainingAvailable(Allocation)`: remaining - sum(active_reservations.quantity)
- [ ] Validazioni con eccezioni esplicite
- [ ] Typecheck e lint passano

---

### Sezione 2: Allocation CRUD & UI

#### US-007: Allocation List in Filament
**Description:** Come Operator, voglio una lista allocations come entry point operativo del modulo.

**Acceptance Criteria:**
- [ ] AllocationResource in Filament con navigation group "Allocations"
- [ ] Lista con colonne: allocation_id, bottle_sku (wine+vintage+format), supply_form, source_type, status, total_qty, sold_qty, remaining_qty, availability_window, constraint_summary, updated_at
- [ ] Filtri: status, source_type, supply_form, bottle_sku
- [ ] Ricerca per: wine name, producer, bottle SKU, allocation ID
- [ ] Closed allocations nascosti di default (filtro)
- [ ] Indicatore visivo per allocations quasi esaurite (remaining < 10%)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-008: Create Allocation wizard - Step 1 (Bottle SKU)
**Description:** Come Operator, voglio selezionare il Bottle SKU come primo step della creazione allocation.

**Acceptance Criteria:**
- [ ] Wizard step 1: selezione Wine + Vintage + Format (Bottle SKU)
- [ ] Autocomplete search per wine name
- [ ] Mostra solo formati esistenti per la wine variant selezionata
- [ ] Messaggio chiaro: "Allocation always happens at Bottle SKU level"
- [ ] No concetti di sellable SKU o packaging a questo stadio
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-009: Create Allocation wizard - Step 2 (Source & Capacity)
**Description:** Come Operator, voglio definire source type, supply form e quantità.

**Acceptance Criteria:**
- [ ] Step 2 fields: source_type, supply_form, total_quantity, expected_availability_start, expected_availability_end, serialization_required
- [ ] Inline guidance che spiega implicazioni di bottled vs liquid
- [ ] Inline guidance per serialization requirement
- [ ] Validazione: total_quantity > 0
- [ ] Validazione: expected_availability_end >= expected_availability_start (se entrambi presenti)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-010: Create Allocation wizard - Step 3 (Commercial Constraints)
**Description:** Come Operator, voglio definire i vincoli commerciali autoritativi dell'allocation.

**Acceptance Criteria:**
- [ ] Step 3 fields: allowed_channels (multi-select), allowed_geographies (multi-select), allowed_customer_types (multi-select)
- [ ] Opzioni channels: b2c, b2b, private_sales, wholesale, club
- [ ] Opzioni customer_types: retail, trade, private_client, club_member, internal
- [ ] Messaggio prominente: "Constraints are AUTHORITATIVE. Module S must enforce them."
- [ ] Default: tutti i canali/geografie/customer types se non specificato
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-011: Create Allocation wizard - Step 4 (Advanced/Liquid Constraints)
**Description:** Come Operator, voglio definire vincoli avanzati e liquid-specific quando applicabile.

**Acceptance Criteria:**
- [ ] Step 4 collapsed by default, espanso solo se supply_form = liquid
- [ ] Fields avanzati: composition_constraint_group, fungibility_exception
- [ ] Fields liquid-only (visibili solo se supply_form = liquid): allowed_bottling_formats, allowed_case_configurations, bottling_confirmation_deadline
- [ ] Spiegazione inline per composition constraints (vertical cases)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-012: Create Allocation wizard - Step 5 (Review & Create)
**Description:** Come Operator, voglio vedere un riepilogo prima di creare l'allocation.

**Acceptance Criteria:**
- [ ] Step 5: read-only summary di tutti i dati inseriti
- [ ] Mostra warnings informativi (se presenti)
- [ ] Allocation creata in status "draft" by default
- [ ] Messaggio: "Draft allocations cannot be consumed and do not issue vouchers"
- [ ] CTA: "Create as Draft" (primary), "Create and Activate" (secondary, role-based)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-013: Allocation Detail con 6 tabs
**Description:** Come Operator, voglio vedere tutti i dettagli di un'allocation organizzati in tabs.

**Acceptance Criteria:**
- [ ] Tab Overview: read-only control panel con status, quantities, availability window, lineage rule
- [ ] Tab Constraints: vincoli commerciali, editabili solo se Draft
- [ ] Tab Capacity & Consumption: breakdown consumo per sellable SKU, channel, time
- [ ] Tab Reservations: lista temporary reservations con status
- [ ] Tab Vouchers: lista read-only vouchers emessi da questa allocation
- [ ] Tab Audit: timeline eventi immutabile
- [ ] Azioni contestuali role-based: Activate (Draft→Active), Close (Active/Exhausted→Closed)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-014: Allocation status transitions
**Description:** Come Admin, voglio che le transizioni di stato seguano regole precise.

**Acceptance Criteria:**
- [ ] Transizioni valide: draft→active, active→exhausted (automatico quando remaining=0), active→closed, exhausted→closed
- [ ] Transizione draft→active: constraints diventano read-only
- [ ] Transizione invalida genera errore user-friendly
- [ ] Status exhausted settato automaticamente quando remaining_quantity = 0
- [ ] Closed allocation non può essere riaperta (creare nuova allocation)
- [ ] Ogni transizione logga user_id, timestamp
- [ ] Typecheck e lint passano

---

### Sezione 3: Infrastruttura Vouchers

#### US-015: Setup modello Voucher
**Description:** Come Admin, voglio il modello Voucher come entitlement atomico cliente.

**Acceptance Criteria:**
- [ ] Tabella `vouchers` con: id, uuid, customer_id (FK), allocation_id (FK), bottle_sku_id (FK), sellable_sku_id (FK nullable), quantity (always 1), lifecycle_state (enum), tradable (boolean default true), giftable (boolean default true), suspended (boolean default false), sale_reference (string), created_at
- [ ] Soft deletes abilitati
- [ ] Relazione: Voucher belongsTo Customer, Allocation, BottleSku, SellableSku
- [ ] Invariante: quantity = 1 sempre (1 voucher = 1 bottiglia o bottle-equivalent)
- [ ] allocation_id è immutabile dopo creazione (lineage non negoziabile)
- [ ] Typecheck e lint passano

---

#### US-016: Setup enum VoucherLifecycleState
**Description:** Come Developer, voglio un enum per gli stati del ciclo di vita del voucher.

**Acceptance Criteria:**
- [ ] Enum `VoucherLifecycleState`: issued, locked, redeemed, cancelled
- [ ] Stati terminali: redeemed, cancelled (no ulteriori transizioni)
- [ ] Transizioni valide: issued→locked, issued→cancelled, locked→redeemed, locked→issued (unlock)
- [ ] Enum in `app/Enums/Allocation/`
- [ ] Typecheck e lint passano

---

#### US-017: Setup modello CaseEntitlement
**Description:** Come Admin, voglio raggruppare vouchers quando un cliente compra un fixed case.

**Acceptance Criteria:**
- [ ] Tabella `case_entitlements` con: id, uuid, customer_id (FK), sellable_sku_id (FK), status (enum: intact, broken), created_at, broken_at (nullable), broken_reason (string nullable)
- [ ] Relazione: CaseEntitlement hasMany Voucher (tramite pivot o FK su voucher)
- [ ] Campo case_entitlement_id (FK nullable) su vouchers
- [ ] Status passa a "broken" irreversibilmente se un voucher viene trasferito, tradato o redento singolarmente
- [ ] Typecheck e lint passano

---

#### US-018: Setup modello VoucherTransfer
**Description:** Come Admin, voglio tracciare i trasferimenti di voucher tra clienti.

**Acceptance Criteria:**
- [ ] Tabella `voucher_transfers` con: id, voucher_id (FK), from_customer_id (FK), to_customer_id (FK), status (enum), initiated_at, expires_at, accepted_at (nullable), cancelled_at (nullable)
- [ ] Enum `VoucherTransferStatus`: pending, accepted, cancelled, expired
- [ ] Relazione: Voucher hasMany VoucherTransfer
- [ ] Transfer non crea nuovo voucher, cambia solo holder
- [ ] Transfer non consuma allocation
- [ ] Typecheck e lint passano

---

#### US-019: VoucherService per gestione vouchers
**Description:** Come Developer, voglio un service per centralizzare la logica dei voucher.

**Acceptance Criteria:**
- [ ] Service class `VoucherService` in `app/Services/Allocation/`
- [ ] Metodo `issueVouchers(Allocation, Customer, SellableSku, saleReference, quantity)`: crea vouchers e consuma allocation
- [ ] Metodo `lockForFulfillment(Voucher)`: issued → locked
- [ ] Metodo `unlock(Voucher)`: locked → issued
- [ ] Metodo `redeem(Voucher)`: locked → redeemed
- [ ] Metodo `cancel(Voucher)`: issued → cancelled
- [ ] Metodo `suspend(Voucher)`: setta suspended = true
- [ ] Metodo `reactivate(Voucher)`: setta suspended = false
- [ ] Validazioni con eccezioni esplicite per transizioni invalide
- [ ] Typecheck e lint passano

---

#### US-020: CaseEntitlementService per gestione case
**Description:** Come Developer, voglio un service per gestire la logica dei case entitlements.

**Acceptance Criteria:**
- [ ] Service class `CaseEntitlementService` in `app/Services/Allocation/`
- [ ] Metodo `createFromVouchers(array vouchers, Customer, SellableSku)`: crea CaseEntitlement e associa vouchers
- [ ] Metodo `breakEntitlement(CaseEntitlement, reason)`: status → broken, irreversibile
- [ ] Metodo `isIntact(CaseEntitlement)`: verifica che tutti i vouchers siano con stesso holder e non redenti
- [ ] Trigger automatico di break quando un voucher del case viene trasferito/tradato/redento
- [ ] Typecheck e lint passano

---

#### US-021: VoucherTransferService per gifting
**Description:** Come Developer, voglio un service per gestire i trasferimenti voucher.

**Acceptance Criteria:**
- [ ] Service class `VoucherTransferService` in `app/Services/Allocation/`
- [ ] Metodo `initiateTransfer(Voucher, toCustomer, expiresAt)`: crea pending transfer
- [ ] Metodo `acceptTransfer(VoucherTransfer)`: aggiorna voucher.customer_id, status → accepted
- [ ] Metodo `cancelTransfer(VoucherTransfer)`: status → cancelled
- [ ] Metodo `expireTransfers()`: job per expirare pending transfers
- [ ] Validazioni: voucher non locked, non suspended, non già in pending transfer
- [ ] Se voucher in CaseEntitlement, break entitlement on accept
- [ ] Typecheck e lint passano

---

### Sezione 4: Voucher CRUD & UI

#### US-022: Voucher List in Filament
**Description:** Come Operator, voglio una lista vouchers come entry point per gli entitlements cliente.

**Acceptance Criteria:**
- [ ] VoucherResource in Filament con navigation group "Vouchers"
- [ ] Lista con colonne: voucher_id, customer, bottle_sku, sellable_sku, allocation_id, lifecycle_state, flags (badges: tradable, giftable, suspended), created_at
- [ ] Filtri: lifecycle_state, allocation_id, customer, suspended
- [ ] Ricerca per: voucher_id, customer name, wine name, allocation_id
- [ ] Redeemed e cancelled nascosti di default
- [ ] **NO azione Create** - vouchers creati solo da sale confirmation
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-023: Voucher Detail view
**Description:** Come Operator, voglio vedere tutti i dettagli di un voucher.

**Acceptance Criteria:**
- [ ] Header: voucher_id, customer (current holder), lifecycle_state con banner prominente
- [ ] Section 1 - What was sold: sellable_sku, bottle_sku, quantity (=1), case_entitlement (if any) con status
- [ ] Section 2 - Allocation lineage: allocation_id (link), source_type, constraints snapshot, serialization requirement. Messaggio: "Lineage can never be modified"
- [ ] Section 3 - Lifecycle state: stato corrente, diagramma transizioni (read-only)
- [ ] Section 4 - Behavioral flags: tradable, giftable, suspended con toggle (role-based)
- [ ] Section 5 - Transfer context: pending transfers, external trading reference
- [ ] Section 6 - Event history: audit trail immutabile
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-024: Voucher behavioral flags management
**Description:** Come Operator, voglio gestire i flags comportamentali dei voucher.

**Acceptance Criteria:**
- [ ] Toggle tradable, giftable solo se voucher è issued (non locked/redeemed/cancelled)
- [ ] Toggle suspended solo se voucher non è terminale
- [ ] Suspended override altri flags (blocca tutte le operazioni)
- [ ] Ogni toggle richiede conferma e genera audit event
- [ ] Flags non modificabili se voucher è suspended (tranne per unsuspend)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-025: Voucher transfer UI
**Description:** Come Operator, voglio gestire i trasferimenti voucher dall'admin panel.

**Acceptance Criteria:**
- [ ] Azione "Initiate Transfer" visibile solo se voucher è issued, non suspended, non in pending transfer
- [ ] Form: select recipient customer, expiration date
- [ ] Pending transfer visibile in voucher detail con actions: Cancel Transfer
- [ ] Accept Transfer non disponibile da admin (fatto dal recipient nel customer portal)
- [ ] Messaggio chiaro: "Transfers do not create new vouchers or consume allocation"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-026: Customer Vouchers tab
**Description:** Come Operator, voglio vedere i vouchers di un cliente dal Customer Detail.

**Acceptance Criteria:**
- [ ] Tab Vouchers in CustomerResource detail view
- [ ] Lista vouchers del customer con: voucher_id, bottle_sku, lifecycle_state, flags
- [ ] Filtri per lifecycle_state
- [ ] Link a voucher detail
- [ ] Summary: total vouchers, issued, locked, redeemed
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-027: Allocation Vouchers tab (read-only)
**Description:** Come Operator, voglio vedere i vouchers emessi da un'allocation.

**Acceptance Criteria:**
- [ ] Tab Vouchers in AllocationResource già implementato (US-013)
- [ ] Lista read-only: voucher_id, customer, lifecycle_state, created_at
- [ ] Nessuna azione di modifica da questo tab
- [ ] Link a voucher detail
- [ ] Summary: total issued, locked, redeemed, cancelled
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 5: Case Entitlements

#### US-028: CaseEntitlement management
**Description:** Come Operator, voglio vedere e gestire i case entitlements.

**Acceptance Criteria:**
- [ ] CaseEntitlementResource in Filament (lightweight, read-focused)
- [ ] Lista: entitlement_id, customer, sellable_sku, status (intact/broken), vouchers_count, created_at
- [ ] Filtri: status, customer
- [ ] Detail view: vouchers nel case, status, broken_at, broken_reason
- [ ] NO azioni manuali per break (avviene automaticamente)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-029: CaseEntitlement breakability rules
**Description:** Come Developer, voglio che il case si rompa automaticamente secondo le regole.

**Acceptance Criteria:**
- [ ] Case diventa broken quando: un voucher viene trasferito, un voucher viene tradato, un voucher viene redento singolarmente
- [ ] Break è irreversibile
- [ ] broken_reason registra la causa (transfer, trade, partial_redemption)
- [ ] Vouchers rimanenti restano validi ma si comportano come loose bottles
- [ ] Event logged per audit
- [ ] Typecheck e lint passano

---

#### US-030: Customer Case Entitlements view
**Description:** Come Operator, voglio vedere i case entitlements di un cliente.

**Acceptance Criteria:**
- [ ] Sezione Case Entitlements in Customer Detail (o sotto-sezione del tab Vouchers)
- [ ] Lista: entitlement_id, sellable_sku, status, vouchers count
- [ ] Indicatore visivo per INTACT vs BROKEN
- [ ] Link a CaseEntitlement detail
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 6: Transfer & Trading

#### US-031: Transfer List globale
**Description:** Come Operator, voglio vedere tutti i trasferimenti voucher pendenti.

**Acceptance Criteria:**
- [ ] VoucherTransferResource in Filament
- [ ] Lista: transfer_id, voucher_id, from_customer, to_customer, status, initiated_at, expires_at
- [ ] Filtri: status, date range
- [ ] Azioni role-based: Cancel Transfer (su pending)
- [ ] Link a voucher detail
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-032: External trading suspension
**Description:** Come Developer, voglio gestire la sospensione voucher durante trading esterno.

**Acceptance Criteria:**
- [ ] Campo external_trading_reference (string nullable) su vouchers
- [ ] Quando suspended=true e external_trading_reference presente, mostra "Suspended for external trading"
- [ ] API/callback per: suspend_for_trading(voucher, reference), complete_trading(voucher, new_customer)
- [ ] complete_trading aggiorna customer_id e unsuspend, preserva lineage
- [ ] Voucher sospeso non può essere redento, trasferito, o modificato
- [ ] Typecheck e lint passano

---

#### US-033: Trading completion callback
**Description:** Come Developer, voglio un endpoint per ricevere completamento trading esterno.

**Acceptance Criteria:**
- [ ] API endpoint POST /api/vouchers/{voucher}/trading-complete
- [ ] Payload: new_customer_id, trading_reference
- [ ] Validazione: voucher suspended, trading_reference match
- [ ] Azione: customer_id = new_customer_id, suspended = false, external_trading_reference = null
- [ ] Event logged con old/new customer
- [ ] Typecheck e lint passano

---

### Sezione 7: Edge Cases & Invariants

#### US-034: Allocation lineage enforcement
**Description:** Come Developer, voglio che la lineage dell'allocation sia enforced in tutto il sistema.

**Acceptance Criteria:**
- [ ] allocation_id su Voucher è immutabile (no update permesso)
- [ ] Fulfillment (Module C) deve validare che bottiglia fisica appartenga alla stessa allocation lineage
- [ ] Tentativo di fulfill con bottiglia da altra allocation genera errore
- [ ] UI mostra warning prominente: "Not fulfillable outside lineage"
- [ ] Test che verifica immutabilità allocation_id
- [ ] Typecheck e lint passano

---

#### US-035: Concurrent transfer and lock handling
**Description:** Come Developer, voglio gestire correttamente conflitti tra transfer e fulfillment lock.

**Acceptance Criteria:**
- [ ] Se voucher diventa locked mentre transfer è pending, transfer acceptance viene bloccato
- [ ] UI mostra: "Locked during transfer - acceptance blocked"
- [ ] Transfer può essere cancelled
- [ ] Timestamps espliciti per ordinamento eventi
- [ ] Test per scenario race condition
- [ ] Typecheck e lint passano

---

#### US-036: Duplicate voucher prevention (idempotency)
**Description:** Come Developer, voglio prevenire creazione duplicata di vouchers per stesso sale.

**Acceptance Criteria:**
- [ ] Campo sale_reference su vouchers è unique per allocation+customer combination
- [ ] Validazione in VoucherService.issueVouchers che verifica duplicati
- [ ] Se duplicato, return vouchers esistenti invece di crearne nuovi
- [ ] Log warning per attempted duplicate
- [ ] Typecheck e lint passano

---

#### US-037: Voucher without allocation (quarantine)
**Description:** Come Developer, voglio gestire vouchers anomali senza allocation.

**Acceptance Criteria:**
- [ ] Constraint: allocation_id non può essere null (database level)
- [ ] Se data import crea voucher senza allocation, validation failure
- [ ] Processo di quarantine per vouchers problematici (fuori scope normale)
- [ ] UI flag per vouchers "anomali" che richiedono intervento manuale
- [ ] Typecheck e lint passano

---

### Sezione 8: Audit & Governance

#### US-038: Audit log per Allocations
**Description:** Come Compliance Officer, voglio un log immutabile per tutte le modifiche alle allocations.

**Acceptance Criteria:**
- [ ] Trait Auditable applicato a: Allocation, AllocationConstraint, LiquidAllocationConstraint, TemporaryReservation
- [ ] Eventi loggati: creation, status_change, constraint_edit (draft only), quantity_change, close
- [ ] Tab Audit in Allocation Detail con timeline
- [ ] Filtri per tipo evento e date range
- [ ] Audit logs sono immutabili
- [ ] Typecheck e lint passano

---

#### US-039: Audit log per Vouchers
**Description:** Come Compliance Officer, voglio un log immutabile per tutte le modifiche ai vouchers.

**Acceptance Criteria:**
- [ ] Trait Auditable applicato a: Voucher, CaseEntitlement, VoucherTransfer
- [ ] Eventi loggati: issuance, lifecycle_change, flag_change, transfer_initiated, transfer_accepted, suspension, reactivation
- [ ] Section Event History in Voucher Detail con timeline
- [ ] Filtri per tipo evento e date range
- [ ] Audit logs sono immutabili
- [ ] Typecheck e lint passano

---

#### US-040: Allocation & Voucher dashboard
**Description:** Come Operations Manager, voglio una dashboard per monitorare la salute del sistema A&V.

**Acceptance Criteria:**
- [ ] Dashboard page in Filament
- [ ] Widgets allocations: total active, near exhaustion, closed this month
- [ ] Widgets vouchers: total issued, pending redemption, redeemed this month
- [ ] Widget reservations: active count, expired today
- [ ] Widget transfers: pending count, failed transfers
- [ ] Link rapidi a problemi (allocations quasi esaurite, transfers scaduti)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

## Key Invariants (Non-Negotiable Rules)

1. **No voucher without sale confirmation** - Vouchers sono creati solo dopo conferma esplicita di vendita
2. **Temporary reservations do not create commitments** - Reservations sono hold temporanei, non obbligazioni
3. **No overselling beyond allocation** - sold_quantity non può mai superare total_quantity
4. **One voucher = one bottle OR one bottle-equivalent** - quantity è sempre 1
5. **Allocation happens at Bottle SKU level** - Mai a sellable SKU level
6. **Allocation constraints are authoritative** - Module S deve enforcarli, non può overridarli
7. **Voucher-to-bottle binding is always late** - Tranne per personalized bottling
8. **Early binding only for personalized bottling** - Altrimenti forbidden
9. **Allocation lineage is immutable** - Non può mai essere modificata o sostituita
10. **Case breakability is irreversible** - Una volta broken, non può tornare intact
11. **Constraints immutable after activation** - Modifiche richiedono close e nuova allocation

---

## Functional Requirements

- **FR-1:** Allocation rappresenta supply vendibile a Bottle SKU level, indipendente da inventario fisico
- **FR-2:** Voucher rappresenta entitlement atomico cliente (1 voucher = 1 bottiglia)
- **FR-3:** AllocationConstraint definisce vincoli commerciali autoritativi (canali, geografie, customer types)
- **FR-4:** LiquidAllocationConstraint aggiunge vincoli per pre-bottling allocations
- **FR-5:** TemporaryReservation previene overselling senza creare commitment
- **FR-6:** CaseEntitlement raggruppa vouchers per fixed case sales
- **FR-7:** VoucherTransfer traccia gifting mantenendo lineage e provenance
- **FR-8:** Allocation lineage preserved in tutto il ciclo di vita del voucher
- **FR-9:** Late binding è il default; early binding solo per personalized bottling
- **FR-10:** Audit log immutabile per tutte le entità

---

## Non-Goals

- NON gestire prodotti o catalogo (Module 0 - PIM)
- NON gestire pricing o offer exposure (Module S - Commercial)
- NON gestire inventory fisico o serialization (Module B - Inventory)
- NON gestire customer eligibility o blocks (Module K - Customers)
- NON gestire fulfillment o shipping (Module C - Fulfillment)
- NON gestire procurement o inbound (Module D - Procurement)
- NON gestire pagamenti o fatturazione (Module E - Finance)
- NON creare vouchers da admin panel (solo da sale confirmation)
- Customer portal per accept transfer (fuori scope MVP admin)
- Blockchain/NFT integration (fuori scope MVP)

---

## Technical Considerations

### Database Schema Principale

```
allocations
├── id, uuid
├── bottle_sku_id (FK)
├── source_type (enum: producer_allocation, owned_stock, passive_consignment, third_party_custody)
├── supply_form (enum: bottled, liquid)
├── total_quantity
├── sold_quantity
├── remaining_quantity (computed)
├── expected_availability_start
├── expected_availability_end
├── serialization_required (boolean)
├── status (enum: draft, active, exhausted, closed)
├── timestamps, soft_deletes

allocation_constraints
├── id
├── allocation_id (FK unique)
├── allowed_channels (JSON)
├── allowed_geographies (JSON)
├── allowed_customer_types (JSON)
├── composition_constraint_group (string nullable)
├── fungibility_exception (boolean)
├── timestamps

liquid_allocation_constraints
├── id
├── allocation_id (FK unique)
├── allowed_bottling_formats (JSON)
├── allowed_case_configurations (JSON)
├── bottling_confirmation_deadline (date nullable)
├── timestamps

temporary_reservations
├── id, uuid
├── allocation_id (FK)
├── quantity
├── context_type (enum: checkout, negotiation, manual_hold)
├── context_reference (string nullable)
├── status (enum: active, expired, cancelled, converted)
├── expires_at
├── created_by (FK users nullable)
├── timestamps

vouchers
├── id, uuid
├── customer_id (FK)
├── allocation_id (FK, immutable)
├── bottle_sku_id (FK)
├── sellable_sku_id (FK nullable)
├── case_entitlement_id (FK nullable)
├── quantity (always 1)
├── lifecycle_state (enum: issued, locked, redeemed, cancelled)
├── tradable (boolean)
├── giftable (boolean)
├── suspended (boolean)
├── external_trading_reference (string nullable)
├── sale_reference (string)
├── timestamps, soft_deletes

case_entitlements
├── id, uuid
├── customer_id (FK)
├── sellable_sku_id (FK)
├── status (enum: intact, broken)
├── broken_at (nullable)
├── broken_reason (string nullable)
├── timestamps

voucher_transfers
├── id
├── voucher_id (FK)
├── from_customer_id (FK)
├── to_customer_id (FK)
├── status (enum: pending, accepted, cancelled, expired)
├── initiated_at
├── expires_at
├── accepted_at (nullable)
├── cancelled_at (nullable)
├── timestamps
```

### Filament Resources

- `AllocationResource` - CRUD Allocations con 6 tabs (primary operational screen)
- `VoucherResource` - Lista e Detail vouchers (read-heavy, no create)
- `CaseEntitlementResource` - Read-only case management
- `VoucherTransferResource` - Lista transfers globale

### Enums

```php
enum AllocationSourceType: string {
    case ProducerAllocation = 'producer_allocation';
    case OwnedStock = 'owned_stock';
    case PassiveConsignment = 'passive_consignment';
    case ThirdPartyCustody = 'third_party_custody';
}

enum AllocationSupplyForm: string {
    case Bottled = 'bottled';
    case Liquid = 'liquid';
}

enum AllocationStatus: string {
    case Draft = 'draft';
    case Active = 'active';
    case Exhausted = 'exhausted';
    case Closed = 'closed';
}

enum ReservationContextType: string {
    case Checkout = 'checkout';
    case Negotiation = 'negotiation';
    case ManualHold = 'manual_hold';
}

enum ReservationStatus: string {
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Converted = 'converted';
}

enum VoucherLifecycleState: string {
    case Issued = 'issued';
    case Locked = 'locked';
    case Redeemed = 'redeemed';
    case Cancelled = 'cancelled';
}

enum CaseEntitlementStatus: string {
    case Intact = 'intact';
    case Broken = 'broken';
}

enum VoucherTransferStatus: string {
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}

enum AllowedCustomerType: string {
    case Retail = 'retail';
    case Trade = 'trade';
    case PrivateClient = 'private_client';
    case ClubMember = 'club_member';
    case Internal = 'internal';
}

enum AllowedChannel: string {
    case B2C = 'b2c';
    case B2B = 'b2b';
    case PrivateSales = 'private_sales';
    case Wholesale = 'wholesale';
    case Club = 'club';
}
```

### Service Classes

```php
// Allocation management
class AllocationService {
    public function activate(Allocation $allocation): void;
    public function close(Allocation $allocation): void;
    public function consumeAllocation(Allocation $allocation, int $quantity): void;
    public function checkAvailability(Allocation $allocation, int $quantity): bool;
    public function getRemainingAvailable(Allocation $allocation): int;
}

// Voucher management
class VoucherService {
    public function issueVouchers(Allocation $allocation, Customer $customer, ?SellableSku $sku, string $saleRef, int $qty): Collection;
    public function lockForFulfillment(Voucher $voucher): void;
    public function unlock(Voucher $voucher): void;
    public function redeem(Voucher $voucher): void;
    public function cancel(Voucher $voucher): void;
    public function suspend(Voucher $voucher, ?string $tradingRef = null): void;
    public function reactivate(Voucher $voucher): void;
}

// Case entitlement management
class CaseEntitlementService {
    public function createFromVouchers(array $vouchers, Customer $customer, SellableSku $sku): CaseEntitlement;
    public function breakEntitlement(CaseEntitlement $entitlement, string $reason): void;
    public function isIntact(CaseEntitlement $entitlement): bool;
}

// Transfer management
class VoucherTransferService {
    public function initiateTransfer(Voucher $voucher, Customer $toCustomer, Carbon $expiresAt): VoucherTransfer;
    public function acceptTransfer(VoucherTransfer $transfer): void;
    public function cancelTransfer(VoucherTransfer $transfer): void;
    public function expireTransfers(): int; // returns count
}
```

### Struttura Directory

```
app/
├── Enums/
│   └── Allocation/
│       ├── AllocationSourceType.php
│       ├── AllocationSupplyForm.php
│       ├── AllocationStatus.php
│       ├── ReservationContextType.php
│       ├── ReservationStatus.php
│       ├── VoucherLifecycleState.php
│       ├── CaseEntitlementStatus.php
│       ├── VoucherTransferStatus.php
│       ├── AllowedCustomerType.php
│       └── AllowedChannel.php
├── Models/
│   └── Allocation/
│       ├── Allocation.php
│       ├── AllocationConstraint.php
│       ├── LiquidAllocationConstraint.php
│       ├── TemporaryReservation.php
│       ├── Voucher.php
│       ├── CaseEntitlement.php
│       └── VoucherTransfer.php
├── Filament/
│   └── Resources/
│       └── Allocation/
│           ├── AllocationResource.php
│           ├── VoucherResource.php
│           ├── CaseEntitlementResource.php
│           └── VoucherTransferResource.php
├── Services/
│   └── Allocation/
│       ├── AllocationService.php
│       ├── VoucherService.php
│       ├── CaseEntitlementService.php
│       └── VoucherTransferService.php
└── Jobs/
    └── Allocation/
        ├── ExpireReservationsJob.php
        └── ExpireTransfersJob.php
```

---

## Success Metrics

- 100% delle allocations hanno constraints definiti
- 100% dei vouchers hanno allocation_id (lineage tracciata)
- Zero overselling (sold_quantity <= total_quantity sempre)
- 100% delle modifiche hanno audit log
- Reservation expiration < 1 minuto dopo expires_at
- Zero vouchers creati senza sale confirmation

---

## Open Questions

1. Quali sono le regole esatte per i timeout di temporary reservations per contesto?
2. Serve un meccanismo di "force unlock" per admin in casi eccezionali?
3. Come si integra il trading esterno (endpoint, autenticazione)?
4. Servono notifiche quando un voucher viene trasferito?
5. Qual è il processo per vouchers "orfani" da data migration?
6. Serve un dashboard separato per Finance con focus su allocation cost/margin?
