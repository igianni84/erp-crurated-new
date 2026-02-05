# PRD: Module K — Parties, Customers & Eligibility

## Introduction

Module K è il sistema autoritativo per l'identità delle parti e la governance dei clienti nell'ERP Crurated. Risponde alla domanda: "Chi esiste nel sistema e cosa sono autorizzati a fare?"

Module K **decide**:
- Chi può esistere come controparte (Party)
- Quali ruoli una Party può avere (Customer, Supplier, Producer, Partner)
- Se un Customer è eleggibile per operazioni specifiche
- Quali canali e account un Customer può utilizzare
- Se esistono blocchi operativi che impediscono transazioni

Module K **non decide**:
- Quali prodotti esistono (Module 0 - PIM)
- A quali prezzi vendere (Module S - Commercial)
- Se l'inventario esiste (Module B - Inventory)
- Come allocare prodotti (Module A - Allocations)

**Nessuna vendita, emissione voucher, redemption, trading o spedizione può procedere senza il clearance di Module K.**

---

## Goals

- Creare un registro autoritativo di tutte le parti (Party) nel sistema
- Gestire i ruoli delle parti (Customer, Supplier, Producer, Partner)
- Implementare il sistema di membership con tiers (Legacy, Member, Invitation Only)
- Definire e calcolare l'eligibility per canale e account
- Controllare le payment permissions e credit limits
- Gestire le affiliazioni Club come fattori di eligibility
- Implementare Operational Blocks come sistema di enforcement
- Garantire audit trail completo per compliance e governance

---

## User Stories

### Sezione 1: Infrastruttura Party

#### US-001: Setup modello Party
**Description:** Come Admin, voglio definire l'entità base per tutte le controparti del sistema per avere un registro centralizzato.

**Acceptance Criteria:**
- [ ] Tabella `parties` con campi: id, uuid, legal_name, party_type (enum: individual, legal_entity), tax_id, vat_number, jurisdiction, status (enum: active, inactive)
- [ ] Soft deletes abilitati
- [ ] Model Party con relazioni verso PartyRole
- [ ] Validazione: legal_name required, tax_id unico per jurisdiction
- [ ] Typecheck e lint passano

---

#### US-002: Setup modello Party Role
**Description:** Come Admin, voglio assegnare ruoli multipli alle Party per distinguere clienti, fornitori, produttori e partner.

**Acceptance Criteria:**
- [ ] Tabella `party_roles` con FK party_id e campo role (enum: customer, supplier, producer, partner)
- [ ] Una Party può avere multipli ruoli contemporaneamente
- [ ] Relazione: Party hasMany PartyRole
- [ ] Vincolo: combinazione party_id + role unica
- [ ] Typecheck e lint passano

---

#### US-003: Party List in Filament
**Description:** Come Operator, voglio una lista delle Party con filtri per tipo e ruolo per trovare velocemente le controparti.

**Acceptance Criteria:**
- [ ] PartyResource in Filament
- [ ] Lista con colonne: legal_name, party_type, roles (badges), jurisdiction, status, updated_at
- [ ] Filtri: party_type, role, status, jurisdiction
- [ ] Ricerca per: legal_name, tax_id, vat_number
- [ ] Bulk actions: activate, deactivate
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-004: Party Detail view con tabs
**Description:** Come Operator, voglio vedere i dettagli completi di una Party organizzati in tabs.

**Acceptance Criteria:**
- [ ] Tab Overview: identity summary, status, created/updated dates
- [ ] Tab Roles: lista ruoli attivi con possibilità di aggiunta/rimozione
- [ ] Tab Legal: tax_id, vat_number, jurisdiction, compliance notes
- [ ] Tab Audit: timeline read-only di tutte le modifiche
- [ ] Azioni contestuali basate su ruolo utente
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 2: Customer Management

#### US-005: Setup modello Customer
**Description:** Come Admin, voglio il modello Customer come specializzazione di Party per gestire i clienti.

**Acceptance Criteria:**
- [ ] Tabella `customers` con: id, uuid, party_id (FK), customer_type (enum: b2c, b2b, partner), status (enum: prospect, active, suspended, closed), default_billing_address_id (FK nullable)
- [ ] Soft deletes abilitati
- [ ] Relazione: Customer belongsTo Party
- [ ] Customer viene creato automaticamente quando Party riceve ruolo "customer"
- [ ] Typecheck e lint passano

