# PRD: Module 0 — Product Information Management (PIM)

## Introduction

Il modulo PIM è il sistema autoritativo per l'identità e la struttura dei prodotti nell'ERP Crurated. Risponde alla domanda: "Cosa esiste e come è strutturalmente definito?"

PIM **non decide**:
- Se un prodotto può essere venduto
- A chi può essere venduto
- A quale prezzo
- Se l'inventario esiste

Queste decisioni appartengono ai moduli downstream (Sales, Allocations, Inventory).

**Publishing in PIM = completezza della definizione**, non disponibilità commerciale.

---

## Goals

- Creare un catalogo prodotti strutturato e normalizzato
- Separare chiaramente Wine Identity da Sellable Units
- Modellare esplicitamente Cases e Packaging come entità first-class
- Supportare import dati da Liv-ex come fonte primaria
- Implementare workflow di approvazione con lifecycle states
- Garantire audit trail completo per compliance e due diligence
- Supportare Liquid Products (pre-bottling) come categoria distinta

---

## User Stories

### Infrastruttura PIM

#### US-001: Setup modello Wine Master
**Description:** Come Product Manager, voglio definire l'identità base di un vino (indipendente da vintage) per avere una struttura gerarchica pulita.

**Acceptance Criteria:**
- [ ] Tabella `wine_masters` con campi: name, producer, appellation, classification, country, region, description
- [ ] Campi opzionali: liv_ex_code, regulatory_attributes (JSON)
- [ ] Filament Resource con form di creazione/modifica
- [ ] Ricerca per nome, producer, appellation
- [ ] Typecheck e lint passano

---

#### US-002: Setup modello Wine Variant (Vintage)
**Description:** Come Product Manager, voglio associare un vintage specifico a un Wine Master per gestire le annate.

**Acceptance Criteria:**
- [ ] Tabella `wine_variants` con FK a wine_masters
- [ ] Campi: vintage_year, alcohol_percentage, drinking_window_start, drinking_window_end
- [ ] Campi JSON: critic_scores, production_notes
- [ ] Relazione: WineMaster hasMany WineVariant
- [ ] Filament Resource con filtro per Wine Master
- [ ] Vincolo: vintage_year unico per wine_master_id
- [ ] Typecheck e lint passano

---

#### US-003: Setup modello Format
**Description:** Come Admin, voglio definire i formati bottiglia standard per riutilizzarli su tutti i prodotti.

**Acceptance Criteria:**
- [ ] Tabella `formats` con campi: name, volume_ml, is_standard, allowed_for_liquid_conversion
- [ ] Formati predefiniti: 375ml, 750ml, 1500ml, 3000ml, 6000ml
- [ ] Filament Resource con creazione/modifica
- [ ] Soft delete per mantenere integrità referenziale
- [ ] Typecheck e lint passano

---

#### US-004: Setup modello Case Configuration
**Description:** Come Product Manager, voglio definire le configurazioni case (OWC, OC, loose) per gestire correttamente il packaging.

**Acceptance Criteria:**
- [ ] Tabella `case_configurations` con campi: name, format_id (FK), bottles_per_case, case_type (enum: owc, oc, none)
- [ ] Campi boolean: is_original_from_producer, is_breakable
- [ ] Configurazioni predefinite: 6x750ml OWC, 6x750ml OC, 12x750ml OWC, 1x1500ml OWC, loose
- [ ] Filament Resource con filtri per format e case_type
- [ ] Typecheck e lint passano

---

#### US-005: Setup modello Sellable SKU
**Description:** Come Product Manager, voglio creare SKU vendibili combinando Wine Variant + Format + Case Configuration.

**Acceptance Criteria:**
- [ ] Tabella `sellable_skus` con FK: wine_variant_id, format_id, case_configuration_id
- [ ] Campi: sku_code (unique, auto-generated), barcode (optional)
- [ ] Enum lifecycle_status: draft, active, retired
- [ ] SKU code format: `{WINE_CODE}-{VINTAGE}-{FORMAT}-{CASE}` (es: SASS-2018-750-6OWC)
- [ ] Filament Resource nested dentro Wine Variant detail
- [ ] Vincolo: combinazione wine_variant + format + case_configuration unica
- [ ] Typecheck e lint passano

---

#### US-006: Setup modello Liquid Product
**Description:** Come Product Manager, voglio definire Liquid Products per vendite pre-bottling.

