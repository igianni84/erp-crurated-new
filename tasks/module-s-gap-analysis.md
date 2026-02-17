# Module S (Commercial) — Gap Analysis Report

**Data:** 9 Febbraio 2026
**Ultima verifica:** 16 Febbraio 2026 (ri-verifica approfondita con 6 agenti paralleli)
**Fonti confrontate:**
1. **Documentazione funzionale** — `tasks/ERP-FULL-DOC.md` (Sezione Module S)
2. **PRD UI/UX** — `tasks/prd-module-s-commercial.md` (62 user stories)
3. **Codice implementato** — Codebase effettivo

---

## 1. Riepilogo Esecutivo

| Area | Completezza | Note |
|------|:-----------:|------|
| **Modelli & Schema** | 100% | 13 modelli, 14 migrazioni, 21 enum |
| **Servizi (business logic)** | 95% | 5 servizi completi, PIM sync placeholder |
| **Filament Resources** | 98% | 6 risorse (3 con wizard, 4 con view multi-tab), 2 relation managers |
| **Pagine custom** | 100% | 6 pagine (Overview, Calendar, Audit, Simulation, Intelligence, IntelligenceDetail) + BulkCreateOffers in OfferResource |
| **DTOs** | 100% | 7 DTO per la simulazione prezzi |
| **Job schedulati** | 100% | 2 job (scadenza offerte, esecuzione policy) |
| **Seeder** | 100% | 3 seeder (Channel, PriceBook, Offer) |
| **CSV Import** | 5% | Solo stub UI, nessuna logica backend |
| **PIM Sync (Bundle)** | 0% | Placeholder, dipende da PIM composite SKU |
| **Policy (Authorization)** | 0% | Nessuna Policy class per i modelli Commercial |
| **Events/Listeners** | 0% | Nessun evento cross-module per Commercial |
| **Test suite** | ~0% | Nessun test domain/servizi; esistono solo 407 righe di test (12 test methods) per AI tools wrapper (`CommercialToolsTest.php`) |

**Stima complessiva: ~90% completo** (funzionalità core pronta, mancano autorizzazione, eventi, test e CSV import)

---

## 2. Entit&agrave; — Confronto Doc Funzionale vs Implementazione

### 2.1 Channel

| Specifica (Doc Funzionale) | Implementato | Gap |
|---|:-:|---|
| channel_id, channel_type, default_currency | ✅ | — |
| allowed_commercial_models (voucher-based, sell-through) | ✅ campo | ⚠️ **Campo presente ma non enforced a runtime** — nessun servizio valida che un'operazione su canale rispetti i modelli commerciali permessi |
| Status (Active/Inactive) | ✅ | — |
| Canali stabili, non proliferanti | ✅ | 9 canali seed (B2C EU/US/UK/CH, B2B Hospitality/Trade, Collectors Club, Elite Circle, APAC Trade inactive) |

### 2.2 Offer

| Specifica (Doc Funzionale) | Implementato | Gap |
|---|:-:|---|
| 1 Offer = 1 Sellable SKU | ✅ | Invariante rispettato |
| Offer NON porta prezzo direttamente | ✅ | Risoluzione via PriceBook |
| offer_type (standard, weekly, private sale) | ✅ | Enum: Standard, Promotion, Bundle |
| visibility (public/restricted) | ✅ | — |
| status (draft/active/paused/expired/cancelled) | ✅ | Transizioni di stato validate |
| validity window (valid_from, valid_to) | ✅ | — |
| campaign_tag / campaign_id | ⚠️ Parziale | Solo `campaign_tag` (stringa) — **non esiste un'entit&agrave; Campaign** |
| Mapping esplicito ad allocazioni | ✅ | `allocation_constraint_id` in OfferEligibility con validazione |
| Offerte composite (verticali) devono referenziare tutte le allocazioni Bottle SKU | ⚠️ Parziale | Bundle gestito come composite SKU, ma validazione multi-allocation non verificata per casi atomici complessi |

