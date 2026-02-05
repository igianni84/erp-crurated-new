# PRD: Admin Panel — Dashboards, Settings & System Governance

## Introduction

L'Admin Panel è il layer di governance dell'ERP Crurated, responsabile della visibilità sistemica, della configurazione centralizzata e dell'auditabilità. Non è un modulo operativo: non esegue transazioni, non gestisce ordini, non processa pagamenti. È il **control tower** che permette a leadership, ops managers e admin di rispondere alle domande: "Il sistema è sotto controllo?", "Perché si comporta così?" e "Cosa è cambiato?".

L'Admin Panel si articola su 4 layer architetturali:
- **Layer 1 — Shell**: Elementi globali (top bar, environment indicator, global search, alert center, user profile)
- **Layer 2 — Dashboards**: System Health, Risk & Exceptions, Module-level dashboards (oversight, non execution)
- **Layer 3 — Operational Modules**: PIM, Allocations, Inventory, Fulfillment, Procurement, Accounting, Commercial, Parties (coperti da PRD separati)
- **Layer 4 — Settings & Configuration**: Rules, Financial config, Integrations, Reference data, Roles & Permissions, Alert config

**Cosa è già coperto in altri PRD (NON duplicare):**
- Infrastructure PRD: Filament 5 installation (US-002), User model e autenticazione (US-004), Sistema ruoli base (US-005), Gestione utenti in Filament (US-006), Struttura navigazione Filament (US-012)

**Invarianti non negoziabili:**
1. Dashboard sono read-only — nessuna esecuzione dalle dashboard
2. Alert non auto-eseguono — informano, gli umani decidono
3. Rules sono dichiarative — configurazione, non codice
4. Settings sono auditati — ogni cambio loggato
5. Drill-down sempre disponibile — da metrica a lista filtrata
6. Explainability obbligatoria — "Perché vedo questo?" sempre risposta

---

## Goals

- Fornire visibilità immediata sulla salute del sistema e sui rischi operativi
- Centralizzare la configurazione di regole, policy e integrazioni
- Implementare un sistema di alert configurabile e auditabile
- Garantire tracciabilità completa di ogni azione e configurazione
- Supportare drill-down da KPI ad entità operativa specifica
- Mantenere separazione netta tra oversight (dashboard) e execution (moduli)
- Fornire explainability per ogni metrica, regola e alert

---

## User Stories

### Sezione 1: Shell — Elementi Globali

#### US-AP001: Environment Indicator
**Description:** Come Operator, voglio vedere chiaramente se sto lavorando in production o staging per evitare errori critici.

**Acceptance Criteria:**
- [ ] Badge visivo persistente nella top bar che mostra l'ambiente corrente
- [ ] Production: badge rosso con testo "PRODUCTION"
- [ ] Staging: badge arancione con testo "STAGING"
- [ ] Development: badge grigio con testo "DEV"
- [ ] Environment letto da variabile `APP_ENV`
- [ ] Badge non dismissable, sempre visibile
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP002: Global Search Setup
**Description:** Come Operator, voglio cercare oggetti (non pagine) da qualsiasi punto dell'applicazione.

**Acceptance Criteria:**
- [ ] Search input nella top bar di Filament
- [ ] Filament Global Search configurato
- [ ] Ricerca su: allocations (by ID, wine name), vouchers (by ID, customer), shipping orders (by ID), customers (by name, email), invoices (by number)
- [ ] Risultati raggruppati per tipo di oggetto
- [ ] Click su risultato naviga direttamente alla view/edit page dell'oggetto
- [ ] Keyboard shortcut: Cmd+K (Mac) / Ctrl+K (Windows)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP003: Alert Center nella Top Bar
**Description:** Come Operator, voglio accedere rapidamente agli alert attivi da qualsiasi punto dell'applicazione.

**Acceptance Criteria:**
- [ ] Icona campanella nella top bar con badge count degli alert attivi
- [ ] Click apre dropdown/panel con lista degli ultimi 10 alert attivi
- [ ] Ogni alert mostra: severity icon, title, timestamp, link all'oggetto correlato
- [ ] Badge rosso se ci sono alert critical, arancione se warning, nascosto se solo info o nessun alert
- [ ] Link "View all alerts" che naviga alla pagina Alert Center completa
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP004: User Profile Preferences
**Description:** Come User, voglio gestire le mie preferenze personali dal pannello.

**Acceptance Criteria:**
- [ ] Menu dropdown utente nella top bar con opzioni: Profile, Preferences, Logout
- [ ] Pagina Profile: visualizza nome, email, ruolo (read-only)
- [ ] Pagina Preferences: timezone preference, notification preferences (in-app on/off per severity)
- [ ] Tabella `user_preferences` con: user_id (FK), timezone, notification_settings (JSON)
- [ ] Preferenze salvate e rispettate in tutta l'applicazione
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP005: Breadcrumb Navigation Enhancement
**Description:** Come Operator, voglio breadcrumb navigation contestuale per capire dove sono.

**Acceptance Criteria:**
- [ ] Breadcrumb sotto la top bar in tutte le pagine
- [ ] Format: Home > [Module/Section] > [Resource] > [Action]
- [ ] Ogni elemento del breadcrumb è cliccabile e naviga alla sezione corrispondente
- [ ] Home naviga sempre alla System Health Dashboard
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 2: Alert Infrastructure

