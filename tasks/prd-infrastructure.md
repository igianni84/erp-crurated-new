# PRD: Infrastruttura ERP Crurated

## Introduction

Setup dell'infrastruttura base per l'ERP Crurated utilizzando Laravel 12, Filament 5 e MySQL. Questo PRD copre la configurazione iniziale del progetto, autenticazione, autorizzazione e struttura base che tutti i moduli utilizzeranno.

---

## Goals

- Configurare progetto Laravel 12 con best practices
- Installare e configurare Filament 5 come admin panel
- Setup database MySQL con convenzioni naming consistenti
- Implementare sistema di autenticazione e autorizzazione
- Definire struttura modulare per organizzazione codice
- Configurare ambiente di sviluppo (migrations, seeders, factories)
- Setup testing infrastructure

---

## User Stories

### Setup Progetto

#### US-001: Inizializzazione progetto Laravel 12
**Description:** Come Developer, voglio un progetto Laravel 12 configurato correttamente per iniziare lo sviluppo.

**Acceptance Criteria:**
- [ ] Progetto Laravel 12 creato con `laravel new`
- [ ] Configurazione `.env` per MySQL
- [ ] Git repository inizializzato con `.gitignore` appropriato
- [ ] Composer dependencies installate
- [ ] `php artisan serve` funziona correttamente
- [ ] Typecheck e lint passano

---

#### US-002: Installazione Filament 5
**Description:** Come Developer, voglio Filament 5 installato e configurato come admin panel.

**Acceptance Criteria:**
- [ ] Filament 5 installato via composer
- [ ] Panel provider configurato in `app/Providers/Filament`
- [ ] Asset pubblicati e compilati
- [ ] Accesso a `/admin` mostra login page
- [ ] Tema base configurato (colori Crurated se forniti)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-003: Configurazione Database MySQL
**Description:** Come Developer, voglio il database MySQL configurato e connesso.

**Acceptance Criteria:**
- [ ] Database `crurated_erp` creato
- [ ] Configurazione in `.env` corretta
- [ ] `php artisan migrate` esegue senza errori
- [ ] Convenzioni naming: snake_case per tabelle e colonne
- [ ] Typecheck e lint passano

---

### Autenticazione & Autorizzazione

#### US-004: Setup User model e autenticazione
**Description:** Come Admin, voglio un sistema di login sicuro per accedere all'ERP.

**Acceptance Criteria:**
- [ ] Model `User` con campi: name, email, password, role
- [ ] Migration per tabella users
- [ ] Filament auth configurato con User model
- [ ] Login funzionante su `/admin/login`
- [ ] Logout funzionante
- [ ] Remember me functionality
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-005: Implementare sistema ruoli base
**Description:** Come Super Admin, voglio assegnare ruoli agli utenti per controllare gli accessi.

**Acceptance Criteria:**
- [ ] Enum `UserRole`: super_admin, admin, manager, editor, viewer
- [ ] Campo `role` su User model
- [ ] Policy base per controllo accessi
- [ ] Super Admin può fare tutto
- [ ] Viewer può solo leggere
- [ ] Seeder per utente super_admin iniziale
- [ ] Typecheck e lint passano

---

#### US-006: Gestione utenti in Filament
**Description:** Come Super Admin, voglio gestire gli utenti dell'ERP dal pannello admin.

**Acceptance Criteria:**
- [ ] UserResource in Filament
- [ ] Lista utenti con filtri per ruolo
- [ ] Creazione utente con assegnazione ruolo
- [ ] Modifica utente (no self-delete)
- [ ] Reset password
- [ ] Solo Super Admin può gestire utenti
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Struttura Modulare

#### US-007: Definire struttura directory modulare
**Description:** Come Developer, voglio una struttura directory organizzata per moduli.

**Acceptance Criteria:**
- [ ] Directory `app/Models/Pim/` per modelli Module 0
- [ ] Directory `app/Filament/Resources/Pim/` per risorse Filament
- [ ] Directory `app/Enums/` per enumerazioni
- [ ] Directory `app/Services/` per business logic
- [ ] Directory `app/Traits/` per traits riutilizzabili
- [ ] Namespace PSR-4 configurati in composer.json
- [ ] Typecheck e lint passano

---

#### US-008: Setup base traits riutilizzabili
**Description:** Come Developer, voglio traits comuni per funzionalità condivise tra modelli.

**Acceptance Criteria:**
- [ ] Trait `HasUuid` per UUID come primary key (opzionale)
- [ ] Trait `Auditable` per audit logging
- [ ] Trait `HasLifecycleStatus` per gestione stati
- [ ] Documentazione uso traits in README
- [ ] Typecheck e lint passano

