# PRD: Module S — Sales & Commercial Management

## Introduction

Module S è il sistema autoritativo per la gestione commerciale nell'ERP Crurated. È il **"cervello commerciale"** che risponde alle domande: "Cosa può essere venduto? Dove? A chi? A quale prezzo?"

Module S **governa**:
- La definizione di **Canali** commerciali (B2C, B2B, Clubs)
- L'**esposizione commerciale** delle allocations su multipli canali
- I **Price Books** come decisioni di pricing autoritativo
- Le **Pricing Policies** per generazione automatica prezzi
- Gli **Offers** come attivazione della vendibilità
- **Discounts, Rules e Bundles** come componenti riutilizzabili

Module S **non decide**:
- Quali prodotti esistono (Module 0 - PIM)
- Quale supply è disponibile per la vendita (Module A - Allocations)
- Chi può comprare (Module K - Customers)
- Come evadere gli ordini (Module C - Fulfillment)
- Come gestire inventory fisico (Module B - Inventory)
- Come fatturare (Module E - Finance)

**Nessuna vendita può procedere senza un Offer attivo. Module S non override mai i vincoli di Module A.**

---

## Goals

- Creare un sistema di Channels che rappresenti contesti commerciali stabili
- Implementare Price Books come decisioni di pricing versionato e approvato
- Automatizzare la generazione prezzi tramite Pricing Policies
- Gestire Offers come attivazione commerciale di Sellable SKUs
- Supportare multi-channel exposure della stessa supply allocation
- Implementare Discounts e Rules come componenti atomici riutilizzabili
- Creare Bundles come raggruppamenti commerciali
- Fornire simulazione end-to-end per debugging pricing
- Garantire audit trail completo per compliance e governance
- Visualizzare Estimated Market Price (EMP) come segnale di riferimento

---

## User Stories

### Sezione 1: Infrastruttura Commerciale

#### US-001: Setup modello Channel
**Description:** Come Admin, voglio definire i canali commerciali come contesti stabili per la vendita.

**Acceptance Criteria:**
- [ ] Tabella `channels` con campi: id, uuid, name, channel_type (enum), default_currency (string), allowed_commercial_models (JSON array), status (enum)
- [ ] Enum `ChannelType`: b2c, b2b, private_club
- [ ] Enum `ChannelStatus`: active, inactive
- [ ] Soft deletes abilitati
- [ ] allowed_commercial_models può contenere: voucher_based, sell_through
- [ ] Typecheck e lint passano

---

#### US-002: Setup enums base per Commercial
**Description:** Come Developer, voglio enums ben definiti per i tipi e stati delle entità commerciali.

**Acceptance Criteria:**
- [ ] Enum `PriceBookStatus`: draft, active, expired, archived
- [ ] Enum `PricingPolicyStatus`: draft, active, paused, archived
- [ ] Enum `PricingPolicyType`: cost_plus_margin, reference_price_book, index_based, fixed_adjustment, rounding
- [ ] Enum `PricingPolicyInputSource`: cost, emp, price_book, external_index
- [ ] Enum `OfferStatus`: draft, active, paused, expired, cancelled
- [ ] Enum `OfferType`: standard, promotion, bundle
- [ ] Enum `OfferVisibility`: public, restricted
- [ ] Enum `ExecutionCadence`: manual, scheduled, event_triggered
- [ ] Enums in `app/Enums/Commercial/`
- [ ] Typecheck e lint passano

---

#### US-003: Setup modello EstimatedMarketPrice (EMP)
**Description:** Come Admin, voglio memorizzare i prezzi di mercato stimati come riferimento per le decisioni di pricing.

**Acceptance Criteria:**
- [ ] Tabella `estimated_market_prices` con: id, sellable_sku_id (FK), market (string), emp_value (decimal 12,2), source (enum), confidence_level (enum), fetched_at (timestamp)
- [ ] Enum `EmpSource`: livex, internal, composite
- [ ] Enum `EmpConfidenceLevel`: high, medium, low
- [ ] Relazione: EstimatedMarketPrice belongsTo SellableSku
- [ ] Vincolo: combinazione sellable_sku_id + market unica
- [ ] EMP è read-only in Module S (importato da processo esterno)
- [ ] Typecheck e lint passano

---

#### US-004: Channel List in Filament
**Description:** Come Operator, voglio una lista dei canali commerciali come overview del contesto vendita.

**Acceptance Criteria:**
- [ ] ChannelResource in Filament con navigation group "Commercial"
- [ ] Lista con colonne: name, channel_type, default_currency, status, allowed_models (badges), updated_at
- [ ] Filtri: channel_type, status
- [ ] Ricerca per: name
- [ ] Channels sono raramente modificati (sistema stabile)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-005: Channel Detail view
**Description:** Come Operator, voglio vedere i dettagli di un canale commerciale.

**Acceptance Criteria:**
- [ ] Tab Overview: name, type, currency, allowed_commercial_models
- [ ] Tab Price Books: lista Price Books applicabili a questo canale
- [ ] Tab Offers: lista Offers attivi su questo canale
- [ ] Tab Audit: timeline eventi
- [ ] Read-heavy view (canali sono configurazione stabile)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 2: Pricing Intelligence & EMP

#### US-006: Pricing Intelligence List view
**Description:** Come Operator, voglio visualizzare l'EMP per tutti i Sellable SKUs come riferimento di mercato.

**Acceptance Criteria:**
- [ ] Pagina Pricing Intelligence in Filament sotto Commercial
- [ ] Lista con una riga per Sellable SKU + market
- [ ] Colonne: sellable_sku (wine + vintage + format + packaging), market, emp_value, confidence, active_price_book_price (nullable), active_offer_price (nullable), delta_vs_emp (percentage), freshness_indicator
- [ ] Filtri: market, confidence_level, deviation_range (e.g. >10%, >20%)
- [ ] Ricerca per: SKU name, wine name
- [ ] Evidenziazione per SKU con deviazione significativa (>15% da EMP)
- [ ] Vista read-only (nessuna azione di modifica)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-007: Pricing Intelligence Detail view
**Description:** Come Operator, voglio vedere l'analisi dettagliata EMP per un Sellable SKU.

**Acceptance Criteria:**
- [ ] Tab EMP Overview: valore corrente, source breakdown, last update, trend storico (chart)
- [ ] Tab Comparisons: EMP vs Price Book prices (per channel), EMP vs active Offer prices
- [ ] Tab Market Coverage: mercati con EMP, warning per dati mancanti/stale
- [ ] Tab Signals & Alerts: outlier detection, deviazioni significative
- [ ] Tab Audit: history aggiornamenti EMP
- [ ] Nessuna azione di modifica (EMP è importato)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-008: EMP Alerts in Commercial Overview
**Description:** Come Operator, voglio vedere alert quando prezzi deviano significativamente da EMP.