#### US-AP006: Alert Model Setup
**Description:** Come Developer, voglio un modello robusto per gli alert di sistema.

**Acceptance Criteria:**
- [ ] Tabella `alerts` con campi: id, uuid, alert_type (enum), severity (enum), title (string), message (text), alertable_type, alertable_id (polymorphic nullable), alert_rule_id (FK nullable), status (enum), acknowledged_by (FK users nullable), acknowledged_at (datetime nullable), resolved_by (FK users nullable), resolved_at (datetime nullable), metadata (JSON nullable), timestamps
- [ ] Model `Alert` in `app/Models/Admin/`
- [ ] Relazioni: Alert belongsTo AlertRule (nullable), Alert morphTo alertable, Alert belongsTo User (as acknowledgedBy), Alert belongsTo User (as resolvedBy)
- [ ] Scope `active()` per alert non risolti
- [ ] Scope `bySeverity($severity)` per filtraggio
- [ ] Typecheck e lint passano

---

#### US-AP007: Alert Enums
**Description:** Come Developer, voglio enums ben definiti per tipo, severity e status degli alert.

**Acceptance Criteria:**
- [ ] Enum `AlertType` in `app/Enums/Admin/`: allocation_exhaustion, blocked_shipment, unpaid_invoice, inventory_mismatch, integration_error, payment_failure, compliance_hold, deadline_approaching, system_error, custom
- [ ] Enum `AlertSeverity`: info, warning, critical
- [ ] Enum `AlertStatus`: active, acknowledged, resolved, expired
- [ ] Ogni enum ha labels human-readable e colors per UI
- [ ] Typecheck e lint passano

---

#### US-AP008: Alert Rule Model
**Description:** Come Developer, voglio un modello per definire regole che generano alert automaticamente.

**Acceptance Criteria:**
- [ ] Tabella `alert_rules` con: id, uuid, name, description (text nullable), rule_type (enum), conditions (JSON), severity (enum), is_active (boolean default true), notification_channels (JSON), escalation_after_minutes (int nullable), cooldown_minutes (int default 0), created_by (FK users), updated_by (FK users nullable), timestamps, soft_deletes
- [ ] Model `AlertRule` in `app/Models/Admin/`
- [ ] Enum `AlertRuleType`: threshold, deadline, status_change, integration_health, custom
- [ ] Relazione: AlertRule hasMany Alert
- [ ] Scope `active()` per regole attive
- [ ] Typecheck e lint passano

---

#### US-AP009: AlertService Base
**Description:** Come Developer, voglio un service per la gestione centralizzata degli alert.

**Acceptance Criteria:**
- [ ] Service `AlertService` in `app/Services/Admin/`
- [ ] Metodo `create(AlertType, severity, title, message, ?alertable, ?rule, ?metadata)`: crea nuovo alert
- [ ] Metodo `acknowledge(Alert, User)`: transizione status → acknowledged, registra who/when
- [ ] Metodo `resolve(Alert, User, ?resolution_note)`: transizione → resolved
- [ ] Metodo `getActiveAlerts(?severity, ?type, ?limit)`: lista alert attivi filtrati
- [ ] Metodo `getActiveCount(?severity)`: count alert attivi
- [ ] Validazione transizioni di stato (non può acknowledged → active)
- [ ] Typecheck e lint passano

---

#### US-AP010: Alert Creation with Cooldown
**Description:** Come Developer, voglio che gli alert rispettino il cooldown per evitare spam.

**Acceptance Criteria:**
- [ ] AlertService.create() verifica cooldown della regola prima di creare
- [ ] Se esiste alert attivo dallo stesso rule negli ultimi `cooldown_minutes`, non crea duplicato
- [ ] Cooldown si applica solo ad alert generati da regole, non manuali
- [ ] Log warning quando alert viene skippato per cooldown
- [ ] Typecheck e lint passano

---

#### US-AP011: Notification Channel Enum
**Description:** Come Developer, voglio enums per i canali di notifica degli alert.

**Acceptance Criteria:**
- [ ] Enum `NotificationChannel` in `app/Enums/Admin/`: in_app, email, webhook
- [ ] AlertRule.notification_channels contiene array di NotificationChannel values
- [ ] Per MVP solo `in_app` è implementato, altri sono placeholder
- [ ] Typecheck e lint passano

---

#### US-AP012: Alert Acknowledgement Flow
**Description:** Come Operator, voglio poter acknowledgare un alert per segnalare che l'ho visto.

**Acceptance Criteria:**
- [ ] Bottone "Acknowledge" su ogni alert attivo
- [ ] Click chiama AlertService.acknowledge()
- [ ] Alert passa a status "acknowledged" ma rimane visibile
- [ ] Mostra chi ha acknowledged e quando
- [ ] Solo alert acknowledged possono essere risolti
- [ ] Audit log entry per ogni acknowledgement
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 3: System Health Dashboard

#### US-AP013: System Health Dashboard Page
**Description:** Come Operator, voglio una dashboard di sistema come landing page dell'ERP.

**Acceptance Criteria:**
- [ ] Filament Page `SystemHealthDashboard` in `app/Filament/Pages/Admin/`
- [ ] Registrata come default dashboard in AdminPanelProvider
- [ ] Navigation group "Dashboards" con icona chart
- [ ] Layout: KPI tiles in top row, exception panels sotto
- [ ] Titolo: "System Health"
- [ ] Sottotitolo: "Real-time operational status"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP014: KPI Widget Infrastructure
**Description:** Come Developer, voglio un'infrastruttura per widget KPI riutilizzabili.

