# PRD: Module C — Fulfillment & Shipping

## Introduction

Module C è il sistema autoritativo per la **conversione da diritti astratti (voucher) a consegne fisiche** nell'ERP Crurated. È il **"point of no return"** dove le obbligazioni commerciali si traducono in movimenti fisici e trasferimenti di proprietà.

Module C **governa**:
- Le **Shipping Orders** come autorizzazioni esplicite di spedizione
- Il **Late Binding** tra voucher e bottiglie serializzate (unico punto autorizzato)
- L'orchestrazione **WMS** per pick/pack/ship
- La **Redemption voucher** (avviene SOLO a spedizione confermata)
- L'attivazione del **trasferimento di proprietà** e aggiornamenti provenance
- La gestione delle **eccezioni di fulfillment** e holds

Module C **non decide**:
- Quali prodotti esistono (Module 0 - PIM)
- Quale supply è vendibile (Module A - Allocations)
- Se l'inventario fisico esiste (Module B - Inventory)
- A quali prezzi vendere (Module S - Commercial)
- Chi può comprare (Module K - Customers)
- Come sourcing il vino (Module D - Procurement)
- Come fatturare (Module E - Finance)

**Nessuna spedizione può procedere senza Shipping Order. Nessun voucher viene redento prima della conferma spedizione. Il Late Binding avviene SOLO in Module C.**

---

## Goals

- Creare un sistema di Shipping Orders che autorizzi esplicitamente ogni spedizione
- Implementare Late Binding come unico meccanismo di associazione voucher → bottiglia serializzata
- Validare Early Binding proveniente da Module D (personalizzazione)
- Orchestrare WMS per l'esecuzione fisica di pick/pack/ship
- Triggerare Redemption voucher SOLO a spedizione confermata (mai prima)
- Attivare trasferimento proprietà e aggiornamenti provenance post-shipment
- Enforcare allocation lineage come vincolo DURO non sostituibile
- Gestire eccezioni di fulfillment con escalation, non override
- Preservare case integrity quando richiesto dal cliente
- Garantire audit trail completo per compliance e governance

---

## User Stories

### Sezione 1: Infrastruttura Base

#### US-C001: Setup modello ShippingOrder
**Description:** Come Admin, voglio definire il modello base per le Shipping Orders come autorizzazioni esplicite di spedizione.