**Acceptance Criteria:**
- [ ] Tabella `liquid_products` con FK a wine_variant_id
- [ ] Campi JSON: allowed_equivalent_units, allowed_final_formats, allowed_case_configurations, bottling_constraints
- [ ] Campo boolean: serialization_required (default true)
- [ ] Enum lifecycle_status: draft, in_review, approved, published, archived
- [ ] Liquid Products non hanno Sellable SKUs diretti
- [ ] Filament Resource separato da Bottle Products
- [ ] Typecheck e lint passano

---

### Lifecycle & Workflow

#### US-007: Implementare lifecycle states per Wine Variant
**Description:** Come Reviewer, voglio che i Wine Variant seguano un workflow di approvazione prima di essere pubblicati.

**Acceptance Criteria:**
- [ ] Enum `ProductLifecycleStatus`: draft, in_review, approved, published, archived
- [ ] Campo lifecycle_status su wine_variants
- [ ] Transizioni valide: draft→in_review, in_review→approved/rejected(draft), approved→published, published→archived
- [ ] Bottoni azione in Filament basati su stato corrente e ruolo utente
- [ ] Modifica di campi sensibili su Published → ritorno automatico a In Review
- [ ] Typecheck e lint passano

---

#### US-008: Implementare completeness percentage
**Description:** Come Product Manager, voglio vedere la percentuale di completamento di un prodotto per sapere cosa manca.

**Acceptance Criteria:**
- [ ] Metodo `getCompletenessPercentage()` su WineVariant
- [ ] Definizione campi required vs optional con pesi
- [ ] Colonna completeness nella lista prodotti
- [ ] Badge colorato: <50% rosso, 50-80% giallo, >80% verde
- [ ] Typecheck e lint passano

---

#### US-009: Implementare blocking issues e warnings
**Description:** Come Product Manager, voglio vedere chiaramente quali problemi bloccano la pubblicazione.

**Acceptance Criteria:**
- [ ] Metodo `getBlockingIssues()` che ritorna array di problemi
- [ ] Metodo `getWarnings()` per problemi non bloccanti
- [ ] Tab Overview mostra issues raggruppati
- [ ] Click su issue naviga al tab/campo corrispondente
- [ ] Blocco pubblicazione se esistono blocking issues
- [ ] Typecheck e lint passano

---

### Audit & Tracking

#### US-010: Implementare audit log
**Description:** Come Compliance Officer, voglio un log immutabile di tutte le modifiche ai prodotti.

**Acceptance Criteria:**
- [ ] Tabella `audit_logs` con: auditable_type, auditable_id, event, old_values, new_values, user_id, created_at
- [ ] Trait `Auditable` per modelli PIM
- [ ] Log automatico su create, update, delete, status change
- [ ] Tab Audit in Product Detail con timeline
- [ ] Filtri per tipo evento e date range
- [ ] Typecheck e lint passano

---

### UI/UX Admin Panel

#### US-011: Product List view
**Description:** Come Operator, voglio una lista prodotti completa con filtri per gestire il catalogo.

**Acceptance Criteria:**
- [ ] Lista con colonne: thumbnail, name+vintage, category, lifecycle_status, completeness%, data_source, updated_at
- [ ] Filtri: status, category (bottle/liquid), completeness range, source (liv-ex/manual)
- [ ] Ricerca: nome, internal code, LWIN
- [ ] Bulk actions: submit for review (solo su draft)
- [ ] No azioni sensibili (publish, archive) dalla lista
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-012: Create Product flow - scelta categoria
**Description:** Come Product Manager, voglio scegliere se creare un Bottle Product o Liquid Product all'inizio.

**Acceptance Criteria:**
- [ ] Wizard step 1: selezione categoria (Bottle/Liquid)
- [ ] Scelta irreversibile dopo creazione
- [ ] UI chiara con descrizione di ogni categoria
- [ ] Redirect a flow specifico dopo selezione
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-013: Create Bottle Product - import Liv-ex
**Description:** Come Product Manager, voglio importare dati da Liv-ex per creare prodotti velocemente con dati accurati.

**Acceptance Criteria:**
- [ ] Step 2a: selezione metodo (Liv-ex / Manual)
- [ ] Ricerca Liv-ex per LWIN o nome vino
- [ ] Mostra risultati matching con preview dati
- [ ] Conferma import con indicazione campi che saranno locked
- [ ] Creazione Wine Master se non esiste
- [ ] Creazione Wine Variant in stato Draft
- [ ] Import attributi e media da Liv-ex
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-014: Create Bottle Product - manual
**Description:** Come Product Manager, voglio creare un prodotto manualmente quando non disponibile su Liv-ex.

**Acceptance Criteria:**
- [ ] Step 2b: form manuale con campi minimi (wine name, vintage, producer)
- [ ] Creazione o selezione Wine Master esistente
- [ ] Creazione Wine Variant in stato Draft
- [ ] Redirect a Product Detail per completamento
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-015: Product Detail - Tab Overview
**Description:** Come Product Manager, voglio vedere lo stato complessivo di un prodotto a colpo d'occhio.

