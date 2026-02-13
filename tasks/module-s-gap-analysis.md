# Module S (Commercial) — Gap Analysis Report

**Data:** 9 Febbraio 2026
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
| **Filament Resources** | 95% | 6 risorse con wizard, view multi-tab, relation managers |
| **Pagine custom** | 100% | 6 pagine (Overview, Calendar, Audit, Simulation, Intelligence, BulkCreate) |
| **DTOs** | 100% | 7 DTO per la simulazione prezzi |
| **Job schedulati** | 100% | 2 job (scadenza offerte, esecuzione policy) |
| **Seeder** | 100% | 3 seeder (Channel, PriceBook, Offer) |
| **CSV Import** | 5% | Solo stub UI, nessuna logica backend |
| **PIM Sync (Bundle)** | 0% | Placeholder, dipende da PIM composite SKU |
| **Policy (Authorization)** | 0% | Nessuna Policy class per i modelli Commercial |
| **Events/Listeners** | 0% | Nessun evento cross-module per Commercial |
| **Test suite** | 0% | Nessun test PHPUnit per Module S |

**Stima complessiva: ~90% completo** (funzionalità core pronta, mancano autorizzazione, eventi, test e CSV import)

---

## 2. Entit&agrave; — Confronto Doc Funzionale vs Implementazione

### 2.1 Channel

| Specifica (Doc Funzionale) | Implementato | Gap |
|---|:-:|---|
| channel_id, channel_type, default_currency | ✅ | — |
| allowed_commercial_models (voucher-based, sell-through) | ✅ campo | ⚠️ **Campo presente ma non enforced a runtime** — nessun servizio valida che un'operazione su canale rispetti i modelli commerciali permessi |
| Status (Active/Inactive) | ✅ | — |
| Canali stabili, non proliferanti | ✅ | 6 canali seed (B2C EU/US/UK/CH, B2B, Club) |

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
| Ogni price line: offer_id + unit_price | ⚠️ Diverso | Le entry referenziano `sellable_sku_id`, **NON `offer_id`** — divergenza dal doc funzionale |
| Approvazione richiede ruolo Manager+ | ✅ | `canApprovePriceBooks()` check in PriceBookService |
| Overlap detection | ✅ | Validazione nel wizard di creazione |

**Gap critico:** La doc funzionale specifica che il pricing &egrave; "per Offer, not per SKU" per permettere pricing specifico per canale/segmento/campagna sullo stesso prodotto. L'implementazione invece lega le PriceBookEntry allo `sellable_sku_id`, non all'`offer_id`. Questo pu&ograve; funzionare se il PriceBook &egrave; gi&agrave; scopato per canale (e lo &egrave;), ma perde la granularit&agrave; per-offer che il doc prevede.

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

**Gap significativo:** La doc funzionale descrive Campaign come entit&agrave; con campi strutturati, lifecycle, e approvazione. L'implementazione riduce tutto a un semplice tag testuale. Questo impedisce governance e approvazione a livello campagna.

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

| US | Descrizione | Stato |
|---|---|:-:|
| US-009 | PriceBook model | ✅ |
| US-010 | PriceBookEntry model | ✅ |
| US-011 | Wizard 4-step per creazione | ✅ |
| US-012 | Detail view con 5 tab | ✅ |
| US-013 | Grid editing inline | ✅ |
| US-014 | Attivazione con ruolo approver | ✅ |
| US-015 | PriceBookService | ✅ |
| US-016 | Overlap detection | ✅ |
| US-017 | Clone funzionalit&agrave; | ✅ |
| US-018 | Expiry management | ✅ |
| US-019 | Archivio | ✅ |

### 3.4 Pricing Policies (US-020 → US-032)

| US | Descrizione | Stato |
|---|---|:-:|
| US-020 | PricingPolicy model | ✅ |
| US-021 | PricingPolicyScope model | ✅ |
| US-022 | PricingPolicyExecution model | ✅ |
| US-023 | Wizard 6-step | ✅ |
| US-024 | Detail view con tab | ✅ |
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

**Riepilogo User Stories: 60/62 complete (97%), 2 parziali**

---

## 4. Gap Critici — Analisi Dettagliata

### GAP-1: PriceBookEntry referenzia SKU, non Offer (DIVERGENZA ARCHITETTURALE)

**Doc funzionale (sez. 5.4.4):**
> "Pricing is always defined per Offer, not per SKU, to allow channel-, segment-, and campaign-specific pricing over the same underlying product."
> Ogni price line include: `offer_id`, `unit_price`

**Implementazione:**
PriceBookEntry ha `sellable_sku_id` + `base_price`, NON `offer_id`.

**Impatto:** Il PriceBook &egrave; gi&agrave; scopato per canale/mercato/currency, quindi la differenziazione per canale funziona. Ma la granularit&agrave; per-Offer (es. pricing diverso per campagna sullo stesso canale) non &egrave; possibile senza duplicare PriceBook. Questa divergenza pu&ograve; essere intenzionale per semplicit&agrave;, ma va documentata come decisione di design.

**Severit&agrave;: MEDIA** — Funziona per i casi d'uso attuali, ma limita scenari avanzati.

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

**Severit&agrave;: MEDIA** — Il doc stesso dice "Campaign is not a first-class executable object" e "campaigns are created and managed by bulk creation of Offers with shared attributes". L'approccio tag &egrave; coerente con questa definizione, ma manca la struttura per governance e approvazione.

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

### GAP-10: Test suite assente

**Nessun test PHPUnit** per Module S: n&eacute; unit test dei servizi, n&eacute; feature test delle risorse Filament.

**Severit&agrave;: ALTA per produzione** — Servizi complessi come PricingPolicyService (calcolo prezzi multi-tipo), OfferService (eligibility, price resolution), SimulationService (5-step chain) non hanno copertura test.

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
| GAP-1 | PriceBookEntry→SKU vs →Offer | Flessibilit&agrave; pricing | Decisione architetturale |
| GAP-4 | Commercial models non enforced | Integrit&agrave; dati | Basso (validazione in servizi) |
| GAP-5 | CSV Import | Operativit&agrave; | Medio (parser + validazione) |
| GAP-8 | Eventi cross-module assenti | Architettura | Medio (5-6 eventi + listener) |

### Migliorativi (nice-to-have)

| # | Gap | Impatto | Effort stimato |
|---|---|---|---|
| GAP-2 | Entit&agrave; Campaign strutturata | Governance | Medio (modello + risorsa) |
| GAP-3 | Customer segment su PriceBook | Granularit&agrave; | Basso (campo + scope) |
| GAP-6 | PIM Sync per Bundle | Cross-module | Dipende da PIM |
| GAP-9 | EMP deviation aggregata | Dashboard | Basso (query + calcolo) |

---

## 7. Conclusioni

Module S &egrave; **sostanzialmente completo** dal punto di vista funzionale. Le 62 user stories del PRD sono implementate al 97% (60 complete, 2 parziali). I 6 invarianti non-negoziabili della doc funzionale sono tutti rispettati.

I gap principali riguardano **qualit&agrave; production-grade** (autorizzazione, test) e **raffinamenti architetturali** (eventi, enforcement runtime, Campaign come entit&agrave;). La divergenza PriceBookEntry→SKU vs →Offer merita una decisione architetturale consapevole e documentata.

La stima del CLAUDE.md di "~97%" &egrave; accurata per la funzionalit&agrave; core. Considerando autorizzazione e test, la completezza production-ready &egrave; pi&ugrave; vicina al **85-90%**.
