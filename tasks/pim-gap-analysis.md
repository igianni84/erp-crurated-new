# PIM Module — Gap Analysis Report

**Data:** 9 Febbraio 2026 | **Ultima verifica codice:** 16 Febbraio 2026
**Fonti confrontate:**
1. **ERP-FULL-DOC.md** (Sezione 4 — Module 0 PIM) — Documentazione funzionale
2. **prd-module-0-pim.md** — PRD UI/UX con 23 user stories
3. **Codebase** — Implementazione effettiva (modelli, enum, Filament, migrazioni, seeders)

---

## 1. RIEPILOGO ESECUTIVO

| Area | ERP-FULL-DOC | PRD (UI/UX) | Implementato | Stato |
|------|:---:|:---:|:---:|:---:|
| Wine Master | ✅ | ✅ | ✅ | ✅ Allineato |
| Wine Variant | ✅ | ✅ | ✅ | ✅ Allineato |
| Format | ✅ | ✅ | ✅ | ✅ Allineato |
| Case Configuration | ✅ | ✅ | ✅ | ✅ Allineato |
| Sellable SKU | ✅ | ✅ | ✅ | ✅ Allineato |
| Composite SKU (Bundle) | ✅ | ✅ | ✅ | ✅ Allineato |
| Liquid Product | ✅ | ✅ | ✅ | ✅ Allineato |
| Product Media | ✅ | ✅ | ✅ | ✅ Allineato |
| Dynamic Attributes | — | ✅ | ✅ | ⚠️ Non in DOC funzionale |
| Lookup Tables (Country/Region/Appellation/Producer) | Parziale | — | ✅ | ⚠️ Superano la DOC |
| Lifecycle Workflow | ✅ | ✅ | ✅ | ⚠️ Differenze stati |
| Liv-ex Integration | ✅ | ✅ | ✅ | ✅ Allineato |
| Data Quality Dashboard | — | ✅ | ✅ | ✅ Allineato al PRD |
| Service Layer | ✅ (implicito) | — | ⚠️ | 🔴 Gap architetturale (LivExService esiste ma non organizzato in Pim/) |
| Events/Listeners | — | — | ❌ | 🔴 Gap architetturale |
| Test Suite | — | ✅ (AC) | ⚠️ | 🔴 Gap critico (solo 1 test AI tools, zero test modelli/servizi) |
| Role-Based Access | ✅ | ✅ (parziale) | ❌ | 🔴 Gap |
| SKU Lifecycle Enum | — | ✅ | ❌ (string) | ⚠️ Parziale |

**Legenda:** ✅ Completo | ⚠️ Parziale/Differenze | 🔴 Gap significativo | — Non specificato

---

## 2. ANALISI DETTAGLIATA PER ENTITÀ

### 2.1 Wine Master

| Aspetto | DOC Funzionale | PRD | Implementato | Delta |
|---------|:-:|:-:|:-:|------|
| Nome/Cuvée | ✅ | ✅ | ✅ `name` | — |
| Producer (stringa) | ✅ | ✅ | ✅ `producer` (legacy) | — |
| Producer (FK relazionale) | — | — | ✅ `producer_id` → Producer | **EXTRA**: Implementato lookup relazionale non in documentazione |
| Appellation | ✅ | ✅ | ✅ `appellation` + `appellation_id` | — |
| Classification | ✅ | ✅ | ✅ `classification` | — |
| Country | ✅ | ✅ | ✅ `country` + `country_id` | — |
| Region | ✅ | ✅ | ✅ `region` + `region_id` | — |
| Liv-ex Reference | ✅ | ✅ | ✅ `liv_ex_code` | — |
| Description | ✅ (producer story) | ✅ | ✅ `description` | — |
| Regulatory Attributes | ✅ | ✅ | ✅ `regulatory_attributes` (JSON) | — |
| Producer metadata (story, estate) | ✅ | — | ❌ | **GAP**: Non esiste campo dedicato per story/estate info del produttore |

**Osservazione chiave:** L'implementazione ha un design dual-field (stringa legacy + FK) per backward compatibility. Questo è un pattern pragmatico non documentato ma corretto.

---

### 2.2 Wine Variant

