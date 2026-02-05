# PRD: Module E — Accounting, Invoicing & Payments

## Introduction

Module E è il sistema autoritativo per la **gestione finanziaria** nell'ERP Crurated. Risponde alle domande: "Quanto deve pagare il cliente?", "Quanto ha pagato?" e "Qual è la sua posizione finanziaria?"

**Principio fondamentale: Finance è conseguenza, non causa.** Gli eventi finanziari sono generati da altri moduli; Module E li registra, traccia e riconcilia.

Module E **governa**:
- Le **Invoices** (INV0-INV4) come documenti fiscali generati da eventi ERP
- I **Payments** come evidenza di pagamento ricevuto (Stripe/Bank)
- Le **Credit Notes** come correzioni formali a fatture emesse
- I **Refunds** come rimborsi linkati a invoice+payment
- Le **Subscriptions** per membership e servizi ricorrenti (INV0)
- Lo **Storage Billing** per fatturazione custodia (INV3)
- L'integrazione con **Stripe** per pagamenti e webhook
- L'integrazione con **Xero** per statutory accounting
- La **Customer Financial View** come dashboard finanziario per cliente

Module E **non decide**:
- Quali prodotti esistono (Module 0 - PIM)
- Quale supply è vendibile (Module A - Allocations)
- A quali prezzi vendere (Module S - Commercial)
- Se l'inventario fisico esiste (Module B - Inventory)
- Chi può comprare (Module K - Customers)
- Come evadere gli ordini (Module C - Fulfillment)
- Come sourcing il vino (Module D - Procurement)

**Nessuna fattura può esistere senza evento trigger. I pagamenti sono evidenza, non autorità. Gli importi sono immutabili dopo issuance.**

---

## Goals

- Creare un sistema di invoicing che distingua chiaramente 5 tipi di fattura (INV0-INV4)
- Implementare invoice lines read-only dopo issuance
- Gestire pagamenti da Stripe e bank transfer con riconciliazione automatica/manuale
- Supportare credit notes come correzioni formali con reason obbligatorio
- Gestire refunds con link esplicito a invoice + payment
- Implementare subscription billing per membership (INV0)
- Implementare storage billing con calcolo usage retrospettivo (INV3)
- Integrare Stripe webhook processing con idempotency
- Integrare sync Xero per statutory invoices
- Fornire customer financial view con eligibility signals
- Preservare immutabilità post-issuance come invariante
- Garantire audit trail completo per compliance e governance

---

## User Stories

### Sezione 1: Infrastruttura Base

#### US-E001: Setup enums per Finance
**Description:** Come Developer, voglio enums ben definiti per i tipi e stati delle entità finanziarie.

**Acceptance Criteria:**
- [ ] Enum `InvoiceType`: membership_service (INV0), voucher_sale (INV1), shipping_redemption (INV2), storage_fee (INV3), service_events (INV4)
- [ ] Enum `InvoiceStatus`: draft, issued, paid, partially_paid, credited, cancelled
- [ ] Enum `PaymentSource`: stripe, bank_transfer
- [ ] Enum `PaymentStatus`: pending, confirmed, failed, refunded
- [ ] Enum `ReconciliationStatus`: pending, matched, mismatched
- [ ] Enum `CreditNoteStatus`: draft, issued, applied
- [ ] Enum `RefundType`: full, partial
- [ ] Enum `RefundMethod`: stripe, bank_transfer
- [ ] Enum `RefundStatus`: pending, processed, failed
- [ ] Enum `SubscriptionPlanType`: membership, service
- [ ] Enum `BillingCycle`: monthly, quarterly, annual
- [ ] Enum `SubscriptionStatus`: active, suspended, cancelled
- [ ] Enum `StorageBillingStatus`: pending, invoiced, paid, blocked
- [ ] Enum `XeroSyncType`: invoice, credit_note, payment
- [ ] Enum `XeroSyncStatus`: pending, synced, failed
- [ ] Enums in `app/Enums/Finance/`
- [ ] Typecheck e lint passano

---

#### US-E002: Setup modello Invoice
**Description:** Come Admin, voglio definire il modello base per le fatture ERP.

**Acceptance Criteria:**
- [ ] Tabella `invoices` con campi: id, uuid, invoice_number (string unique), invoice_type (enum), customer_id (FK), currency (string default 'EUR'), subtotal (decimal 10,2), tax_amount (decimal 10,2), total_amount (decimal 10,2), amount_paid (decimal 10,2 default 0), status (enum), source_type (string nullable), source_id (int nullable), issued_at (timestamp nullable), due_date (date nullable), notes (text nullable), xero_invoice_id (string nullable), xero_synced_at (timestamp nullable)
- [ ] Soft deletes abilitati
- [ ] Vincolo: invoice_number unique e immutabile dopo issuance
- [ ] Relazione: Invoice belongsTo Customer
- [ ] Relazione: Invoice hasMany InvoiceLine
- [ ] Relazione: Invoice hasMany InvoicePayment
- [ ] Relazione: Invoice hasMany CreditNote
- [ ] invoice_type è IMMUTABILE dopo creazione
- [ ] Typecheck e lint passano

---

#### US-E003: Setup modello InvoiceLine
**Description:** Come Admin, voglio definire le righe fattura con dettaglio fiscale.

**Acceptance Criteria:**
- [ ] Tabella `invoice_lines` con: id, invoice_id (FK), description (string), quantity (decimal 8,2), unit_price (decimal 10,2), tax_rate (decimal 5,2), tax_amount (decimal 10,2), line_total (decimal 10,2), sellable_sku_id (FK nullable), metadata (JSON nullable)
- [ ] Relazione: InvoiceLine belongsTo Invoice
- [ ] Relazione: InvoiceLine belongsTo SellableSku (nullable)
- [ ] line_total = (quantity * unit_price) + tax_amount
- [ ] Lines sono IMMUTABILI dopo invoice issuance
- [ ] Typecheck e lint passano

---

#### US-E004: Setup modello Payment
**Description:** Come Admin, voglio definire il modello per i pagamenti ricevuti.

**Acceptance Criteria:**
- [ ] Tabella `payments` con: id, uuid, payment_reference (string unique), source (enum), amount (decimal 10,2), currency (string), status (enum), reconciliation_status (enum), stripe_payment_intent_id (string nullable unique), stripe_charge_id (string nullable), bank_reference (string nullable), received_at (timestamp), customer_id (FK nullable), metadata (JSON nullable)
- [ ] Soft deletes abilitati
- [ ] Vincolo: payment_reference unique
- [ ] Relazione: Payment belongsTo Customer (nullable - per pagamenti non ancora riconciliati)
- [ ] Relazione: Payment hasMany InvoicePayment
- [ ] Typecheck e lint passano

---

#### US-E005: Setup modello InvoicePayment (pivot)
**Description:** Come Admin, voglio tracciare l'applicazione dei pagamenti alle fatture.

**Acceptance Criteria:**
- [ ] Tabella `invoice_payments` con: id, invoice_id (FK), payment_id (FK), amount_applied (decimal 10,2), applied_at (timestamp), applied_by (FK users nullable)
- [ ] Relazione: InvoicePayment belongsTo Invoice
- [ ] Relazione: InvoicePayment belongsTo Payment
- [ ] Vincolo: sum(amount_applied) per invoice <= invoice.total_amount
- [ ] Vincolo: sum(amount_applied) per payment <= payment.amount
- [ ] Typecheck e lint passano

---

#### US-E006: Setup modello CreditNote
**Description:** Come Admin, voglio definire il modello per le note credito.

**Acceptance Criteria:**
- [ ] Tabella `credit_notes` con: id, uuid, credit_note_number (string unique), invoice_id (FK), customer_id (FK), amount (decimal 10,2), currency (string), reason (text required), status (enum), issued_at (timestamp nullable), applied_at (timestamp nullable), issued_by (FK users nullable), xero_credit_note_id (string nullable), xero_synced_at (timestamp nullable)
- [ ] Soft deletes abilitati
- [ ] Vincolo: reason NOT NULL
- [ ] Relazione: CreditNote belongsTo Invoice
- [ ] Relazione: CreditNote belongsTo Customer
- [ ] Preserva invoice_type dell'invoice originale
- [ ] Typecheck e lint passano

---

#### US-E007: Setup modello Refund
**Description:** Come Admin, voglio definire il modello per i rimborsi.