**Acceptance Criteria:**
- [ ] Abstract class `BaseKpiWidget` in `app/Filament/Widgets/Admin/`
- [ ] Proprietà: title, value, trend (up/down/stable), status (ok/warning/critical), drilldownUrl
- [ ] Metodo astratto `calculate()` che ritorna KPI data
- [ ] Rendering: card con titolo, valore grande, trend indicator, status color
- [ ] Click sulla card naviga a drilldownUrl
- [ ] Typecheck e lint passano

---

#### US-AP015: Active Alerts KPI Widget
**Description:** Come Operator, voglio vedere il count degli alert attivi nella dashboard.

**Acceptance Criteria:**
- [ ] Widget `ActiveAlertsWidget` extends BaseKpiWidget
- [ ] Mostra count totale alert attivi
- [ ] Breakdown per severity: X critical, Y warning, Z info
- [ ] Status: critical se almeno 1 critical alert, warning se almeno 1 warning, ok altrimenti
- [ ] Drill-down naviga a Alert Center con filtro status=active
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP016: Integration Health KPI Widget
**Description:** Come Operator, voglio vedere lo stato delle integrazioni nella dashboard.

**Acceptance Criteria:**
- [ ] Widget `IntegrationHealthWidget` extends BaseKpiWidget
- [ ] Mostra count integrazioni healthy vs unhealthy
- [ ] Status: critical se almeno 1 integration in error, warning se sync delayed > 1h, ok altrimenti
- [ ] Drill-down naviga a Integrations Health page
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP017: Recent Activity Panel
**Description:** Come Operator, voglio vedere le attività recenti nel sistema.

**Acceptance Criteria:**
- [ ] Widget `RecentActivityWidget` nella System Health Dashboard
- [ ] Mostra ultimi 10 audit log entries con: timestamp, user, action, resource
- [ ] Formato compatto: "[time ago] [user] [action] [resource type] [resource id]"
- [ ] Link "View full audit log" naviga a Audit Log Viewer
- [ ] Refresh automatico ogni 60 secondi
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP018: Exception Panel Container
**Description:** Come Developer, voglio un container per pannelli di eccezioni nella dashboard.

**Acceptance Criteria:**
- [ ] Widget `ExceptionPanelContainer` che raggruppa exception panels
- [ ] Layout: grid 2 colonne su desktop, 1 colonna su mobile
- [ ] Ogni panel ha: title, count badge, severity indicator, list preview (max 5 items)
- [ ] Click su panel header espande/collassa lista
- [ ] Click su "View all" naviga a lista filtrata nel modulo owner
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 4: Risk & Exceptions Dashboard

#### US-AP019: Risk Dashboard Page
**Description:** Come Manager, voglio una dashboard dedicata ai rischi e alle eccezioni future.

**Acceptance Criteria:**
- [ ] Filament Page `RiskDashboard` in `app/Filament/Pages/Admin/`
- [ ] Navigation group "Dashboards"
- [ ] Posizione nel menu dopo System Health
- [ ] Titolo: "Risk & Exceptions"
- [ ] Sottotitolo: "What needs attention this week"
- [ ] Layout: exception panels raggruppati per categoria
- [ ] Dashboard è forward-looking, non storica
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP020: Exception Panel Base Class
**Description:** Come Developer, voglio una classe base per i pannelli di eccezione.

**Acceptance Criteria:**
- [ ] Abstract class `BaseExceptionPanel` in `app/Filament/Widgets/Admin/`
- [ ] Proprietà: title, category (string), icon
- [ ] Metodo astratto `getExceptions()`: ritorna collection di eccezioni
- [ ] Ogni eccezione ha: title, description, severity, ownerModule, actionUrl
- [ ] Rendering: lista con severity indicator, click naviga a actionUrl
- [ ] Footer: "X items need attention" con link a vista completa
- [ ] Typecheck e lint passano

---

### Sezione 5: Alert Center Page

#### US-AP021: Alert Center Full Page
**Description:** Come Operator, voglio una pagina dedicata per gestire tutti gli alert.

**Acceptance Criteria:**
- [ ] Filament Page `AlertCenter` in `app/Filament/Pages/Admin/`
- [ ] Navigation group "Dashboards" o "System"
- [ ] Lista di tutti gli alert con paginazione
- [ ] Colonne: severity icon, type, title, related object link, status, created_at, acknowledged_by, actions
- [ ] Filtri: status (active, acknowledged, resolved, all), severity, type, date range
- [ ] Bulk actions: acknowledge selected (solo per active)
- [ ] Ordinamento default: created_at desc, critical first
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP022: Alert Detail View
**Description:** Come Operator, voglio vedere i dettagli completi di un alert.

**Acceptance Criteria:**
- [ ] View page per singolo Alert
- [ ] Mostra: title, message (full), severity badge, status badge, type badge
- [ ] Sezione "Related Object": link all'oggetto correlato (se presente)
- [ ] Sezione "Rule": link alla regola che ha generato l'alert (se presente)
- [ ] Sezione "Timeline": created → acknowledged → resolved con timestamps e users
- [ ] Azioni: Acknowledge (se active), Resolve (se acknowledged)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP023: Resolve Alert with Note
**Description:** Come Operator, voglio poter risolvere un alert con una nota di risoluzione.