| Aspetto | DOC Funzionale | PRD | Implementato | Delta |
|---------|:-:|:-:|:-:|------|
| wine_master_id | ✅ | ✅ | ✅ FK cascade | — |
| vintage_year | ✅ | ✅ | ✅ integer, unique per master | — |
| alcohol_percentage | ✅ | ✅ | ✅ decimal(4,2) | — |
| Drinking window | ✅ | ✅ | ✅ start/end integers | — |
| Critic scores | ✅ | ✅ | ✅ JSON | — |
| Production notes | ✅ | ✅ | ✅ JSON | — |
| Lifecycle status | ✅ (4 stati) | ✅ (5 stati) | ✅ (5 stati) | ⚠️ Vedi sotto |
| Data source | ✅ | ✅ | ✅ DataSource enum | — |
| LWIN code | ✅ | ✅ | ✅ `lwin_code` | — |
| Internal code | — | ✅ | ✅ `internal_code` | — |
| Thumbnail URL | — | — | ✅ `thumbnail_url` | Extra |
| Locked fields | — | ✅ | ✅ JSON array | — |
| Completeness % | — | ✅ | ✅ Calcolato dinamicamente | — |
| Blocking issues | — | ✅ | ✅ 4 regole (master, vintage, SKU, image) | — |

#### ⚠️ Delta Lifecycle States

| Stato | DOC Funzionale | PRD | Implementato |
|-------|:-:|:-:|:-:|
| Draft | ✅ | ✅ | ✅ |
| In Review | — | ✅ | ✅ |
| Reviewed | ✅ | — | — |
| Approved | — | ✅ | ✅ |
| Active/Published | ✅ `active` | ✅ `published` | ✅ `Published` |
| Retired/Archived | ✅ `retired` | ✅ `archived` | ✅ `Archived` |

**Delta significativo:** La DOC funzionale definisce 4 stati (`draft`, `reviewed`, `active`, `retired`), il PRD ne definisce 5 (`draft`, `in_review`, `approved`, `published`, `archived`). L'implementazione segue il PRD con 5 stati. La DOC funzionale è quindi **outdated** su questo punto — il PRD e l'implementazione sono allineati tra loro.

---

### 2.3 Format

| Aspetto | DOC Funzionale | PRD | Implementato | Delta |
|---------|:-:|:-:|:-:|------|
| volume_ml | ✅ | ✅ | ✅ integer | — |
| name | — | ✅ | ✅ string | — |
| is_standard | ✅ `standard flag` | ✅ | ✅ boolean | — |
| allowed_for_liquid_conversion | ✅ | ✅ | ✅ boolean | — |
| Formati supportati | 0.375, 0.75, 1.5, 3.0 | 0.375, 0.75, 1.5, 3.0 | 375, 750, 1500, 3000, 6000 | ⚠️ Imperial (6000ml) extra |

**Nota:** L'implementazione include Imperial (6000ml/Jeroboam) non menzionato in nessuna documentazione. È un'aggiunta ragionevole.

---

### 2.4 Case Configuration

| Aspetto | DOC Funzionale | PRD | Implementato | Delta |
|---------|:-:|:-:|:-:|------|
| format_id | ✅ | ✅ | ✅ FK | — |
| bottles_per_case | ✅ | ✅ | ✅ integer | — |
| case_type | ✅ (OWC, OC, none) | ✅ | ✅ enum string | — |
| is_original_from_producer | ✅ | ✅ | ✅ boolean | — |
| is_breakable | ✅ `breakable` | ✅ | ✅ boolean | — |
| name | — | ✅ | ✅ "6x750ml OWC" | — |

**Completamente allineato.**

---

### 2.5 Sellable SKU

| Aspetto | DOC Funzionale | PRD | Implementato | Delta |
|---------|:-:|:-:|:-:|------|
| wine_variant_id | ✅ | ✅ | ✅ FK | — |
| format_id | ✅ | ✅ | ✅ FK | — |
| case_configuration_id | ✅ | ✅ | ✅ FK | — |
| sku_code | ✅ | ✅ auto-gen | ✅ auto-gen in boot() | — |
| barcode | ✅ | ✅ | ✅ nullable | — |
| lifecycle_status | ✅ `draft/active/retired` | ✅ SkuLifecycleStatus enum | ⚠️ String constants | **GAP** |
| is_intrinsic | ✅ (concept) | ✅ | ✅ boolean | — |
| is_composite | ✅ | ✅ | ✅ boolean | — |
| is_producer_original | — | — | ✅ boolean | Extra |
| is_verified | — | — | ✅ boolean | Extra |
| source | — | — | ✅ manual/liv_ex/producer/generated | Extra |
| notes | — | — | ✅ text | Extra |
| Unique constraint | ✅ (variant+format+case) | ✅ | ✅ | — |