**Acceptance Criteria:**
- [ ] Tabella `shipping_orders` con campi: id, uuid, customer_id (FK parties), destination_address_id (FK addresses nullable), source_warehouse_id (FK locations nullable), status (enum), packaging_preference (enum), shipping_method (string nullable), carrier (string nullable), incoterms (string nullable), requested_ship_date (date nullable), special_instructions (text nullable), created_by (FK users), approved_by (FK users nullable), approved_at (timestamp nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione: ShippingOrder belongsTo Customer (Party)
- [ ] Relazione: ShippingOrder belongsTo Location (source warehouse)
- [ ] Relazione: ShippingOrder hasMany ShippingOrderLine
- [ ] Vincolo: status transitions must follow defined workflow
- [ ] Typecheck e lint passano

---

#### US-C002: Setup modello Shipment
**Description:** Come Admin, voglio definire Shipment come record dell'evento fisico di spedizione (point of no return).

**Acceptance Criteria:**
- [ ] Tabella `shipments` con campi: id, uuid, shipping_order_id (FK), carrier (string), tracking_number (string nullable), shipped_at (timestamp), delivered_at (timestamp nullable), status (enum), shipped_bottle_serials (JSON), origin_warehouse_id (FK locations), destination_address (text), weight (decimal nullable), notes (text nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione: Shipment belongsTo ShippingOrder
- [ ] Relazione: Shipment belongsTo Location (origin)
- [ ] shipped_bottle_serials è immutabile dopo conferma
- [ ] Typecheck e lint passano

---

#### US-C003: Setup modello ShippingOrderLine
**Description:** Come Admin, voglio definire ShippingOrderLine come dettaglio degli items in una Shipping Order.

**Acceptance Criteria:**
- [ ] Tabella `shipping_order_lines` con campi: id, uuid, shipping_order_id (FK), voucher_id (FK vouchers), allocation_id (FK allocations, immutable), status (enum), bound_bottle_serial (string nullable), bound_case_id (FK cases nullable), early_binding_serial (string nullable), binding_confirmed_at (timestamp nullable), binding_confirmed_by (FK users nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione: ShippingOrderLine belongsTo ShippingOrder
- [ ] Relazione: ShippingOrderLine belongsTo Voucher
- [ ] Relazione: ShippingOrderLine belongsTo Allocation (immutable, copied from voucher)
- [ ] allocation_id è IMMUTABLE dopo creazione
- [ ] Vincolo: 1 voucher = 1 line = 1 bottiglia
- [ ] Typecheck e lint passano

---

#### US-C004: Setup Enum ShippingOrderStatus
**Description:** Come Developer, voglio un enum per gli stati del ciclo di vita della Shipping Order.

**Acceptance Criteria:**
- [ ] Enum `ShippingOrderStatus`: draft, planned, picking, shipped, completed, cancelled, on_hold
- [ ] Transizioni valide: draft→planned, planned→picking, picking→shipped, shipped→completed, any→cancelled, any→on_hold, on_hold→previous_state
- [ ] Enum in `app/Enums/Fulfillment/`
- [ ] Typecheck e lint passano

---

#### US-C005: Setup Enum ShipmentStatus
**Description:** Come Developer, voglio un enum per gli stati della Shipment fisica.

**Acceptance Criteria:**
- [ ] Enum `ShipmentStatus`: preparing, shipped, in_transit, delivered, failed
- [ ] delivered e failed sono stati terminali
- [ ] Enum in `app/Enums/Fulfillment/`
- [ ] Typecheck e lint passano

---

#### US-C006: Setup Enum PackagingPreference
**Description:** Come Developer, voglio un enum per le preferenze di packaging del cliente.

**Acceptance Criteria:**
- [ ] Enum `PackagingPreference`: loose, cases, preserve_cases
- [ ] `loose`: bottiglie singole
- [ ] `cases`: assemblare in case composite
- [ ] `preserve_cases`: preservare case intrinseci (OWC) quando possibile
- [ ] Enum in `app/Enums/Fulfillment/`
- [ ] Typecheck e lint passano

---

#### US-C007: Setup Enum ShippingOrderLineStatus
**Description:** Come Developer, voglio un enum per gli stati delle righe di Shipping Order.

**Acceptance Criteria:**
- [ ] Enum `ShippingOrderLineStatus`: pending, validated, picked, shipped, cancelled
- [ ] Transizioni: pending→validated, validated→picked, picked→shipped, any→cancelled
- [ ] Enum in `app/Enums/Fulfillment/`
- [ ] Typecheck e lint passano

---

#### US-C008: Setup modello ShippingOrderException
**Description:** Come Admin, voglio registrare eccezioni di fulfillment per audit e risoluzione.

**Acceptance Criteria:**
- [ ] Tabella `shipping_order_exceptions` con campi: id, uuid, shipping_order_id (FK), shipping_order_line_id (FK nullable), exception_type (enum), description (text), resolution_path (text nullable), status (enum: active, resolved), resolved_at (timestamp nullable), resolved_by (FK users nullable), created_by (FK users)
- [ ] Soft deletes abilitati
- [ ] Relazione: ShippingOrderException belongsTo ShippingOrder
- [ ] Relazione: ShippingOrderException belongsTo ShippingOrderLine (optional)
- [ ] Typecheck e lint passano

---

#### US-C009: Setup Enum ShippingOrderExceptionType
**Description:** Come Developer, voglio un enum per i tipi di eccezione fulfillment.

**Acceptance Criteria:**
- [ ] Enum `ShippingOrderExceptionType`: supply_insufficient, voucher_ineligible, wms_discrepancy, binding_failed, case_integrity_violated, ownership_constraint, early_binding_failed
- [ ] Enum in `app/Enums/Fulfillment/`
- [ ] Typecheck e lint passano

---

#### US-C010: Setup modello ShippingOrderAuditLog
**Description:** Come Admin, voglio un audit log immutabile per tutte le azioni su Shipping Orders.

**Acceptance Criteria:**
- [ ] Tabella `shipping_order_audit_logs` con campi: id, shipping_order_id (FK), event_type (string), description (text), old_values (JSON nullable), new_values (JSON nullable), user_id (FK users nullable), created_at
- [ ] NO soft deletes - audit logs sono immutabili
- [ ] Relazione: AuditLog belongsTo ShippingOrder
- [ ] Typecheck e lint passano

---

### Sezione 2: Services Core

#### US-C011: ShippingOrderService per gestione SO
**Description:** Come Developer, voglio un service per centralizzare la logica delle Shipping Orders.

**Acceptance Criteria:**
- [ ] Service class `ShippingOrderService` in `app/Services/Fulfillment/`
- [ ] Metodo `create(Customer $customer, array $vouchers, ?Address $destination, ?string $shippingMethod)`: crea SO in draft
- [ ] Metodo `validateVouchers(ShippingOrder $so)`: verifica eligibility di tutti i voucher
- [ ] Metodo `transitionTo(ShippingOrder $so, ShippingOrderStatus $status)`: gestisce transizioni con validazioni
- [ ] Metodo `cancel(ShippingOrder $so, string $reason)`: cancella SO e sblocca voucher
- [ ] Metodo `lockVouchersForSO(ShippingOrder $so)`: lock voucher quando SO passa a planned
- [ ] Metodo `unlockVouchers(ShippingOrder $so)`: unlock se SO cancellata
- [ ] Validazioni con eccezioni esplicite
- [ ] Typecheck e lint passano

---

#### US-C012: LateBindingService per gestione binding
**Description:** Come Developer, voglio un service per centralizzare la logica del Late Binding.

**Acceptance Criteria:**
- [ ] Service class `LateBindingService` in `app/Services/Fulfillment/`
- [ ] Metodo `requestEligibleInventory(ShippingOrder $so)`: richiede a Module B inventory per allocation lineage
- [ ] Metodo `bindVoucherToBottle(ShippingOrderLine $line, string $serialNumber)`: esegue binding
- [ ] Metodo `validateBinding(ShippingOrderLine $line)`: verifica integrità binding
- [ ] Metodo `validateEarlyBinding(ShippingOrderLine $line)`: verifica early binding da Module D
- [ ] Metodo `unbindLine(ShippingOrderLine $line)`: rimuove binding (solo se non shipped)
- [ ] Invariante: allocation lineage must match tra voucher e bottle
- [ ] Validazioni con eccezioni esplicite
- [ ] Typecheck e lint passano

---

#### US-C013: ShipmentService per gestione spedizioni
**Description:** Come Developer, voglio un service per centralizzare la logica delle Shipments.

**Acceptance Criteria:**
- [ ] Service class `ShipmentService` in `app/Services/Fulfillment/`
- [ ] Metodo `createFromOrder(ShippingOrder $so)`: crea Shipment da SO con tutti i serials
- [ ] Metodo `confirmShipment(Shipment $shipment, string $trackingNumber)`: conferma spedizione
- [ ] Metodo `triggerRedemption(Shipment $shipment)`: redime tutti i voucher della shipment
- [ ] Metodo `triggerOwnershipTransfer(Shipment $shipment)`: triggera provenance updates
- [ ] Metodo `updateTracking(Shipment $shipment, string $status)`: aggiorna stato tracking
- [ ] Metodo `markDelivered(Shipment $shipment)`: marca come consegnato
- [ ] Validazioni con eccezioni esplicite
- [ ] Typecheck e lint passano

---

#### US-C014: VoucherLockService per gestione lock voucher
**Description:** Come Developer, voglio un service per gestire il lock/unlock dei voucher durante fulfillment.

**Acceptance Criteria:**
- [ ] Service class `VoucherLockService` in `app/Services/Fulfillment/`
- [ ] Metodo `lockForShippingOrder(Voucher $voucher, ShippingOrder $so)`: lock voucher per SO
- [ ] Metodo `unlock(Voucher $voucher)`: unlock voucher
- [ ] Metodo `isLockedForSO(Voucher $voucher, ShippingOrder $so)`: verifica lock
- [ ] Metodo `getLockedVouchers(ShippingOrder $so)`: ritorna voucher locked per SO
- [ ] Integrazione con Module A VoucherService per lifecycle transitions
- [ ] Typecheck e lint passano

---

#### US-C015: WmsIntegrationService per orchestrazione WMS
**Description:** Come Developer, voglio un service per gestire l'integrazione con il WMS.

**Acceptance Criteria:**
- [ ] Service class `WmsIntegrationService` in `app/Services/Fulfillment/`
- [ ] Metodo `sendPickingInstructions(ShippingOrder $so)`: invia istruzioni picking a WMS
- [ ] Metodo `receivePickingFeedback(array $pickedSerials, ShippingOrder $so)`: processa feedback WMS
- [ ] Metodo `validateSerials(array $serials, ShippingOrder $so)`: valida seriali ricevuti vs allocation lineage
- [ ] Metodo `confirmShipment(Shipment $shipment)`: riceve conferma shipment da WMS
- [ ] Metodo `handleDiscrepancy(array $discrepancy, ShippingOrder $so)`: gestisce discrepanze
- [ ] Typecheck e lint passano

---

### Sezione 3: Shipping Order CRUD & UI

#### US-C016: ShippingOrderResource in Filament
**Description:** Come Operator, voglio una risorsa Filament per gestire le Shipping Orders.

**Acceptance Criteria:**
- [ ] ShippingOrderResource in Filament con navigation group "Fulfillment"
- [ ] Navigation icon appropriato (truck o shipping)
- [ ] Permessi: create, view, edit (limited), delete (draft only)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C017: SO List View con filtri
**Description:** Come Operator, voglio una lista Shipping Orders come workspace operativo principale.

**Acceptance Criteria:**
- [ ] Lista con colonne: so_id, customer, destination_country, voucher_count, source_warehouse, status (badge colorato), created_at, requested_ship_date
- [ ] Status color coding prominente (draft=gray, planned=blue, picking=yellow, shipped=green, on_hold=red)
- [ ] Filtri: status, customer, source_warehouse, date_range, carrier
- [ ] Ricerca per: so_id, customer name, tracking_number
- [ ] Ordinamento default: created_at DESC
- [ ] Completed/cancelled nascosti di default
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C018: SO Creation Wizard - Step 1 (Customer & Destination)
**Description:** Come Operator, voglio selezionare customer e destinazione come primo step.

**Acceptance Criteria:**
- [ ] Wizard step 1: selezione Customer
- [ ] Autocomplete search per customer name/email
- [ ] Una volta selezionato customer, mostra indirizzi salvati
- [ ] Opzione per inserire nuovo indirizzo di destinazione
- [ ] Validazione: customer attivo, non bloccato (check Module K eligibility)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C019: SO Creation Wizard - Step 2 (Voucher Selection)
**Description:** Come Operator, voglio selezionare i voucher da includere nella spedizione.

**Acceptance Criteria:**
- [ ] Step 2: multi-select vouchers del customer selezionato
- [ ] Mostra solo voucher con lifecycle_state = issued (non locked, non redeemed, non cancelled)
- [ ] Per ogni voucher: voucher_id, wine, format, allocation_lineage
- [ ] Filtri inline: wine name, allocation_id
- [ ] Badge per voucher con early binding (personalizzati)
- [ ] Validazione: almeno 1 voucher selezionato
- [ ] Warning se voucher suspended (non selezionabili)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C020: SO Creation Wizard - Step 3 (Shipping Method)
**Description:** Come Operator, voglio definire il metodo di spedizione.

**Acceptance Criteria:**
- [ ] Step 3 fields: carrier (select), shipping_method (string), incoterms (select), requested_ship_date (date picker)
- [ ] Carrier options configurabili (DHL, FedEx, UPS, etc.)
- [ ] Incoterms options: EXW, FCA, DDP, DAP, etc.
- [ ] Special instructions (textarea)
- [ ] Validazione: requested_ship_date >= today
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C021: SO Creation Wizard - Step 4 (Packaging Preferences)
**Description:** Come Operator, voglio definire le preferenze di packaging.

**Acceptance Criteria:**
- [ ] Step 4: packaging_preference (radio: loose, cases, preserve_cases)
- [ ] Spiegazione inline per ogni opzione:
  - [ ] Loose: "Bottles shipped individually"
  - [ ] Cases: "Bottles assembled in composite cases"
  - [ ] Preserve cases: "Preserve original wooden cases (OWC) when available"
- [ ] Se preserve_cases: warning "May delay shipment if case not available"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C022: SO Creation Wizard - Step 5 (Review & Submit)
**Description:** Come Operator, voglio vedere un riepilogo prima di creare la Shipping Order.

**Acceptance Criteria:**
- [ ] Step 5: read-only summary di tutti i dati inseriti
- [ ] Summary sections: Customer & Destination, Vouchers (count + list), Shipping Method, Packaging
- [ ] Warnings informativi se presenti
- [ ] SO creata in status "draft" by default
- [ ] Messaggio: "Draft Shipping Orders require planning before execution"
- [ ] CTA: "Create Draft" (primary)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C023: SO Bulk Actions
**Description:** Come Operator, voglio azioni bulk limitate sulle Shipping Orders.

**Acceptance Criteria:**
- [ ] Bulk action: Export CSV (sempre disponibile)
- [ ] Bulk action: Cancel (solo su draft/planned, richiede conferma)
- [ ] NO bulk actions per shipped/completed
- [ ] NO bulk transition to picking (too risky)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 4: Shipping Order Detail View (5 Tabs)

#### US-C024: SO Detail - Tab Overview
**Description:** Come Operator, voglio una tab Overview che risponda "Cosa, per chi, da dove?"

**Acceptance Criteria:**
- [ ] Tab Overview come prima tab (default)
- [ ] Section 1 - Customer & Destination: customer name (link), destination address, contact info
- [ ] Section 2 - Shipping Method: carrier, method, incoterms, requested_ship_date
- [ ] Section 3 - Packaging: packaging_preference con spiegazione
- [ ] Section 4 - Voucher Summary: count, list con voucher_id/wine/allocation, state badges
- [ ] Read-only, nessun editing da questa tab
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C025: SO Detail - Tab Vouchers & Eligibility
**Description:** Come Operator, voglio validare l'eligibility di ogni voucher prima della pianificazione.

**Acceptance Criteria:**
- [ ] Tab Vouchers & Eligibility
- [ ] Per ogni voucher: voucher_id, wine/SKU, allocation_lineage, eligibility_status (badge)
- [ ] Eligibility checks mostrati esplicitamente:
  - [ ] Voucher non cancelled
  - [ ] Voucher non già redeemed
  - [ ] Voucher non locked da altri processi
  - [ ] Voucher non suspended
  - [ ] Customer match (holder = SO customer)
- [ ] Voucher ineligibili: riga in rosso con spiegazione
- [ ] Banner blocking se almeno un voucher ineligibile: "One or more vouchers are not eligible for fulfillment"
- [ ] NO override possibile - fix must happen upstream
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C026: SO Detail - Tab Planning
**Description:** Come Operator, voglio pianificare la SO verificando disponibilità inventario.

**Acceptance Criteria:**
- [ ] Tab Planning attiva solo se status = draft o planned
- [ ] Section 1 - Source Warehouse: select o confirm warehouse
- [ ] Section 2 - Inventory Availability: per ogni allocation lineage, mostra available bottles
- [ ] Call a Module B per check availability
- [ ] Allocation lineage è vincolo DURO - no cross-allocation substitution
- [ ] States possibili:
  - [ ] "Eligible inventory available" (green)
  - [ ] "Bottles available, intact case unavailable" (yellow, if preserve_cases)
  - [ ] "Insufficient eligible inventory" (red)
- [ ] Se insufficient: SO non può procedere, crea Supply Exception
- [ ] Azione "Plan Order" → transition to planned, lock vouchers
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C027: SO Detail - Tab Picking & Binding
**Description:** Come Operator, voglio vedere il late binding in azione durante picking.

**Acceptance Criteria:**
- [ ] Tab Picking & Binding attiva solo se status >= picking
- [ ] Read-only fino a picking status
- [ ] Per ogni ShippingOrderLine:
  - [ ] voucher_id
  - [ ] bound_bottle_serial (populated after WMS feedback)
  - [ ] binding_status (pending/confirmed)
  - [ ] early_binding_serial (se pre-bound da Module D)
- [ ] Se early binding: mostra "Pre-bound (personalized)" badge, skip selection
- [ ] Se late binding: mostra serial dopo WMS pick confirmation
- [ ] Discrepancy handling: serial in rosso se non valido, azione "Request Re-pick"
- [ ] NO manual bottle selection - operator può solo accept/reject WMS feedback
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C028: SO Detail - Tab Audit & Timeline
**Description:** Come Operator/Compliance, voglio una timeline cronologica di tutti gli eventi.

**Acceptance Criteria:**
- [ ] Tab Audit & Timeline
- [ ] Timeline cronologica di:
  - [ ] SO creation
  - [ ] Voucher validation events
  - [ ] Planning events
  - [ ] WMS messages sent/received
  - [ ] Picking confirmations
  - [ ] Binding events
  - [ ] Shipment execution
- [ ] Per ogni evento: timestamp, description, user (if applicable)
- [ ] Read-only, immutabile
- [ ] Export CSV per audit
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C029: SO Actions e State Transitions
**Description:** Come Operator, voglio azioni contestuali per le transizioni di stato.

**Acceptance Criteria:**
- [ ] Header actions basate su status corrente:
  - [ ] Draft: "Plan Order", "Edit", "Cancel"
  - [ ] Planned: "Send to Picking", "Cancel", "Put on Hold"
  - [ ] Picking: "Confirm Shipment" (dopo binding complete), "Put on Hold"
  - [ ] On Hold: "Resume", "Cancel"
  - [ ] Shipped/Completed: nessuna azione (read-only)
- [ ] Ogni transizione richiede conferma dialog
- [ ] Transizioni invalide non mostrate
- [ ] Audit log per ogni transizione
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 5: Late Binding & Picking Logic

#### US-C030: Voucher Eligibility Validation
**Description:** Come Developer, voglio che l'eligibility dei voucher sia validata rigorosamente.

**Acceptance Criteria:**
- [ ] Voucher è eligible se:
  - [ ] lifecycle_state = issued
  - [ ] suspended = false
  - [ ] customer_id match SO customer
  - [ ] non in pending transfer
  - [ ] allocation active (not closed)
- [ ] Validazione eseguita a: SO creation, planning, pre-picking
- [ ] Voucher diventa ineligibile durante SO lifecycle → SO blocked
- [ ] Typecheck e lint passano

---

#### US-C031: Allocation Lineage Constraint Enforcement
**Description:** Come Developer, voglio che l'allocation lineage sia enforced come vincolo DURO.

**Acceptance Criteria:**
- [ ] ShippingOrderLine.allocation_id copiato da voucher.allocation_id
- [ ] allocation_id è IMMUTABLE dopo creazione
- [ ] Late binding DEVE usare bottle con matching allocation_id
- [ ] Tentativo di bind con bottle da altra allocation → hard block
- [ ] Error message: "Allocation lineage mismatch. Cross-allocation substitution not allowed."
- [ ] NO override exists
- [ ] Typecheck e lint passano

---

#### US-C032: Inventory Availability Request
**Description:** Come Developer, voglio richiedere inventory availability da Module B con allocation constraint.

**Acceptance Criteria:**
- [ ] LateBindingService.requestEligibleInventory chiama Module B
- [ ] Request parameters: allocation_id, quantity, warehouse_id (optional)
- [ ] Module B ritorna: available_quantity, available_bottles (serials)
- [ ] Se packaging_preference = preserve_cases: check anche intact case availability
- [ ] Risultato cached per performance (TTL breve, < 5 min)
- [ ] Typecheck e lint passano

---

#### US-C033: Late Binding Execution
**Description:** Come Developer, voglio eseguire il late binding voucher → bottiglia serializzata.

**Acceptance Criteria:**
- [ ] Late binding triggered da WMS pick feedback
- [ ] Per ogni serial ricevuto:
  - [ ] Validare che serial appartiene a allocation corretta
  - [ ] Validare che bottle.state = stored
  - [ ] Validare ownership/custody
  - [ ] Bind: ShippingOrderLine.bound_bottle_serial = serial
  - [ ] Update bottle state: stored → reserved_for_picking
- [ ] Binding è reversibile fino a shipment confirmation
- [ ] Typecheck e lint passano

---

#### US-C034: Early Binding Validation
**Description:** Come Developer, voglio validare early binding proveniente da Module D.

**Acceptance Criteria:**
- [ ] Se voucher ha early_binding_serial (da personalizzazione Module D):
  - [ ] Skip bottle selection
  - [ ] Validate: serial exists, bottle.state = stored, allocation match
  - [ ] Validate: ownership/custody valid
- [ ] Se validation fails:
  - [ ] Shipment blocked
  - [ ] Exception created: early_binding_failed
  - [ ] NO fallback to late binding
- [ ] Typecheck e lint passano

---

#### US-C035: Case Integrity Handling
**Description:** Come Developer, voglio gestire case integrity per richieste preserve_cases.

**Acceptance Criteria:**
- [ ] Se packaging_preference = preserve_cases:
  - [ ] Check intact case availability per allocation lineage
  - [ ] Se case available: ship as intact case
  - [ ] Se case not available: SO cannot proceed as requested
  - [ ] NO automatic downgrade to loose bottles
- [ ] Se partial shipment from case requested:
  - [ ] Explicit warning: "This will permanently break the original case"
  - [ ] Operator must confirm
  - [ ] Case.integrity_status → broken
  - [ ] Irreversibile
- [ ] Typecheck e lint passano

---

### Sezione 6: Shipment Management

#### US-C036: ShipmentResource in Filament
**Description:** Come Operator, voglio una risorsa Filament per le Shipments.

**Acceptance Criteria:**
- [ ] ShipmentResource in Filament con navigation group "Fulfillment"
- [ ] Lista con colonne: shipment_id, so_id (link), carrier, tracking_number, shipped_at, status (badge), bottles_count
- [ ] Filtri: status, carrier, date_range
- [ ] Ricerca per: shipment_id, so_id, tracking_number
- [ ] Read-heavy, limited edit capability
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C037: Shipment Detail View
**Description:** Come Operator, voglio vedere i dettagli di una Shipment.

**Acceptance Criteria:**
- [ ] Header: shipment_id, status, shipped_at
- [ ] Section 1 - Shipping Order: SO link, customer, destination
- [ ] Section 2 - Carrier & Tracking: carrier, tracking_number, tracking URL
- [ ] Section 3 - Shipped Items: list of bottle serials con wine info
- [ ] Section 4 - Audit: events timeline
- [ ] Read-only after shipped status
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C038: Shipment Creation from SO
**Description:** Come Developer, voglio creare Shipment da Shipping Order completata.

**Acceptance Criteria:**
- [ ] Shipment creata quando SO transition to shipped
- [ ] Copia tutti bound_bottle_serials dalle ShippingOrderLines
- [ ] shipped_bottle_serials immutabile dopo creazione
- [ ] Shipment.status = preparing initially
- [ ] Typecheck e lint passano

---

#### US-C039: Shipment Confirmation & Tracking
**Description:** Come Developer, voglio confermare shipment e gestire tracking.

**Acceptance Criteria:**
- [ ] Azione "Confirm Shipment" richiede tracking_number
- [ ] Confirmation triggers:
  - [ ] Shipment.status → shipped
  - [ ] Shipment.shipped_at = now
  - [ ] Voucher redemption (all vouchers in SO)
  - [ ] Ownership transfer trigger
  - [ ] Provenance update trigger
- [ ] Tracking updates possibili: in_transit, delivered
- [ ] Typecheck e lint passano

---

#### US-C040: Voucher Redemption Trigger
**Description:** Come Developer, voglio triggerare redemption voucher SOLO a shipment confirmation.

**Acceptance Criteria:**
- [ ] Redemption triggered da ShipmentService.triggerRedemption
- [ ] Per ogni voucher nella SO:
  - [ ] Call Module A VoucherService.redeem(voucher)
  - [ ] Voucher.lifecycle_state → redeemed
- [ ] Redemption è IRREVERSIBILE
- [ ] Se redemption fails: shipment confirmation fails
- [ ] Audit log per ogni redemption
- [ ] Typecheck e lint passano

---

#### US-C041: Ownership Transfer Trigger
**Description:** Come Developer, voglio triggerare il trasferimento di proprietà post-shipment.

**Acceptance Criteria:**
- [ ] Ownership transfer triggered dopo redemption
- [ ] Per ogni bottle serial nella shipment:
  - [ ] Update Module B bottle ownership
  - [ ] bottle.ownership_type → customer_owned
- [ ] Trigger provenance blockchain update
- [ ] Typecheck e lint passano

---

#### US-C042: Provenance Update Trigger
**Description:** Come Developer, voglio aggiornare provenance records post-shipment.

**Acceptance Criteria:**
- [ ] Dispatch UpdateProvenanceOnShipmentJob
- [ ] Per ogni bottle:
  - [ ] Update on-chain provenance con shipment event
  - [ ] Record customer as new owner
  - [ ] Record timestamp
- [ ] Job con retry on failure
- [ ] Typecheck e lint passano

---

### Sezione 7: Exception Handling

#### US-C043: Exceptions & Holds List
**Description:** Come Operator, voglio una lista di tutte le eccezioni di fulfillment.

**Acceptance Criteria:**
- [ ] Pagina "Exceptions & Holds" in navigation Fulfillment
- [ ] Lista: exception_id, so_id (link), exception_type (badge), description, status (active/resolved), created_at
- [ ] Filtri: exception_type, status, date_range
- [ ] Ricerca per: exception_id, so_id
- [ ] Active exceptions highlighted
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C044: Supply Exception Handling
**Description:** Come Operator, voglio gestire eccezioni di supply insufficiente.

**Acceptance Criteria:**
- [ ] Exception type: supply_insufficient
- [ ] Created quando: planning fails due to no eligible inventory
- [ ] Info mostrate: allocation_id, required_quantity, available_quantity (0)
- [ ] Resolution paths:
  - [ ] "Wait for inventory to become available"
  - [ ] "Request internal transfer (Module B)"
  - [ ] "Cancel Shipping Order"
- [ ] NO resolution via cross-allocation substitution
- [ ] Typecheck e lint passano

---

#### US-C045: WMS Discrepancy Resolution
**Description:** Come Operator, voglio gestire discrepanze WMS.

**Acceptance Criteria:**
- [ ] Exception type: wms_discrepancy
- [ ] Created quando: WMS picks bottle that fails validation
- [ ] Info mostrate: expected_constraints, picked_serial, violation_reason
- [ ] Resolution paths:
  - [ ] "Request WMS re-pick"
  - [ ] "Cancel Shipping Order"
- [ ] NO manual acceptance of invalid pick
- [ ] Typecheck e lint passano

---

#### US-C046: Voucher Ineligible Blocking
**Description:** Come Developer, voglio bloccare SO se voucher diventa ineligibile.

**Acceptance Criteria:**
- [ ] Check voucher eligibility at: creation, planning, pre-picking
- [ ] Se voucher ineligibile:
  - [ ] SO cannot proceed
  - [ ] Exception created: voucher_ineligible
  - [ ] Banner in SO detail: "One or more vouchers are no longer eligible"
- [ ] Resolution paths:
  - [ ] "Remove ineligible voucher from SO"
  - [ ] "Cancel Shipping Order"
- [ ] NO partial fulfillment silently
- [ ] Typecheck e lint passano

---

#### US-C047: Shipment Failure Recovery
**Description:** Come Admin, voglio gestire failures post-shipment.

**Acceptance Criteria:**
- [ ] Se WMS reports shipment ma ERP validation fails:
  - [ ] Shipment status = failed
  - [ ] Redemption NOT executed
  - [ ] Critical alert created
- [ ] Recovery richiede admin intervention
- [ ] Full audit trail preserved
- [ ] Possible actions: retry confirmation, manual reconciliation
- [ ] Typecheck e lint passano

---

### Sezione 8: WMS Integration

#### US-C048: WMS Outbound Message
**Description:** Come Developer, voglio inviare istruzioni picking al WMS.

**Acceptance Criteria:**
- [ ] Messaggio inviato quando SO transition to picking
- [ ] Payload: so_id, warehouse_id, lines (voucher_id, allocation_id, product_info)
- [ ] Se early_binding: include specific serial to pick
- [ ] Se late_binding: include allocation constraint, WMS selects serial
- [ ] Messaggio logged in audit
- [ ] Typecheck e lint passano

---

#### US-C049: WMS Picking Feedback Reception
**Description:** Come Developer, voglio ricevere e processare feedback picking dal WMS.

**Acceptance Criteria:**
- [ ] Endpoint/handler per ricevere picked serials da WMS
- [ ] Per ogni serial:
  - [ ] Validate vs allocation lineage
  - [ ] Validate bottle state
  - [ ] Execute binding se valid
  - [ ] Flag discrepancy se invalid
- [ ] All bindings complete → SO can proceed to shipment
- [ ] Typecheck e lint passano

---

#### US-C050: WMS Serial Validation
**Description:** Come Developer, voglio validare seriali ricevuti dal WMS.

**Acceptance Criteria:**
- [ ] Validation checks:
  - [ ] Serial exists in Module B
  - [ ] Bottle.allocation_id matches ShippingOrderLine.allocation_id
  - [ ] Bottle.state = stored
  - [ ] Bottle.ownership allows fulfillment
  - [ ] Bottle not destroyed/missing
- [ ] Validation failure → discrepancy logged
- [ ] Typecheck e lint passano

---

#### US-C051: WMS Shipment Confirmation
**Description:** Come Developer, voglio ricevere conferma shipment dal WMS.

**Acceptance Criteria:**
- [ ] Endpoint/handler per conferma shipment
- [ ] Payload: so_id, carrier, tracking_number, shipped_serials
- [ ] Validate: all expected serials shipped
- [ ] Trigger: Shipment creation, redemption, ownership transfer
- [ ] Partial shipment handling: not supported in MVP, flag as exception
- [ ] Typecheck e lint passano

---

### Sezione 9: Audit & Governance

#### US-C052: Audit Trail per ShippingOrder
**Description:** Come Compliance Officer, voglio un audit log immutabile per le Shipping Orders.

**Acceptance Criteria:**
- [ ] Eventi loggati: creation, status_change, voucher_added, voucher_removed, planning_result, wms_sent, wms_received, binding, shipment_confirmed, cancelled
- [ ] Per ogni evento: event_type, description, old_values, new_values, user_id, timestamp
- [ ] Tab Audit in SO detail con timeline
- [ ] Audit logs immutabili (no delete, no update)
- [ ] Typecheck e lint passano

---

#### US-C053: Audit Trail per Shipment
**Description:** Come Compliance Officer, voglio un audit log immutabile per le Shipments.

**Acceptance Criteria:**
- [ ] Eventi loggati: creation, status_change, tracking_updated, delivered, failed
- [ ] Per ogni evento: event_type, description, user_id, timestamp
- [ ] Section Audit in Shipment detail
- [ ] Audit logs immutabili
- [ ] Typecheck e lint passano

---

#### US-C054: Late Binding Audit Log
**Description:** Come Compliance Officer, voglio tracciare ogni binding voucher → bottle.

**Acceptance Criteria:**
- [ ] Per ogni binding:
  - [ ] voucher_id, bottle_serial, allocation_id
  - [ ] binding_type (late/early)
  - [ ] timestamp, wms_event_reference
- [ ] Bindings logged sia su ShippingOrderLine che su audit separato
- [ ] Query capability per: "tutti i binding per allocation X"
- [ ] Typecheck e lint passano

---

### Sezione 10: Dashboard & Overview

#### US-C055: Fulfillment Dashboard
**Description:** Come Operations Manager, voglio una dashboard per monitorare lo stato fulfillment.

**Acceptance Criteria:**
- [ ] Pagina "Fulfillment Overview" come landing page del modulo
- [ ] Widget A: SO by Status (counts: draft, planned, picking, shipped, on_hold)
- [ ] Widget B: SOs Requiring Attention (exceptions active, near requested_ship_date)
- [ ] Widget C: Shipments Today/This Week
- [ ] Widget D: Exception Summary (by type, count)
- [ ] Link rapidi a items problematici
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C056: SO Workflow Visualization
**Description:** Come Operator, voglio vedere il workflow visuale dello stato SO.

**Acceptance Criteria:**
- [ ] In SO detail header: visual workflow indicator
- [ ] Steps: Draft → Planned → Picking → Shipped → Completed
- [ ] Current step highlighted
- [ ] Completed steps in green, current in blue, future in gray
- [ ] On Hold shown as red overlay
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 11: Customer Integration

#### US-C057: Customer Shipping Orders tab
**Description:** Come Operator, voglio vedere le Shipping Orders di un cliente dalla Customer Detail.

**Acceptance Criteria:**
- [ ] Tab "Shipping Orders" in CustomerResource detail
- [ ] Lista SO del customer: so_id, status, voucher_count, created_at, shipped_at
- [ ] Filtri: status
- [ ] Link a SO detail
- [ ] Summary: total SOs, pending, shipped
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-C058: Customer Shipments history
**Description:** Come Operator, voglio vedere lo storico spedizioni di un cliente.

**Acceptance Criteria:**
- [ ] Sub-section o tab "Shipment History" in Customer
- [ ] Lista Shipments: shipment_id, carrier, tracking, shipped_at, status
- [ ] Link a Shipment detail
- [ ] Read-only
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 12: Edge Cases & Invariants

#### US-C059: Concurrent SO prevention
**Description:** Come Developer, voglio prevenire che lo stesso voucher sia in multiple SO.

**Acceptance Criteria:**
- [ ] Voucher può essere in una sola SO active (draft/planned/picking) alla volta
- [ ] Tentativo di aggiungere voucher già in altra SO → blocked
- [ ] Error: "Voucher is already assigned to Shipping Order SO-XXX"
- [ ] Lock released quando SO completata o cancellata
- [ ] Typecheck e lint passano

---

#### US-C060: SO cancellation voucher unlock
**Description:** Come Developer, voglio che i voucher siano sbloccati quando SO cancellata.

**Acceptance Criteria:**
- [ ] Quando SO cancelled:
  - [ ] Tutti voucher.lifecycle_state rimangono issued
  - [ ] Voucher lock released
  - [ ] Voucher disponibili per nuova SO
- [ ] Se SO era in picking:
  - [ ] Bindings removed
  - [ ] Bottle.state torna a stored
- [ ] Audit log per ogni unlock
- [ ] Typecheck e lint passano

---

#### US-C061: Shipment without SO prevention
**Description:** Come Developer, voglio che nessuna spedizione possa avvenire senza SO.

**Acceptance Criteria:**
- [ ] Shipment MUST have shipping_order_id (NOT NULL)
- [ ] No API/UI per creare Shipment standalone
- [ ] WMS shipment confirmation richiede SO reference
- [ ] Invariante enforced a database level
- [ ] Typecheck e lint passano

---

#### US-C062: Redemption timing enforcement
**Description:** Come Developer, voglio che redemption avvenga SOLO a shipment confirmation.

**Acceptance Criteria:**
- [ ] Redemption triggered ONLY da ShipmentService.confirmShipment
- [ ] No redemption a: SO creation, planning, picking
- [ ] Voucher.lifecycle_state = locked durante picking (non redeemed)
- [ ] Invariante: redeemed IMPLIES shipped
- [ ] Typecheck e lint passano

---

---

## Key Invariants (Non-Negotiable Rules)

1. **Nessuna spedizione senza Shipping Order** - SO è autorizzazione esplicita, sempre richiesta
2. **Redemption SOLO a spedizione confermata** - Voucher redeemed quando shipment confirmed, MAI prima
3. **Late Binding SOLO in Module C** - Unico punto autorizzato per legare voucher → bottiglia
4. **1 voucher = 1 bottiglia serializzata** - Binding 1:1 sempre, no exceptions
5. **Allocation Lineage è vincolo DURO** - NO substitution cross-allocation, MAI
6. **ERP autorizza, WMS esegue** - Authority model chiaro, no override
7. **Early binding da Module D è vincolante** - Se presente, no fallback a late binding
8. **Case breakability è irreversibile** - Una volta broken, non può tornare intact
9. **Bindings sono reversibili fino a shipment** - Dopo shipment, immutabili
10. **Exceptions are visible, not silently fixed** - Escalation, not override

---

## Functional Requirements

- **FR-1:** ShippingOrder rappresenta autorizzazione esplicita di spedizione
- **FR-2:** ShippingOrderLine traccia binding voucher → bottle con allocation lineage immutabile
- **FR-3:** Shipment è record immutabile dell'evento fisico di spedizione
- **FR-4:** Late Binding esegue associazione voucher → bottle SOLO in Module C
- **FR-5:** Early Binding (da Module D) è validato ma non sostituibile
- **FR-6:** WMS integration è orchestrata dall'ERP (ERP authorizes, WMS executes)
- **FR-7:** Voucher redemption triggered SOLO a shipment confirmation
- **FR-8:** Ownership transfer triggered post-redemption
- **FR-9:** Case integrity preserved quando richiesto, broken esplicitamente
- **FR-10:** Exceptions aggregate blockers con resolution paths informativi
- **FR-11:** Audit log immutabile per tutte le entità e transizioni

---

## Non-Goals

- NON gestire prodotti o catalogo (Module 0 - PIM)
- NON gestire allocations o vouchers lifecycle (Module A - Allocations)
- NON gestire inventory fisico o serialization (Module B - Inventory)
- NON gestire pricing o offer exposure (Module S - Commercial)
- NON gestire customer eligibility o blocks (Module K - Customers)
- NON gestire procurement o inbound (Module D - Procurement)
- NON gestire fatturazione diretta (Module E - Finance)
- NON permettere manual bottle selection (WMS picks based on constraints)
- NON permettere cross-allocation substitution (hard blocked)
- NON supportare partial shipments in MVP
- Customer-facing shipping portal (fuori scope MVP admin)
- Carrier rate shopping (fuori scope MVP)
- Return/refund handling (fuori scope MVP)

---

## Technical Considerations

### Database Schema Principale

```
shipping_orders
├── id, uuid
├── customer_id (FK parties)
├── destination_address_id (FK addresses nullable)
├── source_warehouse_id (FK locations nullable)
├── status (enum: draft, planned, picking, shipped, completed, cancelled, on_hold)
├── packaging_preference (enum: loose, cases, preserve_cases)
├── shipping_method (string nullable)
├── carrier (string nullable)
├── incoterms (string nullable)
├── requested_ship_date (date nullable)
├── special_instructions (text nullable)
├── created_by (FK users)
├── approved_by (FK users nullable)
├── approved_at (timestamp nullable)
├── timestamps, soft_deletes

shipping_order_lines
├── id, uuid
├── shipping_order_id (FK)
├── voucher_id (FK vouchers)
├── allocation_id (FK allocations, IMMUTABLE)
├── status (enum: pending, validated, picked, shipped, cancelled)
├── bound_bottle_serial (string nullable)
├── bound_case_id (FK cases nullable)
├── early_binding_serial (string nullable)
├── binding_confirmed_at (timestamp nullable)
├── binding_confirmed_by (FK users nullable)
├── timestamps, soft_deletes

shipments
├── id, uuid
├── shipping_order_id (FK)
├── carrier (string)
├── tracking_number (string nullable)
├── shipped_at (timestamp)
├── delivered_at (timestamp nullable)
├── status (enum: preparing, shipped, in_transit, delivered, failed)
├── shipped_bottle_serials (JSON, IMMUTABLE after confirm)
├── origin_warehouse_id (FK locations)
├── destination_address (text)
├── weight (decimal nullable)
├── notes (text nullable)
├── timestamps, soft_deletes

shipping_order_exceptions
├── id, uuid
├── shipping_order_id (FK)
├── shipping_order_line_id (FK nullable)
├── exception_type (enum)
├── description (text)
├── resolution_path (text nullable)
├── status (enum: active, resolved)
├── resolved_at (timestamp nullable)
├── resolved_by (FK users nullable)
├── created_by (FK users)
├── timestamps, soft_deletes

shipping_order_audit_logs (NO SOFT DELETES)
├── id
├── shipping_order_id (FK)
├── event_type (string)
├── description (text)
├── old_values (JSON nullable)
├── new_values (JSON nullable)
├── user_id (FK users nullable)
├── created_at
```

### Filament Resources

- `ShippingOrderResource` - CRUD Shipping Orders con 5 tabs (primary operational screen)
- `ShipmentResource` - Read-heavy Shipments list and detail
- Custom pages: "Exceptions & Holds", "Fulfillment Overview"

### Enums

```php
enum ShippingOrderStatus: string {
    case Draft = 'draft';
    case Planned = 'planned';
    case Picking = 'picking';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case OnHold = 'on_hold';
}

enum ShipmentStatus: string {
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';
}

enum PackagingPreference: string {
    case Loose = 'loose';
    case Cases = 'cases';
    case PreserveCases = 'preserve_cases';
}

enum ShippingOrderLineStatus: string {
    case Pending = 'pending';
    case Validated = 'validated';
    case Picked = 'picked';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';
}

enum ShippingOrderExceptionType: string {
    case SupplyInsufficient = 'supply_insufficient';
    case VoucherIneligible = 'voucher_ineligible';
    case WmsDiscrepancy = 'wms_discrepancy';
    case BindingFailed = 'binding_failed';
    case CaseIntegrityViolated = 'case_integrity_violated';
    case OwnershipConstraint = 'ownership_constraint';
    case EarlyBindingFailed = 'early_binding_failed';
}
```

### Service Classes

```php
// Shipping Order management
class ShippingOrderService {
    public function create(Customer $customer, array $vouchers, ?Address $destination, ?string $shippingMethod): ShippingOrder;
    public function validateVouchers(ShippingOrder $so): ValidationResult;
    public function transitionTo(ShippingOrder $so, ShippingOrderStatus $status): void;
    public function cancel(ShippingOrder $so, string $reason): void;
    public function lockVouchersForSO(ShippingOrder $so): void;
    public function unlockVouchers(ShippingOrder $so): void;
}

// Late Binding management
class LateBindingService {
    public function requestEligibleInventory(ShippingOrder $so): InventoryResult;
    public function bindVoucherToBottle(ShippingOrderLine $line, string $serialNumber): void;
    public function validateBinding(ShippingOrderLine $line): bool;
    public function validateEarlyBinding(ShippingOrderLine $line): bool;
    public function unbindLine(ShippingOrderLine $line): void;
}

// Shipment management
class ShipmentService {
    public function createFromOrder(ShippingOrder $so): Shipment;
    public function confirmShipment(Shipment $shipment, string $trackingNumber): void;
    public function triggerRedemption(Shipment $shipment): void;
    public function triggerOwnershipTransfer(Shipment $shipment): void;
    public function updateTracking(Shipment $shipment, string $status): void;
    public function markDelivered(Shipment $shipment): void;
}

// Voucher Lock management
class VoucherLockService {
    public function lockForShippingOrder(Voucher $voucher, ShippingOrder $so): void;
    public function unlock(Voucher $voucher): void;
    public function isLockedForSO(Voucher $voucher, ShippingOrder $so): bool;
    public function getLockedVouchers(ShippingOrder $so): Collection;
}

// WMS Integration
class WmsIntegrationService {
    public function sendPickingInstructions(ShippingOrder $so): void;
    public function receivePickingFeedback(array $pickedSerials, ShippingOrder $so): ValidationResult;
    public function validateSerials(array $serials, ShippingOrder $so): ValidationResult;
    public function confirmShipment(Shipment $shipment): void;
    public function handleDiscrepancy(array $discrepancy, ShippingOrder $so): void;
}
```

### Jobs

```php
// Provenance update on shipment
class UpdateProvenanceOnShipmentJob {
    // Input: Shipment
    // Updates blockchain provenance for each shipped bottle
    // Records ownership transfer to customer
}

// Voucher redemption notification
class NotifyVoucherRedemptionJob {
    // Input: Voucher
    // Sends notification/webhook for redeemed voucher
}
```

### Struttura Directory

```
app/
├── Enums/
│   └── Fulfillment/
│       ├── ShippingOrderStatus.php
│       ├── ShipmentStatus.php
│       ├── PackagingPreference.php
│       ├── ShippingOrderLineStatus.php
│       └── ShippingOrderExceptionType.php
├── Models/
│   └── Fulfillment/
│       ├── ShippingOrder.php
│       ├── ShippingOrderLine.php
│       ├── Shipment.php
│       ├── ShippingOrderException.php
│       └── ShippingOrderAuditLog.php
├── Filament/
│   └── Resources/
│       └── Fulfillment/
│           ├── ShippingOrderResource.php
│           ├── ShipmentResource.php
│           └── Pages/
│               ├── FulfillmentOverview.php
│               └── ExceptionsAndHolds.php
├── Services/
│   └── Fulfillment/
│       ├── ShippingOrderService.php
│       ├── LateBindingService.php
│       ├── ShipmentService.php
│       ├── VoucherLockService.php
│       └── WmsIntegrationService.php
└── Jobs/
    └── Fulfillment/
        ├── UpdateProvenanceOnShipmentJob.php
        └── NotifyVoucherRedemptionJob.php
```

---

## Cross-Module Interactions

| Module | Direzione | Interazione |
|--------|-----------|-------------|
| Module A (Allocations) | Upstream | Provides voucher eligibility, lifecycle state, allocation_id |
| Module A (Allocations) | Triggered | Voucher redemption (lock → redeemed transition) |
| Module B (Inventory) | Upstream | Provides eligible inventory by allocation lineage, bottle state |
| Module B (Inventory) | Triggered | Bottle state updates (stored → reserved → shipped) |
| Module D (Procurement) | Reference | Provides early binding info for personalized bottles |
| Module K (Customers) | Upstream | Customer eligibility, financial clearance |
| Module E (Finance) | Downstream | Shipment events for financial recognition |
| Module 0 (PIM) | Reference | Product info for display |
| Provenance System | Downstream | NFT updates, ownership transfer records |

---

## Success Metrics

- 100% delle spedizioni hanno Shipping Order (invariante enforced)
- 100% delle redemption avvengono a shipment confirmation (mai prima)
- Zero cross-allocation substitutions (hard blocked)
- 100% dei bindings rispettano allocation lineage
- 100% delle transizioni SO hanno audit log
- Zero shipments senza tracking number dopo 24h
- < 5% SO in exception state contemporaneamente
- 100% early bindings validated before shipment

---

## Open Questions

1. **WMS API specifics** - Quale formato API per comunicazione ERP ↔ WMS?
2. **Partial shipment handling** - Come gestire spedizioni parziali (fuori MVP)?
3. **Return/refund flow** - Come gestire resi (fuori MVP)?
4. **Carrier integration** - Integrazione diretta con carrier APIs per tracking?
5. **Customer notification** - Notifiche automatiche a customer per shipment events?
6. **Multi-warehouse fulfillment** - Come gestire SO con items da warehouse diversi?
7. **Shipment consolidation** - Consolidare multiple SO in una spedizione fisica?