**Acceptance Criteria:**
- [ ] Bottone "Resolve" disponibile solo su alert acknowledged
- [ ] Click apre modal con textarea "Resolution note" (opzionale)
- [ ] Conferma chiama AlertService.resolve()
- [ ] Alert passa a status "resolved"
- [ ] Resolution note salvata in metadata
- [ ] Audit log entry per risoluzione
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 6: Integration Infrastructure

#### US-AP024: Integration Model
**Description:** Come Developer, voglio un modello per tracciare lo stato delle integrazioni esterne.

**Acceptance Criteria:**
- [ ] Tabella `integrations` con: id, uuid, name, integration_type (enum), description (text nullable), status (enum), config (JSON encrypted), credentials (JSON encrypted nullable), last_sync_at (datetime nullable), last_sync_status (enum nullable), last_error (text nullable), health_score (int 0-100 default 100), is_active (boolean default true), timestamps
- [ ] Model `Integration` in `app/Models/Admin/`
- [ ] Enum `IntegrationType`: stripe, xero, wms, external_feed
- [ ] Enum `IntegrationStatus`: active, inactive, error, maintenance
- [ ] Enum `SyncStatus`: success, partial, failed
- [ ] Config e credentials criptati via Laravel encrypted casting
- [ ] Typecheck e lint passano

---

#### US-AP025: Integration Sync Log Model
**Description:** Come Developer, voglio tracciare la storia dei sync delle integrazioni.

**Acceptance Criteria:**
- [ ] Tabella `integration_sync_logs` con: id, integration_id (FK), sync_type (string), direction (enum: inbound, outbound, bidirectional), records_processed (int default 0), records_failed (int default 0), started_at (datetime), completed_at (datetime nullable), status (enum), error_details (JSON nullable), metadata (JSON nullable), timestamps
- [ ] Model `IntegrationSyncLog` in `app/Models/Admin/`
- [ ] Relazione: Integration hasMany IntegrationSyncLog
- [ ] Soft deletes non necessari (log immutabile)
- [ ] Index su integration_id e started_at
- [ ] Typecheck e lint passano

---

#### US-AP026: IntegrationHealthService
**Description:** Come Developer, voglio un service per monitorare la salute delle integrazioni.

**Acceptance Criteria:**
- [ ] Service `IntegrationHealthService` in `app/Services/Admin/`
- [ ] Metodo `getHealth(Integration)`: calcola health score basato su recent sync history
- [ ] Metodo `recordSyncStart(Integration, syncType, direction)`: crea sync log entry
- [ ] Metodo `recordSyncComplete(Integration, syncLog, processed, failed, ?errors)`: completa sync log
- [ ] Metodo `updateHealthScore(Integration)`: ricalcola health_score basato su ultimi 10 sync
- [ ] Health score: 100 = tutti success, -10 per ogni partial, -25 per ogni failed
- [ ] Typecheck e lint passano

---

#### US-AP027: Integrations List Page
**Description:** Come Admin, voglio vedere lo stato di tutte le integrazioni.

**Acceptance Criteria:**
- [ ] Filament Page `IntegrationsHealth` in `app/Filament/Pages/Admin/Settings/`
- [ ] Navigation group "Settings"
- [ ] Lista card-based delle integrazioni (non tabella)
- [ ] Ogni card mostra: name, type badge, status badge, health score bar, last sync time, last sync status
- [ ] Health score visualizzato come progress bar colorata (green > 80, yellow 50-80, red < 50)
- [ ] Click su card naviga a detail view
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP028: Integration Detail Page
**Description:** Come Admin, voglio vedere i dettagli e la storia sync di un'integrazione.

**Acceptance Criteria:**
- [ ] Detail page per singola Integration
- [ ] Sezione "Status": status badge, health score, last sync info
- [ ] Sezione "Configuration": mostra config keys (non values) per sicurezza
- [ ] Sezione "Sync History": tabella ultimi 20 sync logs con status, records, duration, errors
- [ ] Azione "Test Connection" (placeholder per ora)
- [ ] Azione "Force Sync" (placeholder per ora)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 7: Rules & Policies

#### US-AP029: Rule Model
**Description:** Come Developer, voglio un modello per le business rules configurabili.

**Acceptance Criteria:**
- [ ] Tabella `rules` con: id, uuid, rule_type (enum), name, description (text nullable), module (string), conditions (JSON), actions (JSON), is_active (boolean default true), priority (int default 0), effective_from (date nullable), effective_to (date nullable), created_by (FK users), updated_by (FK users nullable), timestamps, soft_deletes
- [ ] Model `Rule` in `app/Models/Admin/`
- [ ] Enum `RuleType` in `app/Enums/Admin/`: allocation_constraint, eligibility, pricing, blocking, alert_threshold
- [ ] Relazione: Rule belongsTo User (as createdBy, updatedBy)
- [ ] Scope `active()`: is_active AND (effective_from IS NULL OR effective_from <= today) AND (effective_to IS NULL OR effective_to >= today)
- [ ] Typecheck e lint passano

---

#### US-AP030: RuleService Base
**Description:** Come Developer, voglio un service per valutare le rules.

**Acceptance Criteria:**
- [ ] Service `RuleService` in `app/Services/Admin/`
- [ ] Metodo `getActiveRules(module, ?ruleType)`: ritorna rules attive filtrate
- [ ] Metodo `evaluate(Rule, context)`: valuta conditions contro context, ritorna bool
- [ ] Metodo `evaluateAll(module, ruleType, context)`: valuta tutte le rules attive, ritorna array di rules che matchano
- [ ] Conditions supportano operatori: equals, not_equals, greater_than, less_than, in, not_in, contains
- [ ] Log evaluation results per debugging
- [ ] Typecheck e lint passano