#### ⚠️ Gap: SkuLifecycleStatus
Il PRD specifica un **enum dedicato** `SkuLifecycleStatus` con `draft`, `active`, `retired`. L'implementazione usa **costanti stringa** nel modello:
```php
const STATUS_DRAFT = 'draft';
const STATUS_ACTIVE = 'active';
const STATUS_RETIRED = 'retired';
```
Manca il pattern enum con `label()`, `color()`, `icon()`, `allowedTransitions()` come da convenzioni del progetto.

---

### 2.6 Composite SKU (Bundle)

| Aspetto | DOC Funzionale | PRD | Implementato | Delta |
|---------|:-:|:-:|:-:|------|
| CompositeSkuItem model | ✅ | ✅ | ✅ | — |
| composite_sku_id FK | ✅ | ✅ | ✅ | — |
| sellable_sku_id FK | ✅ | ✅ | ✅ | — |
| quantity | ✅ | ✅ | ✅ default 1 | — |
| Unique constraint | — | — | ✅ (composite+component) | Extra |
| Validation pre-activation | ✅ (all active) | ✅ | ✅ `validateCompositeForActivation()` | — |
| No circular references | ✅ | — | ❌ | **GAP**: No validation against circular refs |

---

### 2.7 Liquid Product

| Aspetto | DOC Funzionale | PRD | Implementato | Delta |
|---------|:-:|:-:|:-:|------|
| wine_variant_id | ✅ unique | ✅ | ✅ FK unique | — |
| allowed_equivalent_units | ✅ | ✅ | ✅ JSON | — |
| allowed_final_formats | ✅ | ✅ | ✅ JSON | — |
| allowed_case_configurations | ✅ | ✅ | ✅ JSON | — |
| bottling_constraints | ✅ | ✅ | ✅ JSON | — |
| serialization_required | ✅ `true default` | ✅ | ✅ boolean | — |
| lifecycle_status | — | ✅ | ✅ string | — |
| JSON validation schema | — | — | ❌ | **GAP**: No validation rules for JSON fields |

---

### 2.8 Product Media

| Aspetto | DOC Funzionale | PRD | Implementato | Delta |
|---------|:-:|:-:|:-:|------|
| wine_variant_id | — | ✅ | ✅ FK | — |
| type (image/document) | ✅ | ✅ | ✅ | — |
| source (manual/liv_ex) | ✅ | ✅ | ✅ DataSource | — |
| file_path | — | ✅ | ✅ | — |
| external_url | — | ✅ | ✅ | — |
| is_primary | — | ✅ | ✅ auto-unset siblings | — |
| sort_order | — | ✅ | ✅ | — |
| is_locked | — | ✅ | ✅ | — |
| original_filename | — | — | ✅ | Extra |
| mime_type | — | — | ✅ | Extra |
| file_size | — | — | ✅ | Extra |
| alt_text | — | — | ✅ | Extra |
| caption | — | — | ✅ | Extra |
| Versioning | ✅ | — | ❌ | **GAP**: DOC dice "all enrichment is versioned" ma no versioning media |
| 3D bottle assets | ✅ | — | ❌ | **GAP**: Nessun supporto per asset 3D |

---

### 2.9 Dynamic Attributes System

| Aspetto | DOC Funzionale | PRD | Implementato | Delta |
|---------|:-:|:-:|:-:|------|
| AttributeSet | — | ✅ US-017 | ✅ model + seeder | — |
| AttributeGroup | — | ✅ | ✅ 4 groups seeded | — |
| AttributeDefinition | — | ✅ | ✅ 8 tipi supportati | — |
| AttributeValue | — | ✅ | ✅ per variant | — |
| Completeness weighting | — | ✅ `pesi` | ✅ `completeness_weight` | — |
| Admin-configurable sets | — | ✅ (ambiguo) | ❌ | **GAP**: Solo seeded, no Filament CRUD per gestire AttributeSet/Group/Definition |

**Nota importante:** La DOC funzionale NON menziona il sistema di attributi dinamici. È un'aggiunta del PRD UI/UX implementata correttamente. Il seeder crea 1 set default (`wine_default`), 4 gruppi (wine_info, production, tasting, compliance), e **24 attributi** con pesi di completeness differenziati. Gli attributi sono integrati dinamicamente nel tab "Attributes" di WineVariantResource e contribuiscono al calcolo di `getDynamicCompletenessPercentage()`.

---

### 2.10 Lookup Tables (Country, Region, Appellation, Producer)

