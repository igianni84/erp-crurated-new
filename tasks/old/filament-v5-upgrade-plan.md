# Filament v4 → v5 Upgrade Plan

**Data:** 2026-02-17
**Stato:** Piano — in attesa di approvazione

---

## Executive Summary

Filament v5 (rilasciato il 16 gennaio 2026) è un major bump motivato dal supporto a **Livewire v4**. Le breaking changes vengono quasi interamente da Livewire v4, non da Filament stesso. Il progetto ERP4 è in ottima posizione per l'upgrade grazie a zero plugin esterni, zero componenti Livewire custom e Tailwind v4 già installato.

---

## Stato Attuale del Progetto

| Componente | Versione Attuale | Richiesto da Filament v5 | Stato |
|---|---|---|---|
| PHP | 8.5.2 | ≥ 8.2 | ✅ OK |
| Laravel | 12.51.0 | ≥ 11.28 | ✅ OK |
| Filament | 4.7.1 | ^5.0 | 🔄 Da aggiornare |
| Livewire | 3.7.10 | ≥ 4.0 | 🔄 Da aggiornare (automatico con Filament v5) |
| Tailwind CSS | ^4.0.0 | ≥ 4.0 | ✅ OK |
| @tailwindcss/vite | ^4.0.0 | v4+ | ✅ OK |

### Punti di Forza del Progetto
- **Zero plugin Filament di terze parti** — solo 10 pacchetti core del monorepo
- **Zero componenti Livewire custom** — nessuna classe estende `Livewire\Component`, nessuna directory `app/Livewire/`
- **Tailwind v4 già installato** — nessuna migrazione CSS necessaria
- **Nessuna view Filament vendor-published** — directory `resources/views/vendor/` non esiste
- **Nessun `config/livewire.php` pubblicato** — nessun config da migrare
- **Theme via Vite** — `@tailwindcss/vite` plugin, approccio moderno già compatibile
- **`laravel/ai` v0.1.5 NON dipende da Livewire** — nessun rischio di blocco Composer

### Statistiche Filament nel Progetto
| Elemento | Quantità |
|---|---|
| Resources | 45 |
| Custom Pages | 32 |
| Resource Page Files (Create/Edit/View/List + custom) | 136 |
| Widgets | 3 |
| Relation Managers | 3 |
| Exporters | 1 |
| Custom Blade Views | 46 |
| Filament Test Files | 4 |
| **Totale file Filament PHP** | **220** |

---

## Analisi dei Rischi

### RISCHIO BASSO ✅
| Area | Dettaglio |
|---|---|
| Filament core | Nessuna breaking change propria — solo wrapper per Livewire v4 |
| Tailwind CSS | Già su v4, nessuna azione |
| PHP/Laravel | Versioni ben superiori ai requisiti minimi |
| Plugin esterni | Zero — nessun rischio di incompatibilità |
| `laravel/ai` v0.1.5 | Nessuna dipendenza su Livewire — non blocca l'upgrade |
| wire:click / wire:submit | 58 usi (51 + 7) — sintassi identica tra Livewire v3 e v4 |
| wire:transition | Zero usi nel codebase — nessun impatto |
| `<livewire:>` tags | Zero usi — nessun problema self-closing tags |
| wire:model (senza .live) | Zero usi — tutto usa `wire:model.live` |

### RISCHIO MEDIO ⚠️
| Area | Dettaglio |
|---|---|
| Livewire v4 — wire:model | 34 direttive `wire:model.live` in 12 file Blade. `.live` è pienamente supportato in Livewire v4. Tutti i nostri usi sono su elementi HTML nativi (`<input>`, `<select>`), quindi il cambio di bubbling non impatta. |
| Livewire v4 — endpoint URL | Path cambia da `/livewire/update` a `/livewire-{hash}/update`. Se ci sono regole firewall/CDN/reverse proxy per `/livewire/`, vanno aggiornate. |
| Alpine.js + $wire | 1 uso in `finance-overview.blade.php` — sintassi compatibile ma da testare manualmente |

---

## Piano Step-by-Step

### Pre-requisiti

- [ ] **P0: Branch main pulito e pushato**
  ```bash
  git status && git push origin main
  ```

- [ ] **P1: Creare branch dedicato**
  ```bash
  git checkout -b upgrade/filament-v5
  ```

- [ ] **P2: Baseline test suite**
  ```bash
  composer test 2>&1 | tee /tmp/pre-upgrade-tests.log
  composer analyse 2>&1 | tee /tmp/pre-upgrade-phpstan.log
  composer lint:test 2>&1 | tee /tmp/pre-upgrade-pint.log
  ```

- [ ] **P3: Annotare commit hash di rollback**
  ```bash
  git rev-parse HEAD
  ```