---

#### US-006: Setup modello Account
**Description:** Come Admin, voglio il modello Account per rappresentare contesti operativi multipli di un Customer.

**Acceptance Criteria:**
- [ ] Tabella `accounts` con: id, uuid, customer_id (FK), name, channel_scope (enum: b2c, b2b, club), status (enum: active, suspended)
- [ ] Un Customer può avere multipli Account
- [ ] Relazione: Customer hasMany Account
- [ ] Account eredita restrizioni Customer ma può aggiungerne di proprie
- [ ] Typecheck e lint passano

---

#### US-007: Customer List in Filament
**Description:** Come Operator, voglio una lista clienti completa come schermata operativa principale.

**Acceptance Criteria:**
- [ ] CustomerResource in Filament con navigation group "Customers"
- [ ] Lista con colonne: customer name (via Party), customer_type, membership_tier, status, accounts_count, updated_at
- [ ] Filtri: customer_type, status, membership_tier, has_blocks
- [ ] Ricerca per: legal_name, email, tax_id
- [ ] Quick actions: view, suspend, activate
- [ ] Indicatore visivo se Customer ha blocchi attivi
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-008: Customer Detail view con 9 tabs
**Description:** Come Operator, voglio vedere tutti i dettagli di un Customer organizzati in 9 tabs tematici.

**Acceptance Criteria:**
- [ ] Tab Overview: identity, status, membership summary, active blocks summary
- [ ] Tab Membership: tier, lifecycle, decision history
- [ ] Tab Accounts: lista account con status
- [ ] Tab Addresses: billing e shipping addresses
- [ ] Tab Eligibility: channel eligibility computed (read-only)
- [ ] Tab Payment & Credit: payment permissions, credit limits
- [ ] Tab Clubs: affiliazioni club attive
- [ ] Tab Operational Blocks: blocchi attivi e storici
- [ ] Tab Audit: timeline completa
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-009: Account management (CRUD dentro Customer)
**Description:** Come Operator, voglio creare e gestire Account all'interno del Customer Detail.

**Acceptance Criteria:**
- [ ] Tab Accounts mostra lista Account del Customer
- [ ] Create Account: form con name, channel_scope
- [ ] Edit Account: modifica name e channel_scope
- [ ] Suspend/Activate Account: toggle status
- [ ] Delete Account: solo se nessuna transazione associata
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-010: Address management (billing/shipping)
**Description:** Come Operator, voglio gestire gli indirizzi di billing e shipping di un Customer.

**Acceptance Criteria:**
- [ ] Tabella `addresses` con: addressable_type, addressable_id (polymorphic), type (enum: billing, shipping), line_1, line_2, city, state, postal_code, country, is_default
- [ ] Tab Addresses in Customer Detail
- [ ] CRUD completo per indirizzi
- [ ] Set default billing/shipping address
- [ ] Validazione: almeno un billing address required per Customer active
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 3: Membership & Tiers

#### US-011: Setup modello Membership con lifecycle states
**Description:** Come Admin, voglio il modello Membership per tracciare lo stato di adesione dei Customer.

**Acceptance Criteria:**
- [ ] Tabella `memberships` con: id, customer_id (FK), tier (enum: legacy, member, invitation_only), status (enum: applied, under_review, approved, rejected, suspended), effective_from, effective_to, decision_notes
- [ ] Un Customer ha una sola Membership attiva alla volta
- [ ] Relazione: Customer hasOne Membership (active), hasMany Memberships (historical)
- [ ] Typecheck e lint passano

---

#### US-012: Membership Tiers (Legacy, Member, Invitation Only)
**Description:** Come Admin, voglio configurare i tier di membership con le relative caratteristiche.

**Acceptance Criteria:**
- [ ] Enum MembershipTier con valori: legacy, member, invitation_only
- [ ] Tabella `membership_tier_configs` per configurazioni tier (opzionale, o in codice)
- [ ] Tier "Legacy": grandfathered members, accesso completo
- [ ] Tier "Member": standard membership, approval required
- [ ] Tier "Invitation Only": accesso a prodotti esclusivi
- [ ] Tier influenza channel eligibility (computed)
- [ ] Typecheck e lint passano