| Aspetto | DOC Funzionale | PRD | Implementato | Delta |
|---------|:-:|:-:|:-:|------|
| Country model | ✅ (menzionato come campo) | — | ✅ Modello + Seeder (18 paesi) | **EXTRA** |
| Region model | ✅ (menzionato come campo) | — | ✅ Gerarchico con parent (113 regioni) | **EXTRA** |
| Appellation model | ✅ (menzionato come campo) | — | ✅ Con AppellationSystem enum (156 appellazioni) | **EXTRA** |
| Producer model | ✅ (menzionato come campo) | — | ✅ Con party_id per B2B (133 produttori) | **EXTRA** |
| Filament CRUD | — | — | ✅ Resources complete | **EXTRA** |

**Nota:** La DOC e il PRD menzionano country/region/appellation/producer come **campi stringa** del Wine Master. L'implementazione li ha promossi a **modelli first-class** con lookup relazionali, gerarchie (Region), e sistema di appellazione (AppellationSystem enum con 14 casi). Questo è un miglioramento significativo rispetto alla documentazione.

---

## 3. ANALISI UI/UX (PRD vs Implementazione)

### 3.1 Filament Resources

| Resource | PRD Prevede | Implementato | Delta |
|----------|:-:|:-:|------|
| WineMasterResource | ✅ | ✅ CRUD completo | — |
| WineVariantResource | ✅ (6 tabs) | ✅ (6 tabs) + SellableSkusRelationManager | — |
| SellableSkuResource | ✅ | ✅ | — |
| FormatResource | ✅ | ✅ | — |
| CaseConfigurationResource | ✅ | ✅ | — |
| LiquidProductResource | ✅ | ✅ | — |
| ProductResource (hub) | ✅ | ✅ (create flows) | — |
| CountryResource | — | ✅ | Extra |
| RegionResource | — | ✅ | Extra |
| AppellationResource | — | ✅ | Extra |
| ProducerResource | — | ✅ | Extra |
| Data Quality Dashboard | ✅ | ✅ PimDashboard | — |

### 3.2 User Stories Coverage

| US | Titolo | Implementato | Note |
|----|--------|:-:|------|
| US-001 | Creare Wine Master | ✅ | WineMasterResource form |
| US-002 | Creare Wine Variant | ✅ | WineVariantResource + CreateManualBottle |
| US-003 | Gestire Format | ✅ | FormatResource |
| US-004 | Gestire Case Configuration | ✅ | CaseConfigResource |
| US-005 | Creare Sellable SKU | ✅ | SellableSkuResource + SellableSkusRelationManager (in WineVariantResource) |
| US-006 | Gestire Liquid Product | ✅ | LiquidProductResource |
| US-007 | Lifecycle workflow | ✅ | ProductLifecycleStatus + transition actions |
| US-008 | Completeness % | ✅ | getCompletenessPercentage() + dashboard |
| US-009 | Blocking issues | ✅ | getBlockingIssues() + warnings |
| US-010 | Pubblicazione | ✅ | canPublish() + action |
| US-011 | Import Liv-ex | ✅ | ImportLivex page + LivExService |
| US-012 | Lock fields Liv-ex | ✅ | locked_fields JSON + indicators UI |
| US-013 | Override lock (Admin) | ✅ | Tab "Override Locked Fields" con audit |
| US-014 | Ricerca globale prodotti | ✅ | ProductResource con filtri |
| US-015 | Gestione media | ✅ | Tab Media con dual-source |
| US-016 | Refresh Liv-ex media | ✅ | `refresh_livex_media` action |
| US-017 | Attributi dinamici | ✅ | AttributeSet system + tab Attributes |
| US-018 | Media management avanzato | ✅ | Upload + primary + reorder con persistenza sort_order in EditWineVariant::saveMedia() |
| US-019 | Data Quality Dashboard | ✅ | PimDashboard page |
| US-020 | Export CSV issues | ✅ | `exportIssues()` StreamedResponse |
| US-021 | Audit trail | ✅ | Trait Auditable + tab Audit dedicato in WineVariantResource con timeline, filtri per tipo evento, e diff old/new values |
| US-022 | Role-based permissions | ❌ | No Policy/Gate implementati per PIM |
| US-023 | Composite SKU | ✅ | CompositeSkuItem + RelationManager |

**Coverage: 22/23 complete, 0 parziali, 1 mancante**

---

## 4. GAP CRITICI

### 🔴 GAP-01: Service Layer PIM non organizzato
**Severità: Media-Alta**