---

### Fase 1: Upgrade Filament (~15 min)

- [ ] **1.1: Installare il tool di upgrade**
  ```bash
  composer require filament/upgrade:"~5.0" -W --dev
  ```

- [ ] **1.2: Eseguire lo script automatico**
  ```bash
  vendor/bin/filament-v5
  ```
  Lo script analizza il progetto e genera i comandi specifici. Leggere attentamente l'output.

- [ ] **1.3: Eseguire i comandi generati dallo script**
  Tipicamente:
  ```bash
  composer require filament/filament:"~5.0" -W --no-update
  composer update
  ```

- [ ] **1.4: Rimuovere il tool di upgrade**
  ```bash
  composer remove filament/upgrade --dev
  ```

- [ ] **1.5: Post-update**
  ```bash
  php artisan filament:upgrade
  php artisan optimize:clear
  ```

---

### Fase 2: Verifica Livewire v4 (~20 min)

- [ ] **2.1: Verificare versione Livewire**
  ```bash
  composer show livewire/livewire | grep versions
  ```
  Deve essere ≥ 4.0.0

- [ ] **2.2: Audit wire:model.live nei 12 file Blade**

  Tutti usano `wire:model.live` su elementi HTML nativi (`<input>`, `<select>`) — nessuna modifica necessaria. Verificare che funzionino correttamente:

  1. `resources/views/filament/pages/procurement-dashboard.blade.php` (2 occorrenze)
  2. `resources/views/filament/pages/finance/fx-impact-report.blade.php` (4)
  3. `resources/views/filament/pages/finance/customer-finance.blade.php` (3)
  4. `resources/views/filament/pages/finance/storage-billing-preview.blade.php` (3)
  5. `resources/views/filament/pages/finance/audit-export.blade.php` (5)
  6. `resources/views/filament/pages/finance/revenue-by-type-report.blade.php` (4)
  7. `resources/views/filament/pages/finance/outstanding-exposure-report.blade.php` (2)
  8. `resources/views/filament/pages/finance/invoice-aging-report.blade.php` (2)
  9. `resources/views/filament/pages/finance/reconciliation-status-report.blade.php` (3)
  10. `resources/views/filament/pages/finance/event-to-invoice-traceability-report.blade.php` (3)
  11. `resources/views/filament/pages/commercial-calendar.blade.php` (2)
  12. `resources/views/filament/resources/pim/product-resource/pages/import-livex.blade.php` (1)

- [ ] **2.3: Testare integrazione Alpine.js + $wire**

  File: `resources/views/filament/pages/finance/finance-overview.blade.php` (linea 79)
  ```blade
  x-on:click="dismissed = true; $wire.dismissAlert('{{ $alert['id'] }}')"
  ```
  Verificare manualmente che il dismiss degli alert funzioni.

---

### Fase 3: Rebuild Assets (~10 min)

- [ ] **3.1: Rebuild frontend**
  ```bash
  npm run build
  ```

- [ ] **3.2: Cache clear**
  ```bash
  php artisan filament:upgrade
  php artisan optimize:clear
  php artisan icons:cache
  ```

- [ ] **3.3: Verificare il tema custom**

  Il file `resources/css/filament/admin.css` attualmente importa:
  ```css
  @import '../../../vendor/filament/filament/resources/css/theme.css';
  ```
  Se il path del CSS è cambiato in v5, aggiornare. Il Panel Provider referenzia il tema via `->viteTheme('resources/css/filament/admin.css')`.

---

### Fase 4: Test Suite Completa (~1-2 ore)

#### 4.1: Test Automatizzati
- [ ] `composer test` — confrontare con baseline pre-upgrade
- [ ] `composer analyse` — PHPStan level 5 (possibili nuovi warning per type signature v5)
- [ ] `composer lint:test` — Laravel Pint

#### 4.2: Test Manuali — Pagine con wire:model.live
- [ ] Procurement Dashboard
- [ ] Commercial Calendar
- [ ] Finance Overview (+ Alpine $wire dismiss alert)
- [ ] Finance: FX Impact Report
- [ ] Finance: Customer Finance
- [ ] Finance: Storage Billing Preview
- [ ] Finance: Audit Export
- [ ] Finance: Revenue by Type Report
- [ ] Finance: Outstanding Exposure Report
- [ ] Finance: Invoice Aging Report
- [ ] Finance: Reconciliation Status Report
- [ ] Finance: Event-to-Invoice Traceability Report
- [ ] PIM: Import Liv-ex

#### 4.3: Test Manuali — Dashboard senza wire:model
- [ ] PIM Dashboard
- [ ] Allocation/Voucher Dashboard
- [ ] Fulfillment Dashboard
- [ ] Inventory Overview / Audit