---

#### US-013: Membership Tab in Customer Detail
**Description:** Come Operator, voglio vedere e gestire la Membership di un Customer.

**Acceptance Criteria:**
- [ ] Tab Membership mostra: tier corrente, status, effective dates
- [ ] Timeline decisioni (applied→under_review→approved/rejected)
- [ ] Azioni: Apply for Membership, Submit for Review, Approve, Reject, Suspend
- [ ] Azioni visibili solo se permesse da ruolo e stato corrente
- [ ] Decision notes obbligatorie per Reject e Suspend
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-014: Membership workflow transitions
**Description:** Come Admin, voglio che le transizioni di membership seguano regole precise.

**Acceptance Criteria:**
- [ ] Transizioni valide: applied→under_review, under_review→approved/rejected, approved→suspended, suspended→approved
- [ ] Transizione invalida genera errore user-friendly
- [ ] Ogni transizione logga user_id, timestamp, notes
- [ ] Approval richiede ruolo Manager o superiore
- [ ] Effective_from settato automaticamente su approval
- [ ] Typecheck e lint passano

---

### Sezione 4: Eligibility & Channels

#### US-015: Setup computed eligibility engine
**Description:** Come Developer, voglio un engine che calcoli l'eligibility di un Customer/Account in base a tutti i fattori.

**Acceptance Criteria:**
- [ ] Service class `EligibilityEngine` con metodo `compute(Customer|Account)`
- [ ] Fattori considerati: membership_tier, membership_status, operational_blocks, payment_permissions, club_affiliations
- [ ] Output: array di channels eleggibili con spiegazione per ogni canale
- [ ] Risultato è computed, non stored
- [ ] Typecheck e lint passano

---

#### US-016: Channel eligibility (B2C, B2B, Clubs)
**Description:** Come Operator, voglio sapere a quali canali un Customer è eleggibile.

**Acceptance Criteria:**
- [ ] Enum Channel: b2c, b2b, club
- [ ] Regole B2C: Membership approved + no payment blocks
- [ ] Regole B2B: Membership approved + customer_type = b2b + credit approved
- [ ] Regole Club: Membership approved + club affiliation active
- [ ] Ogni canale mostra: eligible (bool), reasons (array)
- [ ] Typecheck e lint passano

---

#### US-017: Eligibility Tab (read-only, fully explainable)
**Description:** Come Operator, voglio vedere l'eligibility di un Customer con spiegazione completa dei fattori.

**Acceptance Criteria:**
- [ ] Tab Eligibility completamente read-only
- [ ] Per ogni canale: status (eligible/not eligible), lista fattori positivi e negativi
- [ ] Spiegazione human-readable di ogni fattore (es: "Membership is approved", "No active payment blocks")
- [ ] Link ai tab rilevanti per risolvere problemi (es: click su "Payment block" → Tab Operational Blocks)
- [ ] Refresh button per ricalcolare
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 5: Payment & Credit

#### US-018: Setup Payment Permissions model
**Description:** Come Admin, voglio definire quali metodi di pagamento un Customer può usare.

**Acceptance Criteria:**
- [ ] Tabella `payment_permissions` con: id, customer_id (FK unique), card_allowed (bool default true), bank_transfer_allowed (bool default false), credit_limit (decimal nullable)
- [ ] Creato automaticamente con defaults quando Customer diventa active
- [ ] Relazione: Customer hasOne PaymentPermission
- [ ] Typecheck e lint passano

---

#### US-019: Credit limits e bank transfer authorization
**Description:** Come Finance Manager, voglio gestire i credit limits e autorizzare bank transfers.

**Acceptance Criteria:**
- [ ] credit_limit: null = no credit, valore = limite massimo
- [ ] bank_transfer_allowed: richiede approvazione Finance
- [ ] Audit log per ogni modifica a payment permissions
- [ ] Solo ruolo Finance o superiore può modificare
- [ ] Typecheck e lint passano

---

#### US-020: Payment & Credit Tab
**Description:** Come Finance Operator, voglio gestire payment permissions e credit limits dal Customer Detail.