| | Dettaglio |
|---|---|
| **DOC** | Architecture patterns specifica "Service layer: Business logic in Services, not Controllers or Models" |
| **PRD** | Non specifica services |
| **Implementato** | `LivExService` esiste in `app/Services/LivExService.php` (non nel subfolder `Pim/`). Tutta l'altra business logic e' in Models (completeness, lifecycle, validation) e Filament Pages (create flows, import) |
| **Impatto** | Viola il pattern architetturale del progetto. Tutti gli altri moduli (Finance: 14 servizi, Procurement: 4, Fulfillment: 4, Allocation: 5, Inventory: 4, Commercial: 5) hanno service layer dedicato in subfolder |
| **Raccomandazione** | Spostare `LivExService` in `app/Services/Pim/LivExService.php`. Creare `WineVariantService`, `SellableSkuService` |

---

### 🔴 GAP-02: Nessun Test per modelli e servizi PIM
**Severità: Alta**

| | Dettaglio |
|---|---|
| **DOC** | Quality: PHPStan level 5, PHPUnit |
| **PRD** | Ogni US ha acceptance criteria con "typecheck/lint requirements" |
| **Implementato** | Esiste solo `tests/Unit/AI/Tools/Pim/PimToolsTest.php` (test per AI tools: ProductCatalogSearchTool, DataQualityIssuesTool). Zero test per modelli, servizi e workflow PIM core |
| **Impatto** | No regression testing, no CI/CD validation, no refactoring safety |
| **Raccomandazione** | Priorità test per: lifecycle transitions, SKU generation, completeness calculation, composite validation, Liv-ex import |

---

### 🔴 GAP-03: Nessun Event/Listener PIM
**Severità: Media**

| | Dettaglio |
|---|---|
| **DOC** | "Event-driven cross-module: Events trigger listeners" |
| **PRD** | Non specifica events |
| **Implementato** | Nessun Event PIM (tutti gli altri moduli li hanno) |
| **Impatto** | Altri moduli non possono reagire a cambiamenti PIM (es. ProductPublished → notifica Commercial) |
| **Raccomandazione** | Creare almeno: `ProductPublished`, `ProductArchived`, `SkuActivated`, `SkuRetired` |

---

### 🔴 GAP-04: No Role-Based Permissions (Policies)
**Severità: Media-Alta**

