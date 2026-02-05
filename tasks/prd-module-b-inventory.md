# PRD: Module B — Inventory, Serialization & Provenance

## Introduction

Module B è il sistema autoritativo per la gestione della **realtà fisica** nell'ERP Crurated. È il **"physical system of record"** che risponde alle domande: "Cosa esiste fisicamente?", "Dove si trova?" e "In che stato è?"

Module B **governa**:
- Le **Locations** come container fisici con autorizzazione alla serialization
- Gli **Inbound Batches** come entry point della realtà fisica nel sistema (receipt da Module D)
- Le **Serialized Bottles** come first-class objects con identità unica e provenance
- I **Cases** come container fisici con integrity status
- Gli **Inventory Movements** come ledger append-only di eventi fisici
- L'**Event Consumption** come flow dedicato per consumo inventory non-fulfillment
- La **committed vs free inventory** distinction per allocation lineage
- L'integrazione con **WMS** per eventi fisici e sincronizzazione

Module B **non decide**:
- Quali prodotti esistono (Module 0 - PIM)
- Quale supply è vendibile (Module A - Allocations)
- A quali prezzi vendere (Module S - Commercial)
- Chi può comprare (Module K - Customers)
- Come evadere gli ordini customer (Module C - Fulfillment)
- Come sourcing il vino (Module D - Procurement)
- Come fatturare (Module E - Finance)

**Nessuna bottiglia esiste come individuo prima della serialization. Allocation lineage è immutabile. I movements fisici sono append-only.**

---

## Goals

- Creare un sistema di Locations che distingua warehouse principali, satelliti, consignee e third-party
- Implementare serialization authorization per location con hard blockers
- Gestire Inbound Batches come entry point da Module D con discrepancy handling
- Trasformare quantities non-identificate in Serialized Bottles (first-class objects)
- Preservare allocation lineage come invariante immutabile su ogni bottiglia
- Tracciare Cases come container fisici con integrity status (intact/broken)
- Implementare Inventory Movements come ledger append-only
- Distinguere committed inventory (reserved for vouchers) da free inventory
- Supportare Event Consumption come flow separato da fulfillment
- Integrare NFT provenance minting post-serialization
- Garantire deduplication WMS events
- Preservare audit trail completo per compliance e governance

---

## User Stories

### Sezione 1: Infrastruttura Base

#### US-B001: Setup modello Location
**Description:** Come Admin, voglio definire il modello base per le locations fisiche dove il vino può essere stoccato.

**Acceptance Criteria:**
- [ ] Tabella `locations` con campi: id, uuid, name (string), location_type (enum), country (string), address (text nullable), serialization_authorized (boolean default false), linked_wms_id (string nullable), status (enum), notes (text nullable)
- [ ] Soft deletes abilitati
- [ ] Vincolo: name unique
- [ ] serialization_authorized determina se la location può eseguire serialization
- [ ] Typecheck e lint passano

---

#### US-B002: Setup enums per Inventory
**Description:** Come Developer, voglio enums ben definiti per i tipi e stati delle entità inventory.

**Acceptance Criteria:**
- [ ] Enum `LocationType`: main_warehouse, satellite_warehouse, consignee, third_party_storage, event_location
- [ ] Enum `LocationStatus`: active, inactive, suspended
- [ ] Enum `InboundBatchStatus`: pending_serialization, partially_serialized, fully_serialized, discrepancy
- [ ] Enum `BottleState`: stored, reserved_for_picking, shipped, consumed, destroyed, missing
- [ ] Enum `CaseIntegrityStatus`: intact, broken
- [ ] Enum `MovementType`: internal_transfer, consignment_placement, consignment_return, event_shipment, event_consumption
- [ ] Enum `MovementTrigger`: wms_event, erp_operator, system_automatic
- [ ] Enum `OwnershipType`: crurated_owned, in_custody, third_party_owned
- [ ] Enum `ConsumptionReason`: event_consumption, sampling, damage_writeoff
- [ ] Enum `DiscrepancyResolution`: shortage, overage, damage, other
- [ ] Enums in `app/Enums/Inventory/`
- [ ] Typecheck e lint passano

---

#### US-B003: Setup modello InboundBatch
**Description:** Come Admin, voglio definire Inbound Batch come record di receipt fisico da Module D.