**Acceptance Criteria:**
- [ ] Tab Payment & Credit mostra: card_allowed, bank_transfer_allowed, credit_limit
- [ ] Form edit con validazione
- [ ] Toggle card_allowed e bank_transfer_allowed
- [ ] Input credit_limit con validazione numeric >= 0
- [ ] History delle modifiche in sub-section
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 6: Clubs

#### US-021: Setup modello Club
**Description:** Come Admin, voglio definire i Club come entità per raggruppare clienti.

**Acceptance Criteria:**
- [ ] Tabella `clubs` con: id, uuid, partner_name, status (enum: active, suspended, ended), branding_metadata (JSON)
- [ ] Club è un'entità indipendente da Customer
- [ ] Soft deletes abilitati
- [ ] Typecheck e lint passano

---

#### US-022: Setup modello Customer-Club affiliation
**Description:** Come Admin, voglio tracciare l'affiliazione tra Customer e Club.

**Acceptance Criteria:**
- [ ] Tabella pivot `customer_clubs` con: customer_id, club_id, affiliation_status (enum: active, suspended), start_date, end_date (nullable)
- [ ] Un Customer può appartenere a multipli Club
- [ ] Relazione: Customer belongsToMany Club
- [ ] Affiliation status indipendente da Club status
- [ ] Typecheck e lint passano

---

#### US-023: Club List in Filament
**Description:** Come Operator, voglio una lista dei Club per gestirli.

**Acceptance Criteria:**
- [ ] ClubResource in Filament con navigation group "Customers"
- [ ] Lista con colonne: partner_name, status, members_count, updated_at
- [ ] Filtri: status
- [ ] Ricerca per: partner_name
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-024: Clubs Tab in Customer Detail
**Description:** Come Operator, voglio vedere e gestire le affiliazioni Club di un Customer.

**Acceptance Criteria:**
- [ ] Tab Clubs mostra lista affiliazioni con: club_name, affiliation_status, start_date, end_date
- [ ] Add affiliation: select Club + start_date
- [ ] Edit affiliation: change status, set end_date
- [ ] Remove affiliation: sets end_date to now
- [ ] Indicatore se Club influenza eligibility
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 7: Segments

#### US-025: Setup computed Segments engine
**Description:** Come Developer, voglio un engine che derivi automaticamente i segmenti di un Customer.

**Acceptance Criteria:**
- [ ] Service class `SegmentEngine` con metodo `compute(Customer)`
- [ ] Segmenti derivati da: spending history, membership tier, club affiliations, purchase frequency
- [ ] Segmenti sono computed, non stored
- [ ] Output: array di segment tags
- [ ] Typecheck e lint passano

---

#### US-026: Segments view (read-only, automatic derivation)
**Description:** Come Marketing Operator, voglio vedere i segmenti assegnati automaticamente a un Customer.

**Acceptance Criteria:**
- [ ] Section in Tab Overview o Tab dedicato
- [ ] Lista segmenti come badges
- [ ] Tooltip su ogni segmento spiega criterio derivazione
- [ ] Completamente read-only (no manual override)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 8: Operational Blocks

#### US-027: Setup modello OperationalBlock
**Description:** Come Admin, voglio definire blocchi operativi che impediscono transazioni.

**Acceptance Criteria:**
- [ ] Tabella `operational_blocks` con: id, blockable_type, blockable_id (polymorphic: Customer/Account), block_type (enum), reason, applied_by (FK users), status (enum: active, removed), removed_at, removed_by
- [ ] Un Customer/Account può avere multipli blocchi attivi
- [ ] Relazione: Customer/Account morphMany OperationalBlock
- [ ] Typecheck e lint passano

---

#### US-028: Block types (payment, shipment, redemption, trading, compliance)
**Description:** Come Admin, voglio diversi tipi di blocco per controllare operazioni specifiche.

**Acceptance Criteria:**
- [ ] Enum BlockType: payment, shipment, redemption, trading, compliance
- [ ] Block "payment": impedisce qualsiasi transazione di pagamento
- [ ] Block "shipment": impedisce spedizioni
- [ ] Block "redemption": impedisce redemption voucher
- [ ] Block "trading": impedisce trading voucher
- [ ] Block "compliance": blocco generale per problemi compliance
- [ ] Typecheck e lint passano