---

#### US-AP031: Rules List Page
**Description:** Come Admin, voglio gestire le business rules dal pannello.

**Acceptance Criteria:**
- [ ] Filament Page `RulesAndPolicies` in `app/Filament/Pages/Admin/Settings/`
- [ ] Navigation group "Settings"
- [ ] Lista rules raggruppate per module (accordion o tabs)
- [ ] Colonne: name, rule_type badge, is_active toggle, priority, effective dates, updated_at
- [ ] Filtri: module, rule_type, is_active
- [ ] CTA "Create Rule" naviga a form creazione
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP032: Create/Edit Rule Form
**Description:** Come Admin, voglio creare e modificare business rules.

**Acceptance Criteria:**
- [ ] Form con campi: name, rule_type (select), module (select), description
- [ ] Sezione "Conditions": JSON editor o form builder per definire conditions
- [ ] Sezione "Actions": JSON editor per definire actions (dipende da rule_type)
- [ ] Sezione "Validity": is_active toggle, priority, effective_from, effective_to
- [ ] Validazione: name required, rule_type required, module required
- [ ] Preview section che mostra rule in formato human-readable
- [ ] Audit: created_by/updated_by automatici
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 8: Reference Data

#### US-AP033: Reference Data Model
**Description:** Come Developer, voglio un modello flessibile per reference data.

**Acceptance Criteria:**
- [ ] Tabella `reference_data` con: id, data_type (enum), code (string), name (string), description (text nullable), metadata (JSON nullable), sort_order (int default 0), is_active (boolean default true), timestamps, soft_deletes
- [ ] Model `ReferenceData` in `app/Models/Admin/`
- [ ] Enum `ReferenceDataType`: location, warehouse, custody_type, case_format, bottle_format, legal_entity, country, currency
- [ ] Unique constraint su (data_type, code)
- [ ] Scope `ofType($type)` per filtraggio
- [ ] Scope `active()` per solo attivi
- [ ] Typecheck e lint passano

---

#### US-AP034: Reference Data Resource
**Description:** Come Admin, voglio gestire i reference data dal pannello.

**Acceptance Criteria:**
- [ ] ReferenceDataResource in Filament
- [ ] Navigation group "Settings" con label "Reference Data"
- [ ] Lista con filtro per data_type (tabs o dropdown)
- [ ] Colonne: code, name, is_active toggle, sort_order, updated_at
- [ ] Inline edit per name e sort_order
- [ ] Bulk toggle is_active
- [ ] Form: data_type (select, locked on edit), code (unique per type), name, description, metadata (JSON), sort_order, is_active
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP035: Reference Data Seeder
**Description:** Come Developer, voglio seed data per i reference data comuni.

**Acceptance Criteria:**
- [ ] Seeder `ReferenceDataSeeder` con dati iniziali
- [ ] Locations: almeno 5 country codes comuni (IT, FR, US, UK, CH)
- [ ] Custody types: customer, crurated, producer, third_party
- [ ] Bottle formats: 750ml, 1500ml (magnum), 375ml (half), 3000ml (jeroboam)
- [ ] Case formats: 1, 3, 6, 12
- [ ] Legal entities: placeholder per Crurated entities
- [ ] Seeder idempotente (usa updateOrCreate)
- [ ] Typecheck e lint passano

---

### Sezione 9: Audit Log Viewer

#### US-AP036: Audit Log Viewer Page
**Description:** Come Admin, voglio visualizzare l'audit log completo del sistema.

**Acceptance Criteria:**
- [ ] Filament Page `AuditLogViewer` in `app/Filament/Pages/Admin/`
- [ ] Navigation group "System" o "Dashboards"
- [ ] Utilizza il trait Auditable già definito in Infrastructure PRD
- [ ] Lista entries con: timestamp, user, action (created/updated/deleted), resource type, resource id, changes summary
- [ ] Paginazione: 50 entries per page
- [ ] Ordinamento default: timestamp desc
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP037: Audit Log Filters
**Description:** Come Admin, voglio filtrare l'audit log per trovare eventi specifici.

**Acceptance Criteria:**
- [ ] Filtro per user (select con search)
- [ ] Filtro per resource type (select)
- [ ] Filtro per action (created, updated, deleted)
- [ ] Filtro per date range (from/to)
- [ ] Filtro per resource ID (text input)
- [ ] Filtri combinabili (AND logic)
- [ ] Reset filters button
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP038: Audit Log Entry Detail
**Description:** Come Admin, voglio vedere i dettagli completi di un audit entry.

**Acceptance Criteria:**
- [ ] Click su entry apre modal o slide-over con dettagli
- [ ] Mostra: timestamp, user (con link a profile), action, resource type, resource id (con link se esiste)
- [ ] Sezione "Changes": diff view old values vs new values
- [ ] Per "created": mostra tutti i new values
- [ ] Per "deleted": mostra tutti i old values
- [ ] Per "updated": mostra solo campi modificati con before/after
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP039: Audit Log Export
**Description:** Come Admin, voglio esportare l'audit log per compliance.

