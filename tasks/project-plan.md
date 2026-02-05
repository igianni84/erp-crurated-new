# Crurated ERP - Piano di Progetto

## Tech Stack

- **Backend**: Laravel 12
- **Admin Panel**: Filament 5
- **Database**: MySQL
- **Approccio**: Sviluppo incrementale modulo per modulo

---

## Mappa delle Dipendenze tra Moduli

```
                         ┌─────────────────┐
                         │ Infrastructure  │
                         │ (Laravel, Auth, │
                         │  Filament)      │
                         └────────┬────────┘
                                  │
                    ┌─────────────┼─────────────┐
                    │                           │
                    ▼                           ▼
             ┌─────────────┐            ┌───────────┐
             │  Module 0   │            │ Module K  │
             │    PIM      │            │ Customers │
             │(Wine, SKU)  │            │(Governance│
             └──────┬──────┘            │ - no deps)│
                    │                   └─────┬─────┘
                    │                         │
                    └───────────┬─────────────┘
                                │
                                ▼
                        ┌─────────────┐
                        │  Module A   │
                        │ Allocations │
                        │ & Vouchers  │
                        └──────┬──────┘
                               │
              ┌────────────────┼────────────────┐
              │                │                │
              ▼                ▼                ▼
      ┌───────────┐    ┌───────────┐    ┌───────────┐
      │ Module S  │    │ Module D  │    │           │
      │Commercial │    │Procurement│    │           │
      │(PIM,A,K)  │    │ (PIM,A)   │    │           │
      └─────┬─────┘    └─────┬─────┘    │           │
            │                │          │           │
            │                ▼          │           │
            │        ┌───────────┐      │           │
            │        │ Module B  │      │           │
            │        │ Inventory │      │           │
            │        │(PIM,A,D)  │      │           │
            │        └─────┬─────┘      │           │
            │              │            │           │
            └──────────────┴────────────┘           │
                           │                        │
                           ▼                        │
                   ┌───────────┐                    │
                   │ Module C  │                    │
                   │Fulfillment│                    │
                   │(PIM,A,B,  │                    │
                   │ S,K)      │                    │
                   └─────┬─────┘                    │
                         │                         │
                         ▼                         │
                   ┌───────────┐                   │
                   │ Module E  │◄──────────────────┘
                   │ Finance   │
                   │ (A,C,K)   │
                   └─────┬─────┘
                         │
                         ▼
                   ┌───────────┐
                   │  Admin    │
                   │  Panel    │
                   │ (tutti)   │
                   └───────────┘
```

**Legenda dipendenze:**
- Module K: nessuna (governance layer indipendente)
- Module A: PIM, K
- Module S: PIM, A, K
- Module D: PIM, A
- Module B: PIM, A, D
- Module C: PIM, A, B, S, K
- Module E: A, C, K

---

## Fasi di Sviluppo

### Fase 1: Fondamenta (PIM + Infrastruttura)
**Durata stimata**: Core foundation
**Priorità**: CRITICA

| Modulo | Descrizione | Dipende da |
|--------|-------------|------------|
| Infrastruttura | Setup Laravel 12, Filament 5, MySQL, Auth, Tenancy | - |
| Module 0 - PIM | Wine Master, Wine Variant, Formats, Case Config, Sellable SKU, Liquid Products | Infrastruttura |

**Deliverables Fase 1:**
- [ ] Progetto Laravel configurato con Filament
- [ ] Sistema di autenticazione e ruoli
- [ ] CRUD completo per Wine Master
- [ ] CRUD completo per Wine Variant
- [ ] Gestione Formats e Case Configurations
- [ ] Sellable SKU con lifecycle states
- [ ] Liquid Products (pre-bottling)
- [ ] Integrazione Liv-ex (import dati)
- [ ] Sistema di validazione e completeness %
- [ ] Audit log per tutte le entità

---

### Fase 2: Clienti e Commerciale
**Priorità**: ALTA

| Modulo | Descrizione | Dipende da |
|--------|-------------|------------|
| Module K - Customers | Party, Customer profiles, Tiers, Eligibility rules | PIM |
| Module S - Commercial | Channels, Price lists, Campaigns, Currency rules | PIM, Customers |