| | Dettaglio |
|---|---|
| **DOC** | Sezione 4.6.2: Product Manager, Content Editor, Reviewer/Approver, Admin con permessi differenziati |
| **PRD** | US-022: Role-based access (Open Question #4) |
| **Implementato** | Nessuna Policy per modelli PIM, nessun gate check nelle risorse Filament |
| **Impatto** | Tutti gli utenti possono fare tutto: creare, modificare, pubblicare, archiviare |
| **Raccomandazione** | Implementare `WineMasterPolicy`, `WineVariantPolicy`, `SellableSkuPolicy` con ruoli da infrastruttura (super_admin/admin/manager/editor/viewer) |

---

### 🟡 GAP-05: SkuLifecycleStatus non è un Enum
**Severità: Bassa-Media**

| | Dettaglio |
|---|---|
| **PRD** | Specifica `SkuLifecycleStatus` come enum |
| **Implementato** | String constants nel model (`STATUS_DRAFT`, `STATUS_ACTIVE`, `STATUS_RETIRED`) |
| **Impatto** | Incoerenza con ProductLifecycleStatus (che è un enum proper). Niente `label()`, `color()`, `icon()`, `allowedTransitions()` |
| **Raccomandazione** | Creare `app/Enums/Pim/SkuLifecycleStatus.php` conforme alle convenzioni |

---

### 🟡 GAP-06: Versioning Media e Content non implementato
**Severità: Bassa**

| | Dettaglio |
|---|---|
| **DOC** | Sezione 4.5: "All enrichment is versioned, has a source, can be audited" |
| **Implementato** | Source tracking (✅), Audit via trait (✅), ma nessun versioning esplicito |
| **Impatto** | Non è possibile ripristinare una versione precedente di media o contenuti |
| **Raccomandazione** | Valutare se necessario. Il trait Auditable traccia le modifiche ma non permette rollback |

---

### 🟡 GAP-07: 3D Bottle Assets non supportati
**Severità: Bassa**

| | Dettaglio |
|---|---|
| **DOC** | Sezione 4.5: "3D bottle assets" tra i contenuti supportati |
| **Implementato** | ProductMedia supporta solo image/document come type |
| **Raccomandazione** | Aggiungere type `3d_model` o `asset_3d` se necessario |

---

### 🟡 GAP-08: No Admin CRUD per Attribute Sets
**Severità: Bassa-Media**

| | Dettaglio |
|---|---|
| **PRD** | US-017 menziona attributi "caricati dinamicamente da attribute set" |
| **Implementato** | Solo via seeder (`AttributeSetSeeder`). Nessun Filament resource per gestire sets/groups/definitions |
| **Impatto** | Per aggiungere/modificare attributi serve un developer che modifica il seeder |
| **Raccomandazione** | Creare `AttributeSetResource` con gestione groups e definitions |

---

### 🟡 GAP-09: Dashboard Filters non collegati
**Severità: Bassa**

| | Dettaglio |
|---|---|
| **Implementato** | PimDashboard ha proprietà `$dateFrom`, `$dateTo`, `$eventTypeFilter` dichiarate e inizializzate in `mount()`, ma non utilizzate né dai metodi PHP (`getStatusCounts()`, `getCompletenessDistribution()`, etc.) né dal template Blade (`pim-dashboard.blade.php`). Nessun elemento UI per filtrare. |
| **Impatto** | Il dashboard mostra sempre tutti i dati senza possibilità di filtrare per periodo |
| **Raccomandazione** | Collegare le proprietà ai metodi di query (aggiungendo clausole `whereBetween` sulle date) e aggiungere filtri Livewire nel Blade, oppure rimuovere le proprietà inutilizzate |

---

### 🟡 GAP-10: Navigation Sort Order Duplicato
**Severità: Bassa**

| | Dettaglio |
|---|---|
| **Implementato** | Due coppie duplicate: SellableSkuResource e CountryResource hanno entrambi `$navigationSort = 5`; LiquidProductResource e RegionResource hanno entrambi `$navigationSort = 6` |
| **Impatto** | Ordine imprevedibile nel menu sidebar per 4 risorse |
| **Raccomandazione** | Riassegnare sequenzialmente: Product=0, WineMaster=1, WineVariant=2, Format=3, CaseConfig=4, SellableSku=5, Country=6, LiquidProduct=7, Region=8, Appellation=9, Producer=10 |

---

### 🟡 GAP-11: Circular Reference Composite SKU
**Severità: Media**

| | Dettaglio |
|---|---|
| **DOC** | Composite SKUs sono "indivisibili" |
| **PRD** | "No circular composite SKU references" |
| **Implementato** | Nessuna validation contro riferimenti circolari (SKU A contiene B, B contiene A) |
| **Raccomandazione** | Aggiungere check in `validateCompositeForActivation()` |

---

### 🟡 GAP-12: Producer Metadata mancanti
**Severità: Bassa**

| | Dettaglio |
|---|---|
| **DOC** | Wine Master ha "producer metadata (story, estate info)" |
| **Implementato** | Producer model ha solo: name, country_id, region_id, party_id, website |
| **Raccomandazione** | Valutare se aggiungere `description`, `story`, `estate_info` al Producer model |

---

## 5. ELEMENTI EXTRA (implementati ma non documentati)

| Elemento | Presente in | Note |
|----------|------------|------|
| Lookup Tables (Country/Region/Appellation/Producer) come modelli first-class | Solo implementazione | Miglioramento architetturale significativo |
| AppellationSystem enum (14 sistemi) | Solo implementazione | AOC, DOCG, AVA, DO, etc. |
| Region hierarchy (parent_region_id) | Solo implementazione | Sub-regioni multi-livello |
| Producer → Party link (party_id) | Solo implementazione | Integrazione cross-module con Customers |
| SellableSku integrity flags | Solo implementazione | is_intrinsic, is_producer_original, is_verified |
| SellableSku source tracking | Solo implementazione | manual, liv_ex, producer, generated |
| Intrinsic SKU auto-generation | Solo implementazione | 5 configurazioni standard |
| Imperial format (6000ml) | Solo implementazione | Aggiunto ai formati standard |
| Dual-field (legacy string + FK) | Solo implementazione | Backward compatibility pattern |
| Product creation hub (ChooseCategory → CreateBottle → Import/Manual) | Solo PRD + implementazione | Non in DOC funzionale |
| LivExService (mock) | Solo implementazione | Servizio per integrazione Liv-ex |

---

## 6. MATRICE INVARIANTI

Verifica che gli invarianti definiti nella DOC siano rispettati nell'implementazione:

| # | Invariante DOC | Implementato | Enforcement |
|---|---------------|:-:|-------------|
| 1 | Wine identity ≠ sellable SKU | ✅ | Modelli separati, WineMaster → WineVariant → SellableSku |
| 2 | Case configuration è first-class | ✅ | CaseConfiguration è un model dedicato con relazioni |
| 3 | Liquid products non sono SKU | ✅ | LiquidProduct è modello separato, 1:1 con WineVariant |
| 4 | SKU non implica disponibilità o prezzo | ✅ | Nessun campo prezzo/stock in SellableSku |
| 5 | PIM non codifica business policy | ✅ | No pricing, no allocation, no inventory logic |
| 6 | SKU non è customer entitlement | ✅ | Nessuna relazione diretta SKU → Customer |

**Tutti gli invarianti sono rispettati. ✅**

---

## 7. STATISTICHE IMPLEMENTAZIONE

| Metrica | Quantità |
|---------|----------|
| Modelli PIM | 16 |
| Enum usati dal PIM | 3 — ma solo `AppellationSystem` è in `app/Enums/Pim/`; `ProductLifecycleStatus` e `DataSource` sono in `app/Enums/` (root level, condivisi) |
| Enum PIM (missing) | 1 (SkuLifecycleStatus) |
| Services PIM | 1 (LivExService in `app/Services/`, non nel subfolder `Pim/`) |
| Filament Resources | 11 (WineMaster, WineVariant, SellableSku, Format, CaseConfiguration, LiquidProduct, Product, Country, Region, Appellation, Producer) + 1 RelationManager (SellableSkusRelationManager in WineVariantResource) |
| Filament Custom Pages | 6 (PimDashboard, ChooseProductCategory, CreateBottleProduct, CreateManualBottle, ImportLivex, ListProducts) |
| Migrazioni PIM | 20 (serie 200000+, inclusa `add_lookup_fks_to_wine_masters_table`) |
| Seeders PIM | 13 (Country, Region, Appellation, Producer, Format, AttributeSet, CaseConfiguration, LiquidProduct, WineMaster, WineVariant, SellableSku, ProductMedia, InventoryCaseSeeder) |
| Test PIM | 1 (PimToolsTest — solo AI tools, zero test modelli/servizi) |
| Events PIM | 0 |
| Policies PIM | 0 |

---

## 8. PRIORITÀ RACCOMANDAZIONI

| Priorità | Gap | Effort | Impatto |
|----------|-----|--------|---------|
| 🔴 P1 | GAP-02: Test suite | Alto | Critico per CI/CD e refactoring |
| 🔴 P1 | GAP-04: Role-based permissions | Medio | Critico per sicurezza multi-utente |
| 🔴 P2 | GAP-01: Service layer | Medio | Debito tecnico architetturale |
| 🔴 P2 | GAP-03: Events/Listeners | Basso | Cross-module communication |
| 🟡 P3 | GAP-05: SkuLifecycleStatus enum | Basso | Coerenza codebase |
| 🟡 P3 | GAP-08: Attribute Set admin CRUD | Medio | Self-service per PM |
| 🟡 P3 | GAP-11: Circular ref validation | Basso | Data integrity |
| 🟡 P4 | GAP-06: Media versioning | Medio | Nice-to-have |
| 🟡 P4 | GAP-07: 3D assets | Basso | Nice-to-have |
| 🟡 P4 | GAP-09: Dashboard filters | Basso | UX improvement |
| 🟡 P4 | GAP-10: Nav sort order | Triviale | Bug fix |
| 🟡 P4 | GAP-12: Producer metadata | Basso | Data completeness |

---

## 9. CONCLUSIONI

### Punti di forza
1. **Core entities completamente allineate** — Wine Master, Variant, SKU, Format, Case Config, Liquid Product sono tutti implementati come da documentazione
2. **Invarianti rispettati** — Tutti e 6 gli invarianti PIM sono enforced nell'implementazione
3. **UI/UX PRD ben implementato** — 22/23 user stories completamente coperte
4. **Miglioramenti non documentati** — Lookup tables relazionali, AppellationSystem, region hierarchy, integrity flags sono aggiunte di valore
5. **Liv-ex integration funzionante** — Import, lock, unlock, refresh media tutto implementato

### Aree critiche da indirizzare
1. **Zero test per modelli/servizi** — Solo 1 test AI tools esiste. Nessuna safety net per refactoring o regression sui modelli core PIM
2. **Nessun access control** — Tutti possono fare tutto. Serve urgentemente per go-live
3. **Service layer non organizzato** — LivExService esiste ma non nel subfolder Pim/. Mancano WineVariantService, SellableSkuService. Viola le convenzioni architetturali del progetto
4. **Nessun evento** — Impedisce l'integrazione event-driven con altri moduli

### Documentazione funzionale da aggiornare
1. **Lifecycle states** — Aggiornare da 4 a 5 stati per allinearsi all'implementazione
2. **Lookup tables** — Documentare Country, Region, Appellation, Producer come entità
3. **Dynamic attributes** — Aggiungere sezione dedicata
4. **Integrity flags SKU** — Documentare is_intrinsic, is_producer_original, is_verified

---

## 10. LOG DI VERIFICA

### Verifica del 16 Febbraio 2026 (audit approfondito su codebase)

**Metodologia:** Verifica sistematica di ogni singola affermazione nel documento tramite lettura diretta dei file sorgente (modelli, migrazioni, enum, seeders, risorse Filament, servizi, test, policy, template Blade).

**Correzioni applicate:**

| # | Sezione | Errore originale | Correzione |
|---|---------|-----------------|------------|
| 1 | §3.2 US-018 | Marcato ⚠️ "no bulk reorder persist" | Corretto a ✅ — `EditWineVariant::saveMedia()` persiste `sort_order` per ogni media item nel repeater reorderable |
| 2 | §3.2 US-021 | Marcato ⚠️ "no Audit viewer dedicato in PIM" | Corretto a ✅ — WineVariantResource ha un tab "Audit" completo con timeline, filtri per tipo evento, e diff old/new values (`getAuditSchema()`, righe 1683-1843) |
| 3 | §3.2 Coverage | "20/23 complete, 2 parziali" | Corretto a "22/23 complete, 0 parziali, 1 mancante" |
| 4 | §2.10 Lookup counts | "150+" regioni, "150+" appellazioni, "90+" produttori | Corretto a 113 regioni, 156 appellazioni, 133 produttori (conteggio esatto dai seeders) |
| 5 | §7 Enum location | "3 (ProductLifecycleStatus, DataSource, AppellationSystem)" come enum PIM | Chiarito che solo `AppellationSystem` è in `app/Enums/Pim/`; gli altri due sono in `app/Enums/` (root, condivisi) |
| 6 | §7 Migrazioni | "19" | Corretto a 20 (mancava `add_lookup_fks_to_wine_masters_table`) |
| 7 | §7 Seeders | "12" | Corretto a 13 (mancava `InventoryCaseSeeder`) |
| 8 | GAP-10 | Solo 1 coppia duplicata (sort=5) | Aggiunte 2 coppie: CountryResource/SellableSkuResource (sort=5) e LiquidProductResource/RegionResource (sort=6) |
| 9 | GAP-09 | Descrizione generica | Precisato che le proprietà non sono usate né dai metodi PHP né dal template Blade |
| 10 | §2.8 ProductMedia | Mancavano campi extra | Aggiunti `original_filename`, `mime_type`, `file_size` come campi Extra |
| 11 | §2.9 Attributi | Mancava dettaglio seeder | Aggiunto: 1 set, 4 gruppi, 24 attributi con pesi di completeness |
| 12 | §9 Conclusioni | "20/23" | Corretto a "22/23" |

**Elementi confermati corretti (non modificati):**
- Tutti i campi di WineMaster, WineVariant, Format, CaseConfiguration, SellableSku, CompositeSkuItem, LiquidProduct sono verificati ✅
- ProductLifecycleStatus enum: 5 stati con `label()`, `color()`, `icon()`, `allowedTransitions()` ✅
- SellableSku usa string constants (non enum) — confermato ✅
- Nessun SkuLifecycleStatus enum — confermato ✅
- Nessun servizio in `app/Services/Pim/` — confermato ✅
- LivExService in `app/Services/LivExService.php` (root) — confermato ✅
- Zero eventi PIM — confermato ✅
- Zero policy PIM — confermato ✅
- Zero test modelli/servizi PIM — confermato ✅
- Tutti i 6 invarianti rispettati — confermato ✅
- Nessun CRUD Filament per AttributeSet/Group/Definition — confermato ✅
- Nessun versioning media — confermato ✅
- ProductMedia type limitato a image/document — confermato ✅
- Nessuna circular reference validation in CompositeSkuItem — confermato ✅
- Producer model senza description/story/estate_info — confermato ✅
- AppellationSystem enum con esattamente 14 casi — confermato ✅