**Acceptance Criteria:**
- [ ] Bottone "Export" nella toolbar dell'Audit Log Viewer
- [ ] Export rispetta i filtri attivi
- [ ] Formato: CSV con colonne timestamp, user_email, action, resource_type, resource_id, changes_json
- [ ] Limit: max 10000 entries per export
- [ ] Warning se si tenta export > 10000 entries
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 10: Alert Rule Configuration

#### US-AP040: Alert Rules List Page
**Description:** Come Admin, voglio gestire le regole che generano alert automatici.

**Acceptance Criteria:**
- [ ] Filament Page `AlertConfiguration` in `app/Filament/Pages/Admin/Settings/`
- [ ] Navigation group "Settings"
- [ ] Lista alert rules con: name, rule_type, severity badge, is_active toggle, notification_channels icons, last_triggered, actions
- [ ] Filtri: rule_type, severity, is_active
- [ ] CTA "Create Alert Rule"
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP041: Create/Edit Alert Rule Form
**Description:** Come Admin, voglio creare e modificare regole di alerting.

**Acceptance Criteria:**
- [ ] Form con: name, description, rule_type (select), severity (select)
- [ ] Sezione "Conditions": form builder context-aware basato su rule_type
- [ ] Per threshold: metric (select), operator (>, <, =, >=, <=), value (number)
- [ ] Per deadline: entity_type, days_before (number)
- [ ] Sezione "Notifications": notification_channels (multi-select), escalation_after_minutes (nullable), cooldown_minutes
- [ ] Sezione "Status": is_active toggle
- [ ] Validazione: name unique, conditions required
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP042: Alert Rule Test
**Description:** Come Admin, voglio testare una regola di alert prima di attivarla.

**Acceptance Criteria:**
- [ ] Bottone "Test Rule" nel form di edit della rule
- [ ] Click esegue la valutazione della rule senza creare alert reali
- [ ] Mostra risultato: "Rule would trigger X alerts" o "Rule would not trigger"
- [ ] Se triggererebbe, mostra preview dei primi 5 oggetti che matchano
- [ ] Test non modifica dati, è read-only
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 11: Roles & Permissions Advanced

#### US-AP043: Permission Model
**Description:** Come Developer, voglio un modello granulare per i permessi.

**Acceptance Criteria:**
- [ ] Tabella `permissions` con: id, name (unique), module (string nullable), resource (string nullable), action (string), description (text nullable), timestamps
- [ ] Model `Permission` in `app/Models/Admin/`
- [ ] Tabella pivot `role_permissions` con: role (enum UserRole from Infrastructure), permission_id
- [ ] Naming convention: "{module}.{resource}.{action}" es: "allocations.allocation.create"
- [ ] Actions standard: view, create, update, delete, export
- [ ] Typecheck e lint passano

---

#### US-AP044: PermissionSeeder
**Description:** Come Developer, voglio seed delle permissions base per tutti i moduli.

**Acceptance Criteria:**
- [ ] Seeder `PermissionSeeder` che crea permissions per ogni modulo/resource
- [ ] Permissions per Admin Panel: alerts.*, audit.view, settings.*, integrations.*, rules.*, reference_data.*
- [ ] Super_admin ha tutti i permessi
- [ ] Admin ha tutti tranne system settings critici
- [ ] Manager ha view + alcuni edit
- [ ] Editor ha view + edit su risorse assegnate
- [ ] Viewer ha solo view
- [ ] Seeder idempotente
- [ ] Typecheck e lint passano

---

#### US-AP045: Roles & Permissions Page
**Description:** Come Super Admin, voglio gestire i permessi per ruolo.

**Acceptance Criteria:**
- [ ] Filament Page `RolesAndPermissions` in `app/Filament/Pages/Admin/Settings/`
- [ ] Navigation group "Settings"
- [ ] Vista: matrice ruoli (colonne) x permissions raggruppate per modulo (righe)
- [ ] Checkbox per ogni combinazione role-permission
- [ ] Changes auto-saved o con bottone "Save Changes"
- [ ] Warning banner: "Changes affect all users with this role"
- [ ] Solo super_admin può accedere a questa pagina
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 12: Dashboard Placeholder Widgets (Per Moduli Futuri)

#### US-AP046: Placeholder KPI Widgets
**Description:** Come Developer, voglio widget placeholder per KPI che saranno implementati quando i rispettivi moduli esistono.

**Acceptance Criteria:**
- [ ] Widget `AllocationHealthWidget` (placeholder): mostra "Module A not implemented" se non esiste AllocationService
- [ ] Widget `InventoryHealthWidget` (placeholder): mostra "Module B not implemented" se non esiste
- [ ] Widget `FulfillmentHealthWidget` (placeholder): mostra "Module C not implemented" se non esiste
- [ ] Widget `FinanceHealthWidget` (placeholder): mostra "Module E not implemented" se non esiste
- [ ] Ogni widget controlla esistenza del service/model corrispondente
- [ ] Quando modulo disponibile, mostra dati reali
- [ ] Typecheck e lint passano

---

#### US-AP047: Module Dashboard Links
**Description:** Come Operator, voglio link ai dashboard specifici di ogni modulo dalla System Health.

**Acceptance Criteria:**
- [ ] Sezione "Module Dashboards" nella System Health page
- [ ] Card per ogni modulo con: nome, icona, status summary (se disponibile), link "Go to module dashboard"
- [ ] Moduli non implementati mostrano "Coming soon" greyed out
- [ ] Moduli implementati mostrano mini-status (es: "3 warnings")
- [ ] Click naviga alla dashboard del modulo specifico (es: /admin/allocations/dashboard)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