#### 4.4: Test Manuali — CRUD Resources (1 per modulo)
- [ ] ProductResource (PIM) — Create/Edit/View
- [ ] InvoiceResource (Finance) — Create/Edit/View
- [ ] AllocationResource (Allocation) — Create/Edit/View
- [ ] ShippingOrderResource (Fulfillment) — Create/Edit/View
- [ ] CustomerResource (Customer) — Create/Edit/View
- [ ] PurchaseOrderResource (Procurement) — Create/Edit/View

#### 4.5: Test Manuali — Funzionalità Trasversali
- [ ] Login / Logout
- [ ] Sidebar collapsabile + navigazione tra gruppi
- [ ] Render hook: AI Chat icon (user-menu.before)
- [ ] Relation managers (Bundle components, PriceBook entries, WineVariant SKUs)
- [ ] Filtri tabella e ricerca globale
- [ ] Modals / Actions (delete confirmation, bulk actions)
- [ ] Exporter (OperationalBlockExporter)

---

### Fase 5: Commit e Deploy

- [ ] **5.1: Commit su branch**
  ```bash
  git add -A
  git commit -m "chore: upgrade Filament v4 → v5 (Livewire v4)"
  ```

- [ ] **5.2: Merge in main**
  ```bash
  git checkout main
  git merge upgrade/filament-v5
  git push origin main
  ```

- [ ] **5.3: Verifica post-deploy su server Ploi**
  Il deploy script esistente include `php artisan filament:upgrade` — nessuna modifica necessaria.
  ```bash
  ssh ploi@46.224.207.175 "cd /home/ploi/crurated.giovannibroegg.it && php artisan about | head -30"
  ssh ploi@46.224.207.175 "tail -100 /home/ploi/crurated.giovannibroegg.it/storage/logs/laravel.log"
  ```

---

## Piano di Rollback

### Rollback Locale — < 1 minuto

```bash
git checkout main               # Tornare a main pre-merge
composer install                 # Reinstallare dipendenze v4
npm install && npm run build     # Rebuild assets
php artisan optimize:clear
```

Se il merge è già stato fatto:
```bash
git revert HEAD
composer install
npm install && npm run build
php artisan optimize:clear
```

### Rollback Server — < 2 minuti

```bash
ssh ploi@46.224.207.175 "cd /home/ploi/crurated.giovannibroegg.it && git log --oneline -5"
ssh ploi@46.224.207.175 "cd /home/ploi/crurated.giovannibroegg.it && git checkout <COMMIT_PRE_UPGRADE>"
ssh ploi@46.224.207.175 "cd /home/ploi/crurated.giovannibroegg.it && composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev"
ssh ploi@46.224.207.175 "cd /home/ploi/crurated.giovannibroegg.it && php artisan optimize:clear && echo '' | sudo -S service php8.5-fpm reload"
```

### Database
- Filament v5 e Livewire v4 **non** introducono migrazioni — rollback puramente codice/dipendenze.

---

## Note Operative

### 1. Livewire Endpoint Path
Livewire v4 cambia `/livewire/update` → `/livewire-{hash}/update` (hash derivato da `APP_KEY`). Con la configurazione standard Laravel + Nginx funziona out-of-the-box. Se il server Ploi ha regole specifiche per `/livewire/`, vanno aggiornate.

### 2. Browser Cache
Vite genera hash unici per i bundle — i nuovi asset avranno URL diversi. In caso di problemi post-deploy, hard refresh (Cmd+Shift+R).

### 3. PHPStan Type Signatures
Filament v5 potrebbe aver cambiato type signature (form/table components). PHPStan level 5 potrebbe segnalare nuovi warning da risolvere in Fase 4.

### 4. Test Coverage
Solo 4 test Filament automatizzati (AiAssistantPage, Auth, Navigation, UserResource) su 220 file. I test manuali della Fase 4 sono **critici**.

---

## Verdetto Finale

**Upgrade CONSIGLIATO** con rischio **basso**.

| Pro | Contro |
|---|---|
| Zero plugin esterni = zero rischio incompatibilità | Livewire v3 → v4 = testing manuale necessario |
| Zero Livewire custom = impatto Livewire v4 minimo | 34 wire:model.live in 12 file da verificare |
| Tailwind v4 già presente = zero migrazione CSS | 4 test automatizzati su 220 file Filament |
| `laravel/ai` non dipende da Livewire = upgrade pulito | |
| Upgrade script automatico ufficiale disponibile | |

**Effort stimato**: 2–3 ore (inclusi test manuali)
**Rischio rollback**: Basso (zero migrazioni DB, rollback solo codice)