---

#### US-029: Operational Blocks Tab (enforcement surface)
**Description:** Come Operator, voglio vedere e gestire i blocchi operativi di un Customer.

**Acceptance Criteria:**
- [ ] Tab Operational Blocks mostra: blocchi attivi (evidenziati), blocchi rimossi (historical)
- [ ] Per ogni blocco: type, reason, applied_by, applied_at
- [ ] Add block: select type, enter reason
- [ ] Remove block: enter removal reason, sets status=removed
- [ ] Solo ruoli autorizzati possono aggiungere/rimuovere blocchi
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-030: Block List globale
**Description:** Come Compliance Officer, voglio vedere tutti i blocchi attivi nel sistema.

**Acceptance Criteria:**
- [ ] OperationalBlockResource separato in Filament
- [ ] Lista con: customer/account name, block_type, reason, applied_by, applied_at
- [ ] Filtri: block_type, status (active/removed), date range
- [ ] Export CSV per reporting
- [ ] Link a Customer Detail
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 9: Users & Access

#### US-031: User-Account relationship model
**Description:** Come Admin, voglio tracciare quali Users possono operare su quali Account.

**Acceptance Criteria:**
- [ ] Tabella pivot `account_users` con: account_id, user_id, role (enum: owner, admin, operator, viewer), invited_at, accepted_at
- [ ] Un Account può avere multipli Users
- [ ] Un User può accedere a multipli Account
- [ ] Owner ha tutti i permessi, Viewer solo lettura
- [ ] Typecheck e lint passano

---

#### US-032: Users & Access Tab
**Description:** Come Account Owner, voglio gestire chi può accedere al mio Account.

**Acceptance Criteria:**
- [ ] Tab Users & Access in Account Detail o Customer Detail
- [ ] Lista users con: email, role, invited_at, status
- [ ] Invite user: email + role
- [ ] Change role: dropdown con ruoli disponibili
- [ ] Remove user: rimuove accesso
- [ ] Owner non può essere rimosso
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-033: Access rules e authorization
**Description:** Come Developer, voglio che le regole di accesso siano enforced a livello di policy.

**Acceptance Criteria:**
- [ ] Policy CustomerPolicy con metodi: view, update, delete, manageBlocks, managePayments
- [ ] Policy AccountPolicy con metodi: view, update, delete, manageUsers
- [ ] Filament Resources usano policies per visibilità azioni
- [ ] Test per ogni regola di autorizzazione
- [ ] Typecheck e lint passano

---

### Sezione 10: Audit & Governance

#### US-034: Audit log per tutte le entità Module K
**Description:** Come Compliance Officer, voglio un log immutabile di tutte le modifiche alle entità Module K.

**Acceptance Criteria:**
- [ ] Trait Auditable applicato a: Party, Customer, Account, Membership, PaymentPermission, OperationalBlock, Club, CustomerClub
- [ ] Log automatico su create, update, delete, status change
- [ ] Campi logged: timestamp, user_id, event_type, old_values, new_values
- [ ] Audit logs sono immutabili (no update/delete)
- [ ] Typecheck e lint passano

---

#### US-035: Audit Tab embedded in ogni detail view
**Description:** Come Operator, voglio vedere la storia delle modifiche in ogni detail view.

**Acceptance Criteria:**
- [ ] Tab Audit presente in: Party Detail, Customer Detail, Account Detail, Club Detail
- [ ] Timeline read-only con tutti gli eventi
- [ ] Filtri per tipo evento e date range
- [ ] Mostra: timestamp, user, tipo evento, changes summary
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

## Functional Requirements