**Deliverables Fase 2:**
- [ ] Gestione Party (B2C, B2B, Partners)
- [ ] Customer profiles con tier system
- [ ] Regole di eligibility
- [ ] Definizione canali di vendita
- [ ] Price lists per canale/tier
- [ ] Gestione campagne
- [ ] Currency e tax rules

---

### Fase 3: Inventory
**Priorità**: ALTA

| Modulo | Descrizione | Dipende da |
|--------|-------------|------------|
| Module B - Inventory | Warehouses, Bottles, Cases, Serialization, Custody, Provenance | PIM |

**Deliverables Fase 3:**
- [ ] Gestione Warehouses
- [ ] Inventory tracking (bottle-level)
- [ ] Serializzazione bottiglie
- [ ] Case management (intact/broken)
- [ ] Ownership vs Custody separation
- [ ] Provenance tracking
- [ ] Custody transfers

---

### Fase 4: Allocations & Vouchers (Core Commerciale)
**Priorità**: CRITICA

| Modulo | Descrizione | Dipende da |
|--------|-------------|------------|
| Module A - Allocations | Allocation definitions, Constraints, Supply caps | PIM, Commercial, Customers |
| Module A - Vouchers | Voucher lifecycle, Liabilities, Trading | Allocations |

**Deliverables Fase 4:**
- [ ] Definizione Allocations
- [ ] Constraint system (eligibility, quantity limits)
- [ ] Allocation consumption tracking
- [ ] Voucher issuance
- [ ] Voucher lifecycle states
- [ ] Late binding enforcement
- [ ] Voucher trading rules

---

### Fase 5: Operations
**Priorità**: MEDIA

| Modulo | Descrizione | Dipende da |
|--------|-------------|------------|
| Module D - Procurement | Purchase Orders, Bottling Instructions | PIM, Allocations, Inventory |
| Module C - Fulfillment | Redemption, Shipment planning, WMS integration | Vouchers, Inventory |

**Deliverables Fase 5:**
- [ ] Purchase Order management
- [ ] Bottling instructions
- [ ] Inbound receiving
- [ ] Redemption workflow
- [ ] Late binding resolution
- [ ] Shipment planning
- [ ] WMS integration interface

---

### Fase 6: Finance
**Priorità**: MEDIA

| Modulo | Descrizione | Dipende da |
|--------|-------------|------------|
| Module E - Accounting | Invoices, Payments, Credits, Financial events | Tutti i moduli precedenti |

**Deliverables Fase 6:**
- [ ] Invoice lifecycle (INV0, INV1, INV2)
- [ ] Payment tracking
- [ ] Credit notes
- [ ] Financial event log
- [ ] Xero integration interface

---

### Fase 7: Admin Panel & Reporting
**Priorità**: MEDIA

| Modulo | Descrizione | Dipende da |
|--------|-------------|------------|
| Admin Panel | Dashboards, Controls, Settings | Tutti i moduli |

**Deliverables Fase 7:**
- [ ] Dashboard operativa
- [ ] Dashboard finanziaria
- [ ] Data quality monitoring
- [ ] System settings
- [ ] User & role management

---

## PRD per Fase

| Fase | PRD | Status |
|------|-----|--------|
| 1 | `prd-infrastructure.md` | ✅ Completato |
| 1 | `prd-module-0-pim.md` | ✅ Completato |
| 2 | `prd-module-k-customers.md` | ✅ Completato |
| 3 | `prd-module-a-allocations.md` | ✅ Completato |
| 4 | `prd-module-s-commercial.md` | ✅ Completato |
| 4 | `prd-module-d-procurement.md` | ✅ Completato |
| 5 | `prd-module-b-inventory.md` | ✅ Completato |
| 6 | `prd-module-c-fulfillment.md` | ✅ Completato |
| 7 | `prd-module-e-finance.md` | ✅ Completato |
| 8 | `prd-admin-panel.md` | ✅ Completato |

**Note:**
- I PRD sono organizzati secondo le dipendenze reali tra moduli
- `prd-module-a-vouchers.md` è integrato in `prd-module-a-allocations.md`
- `prd-module-e-accounting.md` rinominato in `prd-module-e-finance.md`

---

## Convenzioni Tecniche