### Sezione 13: Explainability

#### US-AP048: Widget Tooltip Explainability
**Description:** Come Operator, voglio capire come viene calcolato ogni KPI.

**Acceptance Criteria:**
- [ ] Ogni KPI widget ha icona "?" accanto al titolo
- [ ] Hover/click mostra tooltip con: "How is this calculated?", formula/logica in linguaggio naturale, link "View rule" se basato su regola
- [ ] Esempio: "Active Alerts = Count of alerts where status = 'active' or 'acknowledged'"
- [ ] Tooltip usa componente Filament standard (tooltip o popover)
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP049: Alert Explainability
**Description:** Come Operator, voglio capire perché un alert è stato generato.

**Acceptance Criteria:**
- [ ] Nella detail view dell'alert, sezione "Why this alert?"
- [ ] Se generato da rule: mostra rule name con link, conditions che hanno matchato, timestamp evaluation
- [ ] Se manuale: mostra "Created manually by [user] on [date]"
- [ ] Se legato a oggetto: link diretto all'oggetto con contesto
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

#### US-AP050: Settings Change Impact Preview
**Description:** Come Admin, voglio vedere l'impatto di un cambio settings prima di salvare.

**Acceptance Criteria:**
- [ ] Per rules e alert rules, prima di salvare mostra preview impatto
- [ ] "This change will affect X existing [entities]"
- [ ] Per disattivazione: "X active alerts from this rule will be closed"
- [ ] Per cambio conditions: "Rule would now match X objects (previously Y)"
- [ ] Preview è informativo, non blocca il salvataggio
- [ ] Typecheck e lint passano
- [ ] Verify in browser using dev-browser skill

---

## Functional Requirements

- **FR-1:** Dashboard landing page è System Health, non una welcome page
- **FR-2:** Tutte le dashboard sono read-only, nessuna azione di modifica
- **FR-3:** Alert system supporta polymorphic relations con qualsiasi entità
- **FR-4:** Alert acknowledgement e resolution tracciate con user e timestamp
- **FR-5:** Rules engine supporta conditions JSON con operatori standard
- **FR-6:** Integration health monitored via sync logs e health score 0-100
- **FR-7:** Reference data centralizzati per evitare duplicazione
- **FR-8:** Audit log immutabile, searchable, exportable
- **FR-9:** Permessi granulari a livello di module.resource.action
- **FR-10:** Ogni widget e alert ha explainability built-in

---

## Non-Goals

- **Real-time websocket updates** (polling ogni 60s è sufficiente per MVP)
- **Email/webhook notifications** (solo in-app per MVP)
- **Custom dashboard builder** (layout fisso per MVP)
- **Alert auto-resolution** (sempre richiede azione umana)
- **Multi-language UI** (solo inglese per MVP)
- **Mobile-optimized dashboard** (desktop-first, responsive ma non ottimizzato mobile)
- **Historical trend charts** (solo KPI current state per MVP)
- **Scheduled reports** (export manuale per MVP)

---

## Technical Considerations

### Database Schema Summary

