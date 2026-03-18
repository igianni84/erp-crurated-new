# Piano: PHPStan Level 5 → Level 6

## Contesto

PHPStan L6 aggiunge controlli sui **tipi generici** (template types). Il dry-run mostra **173 errori**, ma il ~96% è meccanico e a zero rischio. La codebase è già ben tipizzata — nessun errore in Services, Controllers, Jobs, Events, Policies.

## Numeri

| Categoria | Errori | Rischio | Auto-fixabile |
|-----------|--------|---------|---------------|
| `HasFactory<TFactory>` mancante sui Model | 121 | Zero | Si |
| `Builder<TModel>` mancante su Resources | 45 (stima dal report) | Zero | Si |
| Array senza value type (PHPDoc) | 31 | Zero | Si |
| Parametri senza type hint | 6 | Zero | Si |
| Relationship senza generic type | 2 | Zero | Si |
| `collect()` template irrisolvibili | 4 | Zero | Baseline/ignore |

**~169 fix meccanici + 4 da ignorare via baseline = 0 errori residui**

## Strategia: Fix Tutto + Baseline Minimale

Niente baseline massiccio — fixiamo tutto il fixabile e mettiamo in baseline solo i 4 falsi positivi di `collect()`.

---

## Step 1: Bump a L6 + baseline dei soli falsi positivi

**File:** `phpstan.neon`

```neon
parameters:
    level: 6
    ignoreErrors:
        - identifier: trait.unused
        - message: '#Unable to resolve the template type T(Key|Value) in call to function collect#'
```

Questo ignora i 4 errori irrisolvibili (`collect()` su array dinamici — noto limite di Larastan). Nessun baseline file necessario.

**Verifica:** `phpstan analyse` deve dare ~169 errori (i 4 collect spariscono).

---

## Step 2: Fix HasFactory su 69 Model (121 errori)

**Pattern da applicare su ogni Model con `use HasFactory`:**

```php
// Aggiungere PHPDoc PRIMA del trait use
/** @use HasFactory<\Database\Factories\ModelNameFactory> */
use HasFactory;
```

**Nota:** Non tutti i Model hanno una Factory corrispondente in `database/factories/`. Per quelli senza factory, il PHPDoc punta al generic `\Illuminate\Database\Eloquent\Factories\Factory<static>`:

```php
/** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
```

**File coinvolti:** 69 Model in `app/Models/` (tutti i moduli). Verificheremo prima quali hanno factory reali.

**Verifica:** `phpstan analyse --filter app/Models/` — 0 errori HasFactory.

---

## Step 3: Fix Builder return type su ~30 Filament Resources (45 errori stimati)

**Pattern da applicare:**

```php
// Da:
public static function getEloquentQuery(): Builder

// A:
/** @return Builder<ModelName> */
public static function getEloquentQuery(): Builder
```

Stessa cosa per `getGlobalSearchEloquentQuery()`, `getTableQuery()`, `scopeQuery()`.

**File coinvolti:** ~30 Resources in `app/Filament/Resources/`, ~3 custom pages, 1 widget.

**Verifica:** `phpstan analyse --filter app/Filament/` — 0 errori Builder.

---

## Step 4: Fix array value types (31 errori)

### 4a. AI Tools — metodo `schema()` (18 file)

```php
// Da:
public function schema(): array

// A:
/** @return array<string, mixed> */
public function schema(): array
```

### 4b. PHPDoc @property su Model (2 errori)

```php
// AuditLog: $new_values, $old_values
// Club: $branding_metadata
// Da: @property array|null $field
// A:  @property array<string, mixed>|null $field
```

### 4c. Parametri array su metodi (3+ errori)

```php
// AllocationCheckResult::success($allocation, $message, array $details)
// → array<string, mixed> $details

// ViewCustomer::createAddress(array $data)
// → array<string, mixed> $data
```

**Verifica:** `phpstan analyse` — 0 errori "no value type".

---

## Step 5: Fix parametri senza type hint (6 errori)

### InventoryMovementResource (2 errori)
```php
public static function canEdit($record): bool → canEdit(InventoryMovement $record): bool
public static function canDelete($record): bool → canDelete(InventoryMovement $record): bool
```

### GenerateSubscriptionInvoice (4 errori)
```php
buildInvoiceLines($subscription) → buildInvoiceLines(Subscription $subscription)
buildLineDescription($subscription) → buildLineDescription(Subscription $subscription)
buildInvoiceNotes($subscription) → buildInvoiceNotes(Subscription $subscription)
isRecentBillingPeriod($subscription) → isRecentBillingPeriod(Subscription $subscription)
```

**Verifica:** `phpstan analyse --filter` sui 2 file — 0 errori.

---

## Step 6: Fix Relationship generic types (2 errori)

```php
// CaseConfiguration.php — BelongsTo
public function format(): BelongsTo  →  /** @return BelongsTo<Format, $this> */

// Format.php — HasMany
public function caseConfigurations(): HasMany  →  /** @return HasMany<CaseConfiguration, $this> */
```

**Verifica:** `phpstan analyse --filter app/Models/Pim/` — 0 errori.

---

## Step 7: Run completo + Pint + Test

1. `php -d memory_limit=1G vendor/bin/phpstan analyse` — **deve dare 0 errori**
2. `vendor/bin/pint --dirty --format agent` — formattazione
3. `php artisan test --compact` — nessuna regressione (le modifiche sono solo PHPDoc/type hints)

---

## Ordine di esecuzione

| # | Step | Errori risolti | Tempo stimato |
|---|------|---------------|---------------|
| 1 | Config phpstan.neon | 4 (ignorati) | 1 min |
| 2 | HasFactory su 69 Model | 121 | Batch con subagent |
| 3 | Builder su ~30 Resources | ~45 | Batch con subagent |
| 4 | Array value types | 31 | Batch con subagent |
| 5 | Parametri type hint | 6 | Manuale, 2 file |
| 6 | Relationship generics | 2 | Manuale, 2 file |
| 7 | Verifica finale | — | 5 min |

Step 2, 3, 4 possono essere parallelizzati con subagent separati.

## Rischi

- **Zero rischio runtime:** tutte le modifiche sono PHPDoc e type hints — nessun cambiamento di comportamento
- **Zero rischio test:** nessuna logica modificata
- **Unico rischio:** errore di battitura in un PHPDoc che causa nuovo errore PHPStan — catturato da Step 7