**Acceptance Criteria:**
- [ ] Widget in Commercial Overview che mostra count di SKU con prezzo >15% diverso da EMP
- [ ] Link a Pricing Intelligence filtrato per deviazione
- [ ] Alert severity basato su percentuale deviazione
- [ ] Configurazione threshold in settings (default 15%)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 3: Price Books

#### US-009: Setup modello PriceBook
**Description:** Come Admin, voglio definire Price Books come decisioni di pricing autoritativo.

**Acceptance Criteria:**
- [ ] Tabella `price_books` con: id, uuid, name, market (string), channel_id (FK nullable), currency (string), valid_from (date), valid_to (date nullable), status (enum), approved_at (timestamp nullable), approved_by (FK users nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione: PriceBook belongsTo Channel (optional)
- [ ] Status transitions: draft → active (requires approval), active → expired (automatic o manual), expired → archived
- [ ] Solo un PriceBook active per combinazione market+channel+currency alla volta
- [ ] Typecheck e lint passano

---

#### US-010: Setup modello PriceBookEntry
**Description:** Come Admin, voglio memorizzare prezzi per singoli Sellable SKUs in un Price Book.

**Acceptance Criteria:**
- [ ] Tabella `price_book_entries` con: id, price_book_id (FK), sellable_sku_id (FK), base_price (decimal 12,2), source (enum: manual, policy_generated), policy_id (FK nullable)
- [ ] Relazione: PriceBookEntry belongsTo PriceBook, belongsTo SellableSku, belongsTo PricingPolicy (optional)
- [ ] Vincolo: combinazione price_book_id + sellable_sku_id unica
- [ ] base_price deve essere > 0
- [ ] Typecheck e lint passano

---

#### US-011: PriceBook List in Filament
**Description:** Come Operator, voglio una lista dei Price Books come entry point per il pricing.

**Acceptance Criteria:**
- [ ] PriceBookResource in Filament con navigation group "Commercial"
- [ ] Lista con colonne: name, market, channel, currency, valid_from, valid_to, status, entries_count, last_updated
- [ ] Filtri: status, channel, market, currency
- [ ] Ricerca per: name
- [ ] Indicatore visivo per Price Books in scadenza (< 30 giorni)
- [ ] Indicatore visivo per Price Books con missing prices (SKU allocati senza entry)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-012: Create PriceBook wizard - Step 1 (Metadata)
**Description:** Come Operator, voglio definire i metadati base di un Price Book.

**Acceptance Criteria:**
- [ ] Step 1: name, market (select da lista mercati), channel_id (select nullable), currency
- [ ] Validazione: name required, currency required
- [ ] Messaggio: "Price Books store base prices for all commercially available SKUs"
- [ ] Preview mercati disponibili basato su allocations attive
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-013: Create PriceBook wizard - Step 2 (Validity)
**Description:** Come Operator, voglio definire il periodo di validità di un Price Book.

**Acceptance Criteria:**
- [ ] Step 2: valid_from (date), valid_to (date nullable)
- [ ] Validazione: valid_to >= valid_from (se presente)
- [ ] Warning se overlap con Price Book esistente per stessa combinazione market+channel
- [ ] Spiegazione inline: "Leave valid_to empty for indefinite validity"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-014: Create PriceBook wizard - Step 3 (Initial Prices)
**Description:** Come Operator, voglio opzionalmente importare prezzi iniziali durante creazione.

**Acceptance Criteria:**
- [ ] Step 3 opzionale: scelta import method
- [ ] Opzioni: Empty (add prices later), Clone from existing Price Book, Import from CSV
- [ ] Clone: select source Price Book, preview entries count
- [ ] CSV: template download, upload, validation preview
- [ ] Messaggio: "You can add or modify prices after creation"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-015: Create PriceBook wizard - Step 4 (Review & Create)
**Description:** Come Operator, voglio vedere un riepilogo prima di creare il Price Book.

**Acceptance Criteria:**
- [ ] Step 4: summary di tutti i dati inseriti
- [ ] Price Book creato in status "draft"
- [ ] Messaggio: "Draft Price Books are not used for pricing until activated"
- [ ] CTA: "Create as Draft"
- [ ] Redirect a Price Book Detail dopo creazione
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-016: PriceBook Detail con 5 tabs
**Description:** Come Operator, voglio vedere tutti i dettagli di un Price Book organizzati in tabs.

**Acceptance Criteria:**
- [ ] Tab Overview: summary, status, validity, coverage stats, linked policies, linked offers
- [ ] Tab Prices: grid editabile di Sellable SKU + price + source + EMP reference
- [ ] Tab Scope & Applicability: market, channel, currency, priority rules
- [ ] Tab Lifecycle: activation history, approval info, expiration
- [ ] Tab Audit: timeline completa modifiche
- [ ] Azioni contestuali: Activate (draft→active, richiede approvazione), Archive (active→archived)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-017: PriceBook Prices grid editing
**Description:** Come Operator, voglio editare i prezzi direttamente nella grid del Price Book.

**Acceptance Criteria:**
- [ ] Grid paginata con search e filtri
- [ ] Colonne: sellable_sku, base_price (editable), source (badge), emp_value, delta_vs_emp
- [ ] Inline edit per base_price
- [ ] Bulk edit: select multiple rows, set price o percentage adjustment
- [ ] Source diventa "manual" quando prezzo è editato manualmente
- [ ] Filtro: "Missing prices" (SKU allocati senza entry)
- [ ] Filtro: "Policy-generated" vs "Manual"
- [ ] Editing permesso solo se Price Book è Draft
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-018: PriceBook activation workflow
**Description:** Come Admin, voglio che l'attivazione di un Price Book richieda approvazione esplicita.

**Acceptance Criteria:**
- [ ] Transizione draft → active richiede ruolo approver
- [ ] Approvazione registra approved_at e approved_by
- [ ] Attivazione verifica: almeno 1 price entry presente
- [ ] Attivazione verifica: no overlap con altro PriceBook active per stessa combinazione
- [ ] Se overlap, richiede conferma per expire il precedente
- [ ] Prices diventano read-only dopo attivazione (create new version per modifiche)
- [ ] Typecheck e lint passano

---

#### US-019: PriceBookService per gestione Price Books
**Description:** Come Developer, voglio un service per centralizzare la logica dei Price Books.

**Acceptance Criteria:**
- [ ] Service class `PriceBookService` in `app/Services/Commercial/`
- [ ] Metodo `activate(PriceBook)`: draft → active con validazioni
- [ ] Metodo `archive(PriceBook)`: active → archived
- [ ] Metodo `cloneToNew(PriceBook, newMetadata)`: crea nuovo draft da esistente
- [ ] Metodo `getActiveForContext(channel, market, currency)`: trova Price Book applicabile
- [ ] Metodo `getPriceForSku(PriceBook, SellableSku)`: restituisce PriceBookEntry o null
- [ ] Validazioni con eccezioni esplicite
- [ ] Typecheck e lint passano

---

### Sezione 4: Pricing Policies

#### US-020: Setup modello PricingPolicy
**Description:** Come Admin, voglio definire Pricing Policies per automatizzare la generazione prezzi.

**Acceptance Criteria:**
- [ ] Tabella `pricing_policies` con: id, uuid, name, policy_type (enum), input_source (enum), target_price_book_id (FK nullable), logic_definition (JSON), execution_cadence (enum), status (enum), last_executed_at (timestamp nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione: PricingPolicy belongsTo PriceBook (target)
- [ ] logic_definition contiene: margin_percentage, markup_value, rounding_rule, tiered_logic (optional)
- [ ] Policies generano draft prices, mai attivano direttamente
- [ ] Typecheck e lint passano

---

#### US-021: Setup modello PricingPolicyScope
**Description:** Come Admin, voglio definire lo scope di applicazione di una Pricing Policy.

**Acceptance Criteria:**
- [ ] Tabella `pricing_policy_scopes` con: id, pricing_policy_id (FK), scope_type (enum: all, category, product, sku), scope_reference (string nullable), markets (JSON array nullable), channels (JSON array nullable)
- [ ] Relazione: PricingPolicy hasOne PricingPolicyScope
- [ ] Scope risolve sempre a Sellable SKUs sottostanti
- [ ] Policies applicano solo a SKU commercialmente disponibili (allocation-derived)
- [ ] Typecheck e lint passano

---

#### US-022: Setup modello PricingPolicyExecution
**Description:** Come Admin, voglio tracciare le esecuzioni delle Pricing Policies.

**Acceptance Criteria:**
- [ ] Tabella `pricing_policy_executions` con: id, pricing_policy_id (FK), executed_at, execution_type (enum: manual, scheduled, dry_run), skus_processed (int), prices_generated (int), errors_count (int), status (enum: success, partial, failed), log_summary (text nullable)
- [ ] Relazione: PricingPolicy hasMany PricingPolicyExecution
- [ ] Execution log immutabile
- [ ] Typecheck e lint passano

---

#### US-023: PricingPolicy List in Filament
**Description:** Come Operator, voglio una lista delle Pricing Policies per gestire l'automazione prezzi.

**Acceptance Criteria:**
- [ ] PricingPolicyResource in Filament con navigation group "Commercial"
- [ ] Lista con colonne: name, policy_type, input_source, target_price_book, status, last_executed, next_scheduled (se scheduled)
- [ ] Filtri: status, policy_type, target_price_book
- [ ] Ricerca per: name
- [ ] Indicatore visivo per policies con esecuzioni fallite
- [ ] CTA: Create Pricing Policy
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-024: Create PricingPolicy wizard - Step 1 (Type)
**Description:** Come Operator, voglio selezionare il tipo di Pricing Policy.

**Acceptance Criteria:**
- [ ] Step 1: name, policy_type selection
- [ ] Opzioni type: Cost + Margin, Reference Price Book, External Index (EMP/FX), Fixed Adjustment, Rounding/Normalization
- [ ] Descrizione inline per ogni tipo
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-025: Create PricingPolicy wizard - Step 2 (Inputs)
**Description:** Come Operator, voglio definire gli input della Pricing Policy.

**Acceptance Criteria:**
- [ ] Step 2 campi dinamici basati su policy_type
- [ ] Cost + Margin: cost_source selection
- [ ] Reference Price Book: source_price_book_id selection
- [ ] External Index: index_type (emp, fx_rate), currency conversion rules
- [ ] Fixed Adjustment: adjustment_type (percentage, fixed_amount), value
- [ ] Rounding: rounding_rule (.90, .95, .99, nearest_5)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-026: Create PricingPolicy wizard - Step 3 (Logic)
**Description:** Come Operator, voglio definire la logica di calcolo della Pricing Policy.

**Acceptance Criteria:**
- [ ] Step 3: pricing logic builder
- [ ] Margin/Markup: percentage o fixed amount
- [ ] Tiered logic: different margins per category/SKU (optional)
- [ ] Rounding rules: floor, ceil, nearest, specific pattern
- [ ] Preview: formula plain-language ("Cost + 25% margin, rounded to .99")
- [ ] Validazione: logic coerente con policy_type
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-027: Create PricingPolicy wizard - Step 4 (Scope & Target)
**Description:** Come Operator, voglio definire scope e target della Pricing Policy.

**Acceptance Criteria:**
- [ ] Step 4: target_price_book_id (required), scope definition
- [ ] Scope options: All commercially available SKUs, Specific categories, Specific products, Specific SKUs
- [ ] Market/Channel filters (optional)
- [ ] Preview: count of SKUs che saranno affected
- [ ] Warning se scope include SKU senza allocation attiva
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-028: Create PricingPolicy wizard - Step 5 (Execution)
**Description:** Come Operator, voglio definire la cadenza di esecuzione della Pricing Policy.

**Acceptance Criteria:**
- [ ] Step 5: execution_cadence selection
- [ ] Manual: solo esecuzione on-demand
- [ ] Scheduled: frequency (daily, weekly), time of day
- [ ] Event-triggered: triggers (cost_change, emp_update, fx_change)
- [ ] Spiegazione: "Policies generate draft prices, never activate them directly"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-029: Create PricingPolicy wizard - Step 6 (Review & Create)
**Description:** Come Operator, voglio vedere un riepilogo prima di creare la Pricing Policy.

**Acceptance Criteria:**
- [ ] Step 6: summary completo di tutti i dati
- [ ] Preview: formula plain-language
- [ ] Preview: scope resolved (count SKUs)
- [ ] Policy creata in status "draft"
- [ ] CTA: "Create as Draft", "Create and Activate"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-030: PricingPolicy Detail con 7 tabs
**Description:** Come Operator, voglio vedere tutti i dettagli di una Pricing Policy.

**Acceptance Criteria:**
- [ ] Tab Overview: summary, status, last execution result
- [ ] Tab Logic: inputs, calculations, rounding, formula preview
- [ ] Tab Scope: resolved SKUs, markets, channels
- [ ] Tab Execution: manual run button, scheduling info, dry run button
- [ ] Tab Impact Preview: old price vs new price, EMP delta, warnings
- [ ] Tab Lifecycle: status transitions, activation history
- [ ] Tab Audit: immutable log changes and executions
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-031: PricingPolicy execution (manual & dry-run)
**Description:** Come Operator, voglio eseguire una Pricing Policy manualmente o in dry-run.

**Acceptance Criteria:**
- [ ] Button "Execute Now" in Policy Detail (solo se status = active)
- [ ] Button "Dry Run" disponibile sempre per preview
- [ ] Dry Run: genera preview senza scrivere su Price Book
- [ ] Execute: scrive prices su target Price Book come policy_generated
- [ ] Confirmation dialog con impatto (N SKUs, M prices changed)
- [ ] Execution log creato con risultati
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-032: PricingPolicyService per gestione policies
**Description:** Come Developer, voglio un service per centralizzare la logica delle Pricing Policies.

**Acceptance Criteria:**
- [ ] Service class `PricingPolicyService` in `app/Services/Commercial/`
- [ ] Metodo `activate(PricingPolicy)`: draft → active
- [ ] Metodo `pause(PricingPolicy)`: active → paused
- [ ] Metodo `archive(PricingPolicy)`: active/paused → archived
- [ ] Metodo `execute(PricingPolicy, isDryRun = false)`: esegue logica e genera prices
- [ ] Metodo `resolveScope(PricingPolicy)`: restituisce collection di SellableSku
- [ ] Metodo `calculatePrice(PricingPolicy, SellableSku)`: calcola singolo prezzo
- [ ] Job per scheduled execution
- [ ] Typecheck e lint passano

---

### Sezione 5: Offers

#### US-033: Setup modello Offer
**Description:** Come Admin, voglio definire Offers come attivazione della vendibilità per Sellable SKUs.

**Acceptance Criteria:**
- [ ] Tabella `offers` con: id, uuid, name, sellable_sku_id (FK), channel_id (FK), price_book_id (FK), offer_type (enum), visibility (enum), valid_from (datetime), valid_to (datetime nullable), status (enum), campaign_tag (string nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione: Offer belongsTo SellableSku, Channel, PriceBook
- [ ] Invariante: 1 Offer = 1 Sellable SKU (bundles sono composite SKUs)
- [ ] Offer non porta price direttamente, risolve via PriceBook
- [ ] Typecheck e lint passano

---

#### US-034: Setup modello OfferEligibility
**Description:** Come Admin, voglio definire le condizioni di eligibility per un Offer.

**Acceptance Criteria:**
- [ ] Tabella `offer_eligibilities` con: id, offer_id (FK), allowed_markets (JSON array), allowed_customer_types (JSON array), allowed_membership_tiers (JSON array nullable), allocation_constraint_id (FK nullable)
- [ ] Relazione: Offer hasOne OfferEligibility
- [ ] Eligibility non può override constraints di Module A
- [ ] allocation_constraint_id referenzia vincolo autoritativo da allocation
- [ ] Typecheck e lint passano

---

#### US-035: Setup modello OfferBenefit
**Description:** Come Admin, voglio definire i benefit applicati da un Offer.

**Acceptance Criteria:**
- [ ] Tabella `offer_benefits` con: id, offer_id (FK), benefit_type (enum), benefit_value (decimal nullable), discount_rule_id (FK nullable)
- [ ] Enum `BenefitType`: none, percentage_discount, fixed_discount, fixed_price
- [ ] Relazione: Offer hasOne OfferBenefit
- [ ] benefit_type = none significa prezzo da Price Book senza modifiche
- [ ] discount_rule_id per referenziare regole riutilizzabili
- [ ] Typecheck e lint passano

---

#### US-036: Offer List in Filament
**Description:** Come Operator, voglio una lista degli Offers come entry point per la gestione vendibilità.

**Acceptance Criteria:**
- [ ] OfferResource in Filament con navigation group "Commercial"
- [ ] Lista con colonne: name, sellable_sku, offer_type, channel, status, valid_from, valid_to, visibility
- [ ] Filtri: status, offer_type, channel, visibility, validity_period
- [ ] Ricerca per: name, SKU name, wine name
- [ ] Indicatore visivo per Offers in scadenza (< 7 giorni)
- [ ] Bulk actions: Create offers for selected SKUs, Pause, Archive
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-037: Create Offer wizard - Step 1 (Product)
**Description:** Come Operator, voglio selezionare il Sellable SKU per un nuovo Offer.

**Acceptance Criteria:**
- [ ] Step 1: sellable_sku_id selection con autocomplete
- [ ] Mostra solo SKU con allocation attiva per almeno un channel
- [ ] Preview: allocation info, available channels, EMP (se presente)
- [ ] Warning se SKU non ha EMP
- [ ] Messaggio: "1 Offer = 1 Sellable SKU"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-038: Create Offer wizard - Step 2 (Channel & Eligibility)
**Description:** Come Operator, voglio definire channel ed eligibility per l'Offer.

**Acceptance Criteria:**
- [ ] Step 2: channel_id selection, eligibility fields
- [ ] Channel: solo channels permessi dall'allocation constraint
- [ ] Eligibility: allowed_markets (multi-select, constrained by allocation), allowed_customer_types (multi-select)
- [ ] Warning prominente: "Eligibility cannot override Allocation constraints"
- [ ] Preview: allocation constraints applicabili
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-039: Create Offer wizard - Step 3 (Pricing)
**Description:** Come Operator, voglio definire il pricing dell'Offer.

**Acceptance Criteria:**
- [ ] Step 3: price_book_id selection, benefit configuration
- [ ] Price Book: mostra solo PriceBooks active per channel/market selezionato
- [ ] Preview: base_price da Price Book, EMP comparison
- [ ] Benefit type: None (use Price Book price), Percentage Discount, Fixed Discount, Fixed Override Price
- [ ] Se benefit, input value corrispondente
- [ ] Preview: final price dopo benefit
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-040: Create Offer wizard - Step 4 (Validity & Visibility)
**Description:** Come Operator, voglio definire validity period e visibility dell'Offer.

**Acceptance Criteria:**
- [ ] Step 4: name, offer_type, visibility, valid_from, valid_to, campaign_tag
- [ ] offer_type: standard, promotion (implica discount), bundle
- [ ] visibility: public, restricted
- [ ] Validazione: valid_to > valid_from (se presente)
- [ ] campaign_tag per raggruppamento (optional)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-041: Create Offer wizard - Step 5 (Review & Create)
**Description:** Come Operator, voglio vedere un riepilogo prima di creare l'Offer.

**Acceptance Criteria:**
- [ ] Step 5: summary completo
- [ ] Preview: SKU, channel, eligibility, pricing, validity
- [ ] Warning per conflitti con Offers esistenti (stesso SKU, channel, overlapping validity)
- [ ] Offer creato in status "draft"
- [ ] CTA: "Create as Draft", "Create and Activate"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-042: Offer Detail con 7 tabs
**Description:** Come Operator, voglio vedere tutti i dettagli di un Offer.

**Acceptance Criteria:**
- [ ] Tab Overview: summary, status, final price computed
- [ ] Tab Eligibility: markets, customer types, allocation constraints
- [ ] Tab Benefit: discount/fixed price config, resolved final price
- [ ] Tab Products: Sellable SKU info, allocation lineage
- [ ] Tab Priority & Conflicts: conflicting offers, resolution rules
- [ ] Tab Simulation: price testing per questo Offer
- [ ] Tab Audit: full traceability
- [ ] Azioni: Activate, Pause, Cancel
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-043: Offer status transitions
**Description:** Come Admin, voglio che le transizioni di stato degli Offers seguano regole precise.

**Acceptance Criteria:**
- [ ] Transizioni valide: draft→active, active→paused, paused→active, active→expired (automatico), any→cancelled
- [ ] Attivazione verifica: eligibility non viola allocation constraints
- [ ] Attivazione verifica: Price Book referenced è active
- [ ] expired settato automaticamente quando now > valid_to
- [ ] cancelled è terminale (no reactivation)
- [ ] Ogni transizione logga user_id, timestamp
- [ ] Typecheck e lint passano

---

#### US-044: Bulk Offer creation
**Description:** Come Operator, voglio creare Offers in bulk per multipli Sellable SKUs.

**Acceptance Criteria:**
- [ ] Entry point: Offer List (select SKUs), Allocation detail, Price Book detail
- [ ] Wizard bulk: select target channel, Price Book, shared eligibility, shared benefit, shared validity
- [ ] Preview: lista Offers che saranno creati
- [ ] Ogni SKU genera 1 Offer indipendente
- [ ] Validation: tutti gli SKU devono avere allocation per channel selezionato
- [ ] Progress indicator durante creazione
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-045: OfferService per gestione Offers
**Description:** Come Developer, voglio un service per centralizzare la logica degli Offers.

**Acceptance Criteria:**
- [ ] Service class `OfferService` in `app/Services/Commercial/`
- [ ] Metodo `activate(Offer)`: draft → active con validazioni
- [ ] Metodo `pause(Offer)`: active → paused
- [ ] Metodo `cancel(Offer)`: any → cancelled
- [ ] Metodo `getActiveForContext(SellableSku, Channel, Customer)`: trova Offer applicabile
- [ ] Metodo `resolvePrice(Offer)`: calcola final price (base + benefit)
- [ ] Metodo `validateEligibility(Offer, Customer)`: verifica customer eligibility
- [ ] Job per auto-expire Offers scaduti
- [ ] Typecheck e lint passano

---

### Sezione 6: Discounts & Rules

#### US-046: Setup modello DiscountRule
**Description:** Come Admin, voglio definire regole di sconto riutilizzabili.

**Acceptance Criteria:**
- [ ] Tabella `discount_rules` con: id, uuid, name, rule_type (enum), logic_definition (JSON), status (enum: active, inactive)
- [ ] Enum `DiscountRuleType`: percentage, fixed_amount, tiered, volume_based
- [ ] logic_definition contiene: value, tiers (per tiered), thresholds (per volume)
- [ ] DiscountRules sono definitions, non si applicano da sole
- [ ] Typecheck e lint passano

---

#### US-047: DiscountRule List in Filament
**Description:** Come Operator, voglio una lista delle regole di sconto riutilizzabili.

**Acceptance Criteria:**
- [ ] DiscountRuleResource in Filament sotto Commercial > Discounts & Rules
- [ ] Lista con colonne: name, rule_type, summary (plain-language), status, offers_using_count
- [ ] Filtri: rule_type, status
- [ ] Ricerca per: name
- [ ] Lightweight UI (advanced users)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-048: DiscountRule CRUD
**Description:** Come Operator, voglio creare e modificare regole di sconto.

**Acceptance Criteria:**
- [ ] Create: name, rule_type, logic builder
- [ ] Logic builder dinamico per tipo: percentage (value), fixed_amount (value), tiered (tiers array), volume_based (thresholds)
- [ ] Preview: plain-language explanation ("15% off", "€10 off when qty >= 6")
- [ ] Edit: solo se nessun Offer active la sta usando
- [ ] Delete: solo se non referenziata da alcun Offer
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 7: Bundles

#### US-049: Setup modello Bundle
**Description:** Come Admin, voglio definire Bundles come raggruppamenti commerciali di Sellable SKUs.

**Acceptance Criteria:**
- [ ] Tabella `bundles` con: id, uuid, name, bundle_sku (string unique), pricing_logic (enum), fixed_price (decimal nullable), status (enum: draft, active, inactive)
- [ ] Enum `BundlePricingLogic`: sum_components, fixed_price, percentage_off_sum
- [ ] Bundle genera un Sellable SKU composito in PIM (reference)
- [ ] Typecheck e lint passano

---

#### US-050: Setup modello BundleComponent
**Description:** Come Admin, voglio definire i componenti di un Bundle.

**Acceptance Criteria:**
- [ ] Tabella `bundle_components` con: id, bundle_id (FK), sellable_sku_id (FK), quantity (int default 1)
- [ ] Relazione: Bundle hasMany BundleComponent, BundleComponent belongsTo SellableSku
- [ ] Validazione: quantity > 0
- [ ] Componenti sono Sellable SKUs (non Bottle SKUs)
- [ ] Typecheck e lint passano

---

#### US-051: Bundle List in Filament
**Description:** Come Operator, voglio una lista dei Bundles commerciali.

**Acceptance Criteria:**
- [ ] BundleResource in Filament sotto Commercial > Bundles
- [ ] Lista con colonne: name, bundle_sku, pricing_logic, components_count, status, computed_price
- [ ] Filtri: status, pricing_logic
- [ ] Ricerca per: name, bundle_sku
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-052: Bundle CRUD con component management
**Description:** Come Operator, voglio creare e modificare Bundles con i loro componenti.

**Acceptance Criteria:**
- [ ] Create wizard: name, bundle_sku (auto-generated o manual), pricing_logic
- [ ] Component selection: add Sellable SKUs con quantity
- [ ] Pricing: sum_components (auto), fixed_price (input), percentage_off_sum (input %)
- [ ] Preview: computed price, component list
- [ ] Edit: components editabili solo se status = draft
- [ ] Activation: genera/aggiorna Sellable SKU composito in PIM
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-053: BundleService per gestione Bundles
**Description:** Come Developer, voglio un service per centralizzare la logica dei Bundles.

**Acceptance Criteria:**
- [ ] Service class `BundleService` in `app/Services/Commercial/`
- [ ] Metodo `activate(Bundle)`: draft → active, sincronizza con PIM
- [ ] Metodo `calculatePrice(Bundle, PriceBook)`: calcola prezzo basato su logic
- [ ] Metodo `validateComponents(Bundle)`: verifica tutti i componenti hanno allocation
- [ ] Metodo `getComponentsPrice(Bundle, PriceBook)`: somma prezzi componenti
- [ ] Typecheck e lint passano

---

### Sezione 8: Simulation

#### US-054: Price Simulation page
**Description:** Come Operator, voglio simulare la risoluzione prezzo end-to-end per debugging.

**Acceptance Criteria:**
- [ ] Pagina Simulation in Filament sotto Commercial
- [ ] Inputs: sellable_sku_id, customer_id (optional), channel_id, date, quantity
- [ ] Auto-suggest per tutti i campi
- [ ] CTA: "Simulate"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-055: Simulation results display
**Description:** Come Operator, voglio vedere i risultati della simulazione con full breakdown.

**Acceptance Criteria:**
- [ ] Output section con breakdown steps:
- [ ] 1. Allocation Check: allocation lineage, constraints, availability
- [ ] 2. EMP Reference: EMP value (se disponibile)
- [ ] 3. Price Book Resolution: quale Price Book applicato, base_price
- [ ] 4. Offer Resolution: quale Offer applicato, benefit applied
- [ ] 5. Final Price: prezzo finale con explanation
- [ ] Se blocco (no allocation, no offer), mostra errore specifico
- [ ] Highlight per ogni step: source, rationale
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-056: SimulationService per calcoli
**Description:** Come Developer, voglio un service per eseguire la simulazione pricing.

**Acceptance Criteria:**
- [ ] Service class `SimulationService` in `app/Services/Commercial/`
- [ ] Metodo `simulate(SellableSku, ?Customer, Channel, date, quantity)`: restituisce SimulationResult
- [ ] SimulationResult contiene: allocation_check, emp_reference, price_book_resolution, offer_resolution, final_price, errors
- [ ] Integrazione con AllocationService per check disponibilità
- [ ] Integrazione con OfferService per risoluzione offer
- [ ] Typecheck e lint passano

---

### Sezione 9: Commercial Overview

#### US-057: Commercial Overview dashboard
**Description:** Come Operator, voglio una dashboard come control panel per tutte le attività commerciali.

**Acceptance Criteria:**
- [ ] Pagina Overview come landing page di Commercial
- [ ] Widget: Active Price Books count by status
- [ ] Widget: Active Offers count by status
- [ ] Widget: Pricing Policies with last execution status
- [ ] Widget: EMP Coverage (% SKU con EMP)
- [ ] Widget: Alerts (expiring offers, price deviations, policy failures)
- [ ] Links rapidi: Create Price Book, Create Offer, Create Policy
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-058: Commercial Calendar view
**Description:** Come Operator, voglio vedere una vista calendario delle attività commerciali.

**Acceptance Criteria:**
- [ ] Calendar component in Commercial Overview
- [ ] Eventi: Price Book validity periods (colored bars), Offer validity periods, Pricing Policy scheduled executions
- [ ] Filtri: tipo evento, channel
- [ ] Click su evento apre detail
- [ ] Vista: mese, settimana
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-059: Upcoming expirations widget
**Description:** Come Operator, voglio vedere gli elementi commerciali in scadenza.

**Acceptance Criteria:**
- [ ] Widget in Overview: Upcoming Expirations
- [ ] Lista: Offers expiring in 7 days, Price Books expiring in 30 days
- [ ] Ordinato per data scadenza
- [ ] Link a detail per ogni item
- [ ] Badge urgenza per < 3 giorni
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 10: Audit & Governance

#### US-060: Audit log per entità Commercial
**Description:** Come Compliance Officer, voglio un log immutabile per tutte le modifiche alle entità commerciali.

**Acceptance Criteria:**
- [ ] Trait Auditable applicato a: Channel, PriceBook, PriceBookEntry, PricingPolicy, Offer, OfferEligibility, OfferBenefit, DiscountRule, Bundle
- [ ] Eventi loggati: creation, update, status_change, activation, expiration
- [ ] Ogni entità ha Tab Audit in detail view
- [ ] Audit logs immutabili
- [ ] Typecheck e lint passano

---

#### US-061: Global Commercial Audit page
**Description:** Come Compliance Officer, voglio una vista globale di tutti gli audit events commerciali.

**Acceptance Criteria:**
- [ ] Pagina Audit in Filament sotto Commercial
- [ ] Lista unificata tutti gli audit events di Commercial
- [ ] Filtri: entity_type, event_type, date_range, user
- [ ] Ricerca per: entity_id, user_name
- [ ] Export CSV per compliance
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-062: Pricing Policy execution history
**Description:** Come Operator, voglio vedere lo storico completo delle esecuzioni delle Pricing Policies.

**Acceptance Criteria:**
- [ ] Tab Execution History in Pricing Policy Detail
- [ ] Lista esecuzioni: date, type (manual/scheduled/dry_run), skus_processed, prices_generated, errors, status
- [ ] Expand per vedere log_summary
- [ ] Link a Price Book target per vedere prices generated
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

## Key Invariants (Non-Negotiable Rules)

1. **No sale without Offer** - Ogni vendita richiede un Offer attivo
2. **1 Offer = 1 Sellable SKU** - Bundles sono composite SKUs, non offer multipli
3. **Offers don't carry prices** - Prices risolvono via Price Books
4. **Pricing Policies generate, never activate** - Prices devono essere approvati in Price Book
5. **Commercial never overrides Allocation** - Vincoli Module A sono autoritativi
6. **EMP is reference only** - Non attiva o setta prezzi
7. **Price Books are versioned and approved** - No implicit price changes
8. **Channels are stable** - Tactical constructs use Offers, not new channels
9. **Multi-channel exposure uses same allocation** - No stock duplication
10. **Discounts are definitions only** - Si applicano solo via Offers

---

## Functional Requirements

- **FR-1:** Channel rappresenta contesto commerciale stabile (B2C, B2B, Club)
- **FR-2:** PriceBook memorizza decisioni di pricing autoritativo, versionato, approvato
- **FR-3:** PriceBookEntry memorizza prezzo per singolo Sellable SKU
- **FR-4:** PricingPolicy automatizza generazione prezzi (mai attiva direttamente)
- **FR-5:** Offer attiva vendibilità per Sellable SKU su Channel
- **FR-6:** OfferEligibility definisce condizioni (constrained by Allocation)
- **FR-7:** OfferBenefit definisce modifiche al prezzo (discount, fixed)
- **FR-8:** DiscountRule definisce logica sconto riutilizzabile
- **FR-9:** Bundle raggruppa Sellable SKUs come SKU composito
- **FR-10:** EMP fornisce riferimento mercato (read-only)
- **FR-11:** Simulation permette debugging pricing end-to-end
- **FR-12:** Audit log immutabile per tutte le entità

---

## Non-Goals

- NON gestire prodotti o catalogo (Module 0 - PIM)
- NON gestire allocation o supply constraints (Module A - Allocations)
- NON gestire vouchers o entitlements (Module A - Allocations)
- NON gestire customer eligibility o blocks (Module K - Customers)
- NON gestire inventory fisico (Module B - Inventory)
- NON gestire fulfillment o shipping (Module C - Fulfillment)
- NON gestire procurement (Module D - Procurement)
- NON gestire fatturazione o pagamenti (Module E - Finance)
- NON override vincoli allocation
- NON applicare prezzi runtime (sempre via Price Book + Offer)
- NON creare implicit pricing

---

## Technical Considerations

### Database Schema Principale

```
channels
├── id, uuid
├── name
├── channel_type (enum: b2c, b2b, private_club)
├── default_currency
├── allowed_commercial_models (JSON)
├── status (enum: active, inactive)
├── timestamps, soft_deletes

estimated_market_prices
├── id
├── sellable_sku_id (FK)
├── market
├── emp_value (decimal)
├── source (enum: livex, internal, composite)
├── confidence_level (enum: high, medium, low)
├── fetched_at
├── timestamps

price_books
├── id, uuid
├── name
├── market
├── channel_id (FK nullable)
├── currency
├── valid_from, valid_to
├── status (enum: draft, active, expired, archived)
├── approved_at, approved_by
├── timestamps, soft_deletes

price_book_entries
├── id
├── price_book_id (FK)
├── sellable_sku_id (FK)
├── base_price (decimal)
├── source (enum: manual, policy_generated)
├── policy_id (FK nullable)
├── timestamps

pricing_policies
├── id, uuid
├── name
├── policy_type (enum)
├── input_source (enum)
├── target_price_book_id (FK)
├── logic_definition (JSON)
├── execution_cadence (enum)
├── status (enum)
├── last_executed_at
├── timestamps, soft_deletes

pricing_policy_scopes
├── id
├── pricing_policy_id (FK)
├── scope_type (enum)
├── scope_reference
├── markets (JSON)
├── channels (JSON)
├── timestamps

pricing_policy_executions
├── id
├── pricing_policy_id (FK)
├── executed_at
├── execution_type (enum)
├── skus_processed, prices_generated, errors_count
├── status (enum)
├── log_summary
├── timestamps

offers
├── id, uuid
├── name
├── sellable_sku_id (FK)
├── channel_id (FK)
├── price_book_id (FK)
├── offer_type (enum)
├── visibility (enum)
├── valid_from, valid_to
├── status (enum)
├── campaign_tag
├── timestamps, soft_deletes

offer_eligibilities
├── id
├── offer_id (FK)
├── allowed_markets (JSON)
├── allowed_customer_types (JSON)
├── allowed_membership_tiers (JSON)
├── allocation_constraint_id (FK nullable)
├── timestamps

offer_benefits
├── id
├── offer_id (FK)
├── benefit_type (enum)
├── benefit_value (decimal nullable)
├── discount_rule_id (FK nullable)
├── timestamps

discount_rules
├── id, uuid
├── name
├── rule_type (enum)
├── logic_definition (JSON)
├── status (enum)
├── timestamps

bundles
├── id, uuid
├── name
├── bundle_sku
├── pricing_logic (enum)
├── fixed_price (decimal nullable)
├── status (enum)
├── timestamps

bundle_components
├── id
├── bundle_id (FK)
├── sellable_sku_id (FK)
├── quantity
├── timestamps
```

### Filament Resources

- `ChannelResource` - Lightweight config per channels
- `PriceBookResource` - CRUD Price Books con prices grid
- `PricingPolicyResource` - CRUD Policies con execution
- `OfferResource` - Primary operational screen per offers
- `DiscountRuleResource` - Advanced users, regole riutilizzabili
- `BundleResource` - Gestione bundles

### Enums

```php
// Channel
enum ChannelType: string {
    case B2C = 'b2c';
    case B2B = 'b2b';
    case PrivateClub = 'private_club';
}

enum ChannelStatus: string {
    case Active = 'active';
    case Inactive = 'inactive';
}

// Price Book
enum PriceBookStatus: string {
    case Draft = 'draft';
    case Active = 'active';
    case Expired = 'expired';
    case Archived = 'archived';
}

enum PriceSource: string {
    case Manual = 'manual';
    case PolicyGenerated = 'policy_generated';
}

// Pricing Policy
enum PricingPolicyStatus: string {
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';
}

enum PricingPolicyType: string {
    case CostPlusMargin = 'cost_plus_margin';
    case ReferencePriceBook = 'reference_price_book';
    case IndexBased = 'index_based';
    case FixedAdjustment = 'fixed_adjustment';
    case Rounding = 'rounding';
}

enum PricingPolicyInputSource: string {
    case Cost = 'cost';
    case Emp = 'emp';
    case PriceBook = 'price_book';
    case ExternalIndex = 'external_index';
}

enum ExecutionCadence: string {
    case Manual = 'manual';
    case Scheduled = 'scheduled';
    case EventTriggered = 'event_triggered';
}

enum ExecutionType: string {
    case Manual = 'manual';
    case Scheduled = 'scheduled';
    case DryRun = 'dry_run';
}

enum ExecutionStatus: string {
    case Success = 'success';
    case Partial = 'partial';
    case Failed = 'failed';
}

// Offer
enum OfferStatus: string {
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}

enum OfferType: string {
    case Standard = 'standard';
    case Promotion = 'promotion';
    case Bundle = 'bundle';
}

enum OfferVisibility: string {
    case Public = 'public';
    case Restricted = 'restricted';
}

enum BenefitType: string {
    case None = 'none';
    case PercentageDiscount = 'percentage_discount';
    case FixedDiscount = 'fixed_discount';
    case FixedPrice = 'fixed_price';
}

// Discount Rule
enum DiscountRuleType: string {
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case Tiered = 'tiered';
    case VolumeBased = 'volume_based';
}

enum DiscountRuleStatus: string {
    case Active = 'active';
    case Inactive = 'inactive';
}

// Bundle
enum BundlePricingLogic: string {
    case SumComponents = 'sum_components';
    case FixedPrice = 'fixed_price';
    case PercentageOffSum = 'percentage_off_sum';
}

enum BundleStatus: string {
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
}

// EMP
enum EmpSource: string {
    case Livex = 'livex';
    case Internal = 'internal';
    case Composite = 'composite';
}

enum EmpConfidenceLevel: string {
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
}

// Scope
enum PolicyScopeType: string {
    case All = 'all';
    case Category = 'category';
    case Product = 'product';
    case Sku = 'sku';
}
```

### Service Classes

```php
// Price Book management
class PriceBookService {
    public function activate(PriceBook $priceBook): void;
    public function archive(PriceBook $priceBook): void;
    public function cloneToNew(PriceBook $source, array $newMetadata): PriceBook;
    public function getActiveForContext(?Channel $channel, string $market, string $currency): ?PriceBook;
    public function getPriceForSku(PriceBook $priceBook, SellableSku $sku): ?PriceBookEntry;
}

// Pricing Policy management
class PricingPolicyService {
    public function activate(PricingPolicy $policy): void;
    public function pause(PricingPolicy $policy): void;
    public function archive(PricingPolicy $policy): void;
    public function execute(PricingPolicy $policy, bool $isDryRun = false): PricingPolicyExecution;
    public function resolveScope(PricingPolicy $policy): Collection;
    public function calculatePrice(PricingPolicy $policy, SellableSku $sku): ?float;
}

// Offer management
class OfferService {
    public function activate(Offer $offer): void;
    public function pause(Offer $offer): void;
    public function cancel(Offer $offer): void;
    public function getActiveForContext(SellableSku $sku, Channel $channel, ?Customer $customer = null): ?Offer;
    public function resolvePrice(Offer $offer): float;
    public function validateEligibility(Offer $offer, Customer $customer): bool;
}

// Bundle management
class BundleService {
    public function activate(Bundle $bundle): void;
    public function calculatePrice(Bundle $bundle, PriceBook $priceBook): float;
    public function validateComponents(Bundle $bundle): bool;
    public function getComponentsPrice(Bundle $bundle, PriceBook $priceBook): float;
}

// Simulation
class SimulationService {
    public function simulate(SellableSku $sku, ?Customer $customer, Channel $channel, Carbon $date, int $quantity): SimulationResult;
}
```

### Struttura Directory

```
app/
├── Enums/
│   └── Commercial/
│       ├── ChannelType.php
│       ├── ChannelStatus.php
│       ├── PriceBookStatus.php
│       ├── PriceSource.php
│       ├── PricingPolicyStatus.php
│       ├── PricingPolicyType.php
│       ├── PricingPolicyInputSource.php
│       ├── ExecutionCadence.php
│       ├── ExecutionType.php
│       ├── ExecutionStatus.php
│       ├── OfferStatus.php
│       ├── OfferType.php
│       ├── OfferVisibility.php
│       ├── BenefitType.php
│       ├── DiscountRuleType.php
│       ├── DiscountRuleStatus.php
│       ├── BundlePricingLogic.php
│       ├── BundleStatus.php
│       ├── EmpSource.php
│       ├── EmpConfidenceLevel.php
│       └── PolicyScopeType.php
├── Models/
│   └── Commercial/
│       ├── Channel.php
│       ├── EstimatedMarketPrice.php
│       ├── PriceBook.php
│       ├── PriceBookEntry.php
│       ├── PricingPolicy.php
│       ├── PricingPolicyScope.php
│       ├── PricingPolicyExecution.php
│       ├── Offer.php
│       ├── OfferEligibility.php
│       ├── OfferBenefit.php
│       ├── DiscountRule.php
│       ├── Bundle.php
│       └── BundleComponent.php
├── Filament/
│   └── Resources/
│       └── Commercial/
│           ├── ChannelResource.php
│           ├── PriceBookResource.php
│           ├── PricingPolicyResource.php
│           ├── OfferResource.php
│           ├── DiscountRuleResource.php
│           └── BundleResource.php
├── Services/
│   └── Commercial/
│       ├── PriceBookService.php
│       ├── PricingPolicyService.php
│       ├── OfferService.php
│       ├── BundleService.php
│       └── SimulationService.php
└── Jobs/
    └── Commercial/
        ├── ExecutePricingPolicyJob.php
        └── ExpireOffersJob.php
```

---

## Success Metrics

- 100% degli Offers hanno Price Book reference valido
- 100% delle attivazioni commerciali hanno audit log
- Zero prezzi attivati senza Price Book approval
- 100% degli Offers rispettano vincoli allocation
- Pricing Policy execution success rate > 95%
- EMP coverage > 80% dei Sellable SKU attivi
- Simulation accuracy 100% (riflette pricing reale)

---

## Open Questions

1. Qual è il threshold default per alert deviazione EMP? (proposto: 15%)
R. Ok
2. Come gestire conflitti tra Offers overlapping per stesso SKU/Channel?
R. Va notificato che va modificato quello esistente
3. Serve integrazione real-time con Liv-ex per EMP update?
R. No, faremo cron giornaliero di allineamento
4. Qual è la frequenza massima accettabile per scheduled policy execution?
R. Non mi è ben chiara la domanda, ma usa buon senso 
5. Serve un sistema di approvazione multi-level per Price Books di alto valore?
R. Si
6. Come sincronizzare Bundle activation con PIM Sellable SKU composito?
R. Non mi è chiara la domanda, prosegui con buon senso
