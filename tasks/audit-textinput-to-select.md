# Audit: TextInput → Select Conversion

**Data:** 2026-03-13
**Scope:** Tutti i 45 Filament Resources + 35 custom pages
**Metodo:** 8 agenti paralleli per modulo + catalogo 95 enum

---

## Findings Summary

| # | File | Field | Line | Priorità | Tipo Fix |
|---|------|-------|------|----------|----------|
| 1 | `LocationResource.php` | `country` | 115 | **HIGH** | Select → Country model |
| 2 | `ViewCustomer.php` | `country` (create address) | 1262 | **HIGH** | Select → Country model |
| 3 | `ViewCustomer.php` | `country` (edit modal) | 1334 | **HIGH** | Select → Country model |
| 4 | `ViewCustomer.php` | `state` (create address) | 1252 | **MEDIUM** | TextInput → free-text ok, ma si può migliorare |
| 5 | `ViewCustomer.php` | `state` (edit modal) | 1322 | **MEDIUM** | TextInput → free-text ok, ma si può migliorare |
| 6 | `PartyResource.php` | `jurisdiction` | 87 | **HIGH** | Select → Country model (giurisdizioni legali) |
| 7 | `ChannelResource.php` | `default_currency` | 66 | **HIGH** | Select → lista ISO 4217 |
| 8 | `PriceBookResource.php` | `currency` | 69 | **HIGH** | Select → lista ISO 4217 |
| 9 | `PriceBookResource.php` | `market` | 58 | **HIGH** | Select → Country model (codici ISO) |
| 10 | `ShippingOrderResource.php` | `carrier` | 91 | **HIGH** | Select → enum `Carrier` (già esiste!) |
| 11 | `ShippingOrderResource.php` | `shipping_method` | 96 | **MEDIUM** | Select con opzioni standard |
| 12 | `CreateShippingOrder.php` | `shipping_method` | 613 | **MEDIUM** | Select con opzioni standard |
| 13 | `CountryResource.php` | `iso_code` | 54 | **LOW** | Accettabile (campo admin per creazione country) |
| 14 | `CountryResource.php` | `iso_code_3` | 59 | **LOW** | Accettabile (campo admin per creazione country) |

---

## Dettaglio per Categoria

### 🔴 COUNTRY fields (5 occorrenze — Priorità HIGH)

Il progetto ha già un model `App\Models\Pim\Country` con `iso_code`, `iso_code_3`, e `name`.
Tutti i campi "country" free-text dovrebbero usare una Select che punta a questo model.

**1. LocationResource.php:115** — `country`
```php
// ATTUALE
TextInput::make('country')
    ->label('Country')
    ->required()
    ->maxLength(100)
    ->placeholder('e.g., Italy, United Kingdom, France'),

// FIX → Select searchable dal model Country
Select::make('country')
    ->label('Country')
    ->required()
    ->searchable()
    ->options(fn () => Country::orderBy('name')->pluck('name', 'name')->toArray()),
```

**2-3. ViewCustomer.php:1262 e :1334** — `country` (create + edit address modal)
```php
// ATTUALE (entrambi)
TextInput::make('country')
    ->label('Country')
    ->required()
    ->maxLength(255),

// FIX → Select searchable dal model Country
Select::make('country')
    ->label('Country')
    ->required()
    ->searchable()
    ->options(fn () => Country::orderBy('name')->pluck('name', 'name')->toArray()),
```

**4. PartyResource.php:87** — `jurisdiction`
```php
// ATTUALE
TextInput::make('jurisdiction')
    ->label('Jurisdiction')
    ->maxLength(255),

// FIX → Select searchable dal model Country
Select::make('jurisdiction')
    ->label('Jurisdiction')
    ->searchable()
    ->options(fn () => Country::orderBy('name')->pluck('name', 'name')->toArray()),
```

**5. PriceBookResource.php:58** — `market`
```php
// ATTUALE
TextInput::make('market')
    ->required()
    ->maxLength(255)
    ->placeholder('e.g., IT, DE, US'),

// FIX → Select con codici ISO dal model Country
Select::make('market')
    ->required()
    ->searchable()
    ->options(fn () => Country::whereNotNull('iso_code')
        ->orderBy('name')
        ->pluck('name', 'iso_code')
        ->toArray()),
```

---

### 🔴 CURRENCY fields (2 occorrenze — Priorità HIGH)

Non esiste un enum Currency nel progetto. Serve una lista statica ISO 4217 o un nuovo enum.

**6. ChannelResource.php:66** — `default_currency`
```php
// ATTUALE
TextInput::make('default_currency')
    ->label('Default Currency')
    ->required()
    ->maxLength(3)
    ->placeholder('EUR'),

// FIX → Select con valute supportate
Select::make('default_currency')
    ->label('Default Currency')
    ->required()
    ->searchable()
    ->options([
        'EUR' => 'EUR — Euro',
        'GBP' => 'GBP — British Pound',
        'USD' => 'USD — US Dollar',
        'CHF' => 'CHF — Swiss Franc',
    ]),
```