**Acceptance Criteria:**
- [ ] Tabella `inbound_batches` con: id, uuid, source_type (string: producer/supplier/transfer), product_reference_type (string), product_reference_id (morphic FK), allocation_id (FK allocations nullable), procurement_intent_id (FK nullable), quantity_expected (int), quantity_received (int), packaging_type (string), receiving_location_id (FK locations), ownership_type (enum), received_date (date), condition_notes (text nullable), serialization_status (enum), wms_reference_id (string nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione: InboundBatch belongsTo Location (receiving_location)
- [ ] Relazione: InboundBatch belongsTo Allocation (optional)
- [ ] allocation_id preserva allocation lineage per serialization
- [ ] Vincolo: quantity_received >= 0
- [ ] Typecheck e lint passano

---

#### US-B004: Setup modello SerializedBottle
**Description:** Come Admin, voglio definire Serialized Bottle come first-class object con identità unica.

**Acceptance Criteria:**
- [ ] Tabella `serialized_bottles` con: id, uuid, serial_number (string unique), wine_variant_id (FK), format_id (FK), allocation_id (FK allocations), inbound_batch_id (FK), current_location_id (FK locations), case_id (FK cases nullable), ownership_type (enum), custody_holder (string nullable), state (enum), serialized_at (timestamp), serialized_by (FK users nullable), nft_reference (string nullable), nft_minted_at (timestamp nullable)
- [ ] Soft deletes abilitati (ma bottles are never truly deleted)
- [ ] Vincolo: serial_number unique e immutable
- [ ] Relazione: SerializedBottle belongsTo WineVariant
- [ ] Relazione: SerializedBottle belongsTo Format
- [ ] Relazione: SerializedBottle belongsTo Allocation (immutable)
- [ ] Relazione: SerializedBottle belongsTo InboundBatch
- [ ] Relazione: SerializedBottle belongsTo Location (current)
- [ ] Relazione: SerializedBottle belongsTo Case (nullable)
- [ ] allocation_id è IMMUTABLE dopo creazione
- [ ] Typecheck e lint passano

---

#### US-B005: Setup modello Case
**Description:** Come Admin, voglio definire Case come container fisico per bottiglie.

**Acceptance Criteria:**
- [ ] Tabella `cases` con: id, uuid, case_configuration_id (FK), allocation_id (FK allocations), inbound_batch_id (FK nullable), current_location_id (FK locations), is_original (boolean default true), is_breakable (boolean default true), integrity_status (enum), broken_at (timestamp nullable), broken_by (FK users nullable), broken_reason (text nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione: Case hasMany SerializedBottle
- [ ] Relazione: Case belongsTo CaseConfiguration
- [ ] Relazione: Case belongsTo Location (current)
- [ ] integrity_status default = intact
- [ ] Typecheck e lint passano

---

#### US-B006: Setup modello InventoryMovement
**Description:** Come Admin, voglio definire Inventory Movement come record immutabile di eventi fisici.

**Acceptance Criteria:**
- [ ] Tabella `inventory_movements` con: id, uuid, movement_type (enum), trigger (enum), source_location_id (FK locations nullable), destination_location_id (FK locations nullable), custody_changed (boolean default false), reason (text nullable), wms_event_id (string nullable unique), executed_at (timestamp), executed_by (FK users nullable)
- [ ] NO soft deletes - movements sono immutabili
- [ ] Relazione: InventoryMovement hasMany MovementItem
- [ ] wms_event_id unique per deduplication
- [ ] Movements sono append-only (insert only, no update, no delete)
- [ ] Typecheck e lint passano

---

#### US-B007: Setup modello MovementItem
**Description:** Come Admin, voglio definire Movement Item come dettaglio degli items coinvolti in un movement.

**Acceptance Criteria:**
- [ ] Tabella `movement_items` con: id, inventory_movement_id (FK), serialized_bottle_id (FK nullable), case_id (FK nullable), quantity (int default 1), notes (text nullable)
- [ ] NO soft deletes - items sono immutabili
- [ ] Relazione: MovementItem belongsTo InventoryMovement
- [ ] Relazione: MovementItem belongsTo SerializedBottle (nullable)
- [ ] Relazione: MovementItem belongsTo Case (nullable)
- [ ] Vincolo: almeno uno tra serialized_bottle_id e case_id deve essere valorizzato
- [ ] Typecheck e lint passano

---

#### US-B008: Setup modello InventoryException
**Description:** Come Admin, voglio registrare eccezioni inventory per audit trail.

**Acceptance Criteria:**
- [ ] Tabella `inventory_exceptions` con: id, uuid, exception_type (string), serialized_bottle_id (FK nullable), case_id (FK nullable), inbound_batch_id (FK nullable), reason (text), resolution (text nullable), resolved_at (timestamp nullable), resolved_by (FK users nullable), created_by (FK users)
- [ ] Soft deletes abilitati
- [ ] Relazione: InventoryException belongsTo SerializedBottle (nullable)
- [ ] Relazione: InventoryException belongsTo Case (nullable)
- [ ] Relazione: InventoryException belongsTo InboundBatch (nullable)
- [ ] Typecheck e lint passano

---

#### US-B009: InventoryService per gestione inventory
**Description:** Come Developer, voglio un service per centralizzare la logica di inventory.

**Acceptance Criteria:**
- [ ] Service class `InventoryService` in `app/Services/Inventory/`
- [ ] Metodo `getCommittedQuantity(Allocation $allocation)`: ritorna count vouchers non redenti per allocation
- [ ] Metodo `getFreeQuantity(Allocation $allocation)`: ritorna physical bottles - committed quantity
- [ ] Metodo `canConsume(SerializedBottle $bottle)`: verifica se bottle è free (non committed)
- [ ] Metodo `getBottlesAtLocation(Location $location)`: ritorna bottles stored at location
- [ ] Metodo `getBottlesByAllocationLineage(Allocation $allocation)`: ritorna tutte le bottles per allocation
- [ ] Validazioni con eccezioni esplicite
- [ ] Typecheck e lint passano

---

#### US-B010: SerializationService per gestione serialization
**Description:** Come Developer, voglio un service per centralizzare la logica di serialization.

**Acceptance Criteria:**
- [ ] Service class `SerializationService` in `app/Services/Inventory/`
- [ ] Metodo `canSerializeAtLocation(Location $location)`: verifica serialization_authorized
- [ ] Metodo `serializeBatch(InboundBatch $batch, int $quantity, User $operator)`: crea SerializedBottle records
- [ ] Metodo `generateSerialNumber()`: genera serial number unico
- [ ] Metodo `queueNftMinting(SerializedBottle $bottle)`: dispatcha job per NFT minting
- [ ] Metodo `updateBatchSerializationStatus(InboundBatch $batch)`: aggiorna status basato su quantities
- [ ] Invariante: allocation_id propagato da InboundBatch a ogni SerializedBottle
- [ ] Validazioni con eccezioni esplicite
- [ ] Typecheck e lint passano

---

#### US-B011: MovementService per gestione movements
**Description:** Come Developer, voglio un service per centralizzare la logica dei movements.

**Acceptance Criteria:**
- [ ] Service class `MovementService` in `app/Services/Inventory/`
- [ ] Metodo `createMovement(array $data)`: crea InventoryMovement con MovementItems
- [ ] Metodo `isDuplicateWmsEvent(string $wmsEventId)`: verifica se event già processato
- [ ] Metodo `transferBottle(SerializedBottle $bottle, Location $destination)`: crea movement e aggiorna bottle location
- [ ] Metodo `transferCase(Case $case, Location $destination)`: crea movement e aggiorna case + contained bottles location
- [ ] Metodo `recordConsumption(SerializedBottle $bottle, ConsumptionReason $reason)`: crea consumption movement
- [ ] Validazioni con eccezioni esplicite
- [ ] Typecheck e lint passano

---

### Sezione 2: Locations CRUD & UI

#### US-B012: Location List in Filament
**Description:** Come Operator, voglio una lista Locations per gestire i punti fisici di stoccaggio.

**Acceptance Criteria:**
- [ ] LocationResource in Filament con navigation group "Inventory"
- [ ] Lista con colonne: location_name, location_type, country, serialization_authorized (badge), linked_wms, status, stock_summary (count), updated_at
- [ ] Filtri: location_type, country, serialization_authorized, status
- [ ] Ricerca per: location name, country
- [ ] Indicatore visivo per locations con WMS linked
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B013: Location Detail con 4 tabs
**Description:** Come Operator, voglio vedere tutti i dettagli di una location organizzati in tabs.

**Acceptance Criteria:**
- [ ] Tab Overview: stock summary, serialized vs non-serialized quantities, ownership breakdown
- [ ] Tab Inventory: bottles currently stored here (paginated list), cases stored here
- [ ] Tab Inbound/Outbound: recent receipts, transfers in/out (read from movements)
- [ ] Tab WMS Status: connection status, last sync timestamp, error logs (read-only)
- [ ] Indicatore prominente se serialization NOT authorized
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B014: Create/Edit Location
**Description:** Come Admin, voglio creare e modificare locations.

**Acceptance Criteria:**
- [ ] Form fields: name, location_type, country, address, serialization_authorized, linked_wms_id, status, notes
- [ ] Validazione: name unique
- [ ] Warning prominente se si disabilita serialization_authorized su location con pending serialization
- [ ] Audit log per ogni modifica
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 3: Inbound Batches CRUD & UI

#### US-B015: Inbound Batch List in Filament
**Description:** Come Operator, voglio una lista Inbound Batches come entry point della realtà fisica.

**Acceptance Criteria:**
- [ ] InboundBatchResource in Filament con navigation group "Inventory"
- [ ] Lista con colonne: inbound_batch_id, source, product_reference, quantity_expected, quantity_received, packaging, receiving_location, received_date, serialization_status (badge), ownership_type
- [ ] Filtri: serialization_status, receiving_location, ownership_type, received_date range
- [ ] Ricerca per: batch_id, product name, wms_reference
- [ ] Indicatore rosso per batches con discrepancy
- [ ] Indicatore per batches pending serialization
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B016: Inbound Batch Detail con 5 tabs
**Description:** Come Operator, voglio vedere tutti i dettagli di un inbound batch.

**Acceptance Criteria:**
- [ ] Tab Summary: source, sourcing context (procurement_intent link), allocation lineage reference, ownership
- [ ] Tab Quantities: expected vs received, remaining unserialized, delta with explanation
- [ ] Tab Serialization: eligible for serialization (yes/no based on location), serialization history, serialized bottles list
- [ ] Tab Linked Physical Objects: serialized bottles created from this batch, cases created
- [ ] Tab Audit Log: WMS events, operator actions, discrepancy resolutions
- [ ] Azioni contestuali: Start Serialization (if eligible), Resolve Discrepancy
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B017: Discrepancy Resolution flow
**Description:** Come Operator, voglio risolvere discrepanze tra quantità expected e received.

**Acceptance Criteria:**
- [ ] Discrepancy Resolution tab visible solo se quantity_expected != quantity_received
- [ ] Side-by-side view: expected quantity vs WMS reported quantity
- [ ] Operator seleziona resolution reason (shortage, overage, damage, other)
- [ ] Operator può allegare evidence (note, document reference)
- [ ] Resolution crea immutable correction event
- [ ] Original values never overwritten (delta record)
- [ ] Full audit trail preserved
- [ ] Serialization sbloccata dopo resolution
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B018: Manual Inbound Batch creation
**Description:** Come Admin con permessi speciali, voglio creare inbound batches manualmente.

**Acceptance Criteria:**
- [ ] Creazione manuale permission-gated (admin only)
- [ ] Form fields: source_type, product_reference, allocation_id, quantity_expected, quantity_received, packaging_type, receiving_location, ownership_type, received_date, condition_notes
- [ ] Warning prominente: "Manual creation requires audit justification"
- [ ] Mandatory reason field
- [ ] Full audit trail
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 4: Serialization Flow

#### US-B019: Serialization Queue page
**Description:** Come Operator, voglio vedere una coda di batches eligibili per serialization.

**Acceptance Criteria:**
- [ ] Pagina "Serialization Queue" in navigation Inventory
- [ ] Lista batches con: pending_serialization, partially_serialized status
- [ ] Colonne: batch_id, product, quantity_remaining_unserialized, receiving_location, allocation_lineage
- [ ] Filtri: location, date range
- [ ] Mostra solo batches in locations con serialization_authorized = true
- [ ] Link diretto a "Start Serialization"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B020: Serialization action from Inbound Batch
**Description:** Come Operator, voglio avviare la serialization di un batch.

**Acceptance Criteria:**
- [ ] Azione "Start Serialization" in Inbound Batch Detail
- [ ] Pre-check: location.serialization_authorized = true (hard blocker)
- [ ] Pre-check: batch.serialization_status != discrepancy (hard blocker)
- [ ] Form: quantity to serialize (max = remaining unserialized)
- [ ] Confirmation dialog con warning
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B021: Serialization execution
**Description:** Come Developer, voglio che la serialization crei SerializedBottle records.

**Acceptance Criteria:**
- [ ] Per ogni bottiglia serializzata:
  - [ ] Genera serial_number unico
  - [ ] Crea SerializedBottle record
  - [ ] Propaga allocation_id da InboundBatch (IMMUTABLE)
  - [ ] Setta state = stored
  - [ ] Setta current_location_id = batch receiving location
  - [ ] Registra serialized_at, serialized_by
- [ ] Aggiorna InboundBatch.serialization_status
- [ ] Dispatcha MintProvenanceNftJob per ogni bottiglia
- [ ] Audit log completo
- [ ] Typecheck e lint passano

---

#### US-B022: Serialization location blocker
**Description:** Come Developer, voglio che la serialization sia bloccata in locations non autorizzate.

**Acceptance Criteria:**
- [ ] Se location.serialization_authorized = false:
  - [ ] UI: azione "Start Serialization" non visibile
  - [ ] API: validation error con messaggio "Serialization not authorized at this location"
- [ ] Se triggered via WMS event:
  - [ ] Event rejected
  - [ ] Logged as blocked system event
- [ ] Location detail mostra "Serialization not authorized" prominente
- [ ] No override exists
- [ ] Typecheck e lint passano

---

#### US-B023: Partial serialization handling
**Description:** Come Operator, voglio poter serializzare parzialmente un batch.

**Acceptance Criteria:**
- [ ] Serialization quantity può essere < remaining unserialized
- [ ] Batch.serialization_status = partially_serialized se quantity_serialized < quantity_received
- [ ] Batch resta in Serialization Queue fino a completamento
- [ ] Inventory Overview mostra "Inbound wine pending serialization" count
- [ ] Questo è un normal state, non un'eccezione
- [ ] Typecheck e lint passano

---

#### US-B024: MintProvenanceNftJob
**Description:** Come Developer, voglio un job asincrono per mintare NFT provenance.

**Acceptance Criteria:**
- [ ] Job class `MintProvenanceNftJob` in `app/Jobs/Inventory/`
- [ ] Input: SerializedBottle $bottle
- [ ] Azione: chiama blockchain service per minting NFT
- [ ] On success: aggiorna bottle.nft_reference, bottle.nft_minted_at
- [ ] On failure: retry con exponential backoff, max 3 attempts
- [ ] NFT minting è separato da serialization (può avvenire in momento diverso)
- [ ] Typecheck e lint passano

---

### Sezione 5: Serialized Bottles CRUD & UI

#### US-B025: Bottle Registry List
**Description:** Come Operator, voglio una lista canonica di tutte le bottiglie serializzate.

**Acceptance Criteria:**
- [ ] SerializedBottleResource in Filament con navigation group "Inventory"
- [ ] Lista con colonne: serial_number, wine+format, allocation_lineage, current_location, custody_holder, state (badge), serialized_at
- [ ] Filtri: allocation_lineage, location, state, ownership_type
- [ ] Ricerca per: serial_number, wine name
- [ ] Indicatori visivi per state (stored=green, reserved=yellow, shipped=blue, consumed/destroyed=gray)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B026: Bottle Detail Page (read-heavy, audit-grade)
**Description:** Come Operator/Compliance, voglio vedere tutti i dettagli di una bottiglia.

**Acceptance Criteria:**
- [ ] Tab Overview: identity (serial, wine, format), physical attributes, allocation lineage (immutable, prominente)
- [ ] Tab Location & Custody: current location, custody holder, ownership type
- [ ] Tab Provenance: inbound event link, all movements, NFT reference/link
- [ ] Tab Movements: full movement history (read-only ledger)
- [ ] Tab Fulfillment Status (read-only): reserved/shipped (fed from Module C), NO customer identity shown
- [ ] No edit capability on bottle records (immutable after creation)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B027: Mark Bottle as Damaged/Destroyed
**Description:** Come Operator, voglio marcare una bottiglia come danneggiata/distrutta.

**Acceptance Criteria:**
- [ ] Azione "Mark as Damaged" in Bottle Detail
- [ ] Form: confirm physical destruction (checkbox), reason (breakage/leakage/contamination), optional evidence
- [ ] Bottle.state → DESTROYED
- [ ] Inventory quantity reduced
- [ ] Bottle remains visible for audit (never deleted)
- [ ] Provenance remains intact
- [ ] Destroyed bottles cannot be selected by Module C
- [ ] Audit log completo
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B028: Mark Bottle as Missing
**Description:** Come Operator, voglio marcare una bottiglia come missing (es. consignment lost).

**Acceptance Criteria:**
- [ ] Azione "Mark as Missing" in Bottle Detail
- [ ] Form: reason, last known custody, agreement reference (for consignment)
- [ ] Bottle.state → MISSING
- [ ] Inventory reduced
- [ ] Bottle locked from fulfillment
- [ ] Missing bottles remain visible forever
- [ ] Used in loss & compliance reporting
- [ ] Audit log completo
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B029: Mis-serialization correction flow (admin-only)
**Description:** Come Admin, voglio correggere una bottiglia serializzata con dati errati.

**Acceptance Criteria:**
- [ ] Azione "Flag as Mis-serialized" in Bottle Detail (admin only)
- [ ] Original bottle record flagged as MIS_SERIALIZED
- [ ] Original record locked (no further changes)
- [ ] Corrective record created with correct data
- [ ] Both records linked via correction_reference
- [ ] Original error remains visible
- [ ] Provenance integrity preserved (additive corrections)
- [ ] Audit log completo
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 6: Cases CRUD & UI

#### US-B030: Case List in Filament
**Description:** Come Operator, voglio una lista Cases come container fisici.

**Acceptance Criteria:**
- [ ] CaseResource in Filament con navigation group "Inventory"
- [ ] Lista con colonne: case_id, configuration, is_original, is_breakable, integrity_status (badge), location, bottle_count
- [ ] Filtri: integrity_status, location, is_original
- [ ] Ricerca per: case_id
- [ ] Indicatore visivo per cases broken
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B031: Case Detail con 4 tabs
**Description:** Come Operator, voglio vedere tutti i dettagli di un case.

**Acceptance Criteria:**
- [ ] Tab Summary: configuration, is_original, is_breakable, allocation_lineage, integrity status
- [ ] Tab Contained Bottles: list of SerializedBottles in this case
- [ ] Tab Integrity & Handling: broken_at, broken_by, broken_reason (if applicable)
- [ ] Tab Movements: movement history for this case
- [ ] Azione "Break Case" visibile se integrity_status = intact
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B032: Break Case action
**Description:** Come Operator, voglio aprire/rompere un case (es. per inspection, sampling, events).

**Acceptance Criteria:**
- [ ] Azione "Break Case" in Case Detail
- [ ] Pre-check: integrity_status = intact
- [ ] Form: reason for breaking
- [ ] Effetti:
  - [ ] integrity_status → BROKEN
  - [ ] broken_at = now, broken_by = current user
  - [ ] Bottles remain individually tracked
  - [ ] Case no longer eligible for case-based handling
- [ ] Case disappears from "intact case" filters
- [ ] Bottles immediately appear as loose stock
- [ ] Breaking is IRREVERSIBLE
- [ ] Audit log completo
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 7: Inventory Movements

#### US-B033: Movement List in Filament
**Description:** Come Operator, voglio vedere tutti i movements come ledger fisico.

**Acceptance Criteria:**
- [ ] InventoryMovementResource in Filament con navigation group "Inventory"
- [ ] Lista con colonne: movement_id, movement_type, source_location, destination_location, items_count, trigger (badge), executed_at, executed_by
- [ ] Filtri: movement_type, trigger, date range, location
- [ ] Ricerca per: movement_id, wms_event_id
- [ ] Movements sono read-only (no edit, no delete)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B034: Movement Detail
**Description:** Come Operator, voglio vedere i dettagli di un movement.

**Acceptance Criteria:**
- [ ] Summary: type, trigger, source/destination, custody_changed, reason, wms_event_id
- [ ] Items List: all MovementItems con link a bottle/case detail
- [ ] Audit info: executed_at, executed_by
- [ ] Read-only view (movements are immutable)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B035: Create Internal Transfer
**Description:** Come Operator, voglio creare un movement di trasferimento interno.

**Acceptance Criteria:**
- [ ] Azione "Create Transfer" in Inventory section
- [ ] Step 1: Select source location
- [ ] Step 2: Select items (bottles and/or cases) at source location
- [ ] Step 3: Select destination location
- [ ] Step 4: Review and confirm
- [ ] Movement created with type = internal_transfer
- [ ] All selected items: current_location updated
- [ ] Audit log completo
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B036: Create Consignment Placement
**Description:** Come Operator, voglio piazzare inventory in consignment presso un consignee.

**Acceptance Criteria:**
- [ ] Azione "Consignment Placement" in Inventory section
- [ ] Select items (bottles/cases) owned by Crurated
- [ ] Select consignee location
- [ ] Movement created with type = consignment_placement
- [ ] Items: ownership remains Crurated, custody changes to consignee
- [ ] custody_changed = true
- [ ] Audit log completo
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B037: WMS Event deduplication
**Description:** Come Developer, voglio che eventi WMS duplicati siano ignorati.

**Acceptance Criteria:**
- [ ] InventoryMovement.wms_event_id è unique (nullable)
- [ ] Quando WMS event arriva:
  - [ ] Check if wms_event_id exists
  - [ ] If exists: event ignored, logged in audit
  - [ ] If not exists: process event, create movement
- [ ] Operators see clean, deduplicated history
- [ ] No double movements
- [ ] Typecheck e lint passano

---

#### US-B038: UpdateProvenanceOnMovementJob
**Description:** Come Developer, voglio un job per aggiornare provenance on-chain dopo movements.

**Acceptance Criteria:**
- [ ] Job class `UpdateProvenanceOnMovementJob` in `app/Jobs/Inventory/`
- [ ] Input: InventoryMovement $movement
- [ ] Per ogni SerializedBottle coinvolta nel movement:
  - [ ] Call blockchain service per aggiornare provenance
- [ ] On failure: retry con exponential backoff
- [ ] Typecheck e lint passano

---

### Sezione 8: Event Consumption

#### US-B039: Event Consumption page
**Description:** Come Operator, voglio un flow dedicato per consumare inventory per eventi.

**Acceptance Criteria:**
- [ ] Pagina "Event Consumption" in navigation Inventory
- [ ] Step 1: Select event location
- [ ] Step 2: Select event reference (optional text field)
- [ ] Step 3: Select bottles/cases to consume
  - [ ] Pre-filter: only owned stock (crurated_owned)
  - [ ] Pre-filter: only state = stored
  - [ ] Committed bottles BLOCKED (see US-B047)
- [ ] Step 4: Confirm consumption reason = EVENT_CONSUMPTION
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B040: Event Consumption execution
**Description:** Come Developer, voglio che event consumption riduca inventory correttamente.

**Acceptance Criteria:**
- [ ] Bottles marked as CONSUMED at confirmation
- [ ] Cases opened for events marked as BROKEN
- [ ] Movement created with type = event_consumption
- [ ] Physical inventory reduced
- [ ] Immutable consumption record created
- [ ] Consumed bottles:
  - [ ] Never reappear in available inventory
  - [ ] Never bind to vouchers
  - [ ] Remain visible for audit
- [ ] Typecheck e lint passano

---

#### US-B041: Committed inventory protection in consumption
**Description:** Come Developer, voglio che committed inventory sia protetto dal consumption.

**Acceptance Criteria:**
- [ ] Selection step blocks bottles where state is committed for vouchers
- [ ] Inline warning: "This bottle is reserved for customer fulfillment"
- [ ] Blocking è based on: InventoryService.canConsume() = false
- [ ] Override per special permission (exceptional, see US-B047)
- [ ] Typecheck e lint passano

---

#### US-B042: SyncCommittedInventoryJob
**Description:** Come Developer, voglio un job per sincronizzare committed quantities da Module A.

**Acceptance Criteria:**
- [ ] Job class `SyncCommittedInventoryJob` in `app/Jobs/Inventory/`
- [ ] Runs periodically (configurable, default every hour)
- [ ] Per ogni Allocation:
  - [ ] Count unredeemed vouchers
  - [ ] Cache committed_quantity
- [ ] InventoryService uses cached value for performance
- [ ] Typecheck e lint passano

---

### Sezione 9: Dashboard Overview

#### US-B043: Inventory Overview landing page
**Description:** Come Operator, voglio una dashboard come control tower per Module B.

**Acceptance Criteria:**
- [ ] Pagina "Inventory Overview" come landing page di Inventory navigation group
- [ ] Layout: 4 widget principali in grid
- [ ] Dashboard è read-only, non transactional
- [ ] Links da ogni widget a filtered list corrispondente
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B044: Widget Global KPIs
**Description:** Come Operator, voglio vedere KPIs globali inventory.

**Acceptance Criteria:**
- [ ] Widget A: "Global Inventory KPIs"
- [ ] Metriche:
  - [ ] Total serialized bottles (count)
  - [ ] Unserialized inbound quantities (sum)
  - [ ] Bottles by state breakdown (stored/reserved/shipped)
  - [ ] Committed vs free quantities
- [ ] Color coding per criticità
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B045: Widget Inventory by Location
**Description:** Come Operator, voglio vedere inventory breakdown per location.

**Acceptance Criteria:**
- [ ] Widget B: "Inventory by Location"
- [ ] Top locations by bottle count
- [ ] Warehouse vs consignee breakdown
- [ ] Click su location apre filtered bottle list
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B046: Widget Alerts & Exceptions
**Description:** Come Operator, voglio vedere alerts che richiedono attenzione.

**Acceptance Criteria:**
- [ ] Widget C: "Alerts & Exceptions"
- [ ] Metriche:
  - [ ] Serialization pending (count)
  - [ ] Inbound batches with discrepancy (count)
  - [ ] Committed inventory at risk (free < 10% of committed)
  - [ ] WMS sync errors (count)
- [ ] Tutti alerts in rosso se count > 0
- [ ] Link diretto a items problematici
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 10: Edge Cases & Invariants

#### US-B047: Committed inventory consumption override (exceptional)
**Description:** Come Admin, voglio un flow eccezionale per consumare inventory committed.

**Acceptance Criteria:**
- [ ] Se user ha special permission AND attempts to consume committed bottle:
  - [ ] Explicit justification required (mandatory text)
  - [ ] Creates InventoryException record
  - [ ] Flagged for finance & ops review
- [ ] Flow è intentionally painful (multiple confirmations)
- [ ] Full audit trail
- [ ] Questo è un'eccezione, non una normal operation
- [ ] Typecheck e lint passano

---

#### US-B048: Allocation lineage substitution blocker
**Description:** Come Developer, voglio che substitution tra allocation lineages sia hard blocked.

**Acceptance Criteria:**
- [ ] System blocks any attempt to substitute bottles across allocations
- [ ] Error message: "Allocation lineage mismatch. Substitution not allowed."
- [ ] Allocation lineage always shown prominently in:
  - [ ] Bottle list
  - [ ] Bottle picker (in Module C)
  - [ ] All inventory views
- [ ] No hidden overrides exist in Module B
- [ ] Typecheck e lint passano

---

#### US-B049: Serial number immutability enforcement
**Description:** Come Developer, voglio che serial numbers siano immutabili.

**Acceptance Criteria:**
- [ ] serial_number field ha DB constraint: unique
- [ ] No update allowed on serial_number after creation
- [ ] Model accessor blocks any attempt to modify
- [ ] Mis-serialization handled via correction flow (US-B029), not edit
- [ ] Typecheck e lint passano

---

#### US-B050: Allocation lineage immutability enforcement
**Description:** Come Developer, voglio che allocation_id su bottles sia immutabile.

**Acceptance Criteria:**
- [ ] allocation_id field on SerializedBottle is NOT NULL
- [ ] No update allowed on allocation_id after creation
- [ ] Model accessor blocks any attempt to modify
- [ ] Propagated from InboundBatch at serialization time
- [ ] Typecheck e lint passano

---

#### US-B051: Movement append-only enforcement
**Description:** Come Developer, voglio che movements siano append-only.

**Acceptance Criteria:**
- [ ] InventoryMovement model has no update/delete methods
- [ ] DB table has no soft_deletes
- [ ] Any correction requires new compensating movement
- [ ] Original movements never modified
- [ ] Full audit trail preserved
- [ ] Typecheck e lint passano

---

#### US-B052: Case breakability irreversibility
**Description:** Come Developer, voglio che il breaking di un case sia irreversibile.

**Acceptance Criteria:**
- [ ] Case.integrity_status = BROKEN cannot revert to INTACT
- [ ] No "unbreak" action exists
- [ ] Model blocks any attempt to change BROKEN → INTACT
- [ ] Broken cases remain in system for audit
- [ ] Typecheck e lint passano

---

### Sezione 11: NFT & Provenance

#### US-B053: NFT reference display in Bottle Detail
**Description:** Come Operator, voglio vedere la reference NFT nella bottle detail.

**Acceptance Criteria:**
- [ ] Tab Provenance in Bottle Detail mostra:
  - [ ] NFT reference (if minted)
  - [ ] NFT minted_at timestamp
  - [ ] Link to blockchain explorer
- [ ] Se NFT not yet minted: mostra "Pending" badge
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-B054: Provenance timeline view
**Description:** Come Operator/Customer, voglio vedere la timeline completa di provenance.

**Acceptance Criteria:**
- [ ] Timeline in Bottle Detail Tab Provenance:
  - [ ] Custody with producer (inbound event)
  - [ ] Transfer to warehouse (serialization)
  - [ ] All subsequent movements
  - [ ] Shipment events (if shipped)
- [ ] Timeline alimentata da InventoryMovements
- [ ] Read-only, append-only presentation
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 12: Audit & Governance

#### US-B055: Audit log per SerializedBottle
**Description:** Come Compliance Officer, voglio un log immutabile per tutte le modifiche alle bottles.

**Acceptance Criteria:**
- [ ] Trait Auditable applicato a SerializedBottle
- [ ] Eventi loggati: creation (serialization), state_change, location_change, custody_change, destruction, missing
- [ ] Tab Audit in Bottle Detail con timeline
- [ ] Audit logs sono immutabili
- [ ] Typecheck e lint passano

---

#### US-B056: Audit log per Case
**Description:** Come Compliance Officer, voglio un log immutabile per tutte le modifiche ai cases.

**Acceptance Criteria:**
- [ ] Trait Auditable applicato a Case
- [ ] Eventi loggati: creation, location_change, breaking, bottle_added, bottle_removed
- [ ] Tab Audit in Case Detail con timeline
- [ ] Audit logs sono immutabili
- [ ] Typecheck e lint passano

---

#### US-B057: Audit log per InboundBatch
**Description:** Come Compliance Officer, voglio un log immutabile per tutte le modifiche agli inbound batches.

**Acceptance Criteria:**
- [ ] Trait Auditable applicato a InboundBatch
- [ ] Eventi loggati: creation, quantity_update, discrepancy_flagged, discrepancy_resolved, serialization_started, serialization_completed
- [ ] Tab Audit in Inbound Batch Detail con timeline
- [ ] WMS event references inclusi
- [ ] Audit logs sono immutabili
- [ ] Typecheck e lint passano

---

#### US-B058: Global Module B Audit page
**Description:** Come Compliance Officer, voglio una vista globale di tutti gli audit events di Module B.

**Acceptance Criteria:**
- [ ] Pagina Audit in Filament sotto Inventory
- [ ] Lista unificata tutti gli audit events di Module B
- [ ] Filtri: entity_type (Bottle, Case, InboundBatch, Movement), event_type, date_range, user, location
- [ ] Ricerca per: entity_id, serial_number, user_name
- [ ] Export CSV per compliance
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

## Key Invariants (Non-Negotiable Rules)

1. **Individual bottles exist ONLY after serialization** - Prima della serialization, il vino esiste come quantity non-identificata
2. **Serial numbers are immutable** - Una volta assegnati, non possono essere modificati
3. **Allocation lineage is immutable on physical inventory** - allocation_id su bottle non può cambiare
4. **Bottles from different allocations NEVER substitutable** - Hard block su qualsiasi tentativo
5. **Physical movements are append-only** - No update, no delete su movements
6. **Committed inventory is protected** - Free quantity non può scendere sotto zero
7. **Cases breakability is irreversible** - BROKEN non può tornare INTACT
8. **WMS events are deduplicated** - wms_event_id unique, duplicates ignored
9. **Commercial abstractions never leak into inventory views** - No pricing, vouchers, customers in Module B
10. **Serialization only at authorized locations** - Hard blocker, no override

---

## Functional Requirements

- **FR-1:** Location rappresenta punto fisico con serialization authorization
- **FR-2:** InboundBatch è entry point da Module D, preserva allocation lineage
- **FR-3:** SerializedBottle è first-class object creato solo dopo serialization
- **FR-4:** Case è container con integrity status, non sostituisce bottle tracking
- **FR-5:** InventoryMovement è ledger append-only di eventi fisici
- **FR-6:** Committed vs free inventory distinction per allocation lineage
- **FR-7:** Event consumption è flow separato da fulfillment, protegge committed inventory
- **FR-8:** NFT provenance minting è asincrono post-serialization
- **FR-9:** WMS integration con deduplication events
- **FR-10:** Dashboard Overview come control tower read-only
- **FR-11:** Discrepancy handling per quantity mismatch
- **FR-12:** Audit log immutabile per tutte le entità

---

## Non-Goals

- NON gestire prodotti o catalogo (Module 0 - PIM)
- NON gestire allocations o vouchers (Module A - Allocations)
- NON gestire pricing o offers (Module S - Commercial)
- NON gestire customer eligibility (Module K - Customers)
- NON gestire fulfillment o shipping customer (Module C - Fulfillment)
- NON gestire procurement o inbound sourcing (Module D - Procurement)
- NON gestire fatturazione o pagamenti (Module E - Finance)
- NON decidere quali bottles assegnare a quali vouchers (Module C)
- NON mostrare customer identity in inventory views
- NON permettere early binding bottle-voucher
- NON gestire WMS integration details (abstracted)

---

## Technical Considerations

### Database Schema Principale

```
locations
├── id, uuid
├── name (string unique)
├── location_type (enum)
├── country (string)
├── address (text nullable)
├── serialization_authorized (boolean)
├── linked_wms_id (string nullable)
├── status (enum)
├── notes (text nullable)
├── timestamps, soft_deletes

inbound_batches
├── id, uuid
├── source_type (string)
├── product_reference_type, product_reference_id
├── allocation_id (FK nullable)
├── procurement_intent_id (FK nullable)
├── quantity_expected, quantity_received
├── packaging_type
├── receiving_location_id (FK)
├── ownership_type (enum)
├── received_date
├── condition_notes (text nullable)
├── serialization_status (enum)
├── wms_reference_id (string nullable)
├── timestamps, soft_deletes

serialized_bottles
├── id, uuid
├── serial_number (string unique)
├── wine_variant_id (FK)
├── format_id (FK)
├── allocation_id (FK NOT NULL, IMMUTABLE)
├── inbound_batch_id (FK)
├── current_location_id (FK)
├── case_id (FK nullable)
├── ownership_type (enum)
├── custody_holder (string nullable)
├── state (enum)
├── serialized_at, serialized_by
├── nft_reference (string nullable)
├── nft_minted_at (timestamp nullable)
├── timestamps, soft_deletes

cases
├── id, uuid
├── case_configuration_id (FK)
├── allocation_id (FK)
├── inbound_batch_id (FK nullable)
├── current_location_id (FK)
├── is_original (boolean)
├── is_breakable (boolean)
├── integrity_status (enum)
├── broken_at, broken_by, broken_reason
├── timestamps, soft_deletes

inventory_movements (NO SOFT DELETES)
├── id, uuid
├── movement_type (enum)
├── trigger (enum)
├── source_location_id (FK nullable)
├── destination_location_id (FK nullable)
├── custody_changed (boolean)
├── reason (text nullable)
├── wms_event_id (string unique nullable)
├── executed_at, executed_by

movement_items (NO SOFT DELETES)
├── id
├── inventory_movement_id (FK)
├── serialized_bottle_id (FK nullable)
├── case_id (FK nullable)
├── quantity
├── notes (text nullable)

inventory_exceptions
├── id, uuid
├── exception_type
├── serialized_bottle_id (FK nullable)
├── case_id (FK nullable)
├── inbound_batch_id (FK nullable)
├── reason, resolution
├── resolved_at, resolved_by
├── created_by
├── timestamps, soft_deletes
```

### Filament Resources

- `LocationResource` - CRUD Locations con 4 tabs
- `InboundBatchResource` - CRUD Inbound Batches con 5 tabs
- `SerializedBottleResource` - Read-heavy Bottle Registry con 5 tabs
- `CaseResource` - CRUD Cases con 4 tabs
- `InventoryMovementResource` - Read-only Movement Ledger

### Enums

```php
// Location
enum LocationType: string {
    case MainWarehouse = 'main_warehouse';
    case SatelliteWarehouse = 'satellite_warehouse';
    case Consignee = 'consignee';
    case ThirdPartyStorage = 'third_party_storage';
    case EventLocation = 'event_location';
}

enum LocationStatus: string {
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}

// Inbound Batch
enum InboundBatchStatus: string {
    case PendingSerialization = 'pending_serialization';
    case PartiallySerialized = 'partially_serialized';
    case FullySerialized = 'fully_serialized';
    case Discrepancy = 'discrepancy';
}

// Serialized Bottle
enum BottleState: string {
    case Stored = 'stored';
    case ReservedForPicking = 'reserved_for_picking';
    case Shipped = 'shipped';
    case Consumed = 'consumed';
    case Destroyed = 'destroyed';
    case Missing = 'missing';
}

// Case
enum CaseIntegrityStatus: string {
    case Intact = 'intact';
    case Broken = 'broken';
}

// Movement
enum MovementType: string {
    case InternalTransfer = 'internal_transfer';
    case ConsignmentPlacement = 'consignment_placement';
    case ConsignmentReturn = 'consignment_return';
    case EventShipment = 'event_shipment';
    case EventConsumption = 'event_consumption';
}

enum MovementTrigger: string {
    case WmsEvent = 'wms_event';
    case ErpOperator = 'erp_operator';
    case SystemAutomatic = 'system_automatic';
}

// Ownership
enum OwnershipType: string {
    case CuratedOwned = 'crurated_owned';
    case InCustody = 'in_custody';
    case ThirdPartyOwned = 'third_party_owned';
}

// Consumption
enum ConsumptionReason: string {
    case EventConsumption = 'event_consumption';
    case Sampling = 'sampling';
    case DamageWriteoff = 'damage_writeoff';
}

// Discrepancy
enum DiscrepancyResolution: string {
    case Shortage = 'shortage';
    case Overage = 'overage';
    case Damage = 'damage';
    case Other = 'other';
}
```

### Service Classes

```php
// Inventory management
class InventoryService {
    public function getCommittedQuantity(Allocation $allocation): int;
    public function getFreeQuantity(Allocation $allocation): int;
    public function canConsume(SerializedBottle $bottle): bool;
    public function getBottlesAtLocation(Location $location): Collection;
    public function getBottlesByAllocationLineage(Allocation $allocation): Collection;
}

// Serialization management
class SerializationService {
    public function canSerializeAtLocation(Location $location): bool;
    public function serializeBatch(InboundBatch $batch, int $quantity, User $operator): Collection;
    public function generateSerialNumber(): string;
    public function queueNftMinting(SerializedBottle $bottle): void;
    public function updateBatchSerializationStatus(InboundBatch $batch): void;
}

// Movement management
class MovementService {
    public function createMovement(array $data): InventoryMovement;
    public function isDuplicateWmsEvent(string $wmsEventId): bool;
    public function transferBottle(SerializedBottle $bottle, Location $destination): InventoryMovement;
    public function transferCase(Case $case, Location $destination): InventoryMovement;
    public function recordConsumption(SerializedBottle $bottle, ConsumptionReason $reason): InventoryMovement;
}
```

### Jobs

```php
// NFT minting after serialization
class MintProvenanceNftJob {
    // Input: SerializedBottle
    // Calls blockchain service
    // Updates nft_reference, nft_minted_at
    // Retry with exponential backoff
}

// Provenance update on movements
class UpdateProvenanceOnMovementJob {
    // Input: InventoryMovement
    // Updates blockchain for each bottle in movement
}

// Sync committed inventory from Module A
class SyncCommittedInventoryJob {
    // Runs hourly
    // Counts unredeemed vouchers per allocation
    // Caches committed_quantity for InventoryService
}
```

### Struttura Directory

```
app/
├── Enums/
│   └── Inventory/
│       ├── LocationType.php
│       ├── LocationStatus.php
│       ├── InboundBatchStatus.php
│       ├── BottleState.php
│       ├── CaseIntegrityStatus.php
│       ├── MovementType.php
│       ├── MovementTrigger.php
│       ├── OwnershipType.php
│       ├── ConsumptionReason.php
│       └── DiscrepancyResolution.php
├── Models/
│   └── Inventory/
│       ├── Location.php
│       ├── InboundBatch.php
│       ├── SerializedBottle.php
│       ├── Case.php
│       ├── InventoryMovement.php
│       ├── MovementItem.php
│       └── InventoryException.php
├── Filament/
│   └── Resources/
│       └── Inventory/
│           ├── LocationResource.php
│           ├── InboundBatchResource.php
│           ├── SerializedBottleResource.php
│           ├── CaseResource.php
│           └── InventoryMovementResource.php
├── Services/
│   └── Inventory/
│       ├── InventoryService.php
│       ├── SerializationService.php
│       └── MovementService.php
└── Jobs/
    └── Inventory/
        ├── MintProvenanceNftJob.php
        ├── UpdateProvenanceOnMovementJob.php
        └── SyncCommittedInventoryJob.php
```

---

## Cross-Module Interactions

| Module | Direzione | Interazione |
|--------|-----------|-------------|
| Module D | Upstream | Provides InboundBatches via hand-off, sourcing context |
| Module A | Reference | Provides allocation_id for lineage, committed quantities (voucher counts) |
| Module C | Downstream | Reads reservation state (read-only), requests eligible inventory by lineage |
| Module E | Downstream | Consumes shipment events for financial recognition |
| Module 0 | Reference | Provides wine_variant_id, format_id for bottle identity |

---

## Success Metrics

- 100% bottles hanno allocation_id non-null (invariante enforced)
- 100% serializations avvengono solo in authorized locations
- Zero substitutions tra allocation lineages (hard blocked)
- 100% movements hanno audit log
- 100% discrepancies resolved before serialization proceeds
- < 1% WMS event duplicates processed (deduplication working)
- NFT minting completion rate > 99% entro 24h da serialization
- Zero committed inventory consumed senza exception flow
- 100% case breakings sono irreversibili

---

## Open Questions

1. **NFT blockchain provider** - Quale blockchain/provider per NFT minting?
2. **WMS integration specifics** - API format, event structure, retry policy?
3. **Serialization label format** - QR code specifics, NFC tag specs?
4. **Committed quantity sync frequency** - Ogni ora è sufficiente o serve real-time?
5. **Event consumption approval workflow** - Chi approva consumo di committed inventory?
6. **Bottle page public access** - Quali dati visibili pubblicamente vs authenticated?
7. **Case configuration definitions** - Quali case configurations supportare (OWC, OC, etc.)?