- **FR-1:** Party è l'entità radice per tutte le controparti (clienti, fornitori, produttori, partner)
- **FR-2:** Una Party può avere multipli ruoli contemporaneamente
- **FR-3:** Customer è una specializzazione di Party con attributi specifici per la gestione clienti
- **FR-4:** Account rappresenta un contesto operativo di un Customer con scope specifico (B2C, B2B, Club)
- **FR-5:** Membership definisce il tier e stato di adesione di un Customer
- **FR-6:** Eligibility è computed in real-time basata su membership, blocks, payments, clubs
- **FR-7:** Operational Blocks sono il meccanismo di enforcement che può fermare qualsiasi operazione
- **FR-8:** Payment Permissions controllano metodi di pagamento e credit limits
- **FR-9:** Club affiliations influenzano eligibility per canale Club
- **FR-10:** Segments sono derivati automaticamente, non assegnati manualmente
- **FR-11:** Audit log immutabile per tutte le modifiche
- **FR-12:** Account restrictions possono solo aggiungere a Customer restrictions, mai override

---

## Key Invariants

1. **Party existence ≠ eligibility**: Esistere come Party non significa essere eleggibile per operazioni
2. **Membership approval is explicit**: Nessun Customer può operare senza Membership approved
3. **Operational blocks override all logic**: Un blocco attivo ferma l'operazione indipendentemente da altri fattori
4. **Payment platforms do not define rights**: Avere un metodo di pagamento non implica diritto di acquisto
5. **Club affiliation ≠ transactional rights**: Appartenere a un Club non garantisce diritti transazionali
6. **CRM status does not imply ERP access**: Status in CRM esterno non determina accesso ERP
7. **Accounts may restrict but never override**: Account può restringere ma mai allargare permessi Customer

---

## Non-Goals

- NON gestire pricing o commercial conditions (Module S)
- NON gestire vouchers o allocations (Module A)
- NON gestire inventory o fulfillment (Modules B, C)
- NON sostituire CRM/HubSpot per marketing e sales pipeline
- NON gestire lead qualification o relationship notes
- NON gestire autenticazione utenti (Infrastructure)
- Import automatico da CRM esterni (fuori scope MVP)
- Self-service customer portal (fuori scope MVP)

---

## Technical Considerations

### Database Schema Principale

```
parties
├── id, uuid
├── legal_name
├── party_type (enum: individual, legal_entity)
├── tax_id, vat_number
├── jurisdiction
├── status (enum: active, inactive)
├── timestamps, soft_deletes

party_roles
├── id
├── party_id (FK)
├── role (enum: customer, supplier, producer, partner)
├── timestamps

customers
├── id, uuid
├── party_id (FK)
├── customer_type (enum: b2c, b2b, partner)
├── status (enum: prospect, active, suspended, closed)
├── default_billing_address_id (FK nullable)
├── timestamps, soft_deletes

accounts
├── id, uuid
├── customer_id (FK)
├── name
├── channel_scope (enum: b2c, b2b, club)
├── status (enum: active, suspended)
├── timestamps

memberships
├── id
├── customer_id (FK)
├── tier (enum: legacy, member, invitation_only)
├── status (enum: applied, under_review, approved, rejected, suspended)
├── effective_from, effective_to
├── decision_notes
├── timestamps

clubs
├── id, uuid
├── partner_name
├── status (enum: active, suspended, ended)
├── branding_metadata (JSON)
├── timestamps, soft_deletes

customer_clubs (pivot)
├── id
├── customer_id (FK)
├── club_id (FK)
├── affiliation_status (enum: active, suspended)
├── start_date
├── end_date (nullable)
├── timestamps

operational_blocks
├── id
├── blockable_type, blockable_id (polymorphic: Customer/Account)
├── block_type (enum: payment, shipment, redemption, trading, compliance)
├── reason
├── applied_by (FK users)
├── status (enum: active, removed)
├── removed_at, removed_by
├── timestamps

addresses
├── id
├── addressable_type, addressable_id (polymorphic)
├── type (enum: billing, shipping)
├── line_1, line_2, city, state, postal_code, country
├── is_default
├── timestamps

payment_permissions
├── id
├── customer_id (FK unique)
├── card_allowed (bool, default true)
├── bank_transfer_allowed (bool, default false)
├── credit_limit (decimal nullable)
├── timestamps

account_users (pivot)
├── id
├── account_id (FK)
├── user_id (FK)
├── role (enum: owner, admin, operator, viewer)
├── invited_at
├── accepted_at (nullable)
├── timestamps
```

### Filament Resources