**7. PriceBookResource.php:69** — `currency`
```php
// ATTUALE
TextInput::make('currency')
    ->required()
    ->maxLength(3)
    ->placeholder('EUR'),

// FIX → stessa Select di ChannelResource
Select::make('currency')
    ->required()
    ->searchable()
    ->options([
        'EUR' => 'EUR — Euro',
        'GBP' => 'GBP — British Pound',
        'USD' => 'USD — US Dollar',
        'CHF' => 'CHF — Swiss Franc',
    ]),
```

---

### 🔴 CARRIER field (1 occorrenza — Priorità HIGH)

L'enum `App\Enums\Fulfillment\Carrier` esiste già con: DHL, FedEx, UPS, TNT, DPD, GLS, USPS, Chronopost, Colissimo, Other.
La pagina `CreateShippingOrder` lo usa correttamente, ma il Resource principale no.

**8. ShippingOrderResource.php:91** — `carrier`
```php
// ATTUALE
TextInput::make('carrier')
    ->label('Carrier')
    ->maxLength(255)
    ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),

// FIX → Select con enum Carrier (come già fatto in CreateShippingOrder)
Select::make('carrier')
    ->label('Carrier')
    ->options(
        collect(Carrier::cases())
            ->mapWithKeys(fn (Carrier $c) => [$c->value => $c->label()])
            ->toArray()
    )
    ->searchable()
    ->disabled(fn (?ShippingOrder $record): bool => $record !== null && ! $record->isDraft()),
```

---

### 🟡 SHIPPING METHOD fields (2 occorrenze — Priorità MEDIUM)

Non esiste un enum ShippingMethod. Le opzioni (Express, Standard, Economy) sono implicite nel placeholder.

**9-10. ShippingOrderResource.php:96 + CreateShippingOrder.php:613**
```php
// ATTUALE
TextInput::make('shipping_method')
    ->label('Shipping Method')
    ->placeholder('e.g., Express, Standard, Economy...')

// OPZIONE A: Creare enum ShippingMethod
// OPZIONE B: Select con opzioni inline
Select::make('shipping_method')
    ->label('Shipping Method')
    ->options([
        'Express' => 'Express',
        'Standard' => 'Standard',
        'Economy' => 'Economy',
    ])
    ->searchable(),
```

---

### 🟡 STATE fields (2 occorrenze — Priorità MEDIUM)

I campi "state" nelle address sono problematici perché la lista delle regioni/province/stati
dipende dal paese selezionato. Questo richiede un campo `live()` + reactive.

**11-12. ViewCustomer.php:1252 e :1322** — `state`
```
Opzione: Mantenere TextInput ma aggiungere datalist/suggestions, oppure
implementare un Select reattivo che filtra per country selezionato.
Data la complessità e la variabilità internazionale, TextInput è accettabile.
```

---

### ⚪ ISO CODE fields (2 occorrenze — Priorità LOW)

**13-14. CountryResource.php:54 e :59** — `iso_code`, `iso_code_3`

Questi sono campi nel form di creazione/modifica Country (tabella master PIM).
L'admin inserisce codici ISO quando crea un nuovo paese — TextInput con `unique()` validation
è accettabile qui perché è un form di amministrazione master data.

---

## Moduli Senza Problemi

| Modulo | Resources Analizzate | Stato |
|--------|---------------------|-------|
| Finance | 6 resources + 6 custom pages | ✅ Tutto OK |
| Allocations | 4 resources | ✅ Tutto OK |
| Procurement | 4 resources + custom pages | ✅ Tutto OK |
| PIM (escluso CountryResource) | 10 resources | ✅ Tutto OK |
| Custom Pages (non-module) | 13 pagine | ✅ Tutto OK |

---

## Piano di Implementazione Consigliato

### Fase 1 — Quick Wins (enum/model già esistenti)
1. ✅ `ShippingOrderResource.carrier` → Select con enum `Carrier` (già usato in CreateShippingOrder)

### Fase 2 — Country fields (model Country già esiste)
2. `LocationResource.country` → Select da model Country
3. `ViewCustomer.country` × 2 → Select da model Country
4. `PartyResource.jurisdiction` → Select da model Country
5. `PriceBookResource.market` → Select da model Country (iso_code)

### Fase 3 — Currency (serve definire lista supportata)
6. Creare helper/config con valute supportate (EUR, GBP, USD, CHF)
7. `ChannelResource.default_currency` → Select
8. `PriceBookResource.currency` → Select

### Fase 4 — Shipping Method (opzionale)
9. Valutare se creare enum `ShippingMethod` o usare Select inline
10. `ShippingOrderResource.shipping_method` → Select
11. `CreateShippingOrder.shipping_method` → Select