---

### Testing & Quality

#### US-009: Setup testing infrastructure
**Description:** Come Developer, voglio poter scrivere e eseguire test automatizzati.

**Acceptance Criteria:**
- [ ] PHPUnit configurato
- [ ] Database SQLite in-memory per test
- [ ] Base TestCase configurato
- [ ] Factory base per User
- [ ] `php artisan test` esegue senza errori
- [ ] Typecheck e lint passano

---

#### US-010: Configurare code quality tools
**Description:** Come Developer, voglio strumenti di code quality per mantenere standard elevati.

**Acceptance Criteria:**
- [ ] Laravel Pint installato e configurato
- [ ] PHPStan installato (level 5 minimo)
- [ ] Script composer: `lint`, `analyse`, `test`
- [ ] Pre-commit hook opzionale per lint
- [ ] CI/CD ready configuration
- [ ] Typecheck e lint passano

---

### Configurazione Ambiente

#### US-011: Setup environment configurations
**Description:** Come Developer, voglio configurazioni separate per dev/staging/production.

**Acceptance Criteria:**
- [ ] `.env.example` con tutte le variabili necessarie
- [ ] Configurazione logging appropriata
- [ ] Configurazione cache (file per dev, redis ready per prod)
- [ ] Configurazione queue (sync per dev, database/redis ready per prod)
- [ ] Documentazione setup in README
- [ ] Typecheck e lint passano

---

#### US-012: Setup Filament navigation structure
**Description:** Come Operator, voglio una navigazione chiara nell'admin panel.

**Acceptance Criteria:**
- [ ] Navigation group "PIM" per Module 0
- [ ] Navigation group "System" per settings e users
- [ ] Icone appropriate per ogni sezione
- [ ] Ordinamento logico delle voci
- [ ] Responsive sidebar
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

## Functional Requirements

- **FR-1:** Progetto Laravel 12 con PHP 8.4+
- **FR-2:** Filament 5 come unico admin panel
- **FR-3:** MySQL 8.4+ come database
- **FR-4:** Autenticazione basata su session con remember me
- **FR-5:** Sistema ruoli: super_admin, admin, manager, editor, viewer
- **FR-6:** Struttura modulare con namespace separati per modulo
- **FR-7:** Audit logging su tutte le entità business
- **FR-8:** Code quality enforced via Pint + PHPStan

---

## Non-Goals

- Multi-tenancy (singola istanza per Crurated)
- API pubblica (solo admin panel per MVP)
- OAuth/SSO (email+password per MVP)
- Internazionalizzazione UI (inglese per MVP)
- Real-time notifications (fuori scope MVP)

---

## Technical Considerations

### Requisiti Sistema

- PHP 8.4+
- MySQL 8.4+
- Composer 2.8+
- Node.js 24+ (per asset compilation)

### Dipendenze Principali

```json
{
    "require": {
        "php": "^8.4",
        "laravel/framework": "^12.0",
        "filament/filament": "^5.0"
    },
    "require-dev": {
        "laravel/pint": "^1.0",
        "phpstan/phpstan": "^1.0",
        "pestphp/pest": "^2.0"
    }
}
```

### Struttura Directory Target

```
app/
├── Enums/
│   └── UserRole.php
├── Filament/
│   ├── Resources/
│   │   ├── UserResource.php
│   │   └── Pim/
│   │       └── (Module 0 resources)
│   └── Pages/
├── Models/
│   ├── User.php
│   └── Pim/
│       └── (Module 0 models)
├── Policies/
│   └── UserPolicy.php
├── Services/
├── Traits/
│   ├── Auditable.php
│   └── HasLifecycleStatus.php
└── Providers/
    └── Filament/
        └── AdminPanelProvider.php

database/
├── factories/
├── migrations/
└── seeders/
    └── AdminUserSeeder.php

tests/
├── Feature/
└── Unit/
```

### Configurazione Filament Panel

```php
// app/Providers/Filament/AdminPanelProvider.php
->id('admin')
->path('admin')
->login()
->colors([
    'primary' => Color::Indigo,
])
->navigationGroups([
    'PIM',
    'System',
])
```

---

## Success Metrics

- Setup completo in meno di 2 ore
- Zero errori PHPStan level 5
- 100% test passing
- Login/logout funzionante
- Struttura pronta per Module 0

---

## Open Questions

1. Colori brand Crurated per tema Filament?
2. Logo da usare nell'admin panel?
3. Dominio/URL per ambiente di sviluppo?
4. Credenziali iniziali per super admin?
5. Requisiti password (lunghezza minima, complessità)?