**Acceptance Criteria:**
- [ ] Read-only control panel
- [ ] Mostra: identity, lifecycle status, completeness %, blocking issues, warnings, Liv-ex status
- [ ] Azioni contestuali: Validate, Submit for Review, Approve/Reject, Publish
- [ ] Azioni visibili solo se permesse da ruolo e stato
- [ ] Click su issue naviga al tab corretto
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-016: Product Detail - Tab Core Info
**Description:** Come Product Manager, voglio modificare le informazioni base del prodotto.

**Acceptance Criteria:**
- [ ] Campi: wine name, vintage, producer, appellation, internal references, descriptions
- [ ] Campi Liv-ex: marcati visivamente, locked by default
- [ ] Override Liv-ex: solo Manager/Admin con conferma
- [ ] Modifica campo sensibile su Published → status diventa In Review
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-017: Product Detail - Tab Attributes
**Description:** Come Product Manager, voglio gestire gli attributi strutturati del prodotto.

**Acceptance Criteria:**
- [ ] Attributi caricati dinamicamente da attribute set
- [ ] Raggruppamento in sezioni logiche (Wine Info, Compliance, etc.)
- [ ] Indicatore required/optional per attributo
- [ ] Mostra: valore corrente, source (Liv-ex/Manual), editability
- [ ] Completeness aggiornata in real-time
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-018: Product Detail - Tab Media
**Description:** Come Content Editor, voglio gestire immagini e documenti del prodotto.

**Acceptance Criteria:**
- [ ] Sezione Liv-ex media (read-only)
- [ ] Sezione manual uploads (editable)
- [ ] Upload multiple images e documents
- [ ] Drag & drop reorder per manual images
- [ ] Set primary image (required per publish)
- [ ] Refresh Liv-ex assets button
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-019: Product Detail - Tab Sellable SKUs
**Description:** Come Product Manager, voglio definire come questo vino può essere venduto.

**Acceptance Criteria:**
- [ ] Lista SKU con: code, format, case configuration, integrity flags, lifecycle status
- [ ] Create SKU: select format → select case configuration
- [ ] Generate intrinsic SKUs from producer data
- [ ] Retire obsolete SKUs
- [ ] SKU lifecycle indipendente da product lifecycle
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-020: Product Detail - Tab Lifecycle
**Description:** Come Reviewer, voglio gestire le transizioni di stato del prodotto.

**Acceptance Criteria:**
- [ ] Mostra: stato corrente, publish readiness checklist, blocking vs warnings
- [ ] Solo transizioni valide visibili basate su ruolo
- [ ] Transizioni: Draft→In Review, In Review→Approved/Rejected, Approved→Published, Published→Archived
- [ ] Conferma con commento obbligatorio per Reject
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-021: Product Detail - Tab Audit
**Description:** Come Compliance Officer, voglio vedere la storia completa delle modifiche.

**Acceptance Criteria:**
- [ ] Timeline read-only con tutti gli eventi
- [ ] Eventi: status changes, attribute edits, media updates, SKU changes, Liv-ex imports
- [ ] Mostra: timestamp, user, tipo evento, old→new values
- [ ] Filtri per tipo evento e date range
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-022: Data Quality dashboard
**Description:** Come Operations Manager, voglio monitorare la salute del catalogo prodotti.

**Acceptance Criteria:**
- [ ] Vista cross-product con metriche aggregate
- [ ] Widgets: prodotti per status, completeness distribution, blocking issues count
- [ ] Lista prodotti bloccati con link diretto al problema
- [ ] Export lista issues per bulk fixing
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Composite SKUs (Bundles)

#### US-023: Supporto Composite Sellable SKUs
**Description:** Come Product Manager, voglio creare SKU bundle composti da multiple Bottle SKUs.

**Acceptance Criteria:**
- [ ] Tabella `composite_sku_items` con: composite_sku_id, sellable_sku_id, quantity
- [ ] Campo `is_composite` boolean su sellable_skus
- [ ] Composite SKU = indivisibile commercialmente
- [ ] Validazione: tutti gli SKU componenti devono essere active
- [ ] UI per definire composizione bundle
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

## Functional Requirements

