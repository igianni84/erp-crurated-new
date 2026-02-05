# PRD: Module D — Procurement & Inbound

## Introduction

Module D è il sistema autoritativo per la trasformazione degli impegni commerciali in vino fisico nell'ERP Crurated. È il **"motore di esecuzione"** che risponde alla domanda: "Come facciamo che quel vino esista?"

Module D **governa**:
- Le **decisioni di acquisizione** (Procurement Intents) come entità centrale pre-sourcing
- La **traduzione** di allocations e vouchers in azioni di procurement
- I **Purchase Orders** per sourcing contrattuale con ownership transfer
- Le **Bottling Instructions** per liquid products con deadlines enforced
- L'**orchestrazione inbound flows** nelle warehouse
- La **preparazione per serialization** e hand-off a Module B

Module D **non decide**:
- Quali prodotti esistono (Module 0 - PIM)
- Quale supply è vendibile (Module A - Allocations)
- Chi può comprare (Module K - Customers)
- A quali prezzi vendere (Module S - Commercial)
- Come evadere gli ordini (Module C - Fulfillment)
- Come gestire inventory serializzato (Module B - Inventory)
- Come fatturare (Module E - Finance)

**Nessun PO può esistere senza Procurement Intent. Nessun Inbound implica automaticamente ownership. Bottling deadlines sono hard constraints.**

---

## Goals

- Creare Procurement Intents come entità centrale che precede ogni azione di sourcing
- Implementare Purchase Orders per contratti con ownership transfer esplicito
- Gestire Bottling Instructions con deadlines enforced e customer preferences
- Trattare Inbound come fatto fisico che NON implica ownership
- Supportare multipli sourcing models (Purchase, Passive Consignment, Third-party Custody)
- Mantenere separazione netta tra ownership e custody
- Implementare serialization routing con hard blockers
- Fornire Overview Dashboard come control tower per operations
- Garantire tracciabilità completa allocations/vouchers → procurement/inbound
- Preservare audit trail completo per compliance e governance

---

## User Stories

### Sezione 1: Infrastruttura Base

#### US-001: Setup modello ProcurementIntent
**Description:** Come Admin, voglio definire il modello base per i Procurement Intents come entità centrale pre-sourcing.