### 2.3 Price Book

| Specifica (Doc Funzionale) | Implementato | Gap |
|---|:-:|---|
| Decisione di pricing, non price list | ✅ | Design coerente |
| Versioning, time-bound, approvato | ✅ | status lifecycle + approved_at/approved_by |
| Scope: currency, channel, market, customer segment | ⚠️ Parziale | `market`, `channel_id`, `currency` presenti; **customer segment NON implementato come scope** |
| Ogni price line: offer_id + unit_price (doc funzionale) vs sellable_sku_id (PRD) | ⚠️ Divergenza doc↔PRD | Le entry referenziano `sellable_sku_id` come da PRD US-010. Il doc funzionale dice `offer_id` ma il PRD ha semplificato. Vedi GAP-1. |
| Approvazione richiede ruolo Manager+ | ✅ | `canApprovePriceBooks()` check in PriceBookService |
| Overlap detection | ✅ | Validazione nel wizard di creazione |

**Nota (corretta in verifica 16/02):** La doc funzionale specifica pricing "per Offer, not per SKU", ma il PRD (US-010) specifica esplicitamente `sellable_sku_id`. Il codice è conforme al PRD. Questa è una divergenza doc funzionale↔PRD, non un bug implementativo. Il PriceBook è già scopato per canale/mercato/currency, quindi la differenziazione per canale funziona.

### 2.4 Pricing Policy

| Specifica (Doc Funzionale) | Implementato | Gap |
|---|:-:|---|
| Non-authoritative, genera draft prices | ✅ | Generazione in PriceBookEntry con source=PolicyGenerated |
| Mai applica prezzi a runtime | ✅ | Solo generazione, mai risoluzione checkout |
| Opera su SKU allocation-available | ⚠️ Parziale | Lo scope filtra per categoria/prodotto/SKU, ma **non valida esplicitamente la disponibilit&agrave; allocation** |
| Tipi: CostPlus, Reference, Index, Fixed, Rounding | ✅ | 5 tipi implementati |
| Cadenze: Manual, EventTriggered, Scheduled | ✅ | Job schedulato presente |
| Dry-run | ✅ | Supporto dry-run nel servizio |
| Execution tracking | ✅ | PricingPolicyExecution con metriche |

### 2.5 Campaign