- `PartyResource` - CRUD Parties con tabs
- `CustomerResource` - CRUD Customers con 9 tabs (primary operational screen)
- `ClubResource` - CRUD Clubs
- `OperationalBlockResource` - Lista globale blocchi

### Enums

```php
enum PartyType: string {
    case Individual = 'individual';
    case LegalEntity = 'legal_entity';
}

enum PartyRole: string {
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Producer = 'producer';
    case Partner = 'partner';
}

enum CustomerType: string {
    case B2C = 'b2c';
    case B2B = 'b2b';
    case Partner = 'partner';
}

enum CustomerStatus: string {
    case Prospect = 'prospect';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}

enum AccountStatus: string {
    case Active = 'active';
    case Suspended = 'suspended';
}

enum ChannelScope: string {
    case B2C = 'b2c';
    case B2B = 'b2b';
    case Club = 'club';
}

enum MembershipTier: string {
    case Legacy = 'legacy';
    case Member = 'member';
    case InvitationOnly = 'invitation_only';
}

enum MembershipStatus: string {
    case Applied = 'applied';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}

enum BlockType: string {
    case Payment = 'payment';
    case Shipment = 'shipment';
    case Redemption = 'redemption';
    case Trading = 'trading';
    case Compliance = 'compliance';
}

enum BlockStatus: string {
    case Active = 'active';
    case Removed = 'removed';
}

enum AddressType: string {
    case Billing = 'billing';
    case Shipping = 'shipping';
}

enum AccountUserRole: string {
    case Owner = 'owner';
    case Admin = 'admin';
    case Operator = 'operator';
    case Viewer = 'viewer';
}

enum ClubStatus: string {
    case Active = 'active';
    case Suspended = 'suspended';
    case Ended = 'ended';
}

enum ClubAffiliationStatus: string {
    case Active = 'active';
    case Suspended = 'suspended';
}
```

### Service Classes

```php
// Eligibility computation
class EligibilityEngine {
    public function compute(Customer|Account $entity): EligibilityResult;
    public function explainChannel(Customer|Account $entity, Channel $channel): array;
}

// Segment derivation
class SegmentEngine {
    public function compute(Customer $customer): array;
}
```

### Struttura Directory

```
app/
├── Enums/
│   └── Customer/
│       ├── PartyType.php
│       ├── PartyRole.php
│       ├── CustomerType.php
│       ├── CustomerStatus.php
│       ├── MembershipTier.php
│       ├── MembershipStatus.php
│       ├── BlockType.php
│       └── ...
├── Models/
│   └── Customer/
│       ├── Party.php
│       ├── PartyRole.php
│       ├── Customer.php
│       ├── Account.php
│       ├── Membership.php
│       ├── Club.php
│       ├── CustomerClub.php
│       ├── OperationalBlock.php
│       ├── Address.php
│       ├── PaymentPermission.php
│       └── AccountUser.php
├── Filament/
│   └── Resources/
│       └── Customer/
│           ├── PartyResource.php
│           ├── CustomerResource.php
│           ├── ClubResource.php
│           └── OperationalBlockResource.php
├── Services/
│   └── Customer/
│       ├── EligibilityEngine.php
│       └── SegmentEngine.php
└── Policies/
    └── Customer/
        ├── CustomerPolicy.php
        └── AccountPolicy.php
```

---

## Success Metrics

- 100% dei Customers hanno Membership con stato tracciato
- 100% delle modifiche hanno audit log
- Eligibility computation < 100ms
- Zero transazioni processate con blocchi attivi
- Zero operazioni su canali non eleggibili

---

## Open Questions

1. Quali sono i criteri esatti per ogni tier di membership?
2. Ci sono limiti di credito standard per tipo cliente?
R. Potrebbero esserci
3. Quali integrazioni CRM sono necessarie per sync Customer data?
R. Hubspot, invieremo li tutti gli eventi principali
4. Chi può creare/rimuovere blocchi compliance?
R. Creeremo dei ruoli appositi dando gli accessi a chi potrà farlo
5. Servono notifiche quando un blocco viene applicato?
R. Si, potrebbero servire
6. I segmenti influenzano pricing (Module S) o solo marketing?
R. Influenzano pricing, marketing, accesso a prodotti, sconti, etc...