### Struttura Laravel
```
app/
├── Models/
│   ├── Pim/           # Module 0
│   ├── Customer/      # Module K
│   ├── Commercial/    # Module S
│   ├── Inventory/     # Module B
│   ├── Allocation/    # Module A
│   ├── Procurement/   # Module D
│   ├── Fulfillment/   # Module C
│   └── Accounting/    # Module E
├── Filament/
│   └── Resources/     # Admin panel resources
├── Enums/             # State enums per module
├── Services/          # Business logic
└── Events/            # Domain events
```

### Naming Conventions
- Models: `WineMaster`, `WineVariant`, `SellableSku`
- Tables: `wine_masters`, `wine_variants`, `sellable_skus`
- Enums: `SkuLifecycleStatus`, `VoucherState`
- Resources: `WineMasterResource`, `SellableSkuResource`

### State Machines
Ogni entità con lifecycle usa enum + state machine pattern:
- Draft → In Review → Approved → Published → Archived

---

## Prossimi Passi

1. **Iniziare implementazione Fase 1** - Infrastructure (Laravel 12, Filament 5, MySQL)
2. **Convertire PRD in prd.json** - Usare il formato Ralph per automazione
3. **Implementare Module 0 (PIM)** - Prima entità di business
4. **Procedere con Module K (Customers)** - Governance layer
5. **Continuare con i moduli successivi** - Seguendo l'ordine delle dipendenze

## Sequenza di Implementazione Raccomandata

| Ordine | Modulo | Dipendenze | User Stories |
|--------|--------|------------|--------------|
| 1 | Infrastructure | - | 12 |
| 2 | Module 0 (PIM) | Infrastructure | 23 |
| 3 | Module K (Customers) | - | 35 |
| 4 | Module A (Allocations) | PIM, K | 40 |
| 5 | Module S (Commercial) | PIM, A, K | 62 |
| 6 | Module D (Procurement) | PIM, A | 68 |
| 7 | Module B (Inventory) | PIM, A, D | 58 |
| 8 | Module C (Fulfillment) | PIM, A, B, S, K | 62 |
| 9 | Module E (Finance) | A, C, K | 132 |
| 10 | Admin Panel | Tutti | 50 |

**Totale: 542 User Stories**

---

## Key Invariants (Cross-Module Rules)

Queste regole devono essere rispettate durante tutta l'implementazione e sono enforced nei singoli moduli:

### Allocation & Lineage
1. **Allocation lineage is immutable** (A, B, C) - Una volta assegnato, l'allocation_id su voucher/bottle non cambia mai
2. **No cross-allocation substitution** (B, C) - Bottiglie di allocations diverse MAI intercambiabili
3. **Allocation constraints are authoritative** (S, A) - Module S non può vendere oltre i vincoli di Module A

### Vouchers & Redemption
4. **No voucher without sale confirmation** (A) - Vouchers creati solo dopo conferma esplicita di vendita
5. **One voucher = one bottle** (A, C) - quantity sempre = 1
6. **Voucher redemption ONLY at shipment confirmation** (C) - Voucher redeemed quando shipment confirmed, MAI prima

### Binding Rules
7. **Late Binding ONLY in Module C** (C) - Unico punto autorizzato per legare voucher → bottiglia
8. **Early binding only for personalized bottling** (A, D) - Altrimenti forbidden

### Inventory
9. **Bottles exist ONLY after serialization** (B) - Prima del serialization, il vino esiste come quantity non-identificata
10. **Serial numbers are immutable** (B) - Una volta assegnati, non possono essere modificati
11. **Case breakability is irreversible** (A, B, C) - BROKEN non può tornare INTACT

### Finance
12. **Finance is consequence, not cause** (E) - Gli eventi finanziari sono generati da altri moduli, non viceversa
13. **Invoice type is immutable** (E) - Non può mai essere modificato dopo creazione
14. **Payments are evidence, not authority** (E) - Non creano diritti operativi direttamente

### Operations
15. **Procurement Intent exists before PO** (D) - PO cannot exist without Procurement Intent
16. **No shipment without Shipping Order** (C) - SO è autorizzazione esplicita, sempre richiesta
17. **ERP authorizes, WMS executes** (B, C) - Authority model chiaro, no override