| Specifica (Doc Funzionale) | Implementato | Gap |
|---|:-:|---|
| Campaign come concetto di coordinamento | ⚠️ Minimo | Solo `campaign_tag` su Offer |
| campaign_id, name, campaign_kind | ❌ | **Nessun modello Campaign** |
| start_date, end_date | ❌ | Non presente (le date sono sull'Offer) |
| approval_state (draft/approved/active/archived) | ❌ | Non presente |
| applicable_offers (lista esplicita o query-based) | ❌ | Solo grouping implicito via tag |

**Nota (severity corretta in verifica 16/02):** La doc funzionale descrive Campaign con campi strutturati, ma la stessa doc dice esplicitamente "Campaign is not a first-class executable object" e "campaigns are created and managed by bulk creation of Offers with shared attributes". L'approccio `campaign_tag` è coerente con questa definizione. Manca solo la struttura per governance/approvazione a livello campagna, ma questo è un nice-to-have dato che il doc stesso è ambivalente.

### 2.6 Discounts & Pricing Rules

| Specifica (Doc Funzionale) | Implementato | Gap |
|---|:-:|---|
| Libreria riutilizzabile di regole | ✅ | DiscountRule con 4 tipi |
| Solo definizioni, non eseguibili da sole | ✅ | Applicate via OfferBenefit |
| Non mutano base prices in PriceBook | ✅ | — |
| Stacking behavior, rounding rules | ⚠️ Parziale | Tiered e VolumeBased presenti, ma **stacking tra regole multiple non implementato** |

### 2.7 EMP (Estimated Market Price)

| Specifica (Doc Funzionale) | Implementato | Gap |
|---|:-:|---|
| Segnale di riferimento non-authoritative | ✅ | — |
| Mai risolve a prezzo di vendita | ✅ | — |
| Mai blocca vendita senza regola esplicita | ✅ | — |
| Validazione pricing constraints e guardrails | ⚠️ Parziale | Delta vs EMP visibile nella UI (color-coded), ma **nessun guardrail bloccante implementato** |
| Supporto analytics e AI-assisted decisions | ❌ | Non presente |

---

## 3. User Stories PRD — Stato Implementazione

### 3.1 Infrastruttura (US-001 → US-005)

| US | Descrizione | Stato |
|---|---|:-:|
| US-001 | Channel setup con types e status enums | ✅ |
| US-002 | Base enums per entit&agrave; Commercial | ✅ |
| US-003 | Estimated Market Price (EMP) model | ✅ |
| US-004 | Filament UI Channel list | ✅ |
| US-005 | Filament UI Channel detail | ✅ |

### 3.2 Pricing Intelligence & EMP (US-006 → US-008)

| US | Descrizione | Stato |
|---|---|:-:|
| US-006 | Pricing Intelligence list view | ✅ |
| US-007 | Analisi dettagliata EMP | ✅ |
| US-008 | EMP alerts deviazione >15% | ⚠️ **Parziale** — color-coding UI presente, deviation breakdown su dashboard placeholder (sempre 0) |

### 3.3 Price Books (US-009 → US-019)

*Nota (verifica 16/02): I numeri US qui sono consolidati per leggibilità. Nel PRD, il wizard è suddiviso in US-012/013/014/015 (4 step = 4 US separate), e la detail view è US-016. I numeri qui sotto riflettono il raggruppamento logico, non l'ordine esatto del PRD.*

| US (PRD) | Descrizione | Stato |
|---|---|:-:|
| US-009 | PriceBook model | ✅ |
| US-010 | PriceBookEntry model | ✅ |
| US-011 | PriceBook List in Filament | ✅ |
| US-012→015 | Wizard 4-step per creazione (Metadata, Validity, Initial Prices, Review) | ✅ |
| US-016 | Detail view con 5 tab | ✅ |
| US-017 | Grid editing inline | ✅ |
| US-018 | Attivazione con ruolo approver | ✅ |
| US-019 | PriceBookService | ✅ |

### 3.4 Pricing Policies (US-020 → US-032)

| US | Descrizione | Stato |
|---|---|:-:|
| US-020 | PricingPolicy model | ✅ |
| US-021 | PricingPolicyScope model | ✅ |
| US-022 | PricingPolicyExecution model | ✅ |
| US-023 | Wizard 6-step | ✅ |
| US-024 (=US-030 PRD) | Detail view con tab | ✅ ViewPricingPolicy ha 8 tab (Overview, Logic, Scope, Execution, Execution History, Impact Preview, Lifecycle, Audit) — supera i 7 tab specificati nel PRD |
| US-025 | Esecuzione manuale | ✅ |
| US-026 | Dry-run | ✅ |
| US-027 | Esecuzione schedulata (Job) | ✅ |
| US-028 | 5 tipi di policy | ✅ |
| US-029 | PricingPolicyService | ✅ |
| US-030 | Status transitions | ✅ |
| US-031 | Execution tracking | ✅ |
| US-032 | Scope resolution | ✅ |

### 3.5 Offers (US-033 → US-045)

| US | Descrizione | Stato |
|---|---|:-:|
| US-033 | Offer model | ✅ |
| US-034 | OfferEligibility model | ✅ |
| US-035 | OfferBenefit model | ✅ |
| US-036 | Wizard 5-step | ✅ |
| US-037 | Detail view con 7 tab | ✅ |
| US-038 | Status transitions | ✅ |
| US-039 | Eligibility validation | ✅ |
| US-040 | Benefit calculation | ✅ |
| US-041 | Allocation constraint validation | ✅ |
| US-042 | OfferService | ✅ |
| US-043 | Bulk Offer creation | ✅ |
| US-044 | Conflict detection | ✅ |
| US-045 | Auto-expiry (Job) | ✅ |

### 3.6 Discounts & Rules (US-046 → US-048)

| US | Descrizione | Stato |
|---|---|:-:|
| US-046 | DiscountRule model | ✅ |
| US-047 | CRUD operations | ✅ |
| US-048 | Dynamic form per tipi | ✅ |

### 3.7 Bundles (US-049 → US-053)

| US | Descrizione | Stato |
|---|---|:-:|
| US-049 | Bundle model | ✅ |
| US-050 | BundleComponent model | ✅ |
| US-051 | 3 pricing logics | ✅ |
| US-052 | Component management | ✅ |
| US-053 | BundleService | ✅ |

### 3.8 Simulazione (US-054 → US-056)

| US | Descrizione | Stato |
|---|---|:-:|
| US-054 | Price Simulation page | ✅ |
| US-055 | 5-step breakdown display | ✅ |
| US-056 | SimulationService | ✅ |

### 3.9 Commercial Overview (US-057 → US-059)

| US | Descrizione | Stato |
|---|---|:-:|
| US-057 | Commercial Overview dashboard | ✅ |
| US-058 | Calendar view | ✅ |
| US-059 | Upcoming expirations widget | ✅ |

### 3.10 Audit & Governance (US-060 → US-062)

| US | Descrizione | Stato |
|---|---|:-:|
| US-060 | Audit logging per tutte le entit&agrave; | ✅ |
| US-061 | Global audit page con filtri | ✅ |
| US-062 | Execution history tracking | ✅ |

**Riepilogo User Stories: 60/62 complete (~97%), 2 parziali** (US-008 EMP alerts deviation breakdown placeholder, US-014 CSV import backend assente)

---

## 4. Gap Critici — Analisi Dettagliata

### GAP-1: PriceBookEntry referenzia SKU, non Offer (DIVERGENZA DOC FUNZIONALE vs PRD)

**Doc funzionale (sez. 5.4.4):**
> "Pricing is always defined per Offer, not per SKU, to allow channel-, segment-, and campaign-specific pricing over the same underlying product."
> Ogni price line include: `offer_id`, `unit_price`

**PRD (US-010):**
> "Tabella `price_book_entries` con: id, price_book_id (FK), **sellable_sku_id** (FK), base_price (decimal 12,2)"
> Il PRD specifica esplicitamente `sellable_sku_id`, NON `offer_id`.

**Implementazione:**
PriceBookEntry ha `sellable_sku_id` + `base_price`, conforme al PRD.

**Nota (aggiunta in verifica 16/02):** Questa NON &egrave; una divergenza implementativa — il codice &egrave; conforme al PRD. La divergenza &egrave; tra doc funzionale e PRD, il che suggerisce una **decisione di design deliberata** nel PRD per semplificare il modello. Il PriceBook &egrave; gi&agrave; scopato per canale/mercato/currency, quindi la differenziazione per canale funziona. La granularit&agrave; per-Offer (es. pricing diverso per campagna sullo stesso canale) non &egrave; possibile senza duplicare PriceBook.

**Severit&agrave;: BASSA** — Decisione di design documentata nel PRD. Funziona per i casi d'uso attuali. Valutare solo se emergono requisiti di pricing per-Offer sullo stesso canale.

---

### GAP-2: Entit&agrave; Campaign assente

**Doc funzionale (sez. 5.4.5):**
> Campaign con `campaign_id`, `name`, `campaign_kind`, `start_date`, `end_date`, `approval_state`, `applicable_offers`

**Implementazione:**
Solo `campaign_tag` (stringa nullable) su Offer.

**Impatto:** Impossibile:
- Approvare/archiviare una campagna come unit&agrave;
- Definire date di campagna indipendenti dalle date delle singole offerte
- Avere governance strutturata sulle campagne
- Aggregare metriche a livello campagna

**Severità: BASSA** (corretta da MEDIA in verifica 16/02) — Il doc stesso dice "Campaign is not a first-class executable object" e "campaigns are created and managed by bulk creation of Offers with shared attributes". L'approccio tag è coerente con questa definizione. Nice-to-have per governance strutturata.

---

### GAP-3: Customer Segment mancante su PriceBook

**Doc funzionale (sez. 5.4.4):**
> PriceBook key fields: "customer segment (optional)"

**Implementazione:**
PriceBook ha `market`, `channel_id`, `currency` ma **nessun campo customer_segment**.

**Impatto:** La differenziazione per segmento cliente avviene solo via OfferEligibility (allowed_membership_tiers), non a livello PriceBook. La doc nota che "separate Price Books are appropriate only when the underlying price reality itself differs", quindi questa scelta pu&ograve; essere intenzionale.

**Severit&agrave;: BASSA** — Il doc funzionale stesso suggerisce che la differenziazione per segmento avviene preferibilmente via Offers/Discounts.

---

### GAP-4: allowed_commercial_models non enforced a runtime

**Doc funzionale (sez. 5.4.2):**
> Channel ha `allowed_commercial_models` (voucher-based, sell-through)

**Implementazione:**
Il campo esiste ed &egrave; configurabile, ma **nessun servizio valida** che le operazioni su un canale rispettino i modelli commerciali permessi.

**Severit&agrave;: MEDIA** — Potenziale inconsistenza se si creano offerte sell-through su un canale voucher-only.

---

### GAP-5: CSV Import non implementato

**PRD (US-011, Step 3 del wizard PriceBook):**
> Opzione "Import da CSV" per popolare prezzi iniziali

**Implementazione:**
UI presente con placeholder: "CSV import is not yet implemented. Please use Empty or Clone for now."

**Severit&agrave;: MEDIA** — Funzionalit&agrave; operativa importante per pricing massivo, attualmente workaround via clone/manuale.

---

### GAP-6: PIM Sync per Bundle

**PRD (US-053):**
> BundleService sync con PIM per composite SKU

**Implementazione:**
`syncWithPim()` &egrave; un metodo vuoto con commento TODO.

**Severit&agrave;: BASSA** — Dipendenza da Module 0 (PIM) per supporto composite SKU. Bloccante architetturale, non un gap di implementazione Module S.

---

### GAP-7: Nessuna Policy di autorizzazione

**Best practice Filament & Laravel:**
> Ogni risorsa dovrebbe avere una Policy class per controllare create/read/update/delete/custom actions.

**Implementazione:**
Nessuna Policy class esiste per i 13 modelli Commercial. L'autorizzazione si basa solo su middleware/role generici.

**Severit&agrave;: ALTA per produzione** — Senza Policy, qualsiasi utente autenticato con accesso al pannello pu&ograve; potenzialmente modificare entit&agrave; Commercial.

---

### GAP-8: Nessun Event cross-module

**Doc funzionale (sez. 5.9):**
> Module S provides sellable offers to ecommerce, pricing to checkout, commercial context to Module A, pricing metadata to Module E.

**Pattern architetturale (CLAUDE.md):**
> Event-driven cross-module: Events trigger listeners

**Implementazione:**
Zero eventi Commercial. Nessun `OfferActivated`, `PriceBookApproved`, `PricingPolicyExecuted`.

**Severit&agrave;: MEDIA** — Non bloccante finch&eacute; il checkout non &egrave; implementato, ma l'architettura event-driven &egrave; un principio fondante del progetto.

---

### GAP-9: EMP Deviation Breakdown placeholder

**PRD (US-008):**
> EMP alerts quando prezzi deviano >15% dal mercato

**Implementazione:**
La dashboard mostra deviation_breakdown con valori hardcoded a 0. Il color-coding inline nelle PriceBookEntry funziona, ma le metriche aggregate non sono calcolate.

**Severit&agrave;: BASSA** — L'informazione pi&ugrave; utile (inline nel grid) funziona; manca solo l'aggregazione dashboard.

---

### GAP-10: Test suite domain/servizi assente

**(Corretto in ri-verifica 16/02)** Nessun test PHPUnit per i servizi e modelli domain di Module S. Esistono 407 righe di test in `tests/Unit/AI/Tools/Commercial/CommercialToolsTest.php` (12 test methods, non 9 come erroneamente riportato in precedenza) ma coprono solo gli AI tools wrapper (ActiveOffersTool, PriceBookCoverageTool, EmpAlertsTool), NON la business logic dei servizi.

**Zero test per:** PricingPolicyService (5 algoritmi di calcolo prezzo), OfferService (eligibility, price resolution), BundleService (composite pricing), PriceBookService (approval flow), SimulationService (5-step chain). Zero feature test per le risorse Filament.

**Severità: ALTA per produzione** — ~89KB di codice servizi senza alcuna copertura test di regressione.

---

### ~~GAP-11: RIMOSSO~~ (PricingPolicy Detail View — erroneamente segnalato come "senza tab")

**(Rimosso in ri-verifica 16/02):** La verifica precedente affermava erroneamente che `ViewPricingPolicy.php` usasse una standard view senza tab. L'ispezione diretta del codice mostra che ha **8 tab** implementati (Overview, Logic, Scope, Execution, Execution History, Impact Preview, Lifecycle, Audit) — superando i 7 tab specificati nel PRD US-030. Questo NON è un gap.

---

### GAP-12: Modelli Commercial senza Auditable trait (corretto in ri-verifica 16/02)

**Pattern progetto:**
> Trait Auditable e SoftDeletes dovrebbero essere presenti su tutti i modelli domain significativi.

**(Corretto in ri-verifica 16/02):** La versione precedente affermava erroneamente che "12/13 modelli hanno Auditable, solo BundleComponent no". La verifica diretta mostra che **4 modelli su 13** non hanno il trait `Auditable`:

| Modello | Auditable | SoftDeletes | Note |
|---------|:---------:|:-----------:|------|
| Channel | ✅ | ✅ | — |
| PriceBook | ✅ | ✅ | — |
| PricingPolicy | ✅ | ✅ | — |
| Bundle | ✅ | ✅ | — |
| Offer | ✅ | ✅ | — |
| PriceBookEntry | ✅ | ❌ | Auditable senza SoftDeletes |
| OfferBenefit | ✅ | ❌ | Auditable senza SoftDeletes |
| OfferEligibility | ✅ | ❌ | Auditable senza SoftDeletes |
| DiscountRule | ✅ | ❌ | Auditable senza SoftDeletes |
| **BundleComponent** | ❌ | ❌ | Record di composizione |
| **EstimatedMarketPrice** | ❌ | ❌ | Read-only, importato esternamente |
| **PricingPolicyExecution** | ❌ | ❌ | Log immutabile, intenzionale |
| **PricingPolicyScope** | ❌ | ❌ | Configurazione subordinata |

**Distribuzione:** 9/13 Auditable, 5/13 SoftDeletes. L'assenza di Auditable su PricingPolicyExecution (log immutabile) e EstimatedMarketPrice (read-only esterno) è probabilmente intenzionale. BundleComponent e PricingPolicyScope sono i casi più discutibili.

**Severità: BASSA** — La maggior parte delle eccezioni è giustificabile. Solo BundleComponent potrebbe beneficiare dell'aggiunta di Auditable per tracciare modifiche ai componenti di un bundle.

---

## 5. Invarianti del Doc Funzionale — Verifica

| # | Invariante | Rispettato? | Note |
|---|---|:-:|---|
| 1 | Una allocation pu&ograve; alimentare pi&ugrave; canali | ✅ | Offerte multi-canale sulla stessa allocation |
| 2 | I canali non possiedono mai inventory | ✅ | Nessun legame canale→stock |
| 3 | Nessuna vendita senza Offer | ✅ | Offer obbligatoria nel flusso |
| 4 | Pricing esplicito, versionato, auditabile | ✅ | PriceBook + audit trail |
| 5 | Cambiamenti commerciali non muovono stock | ✅ | Nessuna interazione con inventory |
| 6 | Offer non &egrave; una vendita, &egrave; un invito a transare | ✅ | Modello coerente |

**Tutti gli invarianti non-negoziabili sono rispettati.**

---

## 6. Riepilogo Prioritizzato dei Gap

### Critici (blockers per produzione)

| # | Gap | Impatto | Effort stimato |
|---|---|---|---|
| GAP-7 | Policy di autorizzazione assenti | Sicurezza | Medio (6 Policy classes) |
| GAP-10 | Test suite assente | Affidabilit&agrave; | Alto (servizi + risorse) |

### Importanti (da risolvere prima del go-live)

| # | Gap | Impatto | Effort stimato |
|---|---|---|---|
| GAP-4 | Commercial models non enforced | Integrità dati | Basso (validazione in servizi) |
| GAP-5 | CSV Import | Operatività | Medio (parser + validazione) |
| GAP-8 | Eventi cross-module assenti | Architettura | Medio (5-6 eventi + listener) |

### Migliorativi (nice-to-have)

| # | Gap | Impatto | Effort stimato |
|---|---|---|---|
| GAP-1 | PriceBookEntry→SKU (divergenza doc↔PRD) | Documentazione | Basso (solo decisione da documentare) |
| GAP-2 | Entità Campaign strutturata | Governance | Medio (modello + risorsa) |
| GAP-3 | Customer segment su PriceBook | Granularità | Basso (campo + scope) |
| GAP-6 | PIM Sync per Bundle | Cross-module | Dipende da PIM |
| GAP-9 | EMP deviation aggregata | Dashboard | Basso (query + calcolo) |
| ~~GAP-11~~ | ~~RIMOSSO — PricingPolicy ha 8 tab, non "senza tab"~~ | — | — |
| GAP-12 | 4 modelli senza Auditable (BundleComponent il più critico) | Audit trail | Basso (aggiunta trait) |

---

## 7. Conclusioni

Module S è **sostanzialmente completo** dal punto di vista funzionale. Le 62 user stories del PRD sono implementate al ~97% (60 complete, 2 parziali: US-008 EMP deviation aggregata e US-014 CSV import). I 6 invarianti non-negoziabili della doc funzionale sono tutti rispettati.

I gap principali riguardano **qualità production-grade** (autorizzazione, test) e **raffinamenti architetturali** (eventi, enforcement runtime). La divergenza PriceBookEntry→SKU vs →Offer è una decisione di design del PRD rispetto al doc funzionale, non un bug implementativo.

La stima del CLAUDE.md di "~97%" è **accurata** per la funzionalità core. Considerando autorizzazione e test, la completezza production-ready è più vicina al **85-90%**.

---

## 8. Note sulla Verifica (16 Febbraio 2026)

### 8.1 Prima verifica (16/02 mattina) — 5 agenti paralleli
1. **Agent Models & Schema** — Verificati tutti i 13 modelli, 14 migrazioni, 21 enum (conteggi esatti)
2. **Agent Services & Logic** — Verificati 5 servizi, 7 DTO, 2 job, 3 seeder con ispezione metodi
3. **Agent Filament Resources** — Verificate 6 risorse, 6 pagine, wizard/tab counts
4. **Agent Missing Items** — Confermati zero Policy, zero Events, zero domain test
5. **Agent Doc Funzionale** — Estratte definizioni chiave da ERP-FULL-DOC.md per confronto

**Correzioni applicate nella prima verifica:**
- GAP-1: Riclassificato da "divergenza architetturale" a "divergenza doc funzionale↔PRD" (severity MEDIA→BASSA)
- GAP-2: Severity ridotta da MEDIA a BASSA (doc funzionale stesso dice "not first-class executable object")
- GAP-10: Corretto "0%" — esistono 407 righe test AI tools, ma zero domain/service test
- Sezione 2.1: Corretto conteggio canali seed da 6 a 9
- Sezione 3.3: Corretti numeri US per Price Books (consolidati nel PRD come US separate)
- Aggiunti GAP-11 (PricingPolicy view senza tab) e GAP-12 (BundleComponent senza Auditable)

### 8.2 Ri-verifica (16/02 pomeriggio) — 6 agenti paralleli con ispezione diretta del codice

Ri-verifica completa eseguita con 6 agenti paralleli specializzati per validare ogni singola affermazione del documento:
1. **Agent Models/Migrations/Enums** — Verificati trait per modello (HasUuid, Auditable, SoftDeletes), relazioni, campi
2. **Agent Services/DTOs/Jobs/Seeders** — Verificati metodi pubblici, algoritmi, logica CSV, syncWithPim()
3. **Agent Filament Resources/Pages** — Verificati wizard steps, tab counts, relation managers, custom pages
4. **Agent Missing Items** — Confermati zero Policy (su 12 esistenti), zero Events Commercial, 12 test methods AI-only
5. **Agent PRD User Stories** — Verificate US specifiche (US-008, US-014, US-030, US-043, US-044, US-045, US-051, US-054-056)
6. **Agent GAP Claims** — Verificati GAP-1 through GAP-9 + discount stacking + EMP guardrails

**Errori trovati e corretti nella ri-verifica:**

1. **GAP-11 RIMOSSO (ERRORE CRITICO nella prima verifica):** La prima verifica affermava che ViewPricingPolicy.php usasse "standard view senza tab". L'ispezione diretta del file mostra **8 tab** (Overview, Logic, Scope, Execution, Execution History, Impact Preview, Lifecycle, Audit). Il PRD ne specificava 7 — l'implementazione li supera. Questo gap non esiste.

2. **GAP-12 CORRETTO (conteggio Auditable errato):** La prima verifica affermava "12/13 modelli hanno Auditable". La verifica diretta dei `use` statements mostra che solo **9/13 hanno Auditable**. Mancano: BundleComponent, EstimatedMarketPrice, PricingPolicyExecution, PricingPolicyScope. Di questi, solo BundleComponent è discutibile — gli altri 3 hanno giustificazioni valide (log immutabile, read-only esterno, configurazione subordinata).

3. **GAP-10 test method count corretto:** Da "9 test methods" a **12 test methods** (3 per ActiveOffersTool, 3 per PriceBookCoverageTool, 3 per EmpAlertsTool, 3 per authorization role mapping).

4. **Riepilogo US corretto:** Da 59/62 (3 parziali) a **60/62 (2 parziali)** — US-030 (PricingPolicy detail view) è di fatto completa con 8 tab.

5. **Filament Resources dettaglio corretto:** Solo 3 su 6 risorse hanno wizard (PriceBook, Offer, PricingPolicy), non tutte. 4 su 6 hanno view con tab (PriceBook 5 tab, PricingPolicy 8 tab, Offer 7 tab, Bundle 4 tab). 2 relation managers (EntriesRelationManager su PriceBook, ComponentsRelationManager su Bundle).

6. **Custom pages dettaglio:** La sesta pagina custom è PricingIntelligenceDetail, non BulkCreate. BulkCreateOffers è una pagina _dentro_ OfferResource, non una pagina custom standalone.