**Acceptance Criteria:**
- [ ] Tabella `refunds` con: id, uuid, invoice_id (FK), payment_id (FK), credit_note_id (FK nullable), refund_type (enum), method (enum), amount (decimal 10,2), currency (string), status (enum), reason (text required), stripe_refund_id (string nullable unique), bank_reference (string nullable), processed_at (timestamp nullable), processed_by (FK users nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione: Refund belongsTo Invoice
- [ ] Relazione: Refund belongsTo Payment
- [ ] Relazione: Refund belongsTo CreditNote (nullable)
- [ ] Vincolo: invoice e payment devono essere linkati
- [ ] Typecheck e lint passano

---

#### US-E008: Setup modello Subscription
**Description:** Come Admin, voglio definire il modello per gli abbonamenti.

**Acceptance Criteria:**
- [ ] Tabella `subscriptions` con: id, uuid, customer_id (FK), plan_type (enum), plan_name (string), billing_cycle (enum), amount (decimal 10,2), currency (string), status (enum), started_at (date), next_billing_date (date), cancelled_at (date nullable), cancellation_reason (text nullable), stripe_subscription_id (string nullable unique), metadata (JSON nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione: Subscription belongsTo Customer
- [ ] Relazione: Subscription hasMany Invoice (via source polymorphic)
- [ ] Typecheck e lint passano

---

#### US-E009: Setup modello StorageBillingPeriod
**Description:** Come Admin, voglio definire il modello per i periodi di fatturazione storage.

**Acceptance Criteria:**
- [ ] Tabella `storage_billing_periods` con: id, uuid, customer_id (FK), location_id (FK nullable), period_start (date), period_end (date), bottle_count (int), bottle_days (int), unit_rate (decimal 10,4), calculated_amount (decimal 10,2), currency (string), status (enum), invoice_id (FK nullable), calculated_at (timestamp), metadata (JSON nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione: StorageBillingPeriod belongsTo Customer
- [ ] Relazione: StorageBillingPeriod belongsTo Location (nullable)
- [ ] Relazione: StorageBillingPeriod belongsTo Invoice (nullable)
- [ ] bottle_days = sum(bottles * days_stored) nel periodo
- [ ] Typecheck e lint passano

---

#### US-E010: Setup modello StripeWebhook
**Description:** Come Admin, voglio loggare tutti gli webhook Stripe ricevuti.

**Acceptance Criteria:**
- [ ] Tabella `stripe_webhooks` con: id, event_id (string unique), event_type (string), payload (JSON), processed (boolean default false), processed_at (timestamp nullable), error_message (text nullable), created_at
- [ ] NO soft deletes - logs sono immutabili
- [ ] Vincolo: event_id unique per idempotency
- [ ] Typecheck e lint passano

---

#### US-E011: Setup modello XeroSyncLog
**Description:** Come Admin, voglio loggare tutte le sincronizzazioni con Xero.

**Acceptance Criteria:**
- [ ] Tabella `xero_sync_logs` con: id, sync_type (enum), syncable_type (string), syncable_id (int), xero_id (string nullable), status (enum), request_payload (JSON nullable), response_payload (JSON nullable), error_message (text nullable), synced_at (timestamp nullable), retry_count (int default 0)
- [ ] NO soft deletes - logs sono immutabili
- [ ] Relazione: XeroSyncLog morphTo syncable (Invoice, CreditNote, Payment)
- [ ] Typecheck e lint passano

---

#### US-E012: InvoiceService per gestione fatture
**Description:** Come Developer, voglio un service per centralizzare la logica delle fatture.

**Acceptance Criteria:**
- [ ] Service class `InvoiceService` in `app/Services/Finance/`
- [ ] Metodo `createDraft(InvoiceType, Customer, array $lines, ?string $sourceType, ?int $sourceId)`: crea fattura draft
- [ ] Metodo `issue(Invoice)`: transizione draft → issued, genera invoice_number, setta issued_at
- [ ] Metodo `applyPayment(Invoice, Payment, decimal $amount)`: crea InvoicePayment, aggiorna status
- [ ] Metodo `markPaid(Invoice)`: verifica amount_paid >= total_amount, setta status paid
- [ ] Metodo `cancel(Invoice)`: solo draft → cancelled
- [ ] Metodo `getOutstandingAmount(Invoice)`: total_amount - amount_paid
- [ ] Validazioni con eccezioni esplicite
- [ ] Typecheck e lint passano

---

### Sezione 2: Invoice CRUD & UI

#### US-E013: Invoice List in Filament
**Description:** Come Operator Finance, voglio una lista fatture come entry point operativo del modulo.

**Acceptance Criteria:**
- [ ] InvoiceResource in Filament con navigation group "Finance"
- [ ] Lista con colonne: invoice_number, type (badge colorato), customer, amount, status (badge), issue_date, due_date, xero_ref, flags (overdue, disputed)
- [ ] Filtri: invoice_type, status, customer, date range, currency
- [ ] Ricerca per: invoice_number, customer name, xero_invoice_id
- [ ] Tab per status: All, Draft, Issued, Overdue, Paid
- [ ] Indicatore visivo per fatture overdue (rosso)
- [ ] Default landing page per Finance
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E014: Invoice Detail con 5 tabs
**Description:** Come Operator Finance, voglio vedere tutti i dettagli di una fattura organizzati in tabs.

**Acceptance Criteria:**
- [ ] Tab Lines: lista read-only invoice lines con description, qty, unit_price, tax, total
- [ ] Tab Payments: pagamenti applicati con amount, date, source, reference
- [ ] Tab Linked ERP Events: source reference con link (membership, voucher batch, shipping order, storage period, event)
- [ ] Tab Accounting: xero_invoice_id, xero_synced_at, statutory invoice number, GL posting, FX rate
- [ ] Tab Audit: timeline eventi immutabile (status changes, payments, credits)
- [ ] Header: invoice_number, type (locked badge), status, customer, currency, totals
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E015: Invoice actions contestuali
**Description:** Come Operator Finance, voglio azioni contestuali basate sullo status della fattura.

**Acceptance Criteria:**
- [ ] Azione "Issue" visibile solo se status = draft
- [ ] Azione "Record Bank Payment" visibile solo se status = issued o partially_paid
- [ ] Azione "Create Credit Note" visibile solo se status = issued, paid, partially_paid
- [ ] Azione "Cancel" visibile solo se status = draft
- [ ] Ogni azione richiede conferma
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E016: Invoice issuance flow
**Description:** Come Operator Finance, voglio emettere una fattura draft con validazione.

**Acceptance Criteria:**
- [ ] Azione Issue genera invoice_number sequenziale (formato: INV-YYYY-NNNNNN)
- [ ] issued_at = now()
- [ ] Validazione: almeno una invoice line presente
- [ ] Validazione: total_amount > 0
- [ ] Post-issuance: lines diventano immutabili
- [ ] Post-issuance: trigger sync Xero
- [ ] Audit log dell'evento
- [ ] Typecheck e lint passano

---

#### US-E017: Invoice immutability enforcement
**Description:** Come Developer, voglio che le fatture siano immutabili dopo issuance.

**Acceptance Criteria:**
- [ ] invoice_type non modificabile MAI (neanche draft)
- [ ] invoice_lines non modificabili dopo status != draft
- [ ] subtotal, tax_amount, total_amount non modificabili dopo issuance
- [ ] Tentativo di modifica genera eccezione esplicita
- [ ] UI nasconde edit fields per fatture issued
- [ ] Test che verifica immutabilità
- [ ] Typecheck e lint passano

---

#### US-E018: Create Invoice draft (manual)
**Description:** Come Operator Finance, voglio creare manualmente una fattura draft per casi eccezionali.

**Acceptance Criteria:**
- [ ] Form Create Invoice con: customer (select), invoice_type (select), currency, due_date, notes
- [ ] Step 2: aggiunta lines con description, quantity, unit_price, tax_rate
- [ ] Tax amount calcolato automaticamente
- [ ] Totali calcolati in real-time
- [ ] Warning: "Manual invoices should be exceptional. Most invoices are auto-generated from ERP events."
- [ ] Salva come draft (requires Issue per attivare)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E019: Invoice PDF generation
**Description:** Come Operator Finance, voglio generare PDF della fattura.

**Acceptance Criteria:**
- [ ] Azione "Download PDF" visibile per fatture issued/paid
- [ ] PDF include: header con logo, invoice details, lines table, totals, payment info, footer
- [ ] Template conforme a requisiti fiscali
- [ ] Filename: {invoice_number}.pdf
- [ ] Typecheck e lint passano

---

#### US-E020: Invoice email sending
**Description:** Come Operator Finance, voglio inviare fattura via email al cliente.

**Acceptance Criteria:**
- [ ] Azione "Send to Customer" visibile per fatture issued
- [ ] Email include PDF attachment
- [ ] Template email configurabile
- [ ] Log invio in audit trail
- [ ] Typecheck e lint passano

---

#### US-E021: Overdue invoice detection
**Description:** Come Developer, voglio identificare automaticamente le fatture scadute.

**Acceptance Criteria:**
- [ ] Job schedulato daily per identificare fatture overdue
- [ ] Fattura overdue: status = issued, due_date < today
- [ ] Campo is_overdue (computed) su Invoice
- [ ] Badge visivo rosso in lista
- [ ] Filtro "Overdue" in lista fatture
- [ ] Typecheck e lint passano

---

#### US-E022: Invoice currency handling
**Description:** Come Operator Finance, voglio gestire fatture in diverse valute.

**Acceptance Criteria:**
- [ ] Currency field obbligatorio (default EUR)
- [ ] Currency non modificabile dopo issuance
- [ ] Tutti gli amounts in same currency
- [ ] Exchange rate snapshot at issuance (campo fx_rate_at_issuance)
- [ ] UI mostra currency symbol/code
- [ ] Typecheck e lint passano

---

#### US-E023: Invoice due date management
**Description:** Come Operator Finance, voglio gestire le scadenze delle fatture.

**Acceptance Criteria:**
- [ ] Due date obbligatorio per fatture non-immediate (INV0, INV3)
- [ ] Due date opzionale per INV1, INV2, INV4 (pagamento immediato)
- [ ] Default due date: +30 giorni da issuance (configurabile per tipo)
- [ ] Due date modificabile solo in draft
- [ ] Typecheck e lint passano

---

#### US-E024: Invoice search globale
**Description:** Come Operator Finance, voglio cercare fatture rapidamente.

**Acceptance Criteria:**
- [ ] Global search in Finance section
- [ ] Ricerca per: invoice_number, customer name/email, xero_id
- [ ] Risultati mostrano: invoice_number, customer, amount, status
- [ ] Click apre Invoice Detail
- [ ] Typecheck e lint passano

---

#### US-E025: Invoice bulk actions
**Description:** Come Operator Finance, voglio eseguire azioni su più fatture.

**Acceptance Criteria:**
- [ ] Checkbox selection in lista
- [ ] Bulk action "Export to CSV"
- [ ] Bulk action "Retry Xero Sync" (per failed syncs)
- [ ] NO bulk issue o bulk payment (troppo rischioso)
- [ ] Typecheck e lint passano

---

### Sezione 3: INV0 - Membership & Service

#### US-E026: INV0 auto-generation da subscription
**Description:** Come Developer, voglio che INV0 venga generata automaticamente da eventi subscription.

**Acceptance Criteria:**
- [ ] Listener per evento SubscriptionBillingDue
- [ ] Crea Invoice draft con type = INV0
- [ ] Source reference: subscription_id
- [ ] Invoice lines da subscription plan details
- [ ] Auto-issue se configurato
- [ ] Typecheck e lint passano

---

#### US-E027: INV0 subscription billing cycle
**Description:** Come Developer, voglio generare INV0 secondo il ciclo di billing.

**Acceptance Criteria:**
- [ ] Job schedulato per check subscriptions con next_billing_date = today
- [ ] Per ogni subscription: genera INV0
- [ ] Aggiorna next_billing_date secondo billing_cycle
- [ ] Log billing event
- [ ] Typecheck e lint passano

---

#### US-E028: INV0 membership types support
**Description:** Come Operator Finance, voglio distinguere INV0 per tipo membership.

**Acceptance Criteria:**
- [ ] Invoice lines description include plan_name
- [ ] Metadata JSON include membership tier details
- [ ] Linked ERP Events tab mostra subscription details
- [ ] Typecheck e lint passano

---

#### US-E029: INV0 pro-rata calculation
**Description:** Come Developer, voglio supportare calcoli pro-rata per membership mid-cycle.

**Acceptance Criteria:**
- [ ] Metodo `calculateProRata(Subscription, startDate, endDate)` in SubscriptionBillingService
- [ ] Pro-rata per: new signups, cancellations, upgrades
- [ ] Invoice line description indica periodo pro-rata
- [ ] Typecheck e lint passano

---

#### US-E030: INV0 suspension on non-payment
**Description:** Come Developer, voglio sospendere membership per INV0 non pagate.

**Acceptance Criteria:**
- [ ] Job schedulato: se INV0 overdue > X giorni (configurabile)
- [ ] Setta subscription.status = suspended
- [ ] Emette evento SubscriptionSuspended (per Module K eligibility)
- [ ] Warning visibile in customer view
- [ ] Typecheck e lint passano

---

### Sezione 4: INV1 - Voucher Sale

#### US-E031: INV1 auto-generation da sale confirmation
**Description:** Come Developer, voglio che INV1 venga generata automaticamente da conferma vendita Module A.

**Acceptance Criteria:**
- [ ] Listener per evento VoucherSaleConfirmed (da Module A)
- [ ] Crea Invoice con type = INV1
- [ ] Source reference: voucher_batch_id o sale_order_id
- [ ] Invoice lines da sellable_sku + quantity + price
- [ ] Status = issued (immediate)
- [ ] Typecheck e lint passano

---

#### US-E032: INV1 pricing from Module S
**Description:** Come Developer, voglio che INV1 usi pricing da Module S.

**Acceptance Criteria:**
- [ ] Invoice line unit_price = pricing da Module S (price snapshot at sale)
- [ ] Tax calculation secondo customer geography e product type
- [ ] Metadata include pricing_snapshot_id
- [ ] Typecheck e lint passano

---

#### US-E033: INV1 payment confirmation trigger
**Description:** Come Developer, voglio che payment confirmation di INV1 triggeri voucher issuance.

**Acceptance Criteria:**
- [ ] Quando INV1 diventa paid
- [ ] Emette evento InvoicePaid con invoice_type = INV1
- [ ] Module A ascolta e crea vouchers
- [ ] Log correlation tra invoice e vouchers
- [ ] Typecheck e lint passano

---

#### US-E034: INV1 multi-item support
**Description:** Come Developer, voglio supportare INV1 con multiple sellable SKUs.

**Acceptance Criteria:**
- [ ] Multiple invoice lines per INV1
- [ ] Ogni line linkabile a sellable_sku diverso
- [ ] Totali aggregano tutte le lines
- [ ] Typecheck e lint passano

---

#### US-E035: INV1 immediate payment expectation
**Description:** Come Operator Finance, voglio che INV1 abbia aspettativa pagamento immediato.

**Acceptance Criteria:**
- [ ] INV1 default: no due_date (pagamento atteso immediato)
- [ ] Status transitions rapide: issued → paid (via Stripe)
- [ ] Alert se INV1 issued > 24h senza payment
- [ ] Typecheck e lint passano

---

### Sezione 5: INV2 - Shipping & Redemption

#### US-E036: INV2 auto-generation da shipping execution
**Description:** Come Developer, voglio che INV2 venga generata quando Module C esegue shipment.

**Acceptance Criteria:**
- [ ] Listener per evento ShipmentExecuted (da Module C)
- [ ] Crea Invoice con type = INV2
- [ ] Source reference: shipping_order_id
- [ ] Invoice lines: shipping fees, handling fees, duties/taxes
- [ ] Typecheck e lint passano

---

#### US-E037: INV2 shipping cost calculation
**Description:** Come Developer, voglio che INV2 includa costi shipping accurati.

**Acceptance Criteria:**
- [ ] Invoice lines separate per: base shipping, insurance, packaging
- [ ] Duties e taxes in lines separate
- [ ] Metodo `calculateShippingCosts(ShippingOrder)` in InvoiceService
- [ ] Typecheck e lint passano

---

#### US-E038: INV2 VAT/duty handling
**Description:** Come Developer, voglio gestire VAT e duties su INV2 secondo destinazione.

**Acceptance Criteria:**
- [ ] Tax rate determinato da shipping destination country
- [ ] Duties calcolati se cross-border
- [ ] Tax amount breakdown in invoice detail
- [ ] Typecheck e lint passano

---

#### US-E039: INV2 redemption fee support
**Description:** Come Developer, voglio supportare redemption fees su INV2.

**Acceptance Criteria:**
- [ ] Invoice line per redemption fee (se applicabile)
- [ ] Fee amount da Module S pricing
- [ ] Distingue: shipping-only vs redemption+shipping
- [ ] Typecheck e lint passano

---

#### US-E040: INV2 multi-shipment aggregation
**Description:** Come Developer, voglio supportare aggregazione di più shipments in singola INV2.

**Acceptance Criteria:**
- [ ] Source reference può essere multiple shipping_order_ids (JSON)
- [ ] Invoice lines separate per shipment
- [ ] Totali aggregano tutti gli shipments
- [ ] Linked ERP Events mostra tutti gli shipments
- [ ] Typecheck e lint passano

---

### Sezione 6: INV3 - Storage Fee

#### US-E041: INV3 batch generation da storage billing
**Description:** Come Developer, voglio generare INV3 batch alla fine del periodo di billing storage.

**Acceptance Criteria:**
- [ ] Job schedulato per fine periodo (monthly/quarterly)
- [ ] Per ogni customer con storage usage: genera StorageBillingPeriod
- [ ] Crea INV3 per ogni customer con usage > 0
- [ ] Source reference: storage_billing_period_id
- [ ] Typecheck e lint passano

---

#### US-E042: INV3 usage calculation
**Description:** Come Developer, voglio calcolare usage storage accuratamente.

**Acceptance Criteria:**
- [ ] Metodo `calculateUsage(Customer, periodStart, periodEnd)` in StorageBillingService
- [ ] Calcola bottle-days: sum(bottles_stored * days)
- [ ] Considera: inbound, outbound, transfers durante periodo
- [ ] Snapshot inventory at period boundaries
- [ ] Typecheck e lint passano

---

#### US-E043: INV3 rate tiers support
**Description:** Come Developer, voglio supportare rate tiers per storage.

**Acceptance Criteria:**
- [ ] Rate può variare per: volume tier, customer tier, location
- [ ] Invoice line description include rate tier applicato
- [ ] Metodo `getApplicableRate(Customer, Location, bottleCount)`
- [ ] Typecheck e lint passano

---

#### US-E044: INV3 location breakdown
**Description:** Come Operator Finance, voglio vedere breakdown per location su INV3.

**Acceptance Criteria:**
- [ ] Invoice lines separate per location (se multiple)
- [ ] Ogni line: location name, bottle-days, rate, amount
- [ ] Summary totals
- [ ] Typecheck e lint passano

---

#### US-E045: INV3 custody block on non-payment
**Description:** Come Developer, voglio bloccare custody operations per INV3 non pagate.

**Acceptance Criteria:**
- [ ] Se INV3 overdue > X giorni (configurabile)
- [ ] Setta StorageBillingPeriod.status = blocked
- [ ] Emette evento StoragePaymentBlocked (per Module B/C eligibility)
- [ ] Warning visibile in customer view
- [ ] Block rimuovibile solo dopo payment
- [ ] Typecheck e lint passano

---

#### US-E046: INV3 preview before generation
**Description:** Come Operator Finance, voglio preview delle INV3 prima della generazione.

**Acceptance Criteria:**
- [ ] Page "Storage Billing Preview" in Finance
- [ ] Mostra projected invoices per periodo corrente
- [ ] Breakdown per customer e location
- [ ] Azione "Generate Invoices" per conferma
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 7: INV4 - Service & Events

#### US-E047: INV4 auto-generation da event booking
**Description:** Come Developer, voglio che INV4 venga generata da event booking.

**Acceptance Criteria:**
- [ ] Listener per evento EventBookingConfirmed
- [ ] Crea Invoice con type = INV4
- [ ] Source reference: event_booking_id
- [ ] Invoice lines: event fee, service fees
- [ ] Typecheck e lint passano

---

#### US-E048: INV4 service fee types
**Description:** Come Developer, voglio supportare diversi tipi di service fees su INV4.

**Acceptance Criteria:**
- [ ] Support per: event attendance, tasting fees, consultation, other services
- [ ] Invoice line description specifica il tipo
- [ ] Metadata include service_type
- [ ] Typecheck e lint passano

---

#### US-E049: INV4 manual creation support
**Description:** Come Operator Finance, voglio creare INV4 manualmente per servizi ad-hoc.

**Acceptance Criteria:**
- [ ] Create Invoice form permette INV4 senza source reference obbligatorio
- [ ] Warning: "INV4 without event reference is for ad-hoc services only"
- [ ] Reason/description obbligatoria
- [ ] Typecheck e lint passano

---

#### US-E050: INV4 event cost pass-through
**Description:** Come Developer, voglio che INV4 non includa costi evento (only pass-through).

**Acceptance Criteria:**
- [ ] Invariante: INV4 lines non includono costi vino/inventory
- [ ] Solo: attendance fees, service fees, handling
- [ ] Validation blocca lines con sellable_sku type = bottle
- [ ] Typecheck e lint passano

---

### Sezione 8: Payment Management

#### US-E051: Payment List in Filament
**Description:** Come Operator Finance, voglio una lista pagamenti per monitoraggio.

**Acceptance Criteria:**
- [ ] PaymentResource in Filament con navigation group "Finance"
- [ ] Lista con colonne: payment_reference, source (badge), amount, currency, status, reconciliation_status, customer, received_at
- [ ] Filtri: source, status, reconciliation_status, date range
- [ ] Ricerca per: payment_reference, stripe_payment_intent_id, customer
- [ ] Tab: All, Pending Reconciliation, Confirmed
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E052: Payment Detail view
**Description:** Come Operator Finance, voglio vedere dettagli pagamento.

**Acceptance Criteria:**
- [ ] Header: payment_reference, source, amount, status
- [ ] Section 1 - Source Details: stripe_payment_intent_id, stripe_charge_id, OR bank_reference
- [ ] Section 2 - Applied Invoices: lista InvoicePayments con amount_applied
- [ ] Section 3 - Reconciliation: status, mismatched details (se presente)
- [ ] Section 4 - Metadata: raw metadata JSON
- [ ] Section 5 - Audit: timeline eventi
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E053: Stripe payment auto-reconciliation
**Description:** Come Developer, voglio che pagamenti Stripe si riconcilino automaticamente.

**Acceptance Criteria:**
- [ ] Webhook payment_intent.succeeded crea Payment con status confirmed
- [ ] Metodo `autoReconcile(Payment)`: cerca invoice con matching amount e customer
- [ ] Se match unico: applica pagamento, setta reconciliation_status = matched
- [ ] Se no match o multi-match: setta reconciliation_status = pending
- [ ] Typecheck e lint passano

---

#### US-E054: Bank transfer manual reconciliation
**Description:** Come Operator Finance, voglio riconciliare manualmente pagamenti bank.

**Acceptance Criteria:**
- [ ] Azione "Apply to Invoice" su Payment pending
- [ ] Form: select invoice (filtrato per customer se presente), amount to apply
- [ ] Validazione: amount <= outstanding on invoice
- [ ] Partial application supportato
- [ ] Audit log dell'applicazione
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E055: Payment mismatch resolution
**Description:** Come Operator Finance, voglio risolvere payment mismatches.

**Acceptance Criteria:**
- [ ] Pagamenti con reconciliation_status = mismatched evidenziati
- [ ] Mismatch reasons: amount difference, customer mismatch, duplicate
- [ ] Azioni: Force Match, Create Exception, Refund
- [ ] Ogni risoluzione richiede reason
- [ ] Audit log della risoluzione
- [ ] Typecheck e lint passano

---

#### US-E056: Record Bank Payment action
**Description:** Come Operator Finance, voglio registrare pagamenti bank manualmente.

**Acceptance Criteria:**
- [ ] Azione "Record Bank Payment" in Invoice Detail
- [ ] Form: amount, bank_reference, received_date
- [ ] Crea Payment con source = bank_transfer
- [ ] Auto-apply all'invoice corrente
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E057: Payment split across invoices
**Description:** Come Operator Finance, voglio applicare un payment a multiple invoices.

**Acceptance Criteria:**
- [ ] Azione "Apply to Multiple Invoices" su Payment
- [ ] Form: lista invoices con amount input per ciascuna
- [ ] Validazione: sum amounts <= payment.amount
- [ ] Crea multiple InvoicePayment records
- [ ] Typecheck e lint passano

---

#### US-E058: Overpayment handling
**Description:** Come Developer, voglio gestire overpayments correttamente.

**Acceptance Criteria:**
- [ ] Se payment.amount > invoice.outstanding
- [ ] Opzione 1: Apply partial (leave remainder)
- [ ] Opzione 2: Apply full e crea customer credit
- [ ] Customer credit visibile in customer finance view
- [ ] Typecheck e lint passano

---

#### US-E059: Payment failure handling
**Description:** Come Developer, voglio gestire payment failures da Stripe.

**Acceptance Criteria:**
- [ ] Webhook payment_intent.payment_failed crea/aggiorna Payment con status = failed
- [ ] Log failure reason (metadata)
- [ ] Invoice resta issued (non pagata)
- [ ] Notifica interna per follow-up
- [ ] Typecheck e lint passano

---

#### US-E060: Duplicate payment detection
**Description:** Come Developer, voglio prevenire pagamenti duplicati.

**Acceptance Criteria:**
- [ ] Stripe: stripe_payment_intent_id unique constraint
- [ ] Bank: warning se same amount + same customer + same day
- [ ] UI mostra potential duplicates
- [ ] Operator può confermare o reject
- [ ] Typecheck e lint passano

---

#### US-E061: PaymentService per gestione pagamenti
**Description:** Come Developer, voglio un service per centralizzare logica pagamenti.

**Acceptance Criteria:**
- [ ] Service class `PaymentService` in `app/Services/Finance/`
- [ ] Metodo `createFromStripe(PaymentIntent)`: crea Payment da Stripe webhook
- [ ] Metodo `createBankPayment(amount, reference, customer)`: crea Payment manuale
- [ ] Metodo `applyToInvoice(Payment, Invoice, amount)`: crea InvoicePayment
- [ ] Metodo `autoReconcile(Payment)`: tenta match automatico
- [ ] Metodo `markReconciled(Payment, status)`: aggiorna reconciliation
- [ ] Typecheck e lint passano

---

#### US-E062: Payment audit trail
**Description:** Come Compliance Officer, voglio audit trail completo per pagamenti.

**Acceptance Criteria:**
- [ ] Trait Auditable su Payment, InvoicePayment
- [ ] Eventi loggati: creation, application, reconciliation, status_change
- [ ] Audit visible in Payment Detail
- [ ] Immutable logs
- [ ] Typecheck e lint passano

---

### Sezione 9: Credit Notes & Refunds

#### US-E063: Credit Note List in Filament
**Description:** Come Operator Finance, voglio una lista credit notes.

**Acceptance Criteria:**
- [ ] CreditNoteResource in Filament con navigation group "Finance"
- [ ] Lista: credit_note_number, invoice_number, customer, amount, status, issued_at, reason (truncated)
- [ ] Filtri: status, date range, customer
- [ ] Ricerca per: credit_note_number, invoice_number
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E064: Credit Note Detail view
**Description:** Come Operator Finance, voglio vedere dettagli credit note.

**Acceptance Criteria:**
- [ ] Header: credit_note_number, amount, status
- [ ] Section 1 - Original Invoice: link a invoice, type, amount
- [ ] Section 2 - Reason: full reason text
- [ ] Section 3 - Application: applied_at, related refund (if any)
- [ ] Section 4 - Accounting: xero_credit_note_id, sync status
- [ ] Section 5 - Audit: timeline
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E065: Create Credit Note from Invoice
**Description:** Come Operator Finance, voglio creare credit note da una fattura.

**Acceptance Criteria:**
- [ ] Azione "Create Credit Note" in Invoice Detail
- [ ] Form: amount (max = invoice total), reason (required textarea)
- [ ] Validazione: amount > 0 e <= outstanding
- [ ] Credit note created in draft status
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E066: Credit Note issuance
**Description:** Come Operator Finance, voglio emettere una credit note.

**Acceptance Criteria:**
- [ ] Azione "Issue" su credit note draft
- [ ] Genera credit_note_number sequenziale (formato: CN-YYYY-NNNNNN)
- [ ] Setta issued_at = now()
- [ ] Trigger sync Xero
- [ ] Aggiorna invoice status se fully credited
- [ ] Typecheck e lint passano

---

#### US-E067: Credit Note preserva invoice type
**Description:** Come Developer, voglio che credit note preservi il tipo della fattura originale.

**Acceptance Criteria:**
- [ ] Credit note metadata include original_invoice_type
- [ ] Reporting può filtrare per original invoice type
- [ ] Invariante: credit note non può cambiare invoice type
- [ ] Typecheck e lint passano

---

#### US-E068: Refund List in Filament
**Description:** Come Operator Finance, voglio una lista refunds.

**Acceptance Criteria:**
- [ ] RefundResource in Filament con navigation group "Finance"
- [ ] Lista: refund_id, invoice_number, amount, method, status, processed_at
- [ ] Filtri: method, status, date range
- [ ] Ricerca per: invoice_number, stripe_refund_id
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E069: Refund Detail view
**Description:** Come Operator Finance, voglio vedere dettagli refund.

**Acceptance Criteria:**
- [ ] Header: refund_id, amount, method, status
- [ ] Section 1 - Linked Invoice: link a invoice
- [ ] Section 2 - Linked Payment: link a payment
- [ ] Section 3 - Linked Credit Note: link se presente
- [ ] Section 4 - Processing: stripe_refund_id o bank_reference, processed_at
- [ ] Section 5 - Reason: full reason text
- [ ] Section 6 - Audit: timeline
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E070: Create Refund from Invoice
**Description:** Come Operator Finance, voglio creare refund da una fattura pagata.

**Acceptance Criteria:**
- [ ] Azione "Refund" in Invoice Detail (solo se paid)
- [ ] Form: refund_type (full/partial), amount (if partial), payment to refund (select), method, reason (required)
- [ ] Validazione: payment linkato all'invoice
- [ ] Validazione: amount <= payment applied amount
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E071: Stripe refund processing
**Description:** Come Developer, voglio processare refund via Stripe.

**Acceptance Criteria:**
- [ ] Se method = stripe e payment ha stripe_charge_id
- [ ] Chiama Stripe Refund API
- [ ] Salva stripe_refund_id
- [ ] Aggiorna status = processed dopo conferma
- [ ] Gestione errori con retry
- [ ] Typecheck e lint passano

---

#### US-E072: Bank refund tracking
**Description:** Come Operator Finance, voglio tracciare refund via bank transfer.

**Acceptance Criteria:**
- [ ] Se method = bank_transfer
- [ ] Form richiede bank_reference (post-processing)
- [ ] Status flow: pending → processed (dopo inserimento reference)
- [ ] Azione "Mark Processed" con bank_reference input
- [ ] Typecheck e lint passano

---

#### US-E073: Refund operational warning
**Description:** Come Developer, voglio mostrare warning che refund non revoca operazioni.

**Acceptance Criteria:**
- [ ] Warning prominente in Create Refund form
- [ ] Testo: "Refunding does not automatically reverse operational effects (e.g., voucher cancellation). Coordinate with Operations if needed."
- [ ] Checkbox conferma: "I understand this is a financial transaction only"
- [ ] Typecheck e lint passano

---

#### US-E074: CreditNoteService per gestione credit notes
**Description:** Come Developer, voglio un service per gestire credit notes.

**Acceptance Criteria:**
- [ ] Service class `CreditNoteService` in `app/Services/Finance/`
- [ ] Metodo `createDraft(Invoice, amount, reason)`: crea credit note draft
- [ ] Metodo `issue(CreditNote)`: emette credit note
- [ ] Metodo `apply(CreditNote)`: applica a invoice
- [ ] Typecheck e lint passano

---

#### US-E075: RefundService per gestione refunds
**Description:** Come Developer, voglio un service per gestire refunds.

**Acceptance Criteria:**
- [ ] Service class `RefundService` in `app/Services/Finance/`
- [ ] Metodo `create(Invoice, Payment, type, amount, method, reason)`: crea refund
- [ ] Metodo `processStripeRefund(Refund)`: chiama Stripe API
- [ ] Metodo `markProcessed(Refund, reference)`: per bank refunds
- [ ] Typecheck e lint passano

---

### Sezione 10: Customer Financial View

#### US-E076: Customer Financial Dashboard
**Description:** Come Operator Finance, voglio una vista finanziaria completa per cliente.

**Acceptance Criteria:**
- [ ] Page "Customer Finance" in Finance section
- [ ] Select customer (autocomplete)
- [ ] Mostra: balance summary, open invoices, payment history, credits
- [ ] Link a Customer Resource (Module K)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E077: Customer Open Invoices tab
**Description:** Come Operator Finance, voglio vedere fatture aperte di un cliente.

**Acceptance Criteria:**
- [ ] Tab "Open Invoices" in Customer Finance View
- [ ] Lista: invoice_number, type, amount, outstanding, due_date, status
- [ ] Sorted by due_date (oldest first)
- [ ] Total outstanding prominente
- [ ] Link a Invoice Detail
- [ ] Typecheck e lint passano

---

#### US-E078: Customer Payment History tab
**Description:** Come Operator Finance, voglio vedere storico pagamenti di un cliente.

**Acceptance Criteria:**
- [ ] Tab "Payment History" in Customer Finance View
- [ ] Lista: payment_reference, amount, date, applied_to (invoices)
- [ ] Sorted by date (newest first)
- [ ] Filter by date range
- [ ] Typecheck e lint passano

---

#### US-E079: Customer Credits & Refunds tab
**Description:** Come Operator Finance, voglio vedere credit notes e refunds di un cliente.

**Acceptance Criteria:**
- [ ] Tab "Credits & Refunds" in Customer Finance View
- [ ] Section 1: Credit Notes list
- [ ] Section 2: Refunds list
- [ ] Total credits summary
- [ ] Typecheck e lint passano

---

#### US-E080: Customer Exposure & Limits tab
**Description:** Come Operator Finance, voglio vedere esposizione finanziaria cliente.

**Acceptance Criteria:**
- [ ] Tab "Exposure & Limits" in Customer Finance View
- [ ] Metrics: total outstanding, overdue amount, credit limit (se definito), available credit
- [ ] Chart: exposure trend over time
- [ ] Typecheck e lint passano

---

#### US-E081: Customer Eligibility Signals tab
**Description:** Come Operator Finance, voglio capire perché un cliente è bloccato.

**Acceptance Criteria:**
- [ ] Tab "Eligibility Signals" in Customer Finance View
- [ ] Lista blocchi attivi: payment_blocked (INV0 overdue), custody_blocked (INV3 overdue)
- [ ] Per ogni blocco: reason, invoice reference, how to resolve
- [ ] Messaggio: "This view shows FINANCIAL eligibility only. See Module K for full eligibility."
- [ ] Typecheck e lint passano

---

#### US-E082: CustomerFinanceService per vista cliente
**Description:** Come Developer, voglio un service per aggregare dati finanziari cliente.

**Acceptance Criteria:**
- [ ] Service class `CustomerFinanceService` in `app/Services/Finance/`
- [ ] Metodo `getOpenInvoices(Customer)`: ritorna invoices non pagate
- [ ] Metodo `getTotalOutstanding(Customer)`: somma outstanding
- [ ] Metodo `getOverdueAmount(Customer)`: somma overdue
- [ ] Metodo `getPaymentHistory(Customer, dateRange)`: lista payments
- [ ] Metodo `getEligibilitySignals(Customer)`: lista blocchi attivi
- [ ] Typecheck e lint passano

---

### Sezione 11: Subscriptions & Storage Billing

#### US-E083: Subscription List in Filament
**Description:** Come Operator Finance, voglio gestire subscriptions.

**Acceptance Criteria:**
- [ ] SubscriptionResource in Filament con navigation group "Finance"
- [ ] Lista: subscription_id, customer, plan_name, billing_cycle, amount, status, next_billing_date
- [ ] Filtri: plan_type, status, billing_cycle
- [ ] Ricerca per: customer name
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E084: Subscription Detail view
**Description:** Come Operator Finance, voglio vedere dettagli subscription.

**Acceptance Criteria:**
- [ ] Header: subscription_id, customer, plan_name, status
- [ ] Section 1 - Plan Details: type, billing_cycle, amount, started_at
- [ ] Section 2 - Billing: next_billing_date, payment_method (Stripe)
- [ ] Section 3 - Invoices: lista INV0 generate da questa subscription
- [ ] Section 4 - Actions: Suspend, Resume, Cancel
- [ ] Section 5 - Audit: timeline
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E085: Subscription status transitions
**Description:** Come Developer, voglio gestire transizioni status subscription.

**Acceptance Criteria:**
- [ ] Transizioni valide: active → suspended, suspended → active, active → cancelled, suspended → cancelled
- [ ] Suspended: no billing, customer blocked
- [ ] Cancelled: terminale, no ulteriori billing
- [ ] Ogni transizione logga user_id, timestamp, reason
- [ ] Typecheck e lint passano

---

#### US-E086: SubscriptionBillingService
**Description:** Come Developer, voglio un service per billing subscriptions.

**Acceptance Criteria:**
- [ ] Service class `SubscriptionBillingService` in `app/Services/Finance/`
- [ ] Metodo `getSubscriptionsDue()`: ritorna subscriptions con next_billing_date = today
- [ ] Metodo `generateInvoice(Subscription)`: crea INV0
- [ ] Metodo `calculateProRata(Subscription, start, end)`: calcola importo pro-rata
- [ ] Metodo `advanceNextBillingDate(Subscription)`: aggiorna next_billing_date
- [ ] Typecheck e lint passano

---

#### US-E087: Storage Billing List in Filament
**Description:** Come Operator Finance, voglio vedere periodi billing storage.

**Acceptance Criteria:**
- [ ] StorageBillingResource in Filament con navigation group "Finance"
- [ ] Lista: period, customer, bottle_count, bottle_days, amount, status, invoice (link)
- [ ] Filtri: period, status, customer
- [ ] Tab: Current Period, Past Periods
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E088: Storage Billing Detail view
**Description:** Come Operator Finance, voglio vedere dettagli periodo billing storage.

**Acceptance Criteria:**
- [ ] Header: period (start-end), customer, status
- [ ] Section 1 - Usage: bottle_count, bottle_days, rate, calculated_amount
- [ ] Section 2 - Location Breakdown: se multiple locations
- [ ] Section 3 - Invoice: link se generata
- [ ] Section 4 - Calculation Details: methodology, snapshots
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E089: StorageBillingService
**Description:** Come Developer, voglio un service per billing storage.

**Acceptance Criteria:**
- [ ] Service class `StorageBillingService` in `app/Services/Finance/`
- [ ] Metodo `calculatePeriod(Customer, start, end)`: calcola usage e amount
- [ ] Metodo `getBottleDays(Customer, start, end)`: calcola bottle-days
- [ ] Metodo `getApplicableRate(Customer, Location, volume)`: ritorna rate
- [ ] Metodo `generatePeriods(periodStart, periodEnd)`: genera StorageBillingPeriod per tutti i customers
- [ ] Metodo `generateInvoices()`: crea INV3 per periodi pending
- [ ] Typecheck e lint passano

---

#### US-E090: Storage billing period configuration
**Description:** Come Admin, voglio configurare periodi billing storage.

**Acceptance Criteria:**
- [ ] Config per billing_cycle: monthly, quarterly
- [ ] Config per rate_tiers (volume-based)
- [ ] Config per minimum_charge
- [ ] Stored in config file o database settings
- [ ] Typecheck e lint passano

---

#### US-E091: Storage billing run job
**Description:** Come Developer, voglio un job schedulato per billing storage.

**Acceptance Criteria:**
- [ ] Job `GenerateStorageBillingJob`
- [ ] Triggered: first day of new period
- [ ] Creates StorageBillingPeriod for all customers with storage
- [ ] Optionally auto-generates INV3
- [ ] Log job execution
- [ ] Typecheck e lint passano

---

#### US-E092: Storage billing manual trigger
**Description:** Come Operator Finance, voglio triggerare billing storage manualmente.

**Acceptance Criteria:**
- [ ] Azione "Generate Billing" in Storage Billing section
- [ ] Select period (start, end)
- [ ] Preview affected customers e amounts
- [ ] Confirm to generate
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 12: Integrations (Stripe & Xero)

#### US-E093: Stripe webhook handler
**Description:** Come Developer, voglio processare webhook Stripe con idempotency.

**Acceptance Criteria:**
- [ ] Endpoint POST /api/webhooks/stripe
- [ ] Verifica Stripe signature
- [ ] Crea StripeWebhook record
- [ ] Idempotency: skip se event_id già processato
- [ ] Dispatch job per processing asincrono
- [ ] Return 200 immediately
- [ ] Typecheck e lint passano

---

#### US-E094: Stripe webhook event processing
**Description:** Come Developer, voglio processare diversi tipi di eventi Stripe.

**Acceptance Criteria:**
- [ ] Handler per: payment_intent.succeeded, payment_intent.payment_failed, charge.refunded, charge.dispute.created
- [ ] payment_intent.succeeded: crea/aggiorna Payment, auto-reconcile
- [ ] payment_intent.payment_failed: log failure
- [ ] charge.refunded: crea Refund record
- [ ] charge.dispute.created: flag invoice as disputed
- [ ] Typecheck e lint passano

---

#### US-E095: Stripe integration health monitoring
**Description:** Come Operator Finance, voglio monitorare salute integrazione Stripe.

**Acceptance Criteria:**
- [ ] Page "Integrations Health" in Finance
- [ ] Section Stripe: last webhook received, failed events count, pending reconciliations
- [ ] Alert se no webhooks in last hour
- [ ] List recent failed events con retry action
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E096: Stripe failed webhook retry
**Description:** Come Operator Finance, voglio riprovare webhook Stripe falliti.

**Acceptance Criteria:**
- [ ] Lista failed webhooks (processed = false, error_message not null)
- [ ] Azione "Retry" per singolo webhook
- [ ] Azione "Retry All Failed" per bulk
- [ ] Log retry attempts
- [ ] Typecheck e lint passano

---

#### US-E097: StripeIntegrationService
**Description:** Come Developer, voglio un service per integrazione Stripe.

**Acceptance Criteria:**
- [ ] Service class `StripeIntegrationService` in `app/Services/Finance/`
- [ ] Metodo `processWebhook(StripeWebhook)`: dispatch appropriate handler
- [ ] Metodo `handlePaymentSucceeded(PaymentIntent)`: crea Payment
- [ ] Metodo `handlePaymentFailed(PaymentIntent)`: log failure
- [ ] Metodo `processRefund(StripeRefund)`: crea Refund record
- [ ] Metodo `getIntegrationHealth()`: ritorna health metrics
- [ ] Typecheck e lint passano

---

#### US-E098: Xero invoice sync
**Description:** Come Developer, voglio sincronizzare fatture emesse con Xero.

**Acceptance Criteria:**
- [ ] Quando invoice.status = issued
- [ ] Crea XeroSyncLog con sync_type = invoice
- [ ] Chiama Xero API per creare invoice
- [ ] Salva xero_invoice_id su Invoice
- [ ] Gestione errori con retry
- [ ] Typecheck e lint passano

---

#### US-E099: Xero credit note sync
**Description:** Come Developer, voglio sincronizzare credit notes con Xero.

**Acceptance Criteria:**
- [ ] Quando credit_note.status = issued
- [ ] Crea XeroSyncLog con sync_type = credit_note
- [ ] Chiama Xero API per creare credit note
- [ ] Salva xero_credit_note_id
- [ ] Gestione errori con retry
- [ ] Typecheck e lint passano

---

#### US-E100: Xero integration health monitoring
**Description:** Come Operator Finance, voglio monitorare salute integrazione Xero.

**Acceptance Criteria:**
- [ ] Section Xero in Integrations Health page
- [ ] Metrics: last sync, pending syncs count, failed syncs count
- [ ] Alert se failed syncs > threshold
- [ ] List failed syncs con retry action
- [ ] Typecheck e lint passano

---

#### US-E101: Xero failed sync retry
**Description:** Come Operator Finance, voglio riprovare sync Xero falliti.

**Acceptance Criteria:**
- [ ] Lista failed XeroSyncLogs
- [ ] Azione "Retry" per singolo sync
- [ ] Azione "Retry All Failed" per bulk
- [ ] Increment retry_count
- [ ] Max retries configurabile
- [ ] Typecheck e lint passano

---

#### US-E102: XeroIntegrationService
**Description:** Come Developer, voglio un service per integrazione Xero.

**Acceptance Criteria:**
- [ ] Service class `XeroIntegrationService` in `app/Services/Finance/`
- [ ] Metodo `syncInvoice(Invoice)`: crea invoice in Xero
- [ ] Metodo `syncCreditNote(CreditNote)`: crea credit note in Xero
- [ ] Metodo `syncPayment(Payment)`: optional, sync payment
- [ ] Metodo `retryFailed(XeroSyncLog)`: retry singolo sync
- [ ] Metodo `getIntegrationHealth()`: ritorna health metrics
- [ ] Typecheck e lint passano

---

#### US-E103: Integration configuration page
**Description:** Come Admin, voglio configurare integrazioni Stripe e Xero.

**Acceptance Criteria:**
- [ ] Settings page per: Stripe API keys, Xero OAuth, sync settings
- [ ] Test connection buttons
- [ ] Masked display of sensitive values
- [ ] Stored securely (env o encrypted db)
- [ ] Typecheck e lint passano

---

#### US-E104: Xero sync mandatory for issued invoices
**Description:** Come Developer, voglio che sync Xero sia obbligatorio per fatture emesse.

**Acceptance Criteria:**
- [ ] Invariante: ogni invoice issued deve avere XeroSyncLog
- [ ] Se sync fails: invoice resta issued ma flag sync_pending
- [ ] Alert per invoices issued without xero_invoice_id
- [ ] Dashboard widget per pending syncs
- [ ] Typecheck e lint passano

---

#### US-E105: Integration webhook logging
**Description:** Come Developer, voglio logging completo per debug integrazioni.

**Acceptance Criteria:**
- [ ] StripeWebhook: payload completo stored
- [ ] XeroSyncLog: request e response payload stored
- [ ] Logs retention: 90 giorni (configurabile)
- [ ] Log sanitization: no sensitive data in plain text
- [ ] Typecheck e lint passano

---

### Sezione 13: Reporting & Audit

#### US-E106: Invoice Aging Report
**Description:** Come Operator Finance, voglio report aging fatture.

**Acceptance Criteria:**
- [ ] Page "Reports" in Finance con sub-page "Invoice Aging"
- [ ] Buckets: Current, 1-30 days, 31-60 days, 61-90 days, 90+ days
- [ ] Breakdown per customer
- [ ] Totals per bucket
- [ ] Export to CSV
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E107: Revenue by Invoice Type Report
**Description:** Come Operator Finance, voglio report revenue per tipo fattura.

**Acceptance Criteria:**
- [ ] Sub-page "Revenue by Type" in Reports
- [ ] Period selector (monthly, quarterly, yearly)
- [ ] Breakdown: INV0, INV1, INV2, INV3, INV4
- [ ] Amounts: issued, paid, outstanding
- [ ] Chart visualization
- [ ] Export to CSV
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E108: Outstanding Exposure Report
**Description:** Come Operator Finance, voglio report esposizione outstanding.

**Acceptance Criteria:**
- [ ] Sub-page "Outstanding Exposure" in Reports
- [ ] Total outstanding by customer
- [ ] Total outstanding by invoice type
- [ ] Trend over time (chart)
- [ ] Export to CSV
- [ ] Typecheck e lint passano

---

#### US-E109: FX Impact Summary Report
**Description:** Come Operator Finance, voglio report impatto FX.

**Acceptance Criteria:**
- [ ] Sub-page "FX Impact" in Reports
- [ ] Invoices grouped by currency
- [ ] Payments grouped by currency
- [ ] FX gain/loss calculation (se applicabile)
- [ ] Period selector
- [ ] Typecheck e lint passano

---

#### US-E110: Audit Export for compliance
**Description:** Come Compliance Officer, voglio esportare audit logs.

**Acceptance Criteria:**
- [ ] Page "Audit Export" in Finance
- [ ] Filters: entity type, date range, user
- [ ] Export formats: CSV, JSON
- [ ] Include: entity_id, event_type, user, timestamp, changes
- [ ] Download file
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E111: Event-to-Invoice traceability report
**Description:** Come Auditor, voglio tracciare eventi ERP a fatture.

**Acceptance Criteria:**
- [ ] Report che mostra: ERP Event → Invoice → Payment
- [ ] Filter by: event type (sale, shipment, storage), date range
- [ ] Shows unmatched events (events without invoices)
- [ ] Export to CSV
- [ ] Typecheck e lint passano

---

#### US-E112: Reconciliation Status Report
**Description:** Come Operator Finance, voglio report stato riconciliazione.

**Acceptance Criteria:**
- [ ] Sub-page "Reconciliation Status" in Reports
- [ ] Payments by reconciliation_status
- [ ] List pending reconciliations
- [ ] List mismatches with resolution status
- [ ] Typecheck e lint passano

---

#### US-E113: Monthly Financial Summary
**Description:** Come Finance Manager, voglio summary mensile.

**Acceptance Criteria:**
- [ ] Dashboard widget "Monthly Summary"
- [ ] Metrics: invoices issued, payments received, credit notes, refunds
- [ ] Comparison vs previous month
- [ ] By invoice type breakdown
- [ ] Typecheck e lint passano

---

#### US-E114: Audit trail immutability enforcement
**Description:** Come Developer, voglio che audit logs siano immutabili.

**Acceptance Criteria:**
- [ ] Audit logs table: no update, no delete operations
- [ ] Model observer prevents modifications
- [ ] Soft deletes disabled on audit tables
- [ ] Test che verifica immutabilità
- [ ] Typecheck e lint passano

---

#### US-E115: Audit log retention policy
**Description:** Come Developer, voglio policy di retention per audit logs.

**Acceptance Criteria:**
- [ ] Audit logs: retained indefinitely (statutory requirement)
- [ ] Integration logs (Stripe, Xero): 90 days retention
- [ ] Job per cleanup expired logs
- [ ] Config per retention periods
- [ ] Typecheck e lint passano

---

### Sezione 14: Dashboard & Overview

#### US-E116: Finance Overview Dashboard
**Description:** Come Finance Manager, voglio dashboard overview come landing page.

**Acceptance Criteria:**
- [ ] Page "Finance Overview" come default landing
- [ ] Widget: Total Outstanding
- [ ] Widget: Overdue Amount
- [ ] Widget: Payments This Month
- [ ] Widget: Pending Reconciliations
- [ ] Widget: Integration Health (Stripe, Xero status)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-E117: Dashboard quick actions
**Description:** Come Operator Finance, voglio azioni rapide dalla dashboard.

**Acceptance Criteria:**
- [ ] Quick action: "View Overdue Invoices"
- [ ] Quick action: "Pending Reconciliations"
- [ ] Quick action: "Failed Syncs"
- [ ] Quick action: "Generate Storage Billing"
- [ ] Links to respective sections
- [ ] Typecheck e lint passano

---

#### US-E118: Dashboard recent activity feed
**Description:** Come Operator Finance, voglio vedere attività recente.

**Acceptance Criteria:**
- [ ] Widget "Recent Activity"
- [ ] Shows: invoices issued, payments received, credit notes, refunds
- [ ] Last 24 hours
- [ ] Click to navigate to detail
- [ ] Typecheck e lint passano

---

#### US-E119: Dashboard alerts and warnings
**Description:** Come Operator Finance, voglio alerts visibili in dashboard.

**Acceptance Criteria:**
- [ ] Alert: "X invoices overdue"
- [ ] Alert: "X payments pending reconciliation"
- [ ] Alert: "Xero sync failures"
- [ ] Alert: "Stripe webhook issues"
- [ ] Dismissible alerts con persistence
- [ ] Typecheck e lint passano

---

#### US-E120: Dashboard period comparison
**Description:** Come Finance Manager, voglio comparare periodi.

**Acceptance Criteria:**
- [ ] Widget: This Month vs Last Month
- [ ] Metrics: invoices issued, amount collected, credit notes
- [ ] Percentage change indicators
- [ ] Color coding: green (improvement), red (decline)
- [ ] Typecheck e lint passano

---

#### US-E121: Dashboard customer top outstanding
**Description:** Come Finance Manager, voglio vedere top customers per outstanding.

**Acceptance Criteria:**
- [ ] Widget: "Top 10 Outstanding"
- [ ] Lista customers con: name, outstanding amount
- [ ] Click to open Customer Finance View
- [ ] Typecheck e lint passano

---

#### US-E122: Finance navigation structure
**Description:** Come Developer, voglio struttura navigazione Finance chiara.

**Acceptance Criteria:**
- [ ] Navigation group "Finance" con icon
- [ ] Sub-items: Overview, Invoices, Payments, Credit Notes, Refunds, Customers, Subscriptions, Storage Billing, Integrations, Reports
- [ ] Active state highlighting
- [ ] Role-based visibility
- [ ] Typecheck e lint passano

---

### Sezione 15: Edge Cases & Invariants

#### US-E123: Invoice type immutability enforcement
**Description:** Come Developer, voglio enforcement rigoroso di invoice type immutability.

**Acceptance Criteria:**
- [ ] invoice_type campo immutabile a livello model (no update mai)
- [ ] Attempt to change throws exception
- [ ] UI non mostra edit per invoice_type
- [ ] Test che verifica immutabilità
- [ ] Typecheck e lint passano

---

#### US-E124: No invoice merge/split policy
**Description:** Come Developer, voglio enforcement policy no merge/split fatture.

**Acceptance Criteria:**
- [ ] No azioni merge invoices nell'UI
- [ ] No azioni split invoice nell'UI
- [ ] Documentation: "Each invoice is atomic. For corrections, use credit notes."
- [ ] Typecheck e lint passano

---

#### US-E125: No VAT override post-issuance
**Description:** Come Developer, voglio bloccare modifiche VAT dopo issuance.

**Acceptance Criteria:**
- [ ] tax_rate, tax_amount non modificabili su issued invoices
- [ ] tax_amount su invoice_lines non modificabile dopo issuance
- [ ] UI disabilita edit per campi tax
- [ ] Per correzioni: credit note + nuova fattura
- [ ] Typecheck e lint passano

---

#### US-E126: Amounts immutability post-issuance
**Description:** Come Developer, voglio enforcement immutabilità amounts.

**Acceptance Criteria:**
- [ ] subtotal, tax_amount, total_amount non modificabili dopo issuance
- [ ] invoice_lines (all fields) non modificabili dopo issuance
- [ ] Model observer blocks update attempts
- [ ] Exception esplicita: "Invoice amounts are immutable after issuance. Use credit notes for corrections."
- [ ] Typecheck e lint passano

---

#### US-E127: Payment evidence not authority principle
**Description:** Come Developer, voglio che payments non creino diritti operativi.

**Acceptance Criteria:**
- [ ] Payment di INV1 emette evento ma non crea vouchers direttamente
- [ ] Payment di INV2 non triggera shipment
- [ ] Documentation: "Payments are evidence, not authority. Operational modules react to payment events but Finance does not control operations."
- [ ] Typecheck e lint passano

---

#### US-E128: Refund requires invoice+payment link
**Description:** Come Developer, voglio enforcement link invoice+payment per refunds.

**Acceptance Criteria:**
- [ ] Refund creation requires: invoice_id + payment_id
- [ ] Validation: payment deve avere InvoicePayment per l'invoice
- [ ] Cannot refund payment not applied to invoice
- [ ] Typecheck e lint passano

---

#### US-E129: Reconciliation before business logic
**Description:** Come Developer, voglio che reconciliation preceda business logic.

**Acceptance Criteria:**
- [ ] Payment con reconciliation_status = pending non triggera eventi downstream
- [ ] Solo payments con reconciliation_status = matched emettono InvoicePaid event
- [ ] Documentation: "Payment must be reconciled before triggering business events."
- [ ] Typecheck e lint passano

---

#### US-E130: No invoice without trigger event (INV1-4)
**Description:** Come Developer, voglio che INV1-INV4 richiedano source reference.

**Acceptance Criteria:**
- [ ] INV1: richiede source_type = voucher_sale, source_id obbligatorio
- [ ] INV2: richiede source_type = shipping_order, source_id obbligatorio
- [ ] INV3: richiede source_type = storage_billing_period, source_id obbligatorio
- [ ] INV4: source opzionale (per servizi ad-hoc), ma warning se mancante
- [ ] INV0: source = subscription
- [ ] Validation blocca creazione senza source (eccetto INV0 manual e INV4 ad-hoc)
- [ ] Typecheck e lint passano

---

#### US-E131: Finance as consequence principle
**Description:** Come Developer, voglio enforcement del principio "Finance è conseguenza".

**Acceptance Criteria:**
- [ ] Finance non triggera eventi operativi direttamente
- [ ] Finance emette eventi (InvoicePaid, PaymentReceived) che altri moduli ascoltano
- [ ] Finance non crea vouchers, shipments, o inventory movements
- [ ] Documentation clear in module intro
- [ ] Typecheck e lint passano

---

#### US-E132: Duplicate invoice prevention
**Description:** Come Developer, voglio prevenire fatture duplicate per stesso evento.

**Acceptance Criteria:**
- [ ] Unique constraint: (source_type, source_id) per non-null values
- [ ] Validation in InvoiceService.createDraft
- [ ] Se duplicato: return existing invoice instead of creating
- [ ] Log warning per attempted duplicate
- [ ] Typecheck e lint passano

---

---

## Key Invariants (Non-Negotiable Rules)

1. **Invoice type is immutable** - Non può mai essere modificato dopo creazione
2. **No merge/split invoices** - Ogni fattura è atomica
3. **No VAT override post-issuance** - Tasse bloccate dopo emissione
4. **Amounts immutable after issuance** - Usare credit notes per correzioni
5. **Payments are evidence, not authority** - Non creano diritti operativi direttamente
6. **Credit notes preserve original invoice type** - Per reporting consistency
7. **Refunds require invoice + payment link** - Entrambi obbligatori
8. **Reconciliation before business logic** - Payment deve essere riconciliato prima di triggerare eventi
9. **Xero sync mandatory for issued invoices** - Compliance requirement
10. **Audit logs are immutable** - No update, no delete
11. **Finance is consequence, not cause** - Gli eventi sono generati altrove
12. **No invoice without trigger event** - INV1-INV4 richiedono source reference

---

## Functional Requirements

- **FR-1:** Invoice rappresenta documento fiscale generato da eventi ERP (INV0-INV4)
- **FR-2:** Invoice lines sono read-only dopo issuance
- **FR-3:** Payment rappresenta evidenza di pagamento ricevuto (Stripe/Bank)
- **FR-4:** InvoicePayment traccia applicazione pagamenti a fatture
- **FR-5:** Credit Note permette correzioni formali con reason obbligatorio
- **FR-6:** Refund traccia rimborsi con link esplicito a invoice + payment
- **FR-7:** Subscription gestisce billing ricorrente per membership (INV0)
- **FR-8:** StorageBillingPeriod calcola usage per storage fees (INV3)
- **FR-9:** Stripe webhook processing con idempotency garantita
- **FR-10:** Xero sync obbligatorio per fatture statutory
- **FR-11:** Customer Financial View aggrega dati finanziari per cliente
- **FR-12:** Eligibility signals comunicati a Module K per blocks

---

## Non-Goals

- NON gestire prodotti o catalogo (Module 0 - PIM)
- NON gestire allocations o vouchers (Module A - Allocations)
- NON gestire pricing o offer exposure (Module S - Commercial)
- NON gestire inventory fisico (Module B - Inventory)
- NON gestire customer eligibility logic (Module K - Customers decides, Finance signals)
- NON gestire fulfillment o shipping execution (Module C - Fulfillment)
- NON gestire procurement (Module D - Procurement)
- NON creare vouchers da pagamento (Module A li crea in response a evento payment)
- NON triggerare shipments da pagamento (Module C gestisce)
- NON gestire customer portal per self-service payments (fuori scope admin)
- NON gestire multi-currency conversion dinamica (snapshot at issuance only)

---

## Technical Considerations

### Database Schema Principale

```
invoices
├── id, uuid
├── invoice_number (unique)
├── invoice_type (enum: INV0-INV4)
├── customer_id (FK)
├── currency
├── subtotal, tax_amount, total_amount
├── amount_paid
├── status (enum)
├── source_type, source_id (polymorphic)
├── issued_at, due_date
├── notes
├── xero_invoice_id, xero_synced_at
├── fx_rate_at_issuance
├── timestamps, soft_deletes

invoice_lines
├── id
├── invoice_id (FK)
├── description, quantity, unit_price
├── tax_rate, tax_amount, line_total
├── sellable_sku_id (FK nullable)
├── metadata (JSON)
├── timestamps

payments
├── id, uuid
├── payment_reference (unique)
├── source (enum: stripe, bank_transfer)
├── amount, currency
├── status, reconciliation_status (enums)
├── stripe_payment_intent_id (unique nullable)
├── stripe_charge_id, bank_reference
├── received_at
├── customer_id (FK nullable)
├── metadata (JSON)
├── timestamps, soft_deletes

invoice_payments
├── id
├── invoice_id (FK)
├── payment_id (FK)
├── amount_applied
├── applied_at, applied_by (FK users)
├── timestamps

credit_notes
├── id, uuid
├── credit_note_number (unique)
├── invoice_id (FK)
├── customer_id (FK)
├── amount, currency
├── reason (required)
├── status (enum)
├── issued_at, applied_at
├── issued_by (FK users)
├── xero_credit_note_id, xero_synced_at
├── timestamps, soft_deletes

refunds
├── id, uuid
├── invoice_id (FK)
├── payment_id (FK)
├── credit_note_id (FK nullable)
├── refund_type, method (enums)
├── amount, currency
├── status (enum)
├── reason (required)
├── stripe_refund_id (unique nullable)
├── bank_reference
├── processed_at, processed_by (FK users)
├── timestamps, soft_deletes

subscriptions
├── id, uuid
├── customer_id (FK)
├── plan_type, plan_name
├── billing_cycle (enum)
├── amount, currency
├── status (enum)
├── started_at, next_billing_date
├── cancelled_at, cancellation_reason
├── stripe_subscription_id (unique nullable)
├── metadata (JSON)
├── timestamps, soft_deletes

storage_billing_periods
├── id, uuid
├── customer_id (FK)
├── location_id (FK nullable)
├── period_start, period_end
├── bottle_count, bottle_days
├── unit_rate, calculated_amount
├── currency
├── status (enum)
├── invoice_id (FK nullable)
├── calculated_at
├── metadata (JSON)
├── timestamps, soft_deletes

stripe_webhooks
├── id
├── event_id (unique)
├── event_type
├── payload (JSON)
├── processed, processed_at
├── error_message
├── created_at

xero_sync_logs
├── id
├── sync_type (enum)
├── syncable_type, syncable_id (morphic)
├── xero_id
├── status (enum)
├── request_payload, response_payload (JSON)
├── error_message
├── synced_at
├── retry_count
├── timestamps
```

### Filament Resources

```
Finance/
├── InvoiceResource (5 tabs: Lines, Payments, Linked ERP Events, Accounting, Audit)
├── PaymentResource
├── CreditNoteResource
├── RefundResource
├── SubscriptionResource
├── StorageBillingResource
└── Pages/
    ├── FinanceOverview (dashboard landing page)
    ├── CustomerFinanceView
    ├── IntegrationsHealth
    ├── Reports/
    │   ├── InvoiceAging
    │   ├── RevenueByType
    │   ├── OutstandingExposure
    │   ├── FxImpact
    │   └── ReconciliationStatus
    └── AuditExport
```

### Enums

```php
// app/Enums/Finance/

enum InvoiceType: string {
    case MembershipService = 'membership_service';    // INV0
    case VoucherSale = 'voucher_sale';                // INV1
    case ShippingRedemption = 'shipping_redemption';  // INV2
    case StorageFee = 'storage_fee';                  // INV3
    case ServiceEvents = 'service_events';            // INV4
}

enum InvoiceStatus: string {
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Credited = 'credited';
    case Cancelled = 'cancelled';
}

enum PaymentSource: string {
    case Stripe = 'stripe';
    case BankTransfer = 'bank_transfer';
}

enum PaymentStatus: string {
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Refunded = 'refunded';
}

enum ReconciliationStatus: string {
    case Pending = 'pending';
    case Matched = 'matched';
    case Mismatched = 'mismatched';
}

enum CreditNoteStatus: string {
    case Draft = 'draft';
    case Issued = 'issued';
    case Applied = 'applied';
}

enum RefundType: string {
    case Full = 'full';
    case Partial = 'partial';
}

enum RefundMethod: string {
    case Stripe = 'stripe';
    case BankTransfer = 'bank_transfer';
}

enum RefundStatus: string {
    case Pending = 'pending';
    case Processed = 'processed';
    case Failed = 'failed';
}

enum SubscriptionPlanType: string {
    case Membership = 'membership';
    case Service = 'service';
}

enum BillingCycle: string {
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Annual = 'annual';
}

enum SubscriptionStatus: string {
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
}

enum StorageBillingStatus: string {
    case Pending = 'pending';
    case Invoiced = 'invoiced';
    case Paid = 'paid';
    case Blocked = 'blocked';
}

enum XeroSyncType: string {
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case Payment = 'payment';
}

enum XeroSyncStatus: string {
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
}
```

### Service Classes

```php
// app/Services/Finance/

class InvoiceService {
    public function createDraft(InvoiceType $type, Customer $customer, array $lines, ?string $sourceType, ?int $sourceId): Invoice;
    public function issue(Invoice $invoice): void;
    public function applyPayment(Invoice $invoice, Payment $payment, float $amount): InvoicePayment;
    public function markPaid(Invoice $invoice): void;
    public function cancel(Invoice $invoice): void;
    public function getOutstandingAmount(Invoice $invoice): float;
    public function generateInvoiceNumber(): string;
}

class PaymentService {
    public function createFromStripe(PaymentIntent $intent): Payment;
    public function createBankPayment(float $amount, string $reference, ?Customer $customer): Payment;
    public function applyToInvoice(Payment $payment, Invoice $invoice, float $amount): InvoicePayment;
    public function autoReconcile(Payment $payment): ?Invoice;
    public function markReconciled(Payment $payment, ReconciliationStatus $status): void;
}

class CreditNoteService {
    public function createDraft(Invoice $invoice, float $amount, string $reason): CreditNote;
    public function issue(CreditNote $creditNote): void;
    public function apply(CreditNote $creditNote): void;
    public function generateCreditNoteNumber(): string;
}

class RefundService {
    public function create(Invoice $invoice, Payment $payment, RefundType $type, float $amount, RefundMethod $method, string $reason): Refund;
    public function processStripeRefund(Refund $refund): void;
    public function markProcessed(Refund $refund, string $reference): void;
}

class SubscriptionBillingService {
    public function getSubscriptionsDue(): Collection;
    public function generateInvoice(Subscription $subscription): Invoice;
    public function calculateProRata(Subscription $subscription, Carbon $start, Carbon $end): float;
    public function advanceNextBillingDate(Subscription $subscription): void;
}

class StorageBillingService {
    public function calculatePeriod(Customer $customer, Carbon $start, Carbon $end): StorageBillingPeriod;
    public function getBottleDays(Customer $customer, Carbon $start, Carbon $end): int;
    public function getApplicableRate(Customer $customer, ?Location $location, int $volume): float;
    public function generatePeriods(Carbon $periodStart, Carbon $periodEnd): Collection;
    public function generateInvoices(): Collection;
}

class StripeIntegrationService {
    public function processWebhook(StripeWebhook $webhook): void;
    public function handlePaymentSucceeded(PaymentIntent $intent): Payment;
    public function handlePaymentFailed(PaymentIntent $intent): void;
    public function processRefund(StripeRefund $stripeRefund): Refund;
    public function getIntegrationHealth(): array;
}

class XeroIntegrationService {
    public function syncInvoice(Invoice $invoice): XeroSyncLog;
    public function syncCreditNote(CreditNote $creditNote): XeroSyncLog;
    public function syncPayment(Payment $payment): XeroSyncLog;
    public function retryFailed(XeroSyncLog $log): void;
    public function getIntegrationHealth(): array;
}

class CustomerFinanceService {
    public function getOpenInvoices(Customer $customer): Collection;
    public function getTotalOutstanding(Customer $customer): float;
    public function getOverdueAmount(Customer $customer): float;
    public function getPaymentHistory(Customer $customer, ?array $dateRange = null): Collection;
    public function getEligibilitySignals(Customer $customer): array;
}
```

### Struttura Directory

```
app/
├── Enums/
│   └── Finance/
│       ├── InvoiceType.php
│       ├── InvoiceStatus.php
│       ├── PaymentSource.php
│       ├── PaymentStatus.php
│       ├── ReconciliationStatus.php
│       ├── CreditNoteStatus.php
│       ├── RefundType.php
│       ├── RefundMethod.php
│       ├── RefundStatus.php
│       ├── SubscriptionPlanType.php
│       ├── BillingCycle.php
│       ├── SubscriptionStatus.php
│       ├── StorageBillingStatus.php
│       ├── XeroSyncType.php
│       └── XeroSyncStatus.php
├── Models/
│   └── Finance/
│       ├── Invoice.php
│       ├── InvoiceLine.php
│       ├── Payment.php
│       ├── InvoicePayment.php
│       ├── CreditNote.php
│       ├── Refund.php
│       ├── Subscription.php
│       ├── StorageBillingPeriod.php
│       ├── StripeWebhook.php
│       └── XeroSyncLog.php
├── Filament/
│   └── Resources/
│       └── Finance/
│           ├── InvoiceResource.php
│           ├── PaymentResource.php
│           ├── CreditNoteResource.php
│           ├── RefundResource.php
│           ├── SubscriptionResource.php
│           └── StorageBillingResource.php
│   └── Pages/
│       └── Finance/
│           ├── FinanceOverview.php
│           ├── CustomerFinanceView.php
│           ├── IntegrationsHealth.php
│           ├── AuditExport.php
│           └── Reports/
│               ├── InvoiceAging.php
│               ├── RevenueByType.php
│               ├── OutstandingExposure.php
│               ├── FxImpact.php
│               └── ReconciliationStatus.php
├── Services/
│   └── Finance/
│       ├── InvoiceService.php
│       ├── PaymentService.php
│       ├── CreditNoteService.php
│       ├── RefundService.php
│       ├── SubscriptionBillingService.php
│       ├── StorageBillingService.php
│       ├── StripeIntegrationService.php
│       ├── XeroIntegrationService.php
│       └── CustomerFinanceService.php
├── Jobs/
│   └── Finance/
│       ├── ProcessStripeWebhookJob.php
│       ├── SyncInvoiceToXeroJob.php
│       ├── GenerateSubscriptionInvoicesJob.php
│       ├── GenerateStorageBillingJob.php
│       ├── MarkOverdueInvoicesJob.php
│       └── CleanupIntegrationLogsJob.php
├── Events/
│   └── Finance/
│       ├── InvoiceIssued.php
│       ├── InvoicePaid.php
│       ├── PaymentReceived.php
│       ├── CreditNoteIssued.php
│       ├── RefundProcessed.php
│       ├── SubscriptionSuspended.php
│       └── StoragePaymentBlocked.php
└── Listeners/
    └── Finance/
        ├── CreateInvoiceFromVoucherSale.php
        ├── CreateInvoiceFromShipment.php
        ├── CreateInvoiceFromSubscription.php
        └── NotifyModuleKOnBlock.php
```

---

## Cross-Module Interactions

| Module | Direction | Interaction |
|--------|-----------|-------------|
| Module A (Allocations) | Upstream | VoucherSaleConfirmed → INV1 creation |
| Module A (Allocations) | Downstream | InvoicePaid (INV1) → VoucherIssuance trigger |
| Module C (Fulfillment) | Upstream | ShipmentExecuted → INV2 creation |
| Module B (Inventory) | Reference | Storage billing usa inventory counts |
| Module K (Customers) | Bidirectional | Eligibility signals (payment blocks), customer data |
| Module S (Commercial) | Reference | Pricing per invoice lines |
| Stripe | Bidirectional | Payments, webhooks, refunds API |
| Xero | Downstream | Sync statutory invoices, credit notes |

---

## Success Metrics

- 100% delle fatture issued hanno sync Xero tentato
- 95%+ pagamenti Stripe auto-riconciliati
- < 5% pagamenti con reconciliation_status = pending dopo 24h
- 100% delle modifiche hanno audit log
- Zero fatture create senza source reference (eccetto INV0/INV4 allowed)
- < 1% invoices overdue per più di 90 giorni
- 100% credit notes hanno reason populated
- Zero refunds senza invoice+payment link

---

## Open Questions

1. Qual è la soglia giorni per suspension membership per INV0 non pagata?
2. Qual è la soglia giorni per custody block per INV3 non pagata?
3. Serve supporto per customer credit balance (overpayments)?
4. Qual è la retention policy esatta per audit logs finanziari (statutory)?
5. Servono notifiche email automatiche per fatture emesse?
6. Qual è il formato invoice number preferito (INV-YYYY-NNNNNN o altro)?
7. Serve integrazione con altri payment gateways oltre Stripe?
8. Come gestire dispute Stripe (flag only o workflow dedicato)?