**Acceptance Criteria:**
- [ ] Tabella `procurement_intents` con campi: id, uuid, product_reference_type (enum: bottle_sku, liquid_product), product_reference_id (morphic FK), quantity (int), trigger_type (enum), sourcing_model (enum), preferred_inbound_location (string nullable), rationale (text nullable), status (enum), approved_at (timestamp nullable), approved_by (FK users nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione morphica: ProcurementIntent belongsTo BottleSku OR LiquidProduct
- [ ] quantity rappresenta bottiglie o bottle-equivalents
- [ ] Vincolo: quantity > 0 sempre
- [ ] Typecheck e lint passano

---

#### US-002: Setup enums per Procurement
**Description:** Come Developer, voglio enums ben definiti per i tipi e stati delle entità procurement.

**Acceptance Criteria:**
- [ ] Enum `ProcurementTriggerType`: voucher_driven, allocation_driven, strategic, contractual
- [ ] Enum `SourcingModel`: purchase, passive_consignment, third_party_custody
- [ ] Enum `ProcurementIntentStatus`: draft, approved, executed, closed
- [ ] Enum `PurchaseOrderStatus`: draft, sent, confirmed, closed
- [ ] Enum `BottlingPreferenceStatus`: pending, partial, complete, defaulted
- [ ] Enum `BottlingInstructionStatus`: draft, active, executed
- [ ] Enum `InboundPackaging`: cases, loose, mixed
- [ ] Enum `OwnershipFlag`: owned, in_custody, pending
- [ ] Enum `InboundStatus`: recorded, routed, completed
- [ ] Enums in `app/Enums/Procurement/`
- [ ] Typecheck e lint passano

---

#### US-003: Setup modello PurchaseOrder
**Description:** Come Admin, voglio definire Purchase Orders per sourcing contrattuale con ownership transfer.

**Acceptance Criteria:**
- [ ] Tabella `purchase_orders` con: id, uuid, procurement_intent_id (FK required NOT NULL), supplier_party_id (FK parties), product_reference_type, product_reference_id, quantity, unit_cost (decimal 12,2), currency (string), incoterms (string nullable), ownership_transfer (boolean), expected_delivery_start (date nullable), expected_delivery_end (date nullable), destination_warehouse (string nullable), serialization_routing_note (text nullable), status (enum)
- [ ] Soft deletes abilitati
- [ ] Relazione: PurchaseOrder belongsTo ProcurementIntent (required)
- [ ] Relazione: PurchaseOrder belongsTo Party (supplier)
- [ ] Invariante: procurement_intent_id NOT NULL (enforced at DB level)
- [ ] Typecheck e lint passano

---

#### US-004: Setup modello BottlingInstruction
**Description:** Come Admin, voglio definire Bottling Instructions per liquid products con deadlines.

**Acceptance Criteria:**
- [ ] Tabella `bottling_instructions` con: id, uuid, procurement_intent_id (FK required), liquid_product_id (FK), bottle_equivalents (int), allowed_formats (JSON array), allowed_case_configurations (JSON array), default_bottling_rule (text nullable), bottling_deadline (date), preference_status (enum), personalised_bottling_required (boolean default false), early_binding_required (boolean default false), delivery_location (string nullable), status (enum), defaults_applied_at (timestamp nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione: BottlingInstruction belongsTo ProcurementIntent
- [ ] Relazione: BottlingInstruction belongsTo LiquidProduct
- [ ] Vincolo: bottling_deadline required (non nullable)
- [ ] Typecheck e lint passano

---

#### US-005: Setup modello Inbound
**Description:** Come Admin, voglio definire Inbound come record di fatto fisico senza implicare ownership.

**Acceptance Criteria:**
- [ ] Tabella `inbounds` con: id, uuid, procurement_intent_id (FK nullable), purchase_order_id (FK nullable), warehouse (string), product_reference_type, product_reference_id, quantity (int), packaging (enum), ownership_flag (enum), received_date (date), condition_notes (text nullable), serialization_required (boolean default true), serialization_location_authorized (string nullable), serialization_routing_rule (text nullable), status (enum), handed_to_module_b (boolean default false), handed_to_module_b_at (timestamp nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione: Inbound belongsTo ProcurementIntent (optional)
- [ ] Relazione: Inbound belongsTo PurchaseOrder (optional)
- [ ] Invariante: Inbound NON implica ownership (ownership_flag esplicito)
- [ ] Typecheck e lint passano

---

#### US-006: Setup modello ProducerSupplierConfig
**Description:** Come Admin, voglio memorizzare configurazioni default per producer/supplier.

**Acceptance Criteria:**
- [ ] Tabella `producer_supplier_configs` con: id, party_id (FK unique), default_bottling_deadline_days (int nullable), allowed_formats (JSON array nullable), serialization_constraints (JSON nullable), notes (text nullable)
- [ ] Relazione: ProducerSupplierConfig belongsTo Party (one-to-one)
- [ ] Config è opzionale per party (non tutti i party sono supplier/producer)
- [ ] Typecheck e lint passano

---

#### US-007: ProcurementService per gestione intents
**Description:** Come Developer, voglio un service per centralizzare la logica dei Procurement Intents.

**Acceptance Criteria:**
- [ ] Service class `ProcurementIntentService` in `app/Services/Procurement/`
- [ ] Metodo `createFromVoucherSale(Voucher $voucher)`: crea Intent draft da voucher
- [ ] Metodo `createFromAllocation(Allocation $allocation, int $quantity)`: crea Intent draft da allocation
- [ ] Metodo `createManual(array $data)`: crea Intent draft manuale (strategic)
- [ ] Metodo `approve(ProcurementIntent $intent)`: draft → approved con validazioni
- [ ] Metodo `markExecuted(ProcurementIntent $intent)`: approved → executed
- [ ] Metodo `close(ProcurementIntent $intent)`: executed → closed con validazione linked objects
- [ ] Metodo `canClose(ProcurementIntent $intent)`: verifica tutti linked objects completed
- [ ] Validazioni con eccezioni esplicite
- [ ] Typecheck e lint passano

---

#### US-008: InboundService per gestione inbound
**Description:** Come Developer, voglio un service per centralizzare la logica degli Inbound.

**Acceptance Criteria:**
- [ ] Service class `InboundService` in `app/Services/Procurement/`
- [ ] Metodo `record(array $data)`: crea Inbound in status recorded
- [ ] Metodo `route(Inbound $inbound, string $location)`: recorded → routed con validazione serialization
- [ ] Metodo `complete(Inbound $inbound)`: routed → completed con validazione ownership clarity
- [ ] Metodo `handOffToModuleB(Inbound $inbound)`: marca handed_to_module_b = true
- [ ] Metodo `validateOwnershipClarity(Inbound $inbound)`: verifica ownership_flag != pending
- [ ] Metodo `validateSerializationRouting(Inbound $inbound)`: verifica location autorizzata
- [ ] Validazioni con eccezioni esplicite
- [ ] Typecheck e lint passano

---

### Sezione 2: Procurement Intents CRUD & UI

#### US-009: Procurement Intent List in Filament
**Description:** Come Operator, voglio una lista Procurement Intents come entry point operativo del modulo.

**Acceptance Criteria:**
- [ ] ProcurementIntentResource in Filament con navigation group "Procurement"
- [ ] Lista con colonne: intent_id, product (wine+vintage+format o liquid), quantity, trigger_type, sourcing_model, preferred_location, status, linked_objects_count (badge), updated_at
- [ ] Filtri: status, trigger_type, sourcing_model
- [ ] Ricerca per: intent_id, product name, wine name
- [ ] Closed intents nascosti di default (filtro)
- [ ] Indicatore visivo per intents senza linked objects (awaiting action)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-010: Create Procurement Intent wizard - Step 1 (Product)
**Description:** Come Operator, voglio selezionare il prodotto come primo step della creazione intent.

**Acceptance Criteria:**
- [ ] Wizard step 1: selezione tipo prodotto (Bottle SKU o Liquid Product)
- [ ] Se Bottle SKU: autocomplete Wine + Vintage + Format
- [ ] Se Liquid Product: autocomplete Wine + Vintage (liquid)
- [ ] Preview: product info, existing allocations count
- [ ] Messaggio: "Procurement Intent represents a decision to source this wine"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-011: Create Procurement Intent wizard - Step 2 (Source & Model)
**Description:** Come Operator, voglio definire trigger type e sourcing model.

**Acceptance Criteria:**
- [ ] Step 2 fields: trigger_type, sourcing_model, quantity
- [ ] Trigger type con guidance: voucher_driven (linked to sale), allocation_driven (pre-emptive), strategic (speculative), contractual (committed)
- [ ] Sourcing model con guidance: purchase (ownership transfer), passive_consignment (custody), third_party_custody (no ownership)
- [ ] Inline explanation per ogni opzione
- [ ] Validazione: quantity > 0
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-012: Create Procurement Intent wizard - Step 3 (Delivery)
**Description:** Come Operator, voglio definire le preferenze di delivery.

**Acceptance Criteria:**
- [ ] Step 3 fields: preferred_inbound_location (select), rationale (textarea)
- [ ] Location options: lista warehouse autorizzate
- [ ] Rationale: note operative per context (optional ma recommended)
- [ ] Preview: serialization constraints per location selezionata
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-013: Create Procurement Intent wizard - Step 4 (Review)
**Description:** Come Operator, voglio vedere un riepilogo prima di creare l'intent.

**Acceptance Criteria:**
- [ ] Step 4: read-only summary di tutti i dati inseriti
- [ ] Mostra warnings se applicabili (es. no existing allocation)
- [ ] Intent creato in status "draft" by default
- [ ] Messaggio: "Draft intents require approval before execution"
- [ ] CTA: "Create as Draft" (primary)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-014: Procurement Intent Detail con 4 tabs
**Description:** Come Operator, voglio vedere tutti i dettagli di un intent organizzati in tabs.

**Acceptance Criteria:**
- [ ] Tab Summary: demand source, rationale, quantities, sourcing model, status, approvals
- [ ] Tab Downstream Execution: linked POs (lista), linked Bottling Instructions (lista), linked Inbound batches (lista)
- [ ] Tab Allocation & Voucher Context: allocation IDs driving this intent (read-only), voucher count if voucher-driven (read-only)
- [ ] Tab Audit: creation reason, approvals, status changes, timeline immutabile
- [ ] Azioni contestuali role-based: Approve (Draft→Approved), Create PO, Create Bottling Instruction, Link Inbound
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-015: Procurement Intent status transitions
**Description:** Come Admin, voglio che le transizioni di stato seguano regole precise.

**Acceptance Criteria:**
- [ ] Transizioni valide: draft→approved, approved→executed, executed→closed
- [ ] Transizione draft→approved: registra approved_at e approved_by
- [ ] Transizione executed→closed: richiede tutti linked objects completed (POs closed, Bottling executed, Inbounds completed)
- [ ] Transizione invalida genera errore user-friendly
- [ ] Ogni transizione logga user_id, timestamp
- [ ] Typecheck e lint passano

---

#### US-016: Auto-create Intent from Voucher sale
**Description:** Come Developer, voglio che un Procurement Intent venga creato automaticamente quando un voucher viene venduto.

**Acceptance Criteria:**
- [ ] Listener su VoucherIssued event (da Module A)
- [ ] Crea ProcurementIntent in status draft con trigger_type = voucher_driven
- [ ] Link a allocation_id e voucher_id in context
- [ ] sourcing_model default basato su allocation.source_type
- [ ] Notification/flag per Ops review
- [ ] Typecheck e lint passano

---

#### US-017: Intent Bulk Approval
**Description:** Come Operator, voglio approvare multiple intents in bulk.

**Acceptance Criteria:**
- [ ] Bulk action "Approve Selected" in Intent List
- [ ] Solo intents in status draft selezionabili
- [ ] Confirmation dialog con count
- [ ] Ogni intent approvato individualmente con proprio audit log
- [ ] Progress indicator durante operazione
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-018: Intent Demand Aggregation view
**Description:** Come Operator, voglio vedere intents aggregati per prodotto per identificare volumi.

**Acceptance Criteria:**
- [ ] Toggle view "Aggregated by Product" in Intent List
- [ ] Raggruppa intents per product_reference_id
- [ ] Mostra: product, total_quantity (sum), intents_count, status breakdown
- [ ] Expand row per vedere intents individuali
- [ ] Utile per decisioni di procurement bulk
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 3: Purchase Orders CRUD & UI

#### US-019: PO List in Filament
**Description:** Come Operator, voglio una lista Purchase Orders come gestione contratti sourcing.

**Acceptance Criteria:**
- [ ] PurchaseOrderResource in Filament con navigation group "Procurement"
- [ ] Lista con colonne: po_id, supplier, product, quantity, unit_cost, currency, ownership_transfer (badge), delivery_window, status, updated_at
- [ ] Filtri: status, supplier, ownership_transfer, delivery_period
- [ ] Ricerca per: po_id, supplier name, product name
- [ ] Closed POs nascosti di default
- [ ] Indicatore visivo per POs con delivery window passato
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-020: Create PO wizard - Step 1 (Intent)
**Description:** Come Operator, devo selezionare un Procurement Intent esistente come prerequisito.

**Acceptance Criteria:**
- [ ] Step 1: procurement_intent_id selection (required)
- [ ] Mostra solo intents in status approved o executed
- [ ] Preview: intent summary (product, quantity, sourcing model)
- [ ] Messaggio prominente: "A PO cannot exist without a Procurement Intent"
- [ ] Validazione: intent.sourcing_model deve essere 'purchase' per ownership_transfer = true
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-021: Create PO wizard - Step 2 (Supplier)
**Description:** Come Operator, voglio selezionare il supplier per il PO.

**Acceptance Criteria:**
- [ ] Step 2: supplier_party_id selection
- [ ] Autocomplete search tra Parties con role supplier/producer
- [ ] Preview: supplier info, ProducerSupplierConfig se esistente
- [ ] Se config esistente, mostra default constraints
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-022: Create PO wizard - Step 3 (Commercial Terms)
**Description:** Come Operator, voglio definire i termini commerciali del PO.

**Acceptance Criteria:**
- [ ] Step 3 fields: quantity, unit_cost, currency, incoterms, ownership_transfer
- [ ] quantity pre-filled da intent.quantity (modificabile)
- [ ] Warning se quantity > intent.quantity
- [ ] ownership_transfer checkbox con explanation: "Check if ownership transfers to us on delivery"
- [ ] Validazione: quantity > 0, unit_cost > 0
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-023: Create PO wizard - Step 4 (Delivery)
**Description:** Come Operator, voglio definire le aspettative di delivery.

**Acceptance Criteria:**
- [ ] Step 4 fields: expected_delivery_start, expected_delivery_end, destination_warehouse, serialization_routing_note
- [ ] destination_warehouse pre-filled da intent.preferred_inbound_location
- [ ] serialization_routing_note per istruzioni speciali (es. "France required")
- [ ] Validazione: expected_delivery_end >= expected_delivery_start
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-024: Create PO wizard - Step 5 (Review)
**Description:** Come Operator, voglio vedere un riepilogo prima di creare il PO.

**Acceptance Criteria:**
- [ ] Step 5: read-only summary di tutti i dati
- [ ] Mostra intent linkato con summary
- [ ] PO creato in status "draft"
- [ ] Messaggio: "Draft POs are not sent to suppliers until status changes to Sent"
- [ ] CTA: "Create as Draft"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-025: PO Detail con 5 tabs
**Description:** Come Operator, voglio vedere tutti i dettagli di un PO organizzati in tabs.

**Acceptance Criteria:**
- [ ] Tab Commercial Terms: supplier, product, quantity, pricing, incoterms, ownership flag
- [ ] Tab Linked Intent: read-only link back, quantity coverage vs intent
- [ ] Tab Delivery Expectations: delivery window, destination warehouse, serialization routing note
- [ ] Tab Inbound Matching: linked inbound batches (may be empty), variance flags (qty/timing)
- [ ] Tab Audit: approval trail, status changes, timeline
- [ ] Azioni contestuali: Mark as Sent (Draft→Sent), Confirm (Sent→Confirmed), Close (Confirmed→Closed)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-026: PO status transitions
**Description:** Come Admin, voglio che le transizioni di stato dei PO seguano regole precise.

**Acceptance Criteria:**
- [ ] Transizioni valide: draft→sent, sent→confirmed, confirmed→closed
- [ ] Transizione sent→confirmed: registra confirmation date
- [ ] Transizione confirmed→closed: può avere variance notes
- [ ] Transizione invalida genera errore user-friendly
- [ ] Ogni transizione logga user_id, timestamp
- [ ] Typecheck e lint passano

---

#### US-027: Inbound Variance tracking
**Description:** Come Operator, voglio vedere varianze tra PO e Inbound.

**Acceptance Criteria:**
- [ ] Campo calculated variance su PO: (inbound_quantity - po_quantity)
- [ ] Badge visivo: "Exact Match", "Over Delivery", "Short Delivery"
- [ ] Filtro in PO List per varianze
- [ ] Alert se variance > 10%
- [ ] Variance non blocca closure (è informativo)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 4: Bottling Instructions CRUD & UI

#### US-028: Bottling Instructions List
**Description:** Come Operator, voglio una lista Bottling Instructions per gestire liquid products.

**Acceptance Criteria:**
- [ ] BottlingInstructionResource in Filament con navigation group "Procurement"
- [ ] Lista con colonne: instruction_id, wine+vintage, bottle_equivalents, allowed_formats (badges), bottling_deadline, preference_status, status, updated_at
- [ ] Filtri: status, preference_status, deadline_range
- [ ] Ricerca per: instruction_id, wine name
- [ ] Indicatore urgenza per deadline < 30 giorni
- [ ] Indicatore per preference_status = pending
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-029: Create Bottling Instruction wizard - Step 1 (Liquid Product)
**Description:** Come Operator, voglio selezionare il liquid product per la bottling instruction.

**Acceptance Criteria:**
- [ ] Step 1: procurement_intent_id selection (required, solo intents con liquid product)
- [ ] Preview: intent summary, liquid product info
- [ ] Auto-fill liquid_product_id e bottle_equivalents da intent
- [ ] Messaggio: "Bottling Instructions manage post-sale bottling decisions"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-030: Create Bottling Instruction wizard - Step 2 (Rules)
**Description:** Come Operator, voglio definire le regole di bottling.

**Acceptance Criteria:**
- [ ] Step 2 fields: allowed_formats (multi-select), allowed_case_configurations (multi-select), default_bottling_rule (textarea)
- [ ] allowed_formats da ProducerSupplierConfig come suggestion
- [ ] default_bottling_rule: "Applied automatically if customer doesn't specify preferences by deadline"
- [ ] Preview: formula plain-language della regola
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-031: Create Bottling Instruction wizard - Step 3 (Personalisation)
**Description:** Come Operator, voglio definire i flags di personalizzazione.

**Acceptance Criteria:**
- [ ] Step 3 fields: bottling_deadline (date required), delivery_location, personalised_bottling_required (boolean), early_binding_required (boolean)
- [ ] bottling_deadline: default da ProducerSupplierConfig.default_bottling_deadline_days se presente
- [ ] early_binding_required: explanation "If true, voucher-bottle binding happens before bottling"
- [ ] Warning prominente: "After deadline, defaults will be applied automatically"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-032: Create Bottling Instruction wizard - Step 4 (Review)
**Description:** Come Operator, voglio vedere un riepilogo con deadline warning.

**Acceptance Criteria:**
- [ ] Step 4: read-only summary di tutti i dati
- [ ] Countdown visivo a deadline
- [ ] Warning se deadline < 30 giorni
- [ ] BottlingInstruction creata in status "draft"
- [ ] CTA: "Create as Draft", "Create and Activate"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-033: Bottling Instruction Detail con 5 tabs
**Description:** Come Operator, voglio vedere tutti i dettagli di una bottling instruction.

**Acceptance Criteria:**
- [ ] Tab Bottling Rules: allowed formats, case configurations, default rule, delivery location
- [ ] Tab Customer Preferences: voucher count, preferences collected count, missing preferences count, countdown to deadline
- [ ] Tab Allocation/Voucher Linkage: source allocations (read-only), voucher batch(es) (read-only)
- [ ] Tab Personalisation Flags: personalised_bottling_required, early_binding_required, binding instruction preview
- [ ] Tab Audit: deadline enforcement events, default application event, status changes
- [ ] Azioni: Activate (Draft→Active), Mark Executed (Active→Executed)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-034: Customer Preference Collection tracking
**Description:** Come Operator, voglio vedere lo stato di raccolta preferenze customer.

**Acceptance Criteria:**
- [ ] Widget in Bottling Instruction Detail: preference collection progress
- [ ] Progress bar: collected / total vouchers
- [ ] Lista vouchers con preference status (collected, pending)
- [ ] Link a customer portal per preference collection (external)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-035: Bottling Deadline enforcement (job schedulato)
**Description:** Come Developer, voglio un job che applichi defaults quando deadline scade.

**Acceptance Criteria:**
- [ ] Job `ApplyBottlingDefaultsJob` in `app/Jobs/Procurement/`
- [ ] Esegue daily
- [ ] Trova BottlingInstructions con: status = active, deadline <= today, preference_status != complete
- [ ] Per ogni instruction: applica default_bottling_rule, setta preference_status = defaulted, registra defaults_applied_at
- [ ] Log per audit
- [ ] Notification a Ops per defaults applicati
- [ ] Typecheck e lint passano

---

#### US-036: BottlingInstructionService
**Description:** Come Developer, voglio un service per centralizzare la logica delle Bottling Instructions.

**Acceptance Criteria:**
- [ ] Service class `BottlingInstructionService` in `app/Services/Procurement/`
- [ ] Metodo `activate(BottlingInstruction $instruction)`: draft → active
- [ ] Metodo `markExecuted(BottlingInstruction $instruction)`: active → executed
- [ ] Metodo `applyDefaults(BottlingInstruction $instruction)`: applica regola default
- [ ] Metodo `updatePreferenceStatus(BottlingInstruction $instruction)`: ricalcola preference_status
- [ ] Metodo `getPreferenceProgress(BottlingInstruction $instruction)`: restituisce collected/total
- [ ] Validazioni con eccezioni esplicite
- [ ] Typecheck e lint passano

---

### Sezione 5: Inbound CRUD & UI

#### US-037: Inbound List in Filament
**Description:** Come Operator, voglio una lista Inbound come record di fatti fisici.

**Acceptance Criteria:**
- [ ] InboundResource in Filament con navigation group "Procurement"
- [ ] Lista con colonne: inbound_id, warehouse, product, quantity, packaging, ownership_flag (badge), received_date, serialization_required, status, updated_at
- [ ] Filtri: status, warehouse, ownership_flag, packaging
- [ ] Ricerca per: inbound_id, product name
- [ ] Completed inbounds nascosti di default
- [ ] Indicatore visivo per ownership_flag = pending (requires attention)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-038: Create Inbound - Step 1 (Physical Receipt)
**Description:** Come Operator, voglio registrare i dettagli fisici dell'inbound.

**Acceptance Criteria:**
- [ ] Step 1 fields: warehouse (select), received_date, quantity, packaging, condition_notes
- [ ] warehouse: lista warehouse autorizzate
- [ ] packaging: cases, loose, mixed
- [ ] condition_notes: textarea per note (damage, etc.)
- [ ] Messaggio: "Inbound records physical arrival - it does NOT imply ownership"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-039: Create Inbound - Step 2 (Sourcing Context)
**Description:** Come Operator, voglio linkare l'inbound al sourcing context.

**Acceptance Criteria:**
- [ ] Step 2 fields: procurement_intent_id (select nullable), purchase_order_id (select nullable), ownership_flag
- [ ] Se PO selezionato, intent auto-filled da PO
- [ ] ownership_flag: owned (we own it), in_custody (we hold but don't own), pending (to be determined)
- [ ] Warning se ownership_flag = pending: "Ownership must be clarified before hand-off to inventory"
- [ ] Se no intent/PO linked, warning "Unlinked inbound requires manual validation"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-040: Create Inbound - Step 3 (Serialization)
**Description:** Come Operator, voglio definire i requisiti di serialization.

**Acceptance Criteria:**
- [ ] Step 3 fields: serialization_required (boolean), serialization_location_authorized (select), serialization_routing_rule (textarea)
- [ ] serialization_location_authorized: lista locations autorizzate per serialization
- [ ] serialization_routing_rule: regole speciali (es. "France only for this wine")
- [ ] Default serialization_required = true
- [ ] Preview: blockers se location non autorizzata
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-041: Create Inbound - Step 4 (Review)
**Description:** Come Operator, voglio vedere un riepilogo prima di creare l'inbound.

**Acceptance Criteria:**
- [ ] Step 4: read-only summary di tutti i dati
- [ ] Warning prominente se ownership_flag = pending
- [ ] Warning se no intent linked
- [ ] Inbound creato in status "recorded"
- [ ] CTA: "Record Inbound"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-042: Inbound Detail con 5 tabs
**Description:** Come Operator, voglio vedere tutti i dettagli di un inbound.

**Acceptance Criteria:**
- [ ] Tab Physical Receipt: warehouse, date, quantity, packaging, condition notes
- [ ] Tab Sourcing Context: linked intent(s), linked PO, ownership status (badge prominente)
- [ ] Tab Serialization Routing: authorized location, current rule, blockers if misrouted
- [ ] Tab Downstream Hand-off: sent to Module B (Yes/No), serialized quantity once available
- [ ] Tab Audit: WMS event references, manual adjustments, status changes
- [ ] Azioni: Route (Recorded→Routed), Complete (Routed→Completed), Hand-off to Module B
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-043: Inbound status transitions
**Description:** Come Admin, voglio che le transizioni di stato degli Inbound seguano regole precise.

**Acceptance Criteria:**
- [ ] Transizioni valide: recorded→routed, routed→completed
- [ ] Transizione recorded→routed: richiede serialization_location_authorized
- [ ] Transizione routed→completed: richiede ownership_flag != pending
- [ ] Transizione invalida genera errore user-friendly con explanation
- [ ] Ogni transizione logga user_id, timestamp
- [ ] Typecheck e lint passano

---

#### US-044: Module B Hand-off
**Description:** Come Operator, voglio passare un inbound completato a Module B per inventory management.

**Acceptance Criteria:**
- [ ] Azione "Hand-off to Module B" disponibile solo se status = completed
- [ ] Verifica: ownership_flag clarified (not pending)
- [ ] Verifica: serialization routing valid
- [ ] Setta handed_to_module_b = true, handed_to_module_b_at = now
- [ ] Event/notification per Module B
- [ ] Azione non reversibile (hand-off è one-way)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-045: Inbound without Intent blocking
**Description:** Come Developer, voglio che inbounds senza intent siano flaggati ma non bloccati.

**Acceptance Criteria:**
- [ ] Inbound può essere creato senza procurement_intent_id (warning, not error)
- [ ] Badge prominente "Unlinked Inbound" in list e detail
- [ ] Filtro per "Unlinked Inbounds" in list
- [ ] Dashboard widget conta unlinked inbounds
- [ ] Unlinked inbounds richiedono review manuale prima di hand-off
- [ ] Typecheck e lint passano

---

### Sezione 6: Suppliers & Producers

#### US-046: Config view in Party Detail
**Description:** Come Operator, voglio vedere la config supplier/producer nel Party Detail.

**Acceptance Criteria:**
- [ ] Tab "Supplier Config" in PartyResource (solo per parties con role supplier/producer)
- [ ] Mostra ProducerSupplierConfig se esistente
- [ ] Fields read-only: default_bottling_deadline_days, allowed_formats, serialization_constraints, notes
- [ ] Link "Edit Config" per modifica
- [ ] Se config non esiste, CTA "Create Config"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-047: Edit ProducerSupplierConfig
**Description:** Come Operator, voglio modificare la configurazione di un supplier/producer.

**Acceptance Criteria:**
- [ ] Form in modal o page separata
- [ ] Fields editabili: default_bottling_deadline_days, allowed_formats, serialization_constraints, notes
- [ ] Validazione: deadline_days > 0 se presente
- [ ] Audit log per ogni modifica
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-048: Supplier List filtered view
**Description:** Come Operator, voglio una vista filtrata di parties che sono supplier/producer.

**Acceptance Criteria:**
- [ ] Pagina "Suppliers & Producers" in navigation Procurement
- [ ] Filtered view di PartyResource per role = supplier OR producer
- [ ] Colonne: party name, role, has_config (badge), default_deadline, last_updated
- [ ] Link a Party Detail con tab Supplier Config
- [ ] Lightweight read-focused view
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-049: ProducerSupplierConfigService
**Description:** Come Developer, voglio un service per gestire le config supplier.

**Acceptance Criteria:**
- [ ] Service class `ProducerSupplierConfigService` in `app/Services/Procurement/`
- [ ] Metodo `getOrCreate(Party $party)`: restituisce config esistente o crea nuova
- [ ] Metodo `update(ProducerSupplierConfig $config, array $data)`: aggiorna con audit
- [ ] Metodo `getDefaultsForProduct(Party $party, $product)`: restituisce defaults applicabili
- [ ] Typecheck e lint passano

---

### Sezione 7: Overview Dashboard

#### US-050: Dashboard landing page
**Description:** Come Operator, voglio una dashboard come control tower per Module D.

**Acceptance Criteria:**
- [ ] Pagina Overview come landing page di Procurement navigation group
- [ ] Layout: 4 widgets principali in grid
- [ ] Non è dove actions happen - è dove priorities are identified
- [ ] Link da ogni tile a filtered list corrispondente
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-051: Widget Demand → Execution Knowability
**Description:** Come Operator, voglio vedere lo stato del demand vs execution.

**Acceptance Criteria:**
- [ ] Widget A: "Demand → Execution"
- [ ] Metriche: Vouchers issued awaiting sourcing (count), Allocation-driven procurement pending (count), Bottling-required liquid demand (count), Inbound overdue vs expected (ratio)
- [ ] Click su metrica apre filtered list
- [ ] Color coding: green (healthy), yellow (attention), red (critical)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-052: Widget Bottling Risk
**Description:** Come Operator, voglio vedere il rischio bottling con deadlines.

**Acceptance Criteria:**
- [ ] Widget B: "Bottling Risk"
- [ ] Metriche: Upcoming deadlines next 30 days (count), 60 days (count), 90 days (count), % preferences collected (progress), Default fallback count (count)
- [ ] Progress bar per preference collection
- [ ] Highlight per deadlines < 14 giorni
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-053: Widget Inbound Status
**Description:** Come Operator, voglio vedere lo status degli inbound.

**Acceptance Criteria:**
- [ ] Widget C: "Inbound Status"
- [ ] Metriche: Expected next 30 days (count), Delayed (count), Awaiting serialization routing (count), Awaiting hand-off (count)
- [ ] Badge color per delayed count
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-054: Widget Exceptions
**Description:** Come Operator, voglio vedere le eccezioni che richiedono attenzione.

**Acceptance Criteria:**
- [ ] Widget D: "Exceptions"
- [ ] Metriche: Inbound without ownership clarity (count), Inbound blocked by missing intent (count), Bottling past deadline (count), PO with delivery variance > 10% (count)
- [ ] Tutti in rosso se count > 0
- [ ] Link diretto a items problematici
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-055: Dashboard quick actions
**Description:** Come Operator, voglio azioni rapide dalla dashboard.

**Acceptance Criteria:**
- [ ] Section "Quick Actions" in dashboard
- [ ] Links: Create Procurement Intent, Record Inbound, View Pending Approvals
- [ ] Links contestuali basati su exceptions (es. "Review 5 unlinked inbounds")
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-056: Dashboard refresh e date range
**Description:** Come Operator, voglio controllare il refresh e date range della dashboard.

**Acceptance Criteria:**
- [ ] Button "Refresh" per aggiornare metriche
- [ ] Date range selector per widgets (default: next 30 days)
- [ ] Auto-refresh ogni 5 minuti (configurable)
- [ ] Last updated timestamp visibile
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 8: Edge Cases & Invariants

#### US-057: Enforce Intent-before-PO invariant
**Description:** Come Developer, voglio che il sistema enforci che PO richieda Intent.

**Acceptance Criteria:**
- [ ] FK constraint: purchase_orders.procurement_intent_id NOT NULL
- [ ] UI: PO creation wizard richiede intent selection come step 1
- [ ] API: validation error se tentativo di creare PO senza intent
- [ ] Messaggio: "A Purchase Order cannot exist without a Procurement Intent"
- [ ] Test che verifica constraint
- [ ] Typecheck e lint passano

---

#### US-058: Enforce Intent-before-Inbound invariant
**Description:** Come Developer, voglio che inbounds senza intent siano flaggati.

**Acceptance Criteria:**
- [ ] Inbound.procurement_intent_id è nullable (non hard constraint)
- [ ] Se null, Inbound marcato con flag "unlinked"
- [ ] Warning prominente in UI durante creazione
- [ ] Dashboard conta unlinked inbounds come exception
- [ ] Review richiesta prima di hand-off se unlinked
- [ ] Typecheck e lint passano

---

#### US-059: Inbound ownership clarity enforcement
**Description:** Come Developer, voglio che ownership sia clarified prima di completion.

**Acceptance Criteria:**
- [ ] Transizione routed→completed bloccata se ownership_flag = pending
- [ ] Error message: "Ownership must be clarified (owned or in_custody) before completing inbound"
- [ ] UI: form per update ownership_flag visibile in detail
- [ ] Hand-off a Module B impossibile con pending ownership
- [ ] Typecheck e lint passano

---

#### US-060: Serialization routing hard blockers
**Description:** Come Developer, voglio che serialization routing sia un hard blocker.

**Acceptance Criteria:**
- [ ] Transizione recorded→routed richiede serialization_location_authorized
- [ ] Se location non in lista autorizzata, blocco con errore
- [ ] Error message: "Serialization location [X] is not authorized for this product"
- [ ] ProducerSupplierConfig.serialization_constraints consultato
- [ ] UI mostra blockers prima di tentare transizione
- [ ] Typecheck e lint passano

---

#### US-061: Bottling deadline auto-default application
**Description:** Come Developer, voglio che defaults siano applicati automaticamente dopo deadline.

**Acceptance Criteria:**
- [ ] Job daily scansiona BottlingInstructions
- [ ] Condizioni: status = active, deadline <= today, preference_status in [pending, partial]
- [ ] Azione: applica default_bottling_rule a vouchers senza preferenza
- [ ] Update: preference_status = defaulted, defaults_applied_at = now
- [ ] Audit log per ogni default application
- [ ] Notification a Ops
- [ ] Typecheck e lint passano

---

#### US-062: Concurrent PO-Inbound mismatch handling
**Description:** Come Developer, voglio gestire varianze tra PO e Inbound.

**Acceptance Criteria:**
- [ ] Inbound può essere linked a PO con quantity diversa
- [ ] Variance calcolata: inbound.quantity - po.quantity
- [ ] Variance > 10% genera warning (non blocco)
- [ ] Badge visivo su PO: "Exact", "Over", "Short"
- [ ] Report per Finance su varianze
- [ ] Typecheck e lint passano

---

#### US-063: Intent closure validation
**Description:** Come Developer, voglio che intent closure richieda tutti linked objects completed.

**Acceptance Criteria:**
- [ ] Transizione executed→closed verifica:
- [ ] Tutti PO linked sono closed
- [ ] Tutte BottlingInstructions linked sono executed
- [ ] Tutti Inbounds linked sono completed
- [ ] Se condizioni non met, errore con lista items pending
- [ ] UI mostra checklist prima di closure attempt
- [ ] Typecheck e lint passano

---

### Sezione 9: Audit & Governance

#### US-064: Audit log per Procurement Intent
**Description:** Come Compliance Officer, voglio un log immutabile per tutte le modifiche ai Procurement Intents.

**Acceptance Criteria:**
- [ ] Trait Auditable applicato a ProcurementIntent
- [ ] Eventi loggati: creation, approval, status_change, linked_object_added, closure
- [ ] Tab Audit in Intent Detail con timeline
- [ ] Filtri per tipo evento e date range
- [ ] Audit logs sono immutabili
- [ ] Typecheck e lint passano

---

#### US-065: Audit log per Purchase Order
**Description:** Come Compliance Officer, voglio un log immutabile per tutte le modifiche ai Purchase Orders.

**Acceptance Criteria:**
- [ ] Trait Auditable applicato a PurchaseOrder
- [ ] Eventi loggati: creation, status_change, term_change, inbound_linked, closure
- [ ] Tab Audit in PO Detail con timeline
- [ ] Include approval events
- [ ] Audit logs sono immutabili
- [ ] Typecheck e lint passano

---

#### US-066: Audit log per Bottling Instruction
**Description:** Come Compliance Officer, voglio un log immutabile per tutte le modifiche alle Bottling Instructions.

**Acceptance Criteria:**
- [ ] Trait Auditable applicato a BottlingInstruction
- [ ] Eventi loggati: creation, activation, preference_update, default_application, execution
- [ ] Tab Audit in Bottling Detail con timeline
- [ ] Defaults application ha audit entry speciale
- [ ] Audit logs sono immutabili
- [ ] Typecheck e lint passano

---

#### US-067: Audit log per Inbound
**Description:** Come Compliance Officer, voglio un log immutabile per tutte le modifiche agli Inbound.

**Acceptance Criteria:**
- [ ] Trait Auditable applicato a Inbound
- [ ] Eventi loggati: recording, routing, ownership_update, completion, hand_off
- [ ] Tab Audit in Inbound Detail con timeline
- [ ] WMS event references inclusi se disponibili
- [ ] Audit logs sono immutabili
- [ ] Typecheck e lint passano

---

#### US-068: Global Module D Audit page
**Description:** Come Compliance Officer, voglio una vista globale di tutti gli audit events di Module D.

**Acceptance Criteria:**
- [ ] Pagina Audit in Filament sotto Procurement
- [ ] Lista unificata tutti gli audit events di Module D
- [ ] Filtri: entity_type (Intent, PO, Bottling, Inbound), event_type, date_range, user
- [ ] Ricerca per: entity_id, user_name
- [ ] Export CSV per compliance
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

## Key Invariants (Non-Negotiable Rules)

1. **Procurement Intent exists before PO** - PO cannot exist without Procurement Intent (FK constraint)
2. **Procurement Intent exists before Bottling** - Bottling Instruction requires Intent
3. **Inbound does NOT imply ownership** - ownership_flag è esplicito e separato da physical receipt
4. **Bottling deadlines are visible and enforced** - Job applica defaults automaticamente dopo deadline
5. **Serialization routing rules are hard blockers** - Routing a location non autorizzata bloccato
6. **Module D cannot issue vouchers** - Solo Module A gestisce vouchers
7. **Module D cannot expose products commercially** - Solo Module S gestisce commercial exposure
8. **Module D cannot move inventory for fulfillment** - Solo Module C gestisce fulfillment
9. **Intent closure requires all linked objects completed** - Validation su POs, Bottling, Inbounds
10. **Ownership clarity required for inbound completion** - ownership_flag = pending blocca completion

---

## Functional Requirements

- **FR-1:** ProcurementIntent rappresenta decisione di sourcing, precede ogni azione operativa
- **FR-2:** PurchaseOrder rappresenta contratto con supplier, richiede Intent linkato
- **FR-3:** BottlingInstruction gestisce post-sale bottling con deadlines enforced
- **FR-4:** Inbound registra fatto fisico senza implicare ownership
- **FR-5:** SourcingModel distingue purchase (ownership transfer) da consignment (custody)
- **FR-6:** OwnershipFlag esplicito su Inbound (owned, in_custody, pending)
- **FR-7:** Serialization routing con hard blockers per locations non autorizzate
- **FR-8:** Dashboard Overview come control tower per priorities
- **FR-9:** ProducerSupplierConfig memorizza defaults per supplier/producer
- **FR-10:** Audit log immutabile per tutte le entità
- **FR-11:** Auto-creation di Intent da voucher sale
- **FR-12:** Auto-application di bottling defaults dopo deadline

---

## Non-Goals

- NON gestire prodotti o catalogo (Module 0 - PIM)
- NON gestire allocations o vouchers (Module A - Allocations)
- NON gestire pricing o offers (Module S - Commercial)
- NON gestire customer eligibility (Module K - Customers)
- NON gestire inventory serializzato (Module B - Inventory)
- NON gestire fulfillment o shipping (Module C - Fulfillment)
- NON gestire fatturazione o pagamenti (Module E - Finance)
- NON emettere vouchers
- NON esporre prodotti commercialmente
- NON muovere inventory per fulfillment
- NON gestire WMS integration details (abstracted)

---

## Technical Considerations

### Database Schema Principale

```
procurement_intents
├── id, uuid
├── product_reference_type (enum: bottle_sku, liquid_product)
├── product_reference_id (morphic FK)
├── quantity
├── trigger_type (enum: voucher_driven, allocation_driven, strategic, contractual)
├── sourcing_model (enum: purchase, passive_consignment, third_party_custody)
├── preferred_inbound_location
├── rationale
├── status (enum: draft, approved, executed, closed)
├── approved_at, approved_by
├── timestamps, soft_deletes

purchase_orders
├── id, uuid
├── procurement_intent_id (FK NOT NULL)
├── supplier_party_id (FK)
├── product_reference_type, product_reference_id
├── quantity
├── unit_cost (decimal)
├── currency
├── incoterms
├── ownership_transfer (boolean)
├── expected_delivery_start, expected_delivery_end
├── destination_warehouse
├── serialization_routing_note
├── status (enum: draft, sent, confirmed, closed)
├── timestamps, soft_deletes

bottling_instructions
├── id, uuid
├── procurement_intent_id (FK NOT NULL)
├── liquid_product_id (FK)
├── bottle_equivalents
├── allowed_formats (JSON)
├── allowed_case_configurations (JSON)
├── default_bottling_rule
├── bottling_deadline (date NOT NULL)
├── preference_status (enum)
├── personalised_bottling_required (boolean)
├── early_binding_required (boolean)
├── delivery_location
├── status (enum)
├── defaults_applied_at
├── timestamps, soft_deletes

inbounds
├── id, uuid
├── procurement_intent_id (FK nullable)
├── purchase_order_id (FK nullable)
├── warehouse
├── product_reference_type, product_reference_id
├── quantity
├── packaging (enum)
├── ownership_flag (enum: owned, in_custody, pending)
├── received_date
├── condition_notes
├── serialization_required (boolean)
├── serialization_location_authorized
├── serialization_routing_rule
├── status (enum: recorded, routed, completed)
├── handed_to_module_b (boolean)
├── handed_to_module_b_at
├── timestamps, soft_deletes

producer_supplier_configs
├── id
├── party_id (FK unique)
├── default_bottling_deadline_days
├── allowed_formats (JSON)
├── serialization_constraints (JSON)
├── notes
├── timestamps
```

### Filament Resources

- `ProcurementIntentResource` - CRUD Intents con 4 tabs (primary operational screen)
- `PurchaseOrderResource` - CRUD POs con 5 tabs
- `BottlingInstructionResource` - CRUD Bottling con 5 tabs
- `InboundResource` - CRUD Inbounds con 5 tabs

### Enums

```php
// Procurement Intent
enum ProcurementTriggerType: string {
    case VoucherDriven = 'voucher_driven';
    case AllocationDriven = 'allocation_driven';
    case Strategic = 'strategic';
    case Contractual = 'contractual';
}

enum SourcingModel: string {
    case Purchase = 'purchase';
    case PassiveConsignment = 'passive_consignment';
    case ThirdPartyCustody = 'third_party_custody';
}

enum ProcurementIntentStatus: string {
    case Draft = 'draft';
    case Approved = 'approved';
    case Executed = 'executed';
    case Closed = 'closed';
}

// Purchase Order
enum PurchaseOrderStatus: string {
    case Draft = 'draft';
    case Sent = 'sent';
    case Confirmed = 'confirmed';
    case Closed = 'closed';
}

// Bottling Instruction
enum BottlingPreferenceStatus: string {
    case Pending = 'pending';
    case Partial = 'partial';
    case Complete = 'complete';
    case Defaulted = 'defaulted';
}

enum BottlingInstructionStatus: string {
    case Draft = 'draft';
    case Active = 'active';
    case Executed = 'executed';
}

// Inbound
enum InboundPackaging: string {
    case Cases = 'cases';
    case Loose = 'loose';
    case Mixed = 'mixed';
}

enum OwnershipFlag: string {
    case Owned = 'owned';
    case InCustody = 'in_custody';
    case Pending = 'pending';
}

enum InboundStatus: string {
    case Recorded = 'recorded';
    case Routed = 'routed';
    case Completed = 'completed';
}
```

### Service Classes

```php
// Procurement Intent management
class ProcurementIntentService {
    public function createFromVoucherSale(Voucher $voucher): ProcurementIntent;
    public function createFromAllocation(Allocation $allocation, int $quantity): ProcurementIntent;
    public function createManual(array $data): ProcurementIntent;
    public function approve(ProcurementIntent $intent): void;
    public function markExecuted(ProcurementIntent $intent): void;
    public function close(ProcurementIntent $intent): void;
    public function canClose(ProcurementIntent $intent): bool;
}

// Purchase Order management
class PurchaseOrderService {
    public function create(ProcurementIntent $intent, array $data): PurchaseOrder;
    public function markSent(PurchaseOrder $po): void;
    public function confirm(PurchaseOrder $po): void;
    public function close(PurchaseOrder $po): void;
    public function calculateVariance(PurchaseOrder $po): int;
}

// Bottling Instruction management
class BottlingInstructionService {
    public function create(ProcurementIntent $intent, array $data): BottlingInstruction;
    public function activate(BottlingInstruction $instruction): void;
    public function markExecuted(BottlingInstruction $instruction): void;
    public function applyDefaults(BottlingInstruction $instruction): void;
    public function updatePreferenceStatus(BottlingInstruction $instruction): void;
    public function getPreferenceProgress(BottlingInstruction $instruction): array;
}

// Inbound management
class InboundService {
    public function record(array $data): Inbound;
    public function route(Inbound $inbound, string $location): void;
    public function complete(Inbound $inbound): void;
    public function handOffToModuleB(Inbound $inbound): void;
    public function validateOwnershipClarity(Inbound $inbound): bool;
    public function validateSerializationRouting(Inbound $inbound): bool;
}

// Producer/Supplier config management
class ProducerSupplierConfigService {
    public function getOrCreate(Party $party): ProducerSupplierConfig;
    public function update(ProducerSupplierConfig $config, array $data): void;
    public function getDefaultsForProduct(Party $party, $product): array;
}
```

### Jobs

```php
// Bottling defaults application
class ApplyBottlingDefaultsJob {
    // Runs daily
    // Finds BottlingInstructions with deadline <= today and preference_status != complete
    // Applies default_bottling_rule to vouchers without preference
    // Updates preference_status = defaulted
}

// Overdue inbound checker
class CheckOverdueInboundsJob {
    // Runs daily
    // Finds Inbounds in recorded/routed status past expected delivery
    // Creates notifications for Ops
}
```

### Struttura Directory

```
app/
├── Enums/
│   └── Procurement/
│       ├── ProcurementTriggerType.php
│       ├── SourcingModel.php
│       ├── ProcurementIntentStatus.php
│       ├── PurchaseOrderStatus.php
│       ├── BottlingPreferenceStatus.php
│       ├── BottlingInstructionStatus.php
│       ├── InboundPackaging.php
│       ├── OwnershipFlag.php
│       └── InboundStatus.php
├── Models/
│   └── Procurement/
│       ├── ProcurementIntent.php
│       ├── PurchaseOrder.php
│       ├── BottlingInstruction.php
│       ├── Inbound.php
│       └── ProducerSupplierConfig.php
├── Filament/
│   └── Resources/
│       └── Procurement/
│           ├── ProcurementIntentResource.php
│           ├── PurchaseOrderResource.php
│           ├── BottlingInstructionResource.php
│           └── InboundResource.php
├── Services/
│   └── Procurement/
│       ├── ProcurementIntentService.php
│       ├── PurchaseOrderService.php
│       ├── BottlingInstructionService.php
│       ├── InboundService.php
│       └── ProducerSupplierConfigService.php
└── Jobs/
    └── Procurement/
        ├── ApplyBottlingDefaultsJob.php
        └── CheckOverdueInboundsJob.php
```

---

## Success Metrics

- 100% POs hanno Procurement Intent linkato (FK constraint)
- 100% Bottling Instructions hanno deadline settata
- Zero Inbound completati senza ownership clarity (ownership_flag != pending)
- 100% modifiche hanno audit log
- Bottling default application < 24h dopo deadline scaduta
- Inbound hand-off a Module B < 48h dopo routing completed
- Zero POs creati senza intent (constraint enforced)
- < 5% unlinked inbounds (exception monitored)

---

## Open Questions

1. **Ownership timeout** - Quanto tempo può restare ownership_flag = pending prima di alert/block automatico?
2. **WMS Integration** - Come integriamo con WMS per inbound (API, import, manual)?
3. **Bottling notifications** - Notifica automatica a customer per deadline imminente?
4. **Variance handling** - Processo specifico per Inbound con quantità diversa da PO?
5. **Strategic procurement** - Come gestire procurement strategico senza allocation/voucher linkage?
6. **Multi-level approval** - Servono approvazioni multi-level per PO di alto valore?
