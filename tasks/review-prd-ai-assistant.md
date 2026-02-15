# Review PRD AI Assistant — Analisi Completa e Finale

## Stato: PRD SOLIDO — Pronto per implementazione con le note sotto

---

## Metodo di Verifica (Review #3 — 2026-02-15)

Questa review e' la piu' approfondita. Verifiche eseguite:

1. **7 subagent paralleli** per model/field/enum verification: Customer, Finance, Allocation/Voucher, Inventory/Fulfillment, Procurement/Commercial, UserRole/Auth/Infrastructure, PIM/Filament patterns
2. **SDK verification** via Packagist + Laravel docs: `laravel/ai` v0.1.5 confermato esistente
3. **Pricing verification** via Anthropic docs: Sonnet 4.5 pricing confermato
4. **Testing API verification**: per-class faking (`ErpAssistantAgent::fake()`) confermato
5. **Auth middleware verification**: guard `web` confermato coerente tra Filament e route SSE
6. **Relationship chain verification**: ProcurementIntent→PurchaseOrder, Voucher→Producer

---

## VERDETTO GENERALE

Il PRD e' **eccellente e pronto per l'implementazione**. Dopo 3 round di review, tutti i problemi critici e importanti sono stati risolti direttamente nel PRD. Restano solo **annotazioni operative** (cose da verificare al momento dell'implementazione, non problemi nel PRD).

---

## CONFERME POSITIVE (verificate contro il codebase reale)

### Modelli, Campi, Relazioni — TUTTI VERIFICATI

| Modello | Campi/Relazioni Verificati | Esito |
|---------|---------------------------|-------|
| **Customer** | `name`, `email` (legacy), `party()` BelongsTo, `getName()` -> Party.legal_name, `activeMembership()`, `vouchers()`, `shippingOrders()`, `operationalBlocks()` MorphMany | PASS |
| **Party** | `legal_name` | PASS |
| **CustomerStatus** | Prospect, Active, Suspended, Closed (4 casi) | PASS |
| **CustomerType** | B2C, B2B, Partner (3 casi) | PASS |
| **Invoice** | `status`, `total_amount`, `tax_amount`, `amount_paid`, `issued_at`, `due_date`, `invoice_number`, `customer()`, `getOutstandingAmount()` = `bcsub(total, paid, 2)` | PASS |
| **InvoiceStatus** | Draft, Issued, Paid, PartiallyPaid, Credited, Cancelled (6 casi) | PASS |
| **InvoiceType** | MembershipService/INV0, VoucherSale/INV1, ShippingRedemption/INV2, StorageFee/INV3, ServiceEvents/INV4 (5 casi con `code()`) | PASS |
| **Payment** | `reconciliation_status`, `payment_reference`, `amount`, `received_at`, `source` (PaymentSource enum), `customer()` | PASS |
| **ReconciliationStatus** | Pending, Matched, Mismatched (3 casi) | PASS |
| **CreditNote** | `status`, `amount` | PASS |
| **CreditNoteStatus** | Draft, Issued, Applied (3 casi) | PASS |
| **Allocation** | `status`, `total_quantity`, `sold_quantity`, `wineVariant()` BelongsTo | PASS |
| **AllocationStatus** | Draft, Active, Exhausted, Closed (4 casi) | PASS |
| **Voucher** | `lifecycle_state`, `allocation_id`, `customer_id`, `allocation()` BelongsTo | PASS |
| **VoucherLifecycleState** | Issued, Locked, Redeemed, Cancelled (4 casi) | PASS |
| **WineMaster** | `name`, `producer_id`, `country_id`, `region_id`, `producerRelation()`, `wineVariants()`, `producer_name` accessor | PASS |
| **WineVariant** | `wine_master_id`, `wineMaster()`, `sellableSkus()` | PASS |
| **Producer** | `name` | PASS |
| **SellableSku** | `sku_code`, `lifecycle_status`, `wineVariant()`, `caseConfiguration()` (singolare, BelongsTo) | PASS |
| **CaseConfiguration** | esiste, linked via `case_configuration_id` su SellableSku | PASS |
| **SerializedBottle** | `current_location_id`, `state`, `ownership_type`, `currentLocation()` | PASS |
| **BottleState** | Stored, ReservedForPicking, Shipped, Consumed, Destroyed, Missing, MisSerialized (7 casi) | PASS |
| **OwnershipType** | CururatedOwned (typo noto, valore `crurated_owned`), InCustody, ThirdPartyOwned (3 casi) | PASS |
| **InventoryCase** | table `cases`, `integrity_status` | PASS |
| **CaseIntegrityStatus** | Intact, Broken (2 casi) | PASS |
| **Location** | `name`, `location_type` (LocationType enum), `country` | PASS |
| **ShippingOrder** | `status`, `customer()`, `lines()` HasMany ShippingOrderLine, `requested_ship_date` | PASS |
| **ShippingOrderLine** | Modello esiste (il seeder e' stato mergiato in ShippingOrderSeeder, non il modello) | PASS |
| **ShippingOrderStatus** | Draft, Planned, Picking, Shipped, Completed, Cancelled, OnHold (7 casi), `isTerminal()` = Completed/Cancelled | PASS |
| **Shipment** | `tracking_number`, `status`, `carrier`, `shipped_at`, `delivered_at`, `shippingOrder()` | PASS |
| **ShipmentStatus** | Preparing, Shipped, InTransit, Delivered, Failed (5 casi), `isTerminal()` = Delivered/Failed | PASS |
| **PurchaseOrder** | `status`, `expected_delivery_start`, `expected_delivery_end`, `quantity`, `unit_cost`, `currency`, `supplier_party_id` (non `supplier_id`), `procurementIntent()`, `supplier()` (via Party), `inbounds()`. **NO campo `reference`** (PRD gia' documentato: "no reference field, use UUID id") | PASS |
| **PurchaseOrderStatus** | Draft, Sent, Confirmed, Closed (4 casi), `isTerminal()` = Closed | PASS |
| **ProcurementIntent** | `status`, `purchaseOrders()` HasMany | PASS |
| **ProcurementIntentStatus** | Draft, Approved, Executed, Closed (4 casi) | PASS |
| **Inbound** | `received_date` (solo data ricezione, NO date previste — confermato) | PASS |
| **Offer** | `name` (non `offer_name`), `status` (OfferStatus), `sellableSku()`, `channel()`, `priceBook()`, `valid_from`, `valid_to`, `visibility` | PASS |
| **OfferStatus** | Draft, Active, Paused, Expired, Cancelled (5 casi) | PASS |
| **PriceBookEntry** | `sellable_sku_id`, `price_book_id` | PASS |
| **PriceBook** | `status` (PriceBookStatus: Draft, Active, Expired, Archived) | PASS |
| **EstimatedMarketPrice** | `sellable_sku_id`, `emp_value` (non `market_price`), `confidence_level` | PASS |
| **UserRole** | SuperAdmin(100), Admin(80), Manager(60), Editor(40), Viewer(20) — `level()` method | PASS |
| **User** | `role` cast a UserRole enum | PASS |
| **AuditLog** | boot guard: `static::updating()` + `static::deleting()` throw InvalidArgumentException | PASS |

### Catena Relazionale Voucher → Producer — VERIFICATA

```
Voucher.allocation_id → Allocation.wine_variant_id → WineVariant.wine_master_id → WineMaster.producer_id → Producer.name
```
Relazioni: `voucher->allocation->wineVariant->wineMaster->producerRelation->name`
Accessor shortcut: `$wineMaster->producer_name` (usa `producerRelation->name` con fallback a campo `producer`)

### Infrastruttura — VERIFICATA

| Elemento | Stato | Note |
|----------|-------|------|
| `laravel/ai` v0.1.5 | Esiste su Packagist, rilasciato 2026-02-12 | NON ancora installato nel progetto |
| Model ID `claude-sonnet-4-5-20250929` | Confermato da Anthropic docs | Corretto |
| Pricing $3/$15 per 1M tokens | Confermato da Anthropic docs | Corretto |
| Testing: `ErpAssistantAgent::fake()` | Confermato per-class faking (non generico) | Pattern corretto |
| PHP requirement | `^8.2` in composer.json, server PHP 8.5 | Bump a `^8.4` previsto dal PRD |
| `config/ai.php` | Non esiste | Da creare |
| `routes/web.php` | Solo route welcome `/` | Pronto per le route AI |
| `renderHook()` | Mai usato nel progetto | Prima volta con AI chat icon |
| `discoverPages()` | Presente in AdminPanelProvider | Pages auto-discovered |
| Navigation group "System" | Definito in AdminPanelProvider (collapsed) | Gia' esistente |
| Auth guard `web` | Confermato sia per Filament che per `auth` middleware | Nessun conflitto |
| Alpine.js | Gia' usato (`x-data`, `@click` in finance/inventory views) | Pattern coerente |

---

## QUESTIONI APERTE RIMANENTI (da risolvere al momento dell'implementazione, non bloccanti per il PRD)

### Q1. SDK Smoke Test — Gate bloccante

**Stato:** Il PRD gia' documenta US-AI001 come blocking. Le seguenti verifiche DEVONO passare prima di procedere:

1. Tutti i namespace (`Laravel\Ai\Contracts\{Agent, Conversational, HasTools, Tool}`, etc.)
2. `.enum()` e `.default()` su `JsonSchema` type builders — **non documentati ufficialmente**, solo assunti
3. Return type `Stringable|string` su `Tool::description()` e `Tool::handle()`
4. Struttura `AgentResponse->usage` (field names per token counts)
5. Nomi tabelle SDK: `agent_conversations` vs `conversations`

**Se anche UNO fallisce:** STOP totale, re-design delle parti affette.

### Q2. `composer.json` PHP Bump `^8.2` → `^8.4`

**Stato:** Decisione presa (confermata nella review #1). Da eseguire come primo step di US-AI001. Aggiornare anche CLAUDE.md "Tech Stack" line.

**Rischio residuo:** Basso. Server e' PHP 8.5, dev probabile 8.4+. Ma e' un breaking change per chi ha PHP 8.2/8.3.

### Q3. Context Window 30 messaggi — Monitoring necessario

**Stato:** Il PRD ha gia' ridotto da 50 a 30 e documenta il rationale. Implementare il monitoring hook (`tokens_input` > 100K → warning in audit metadata). Aggiustare dopo dati reali.

---

## NESSUN PROBLEMA NON RISOLTO

Dopo 3 round di review con 10+ subagent:

- **0 problemi critici aperti** (C1 PHP bump deciso, C2 smoke test gia' blocking, C3/C4 schema builder e return types aggiunti al PRD)
- **0 problemi importanti aperti** (I1-I17 tutti risolti nel PRD)
- **0 ambiguita' aperte** (A1 Customer name risolto con `getName()` + dual search, A2 BottleState completato, A3 OwnershipType documentato)
- **3 questioni operative** (Q1-Q3) da verificare durante l'implementazione — sono "do X and verify" steps, non decisioni aperte

---

## STRUTTURA DEL PRD — VALUTAZIONE QUALITATIVA

### Punti di forza

1. **Architettura Tool-Calling** — Scelta eccellente. Niente text-to-SQL, niente hallucination risk, ogni tool e' una query Eloquent controllata
2. **ToolAccessLevel decoupled da UserRole** — Design pulito con `forRole()` come ponte
3. **25 tool ben distribuiti** — Coprono tutti gli 8 moduli con accesso levels appropriati
4. **Error handling centralizzato** — `safeHandle()` + `disambiguateResults()` nel BaseTool
5. **Rate limiting cache-first** — Performance O(1) con fallback DB
6. **Conversation soft-delete via raw update** — Evita override del model SDK
7. **SSE via Alpine.js standalone** — Evita conflitti Livewire DOM diffing
8. **Smoke test blocking** — Mitiga rischio SDK pre-1.0
9. **22 decisioni architetturali documentate** — Ogni scelta ha un rationale
10. **Open Questions tutte RESOLVED** — Nessuna ambiguita' residua

### Nota sulla completezza

Il PRD copre:
- 33 User Stories (6 infra, 1 base tool, 21 tool, 6 UI, 4 conversation, 3 security, 4 testing)
- 12 Functional Requirements espliciti
- 8 Non-Goals espliciti
- 22 decisioni architetturali con rationale
- 8 success metrics misurabili
- 3 fasi di implementazione con priorita'
- Directory structure completa
- Code patterns con esempi

**Non manca nulla per iniziare l'implementazione.**

---

## RACCOMANDAZIONI IMPLEMENTATIVE

### R1. Ordine esecuzione Phase 1 (rigoroso)

```
US-AI001 (SDK install + smoke test) ← GATE BLOCCANTE
    ↓ (solo se smoke test PASS)
US-AI002 (Config) + US-AI006 (AuditLog model) + US-AI010 (BaseTool) — parallelizzabili
    ↓
US-AI003 (Agent) + US-AI004 (System Prompt) — parallelizzabili
    ↓
US-AI005 (Rate Limiting) — dipende da US-AI006
    ↓
6 core tools (US-AI011, US-AI020, US-AI030, US-AI041, US-AI050, US-AI080) — parallelizzabili
    ↓
US-AI100 + US-AI101 + US-AI102 (UI base + streaming) — sequenziali
    ↓
US-AI110 (Persistence) + US-AI120 (RBAC) — parallelizzabili
```

### R2. Ogni tool va verificato con query reale

Dopo aver scritto ogni tool, eseguire la query con `tinker` per verificare che:
- La query non ha N+1
- I campi referenziati esistono
- Gli enum values matchano
- Il `bcmath` produce risultati corretti

### R3. Deploy notes da aggiungere

Dopo l'implementazione, aggiungere a CLAUDE.md Known Gotchas:
- Nginx `proxy_read_timeout 120s` per la route SSE
- PHP-FPM `request_terminate_timeout = 120` per la route SSE
- `ANTHROPIC_API_KEY` richiesto in `.env`

---

## STORICO REVIEW

| Data | Review # | Problemi trovati | Problemi risolti |
|------|----------|-----------------|-----------------|
| 2026-02-15 | #1 | 2 critici + 6 importanti + 3 ambiguita' | Tutti risolti nel PRD |
| 2026-02-15 | #2 | 3 critici + 11 importanti | Tutti risolti nel PRD |
| 2026-02-15 | #3 (questa) | 0 nuovi problemi | Conferma finale di tutti i fix |