- **FR-1:** Wine Master è l'entità radice che rappresenta l'identità di un vino indipendente da vintage
- **FR-2:** Wine Variant rappresenta un vintage specifico di un Wine Master
- **FR-3:** Format definisce le dimensioni bottiglia riutilizzabili (375ml, 750ml, 1500ml, etc.)
- **FR-4:** Case Configuration definisce il packaging fisico (OWC, OC, loose) con integrità e breakability
- **FR-5:** Sellable SKU = Wine Variant × Format × Case Configuration
- **FR-6:** Liquid Product rappresenta vino pre-bottling, vendibile solo via voucher
- **FR-7:** Lifecycle status segue workflow: Draft → In Review → Approved → Published → Archived
- **FR-8:** Completeness percentage calcolata su campi required/optional con pesi
- **FR-9:** Blocking issues impediscono la pubblicazione; warnings sono informativi
- **FR-10:** Audit log immutabile per tutte le modifiche
- **FR-11:** Liv-ex è fonte primaria; campi importati sono locked by default
- **FR-12:** Composite SKU rappresenta bundle atomici di Bottle SKUs
- **FR-13:** SKU code auto-generato con formato consistente

---

## Non-Goals

- PIM non gestisce pricing (Module S)
- PIM non gestisce disponibilità o allocations (Module A)
- PIM non gestisce inventory fisico (Module B)
- PIM non gestisce eligibility clienti (Module K)
- PIM non gestisce ordini o fulfillment (Module C)
- Import automatico bulk da Liv-ex (fuori scope MVP)
- Multi-language content (fuori scope MVP)

---

## Technical Considerations

### Database Schema Principale

```
wine_masters
├── id
├── name
├── producer
├── appellation
├── classification
├── country
├── region
├── description
├── liv_ex_code
├── regulatory_attributes (JSON)
└── timestamps

wine_variants
├── id
├── wine_master_id (FK)
├── vintage_year
├── alcohol_percentage
├── drinking_window_start
├── drinking_window_end
├── critic_scores (JSON)
├── production_notes (JSON)
├── lifecycle_status (enum)
├── data_source (enum: liv_ex, manual)
├── liv_ex_data (JSON)
└── timestamps

formats
├── id
├── name
├── volume_ml
├── is_standard
├── allowed_for_liquid_conversion
└── timestamps + soft_deletes

case_configurations
├── id
├── name
├── format_id (FK)
├── bottles_per_case
├── case_type (enum: owc, oc, none)
├── is_original_from_producer
├── is_breakable
└── timestamps

sellable_skus
├── id
├── wine_variant_id (FK)
├── format_id (FK)
├── case_configuration_id (FK)
├── sku_code (unique)
├── barcode
├── lifecycle_status (enum)
├── is_composite
└── timestamps

composite_sku_items
├── id
├── composite_sku_id (FK)
├── sellable_sku_id (FK)
├── quantity
└── timestamps

liquid_products
├── id
├── wine_variant_id (FK)
├── allowed_equivalent_units (JSON)
├── allowed_final_formats (JSON)
├── allowed_case_configurations (JSON)
├── bottling_constraints (JSON)
├── serialization_required
├── lifecycle_status (enum)
└── timestamps

audit_logs
├── id
├── auditable_type
├── auditable_id
├── event
├── old_values (JSON)
├── new_values (JSON)
├── user_id (FK)
└── created_at
```

### Filament Resources

- `WineMasterResource` - CRUD Wine Masters
- `WineVariantResource` - CRUD Wine Variants con tabs
- `FormatResource` - CRUD Formats
- `CaseConfigurationResource` - CRUD Case Configs
- `SellableSkuResource` - Nested in WineVariant
- `LiquidProductResource` - CRUD Liquid Products
- `DataQualityResource` - Dashboard cross-product

### Enums

```php
enum ProductLifecycleStatus: string {
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Published = 'published';
    case Archived = 'archived';
}

enum SkuLifecycleStatus: string {
    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';
}

enum CaseType: string {
    case Owc = 'owc';
    case Oc = 'oc';
    case None = 'none';
}

enum DataSource: string {
    case LivEx = 'liv_ex';
    case Manual = 'manual';
}
```

---

## Success Metrics

- 100% dei Wine Variants hanno lifecycle status tracciato
- 100% delle modifiche hanno audit log
- Import Liv-ex riduce tempo creazione prodotto del 70%
- Zero SKU duplicati (vincolo database enforced)
- Completeness % visibile su tutti i prodotti

---

## Open Questions

1. Quali sono i campi esatti importati da Liv-ex API?
2. Ci sono attribute sets diversi per categorie di vino (Bordeaux vs Champagne)?
3. È necessario supportare multi-currency per attributi come critic scores?
4. Quali ruoli utente servono? (Admin, Manager, Editor, Reviewer)
5. Serve integrazione real-time con Liv-ex o batch import è sufficiente?