```sql
-- alerts
CREATE TABLE alerts (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid CHAR(36) UNIQUE NOT NULL,
    alert_type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    alertable_type VARCHAR(255) NULL,
    alertable_id BIGINT UNSIGNED NULL,
    alert_rule_id BIGINT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    acknowledged_by BIGINT UNSIGNED NULL,
    acknowledged_at TIMESTAMP NULL,
    resolved_by BIGINT UNSIGNED NULL,
    resolved_at TIMESTAMP NULL,
    metadata JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (status),
    INDEX (severity),
    INDEX (alertable_type, alertable_id),
    FOREIGN KEY (alert_rule_id) REFERENCES alert_rules(id),
    FOREIGN KEY (acknowledged_by) REFERENCES users(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id)
);

-- alert_rules
CREATE TABLE alert_rules (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid CHAR(36) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    rule_type VARCHAR(50) NOT NULL,
    conditions JSON NOT NULL,
    severity VARCHAR(20) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    notification_channels JSON NOT NULL,
    escalation_after_minutes INT UNSIGNED NULL,
    cooldown_minutes INT UNSIGNED DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- integrations
CREATE TABLE integrations (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid CHAR(36) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    integration_type VARCHAR(50) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'inactive',
    config JSON NOT NULL,
    credentials JSON NULL,
    last_sync_at TIMESTAMP NULL,
    last_sync_status VARCHAR(20) NULL,
    last_error TEXT NULL,
    health_score TINYINT UNSIGNED DEFAULT 100,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- integration_sync_logs
CREATE TABLE integration_sync_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    integration_id BIGINT UNSIGNED NOT NULL,
    sync_type VARCHAR(100) NOT NULL,
    direction VARCHAR(20) NOT NULL,
    records_processed INT UNSIGNED DEFAULT 0,
    records_failed INT UNSIGNED DEFAULT 0,
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    status VARCHAR(20) NOT NULL,
    error_details JSON NULL,
    metadata JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (integration_id, started_at),
    FOREIGN KEY (integration_id) REFERENCES integrations(id)
);

-- rules
CREATE TABLE rules (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid CHAR(36) UNIQUE NOT NULL,
    rule_type VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    module VARCHAR(50) NOT NULL,
    conditions JSON NOT NULL,
    actions JSON NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 0,
    effective_from DATE NULL,
    effective_to DATE NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX (module, rule_type, is_active),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- reference_data
CREATE TABLE reference_data (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    data_type VARCHAR(50) NOT NULL,
    code VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    metadata JSON NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE (data_type, code)
);

-- permissions
CREATE TABLE permissions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL,
    module VARCHAR(50) NULL,
    resource VARCHAR(50) NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- role_permissions (pivot)
CREATE TABLE role_permissions (
    role VARCHAR(50) NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role, permission_id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- user_preferences
CREATE TABLE user_preferences (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED UNIQUE NOT NULL,
    timezone VARCHAR(100) DEFAULT 'UTC',
    notification_settings JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Directory Structure

```
app/
├── Enums/
│   └── Admin/
│       ├── AlertType.php
│       ├── AlertSeverity.php
│       ├── AlertStatus.php
│       ├── AlertRuleType.php
│       ├── IntegrationType.php
│       ├── IntegrationStatus.php
│       ├── SyncStatus.php
│       ├── RuleType.php
│       ├── ReferenceDataType.php
│       └── NotificationChannel.php
├── Filament/
│   ├── Pages/
│   │   └── Admin/
│   │       ├── SystemHealthDashboard.php
│   │       ├── RiskDashboard.php
│   │       ├── AlertCenter.php
│   │       ├── AuditLogViewer.php
│   │       └── Settings/
│   │           ├── RulesAndPolicies.php
│   │           ├── IntegrationsHealth.php
│   │           ├── ReferenceData.php (or Resource)
│   │           ├── RolesAndPermissions.php
│   │           └── AlertConfiguration.php
│   ├── Resources/
│   │   └── Admin/
│   │       ├── AlertResource.php
│   │       ├── AlertRuleResource.php
│   │       └── ReferenceDataResource.php
│   └── Widgets/
│       └── Admin/
│           ├── BaseKpiWidget.php
│           ├── ActiveAlertsWidget.php
│           ├── IntegrationHealthWidget.php
│           ├── RecentActivityWidget.php
│           ├── ExceptionPanelContainer.php
│           ├── BaseExceptionPanel.php
│           └── Placeholders/
│               ├── AllocationHealthWidget.php
│               ├── InventoryHealthWidget.php
│               ├── FulfillmentHealthWidget.php
│               └── FinanceHealthWidget.php
├── Models/
│   └── Admin/
│       ├── Alert.php
│       ├── AlertRule.php
│       ├── Integration.php
│       ├── IntegrationSyncLog.php
│       ├── Rule.php
│       ├── ReferenceData.php
│       ├── Permission.php
│       └── UserPreference.php
├── Policies/
│   └── Admin/
│       ├── AlertPolicy.php
│       ├── AlertRulePolicy.php
│       ├── RulePolicy.php
│       └── ReferenceDataPolicy.php
└── Services/
    └── Admin/
        ├── AlertService.php
        ├── IntegrationHealthService.php
        ├── RuleService.php
        └── AuditLogService.php

database/
├── migrations/
│   ├── xxxx_create_alerts_table.php
│   ├── xxxx_create_alert_rules_table.php
│   ├── xxxx_create_integrations_table.php
│   ├── xxxx_create_integration_sync_logs_table.php
│   ├── xxxx_create_rules_table.php
│   ├── xxxx_create_reference_data_table.php
│   ├── xxxx_create_permissions_table.php
│   ├── xxxx_create_role_permissions_table.php
│   └── xxxx_create_user_preferences_table.php
└── seeders/
    ├── ReferenceDataSeeder.php
    └── PermissionSeeder.php
```

### Filament Panel Configuration

```php
// AdminPanelProvider.php additions
->navigationGroups([
    NavigationGroup::make()
        ->label('Dashboards')
        ->icon('heroicon-o-chart-bar'),
    NavigationGroup::make()
        ->label('PIM')
        ->icon('heroicon-o-cube'),
    // ... other modules ...
    NavigationGroup::make()
        ->label('Settings')
        ->icon('heroicon-o-cog-6-tooth')
        ->collapsed(),
    NavigationGroup::make()
        ->label('System')
        ->icon('heroicon-o-server'),
])
->globalSearch(true)
->globalSearchKeyBindings(['command+k', 'ctrl+k'])
```

---

## Success Metrics

- System Health Dashboard carica in < 2 secondi
- Alert acknowledgement tracciato al 100% con user e timestamp
- Tutte le settings changes auditati
- Zero alert persi (tutti hanno stato tracciabile)
- 100% code quality (PHPStan level 5, Pint)
- Explainability disponibile per ogni KPI e alert

---

## Open Questions

1. **Alert retention**: Per quanto tempo mantenere alert risolti? (proposta: 90 giorni, poi archiviazione)
R. Ok
2. **Integration credentials storage**: Usare Laravel encrypted o vault esterno?
R. Laravel encrypted
3. **Permission granularity**: Field-level permissions necessarie o resource-level sufficiente?
R. Field-level permissions
4. **Dashboard refresh rate**: 60 secondi sufficiente o serve real-time per critical alerts?
R. 5 minuti
5. **Audit log storage**: Stessa tabella o sistema separato (es: Elasticsearch) per volumi alti?
R. Stessa tabella
6. **Rule conditions UI**: JSON editor sufficiente o serve visual builder?
R. Visual Builder
