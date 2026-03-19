# Lessons Learned

## Debugging Methodology (2026-03-18)

### Don't Trial-and-Error — Find the Root Cause First
- When a CI job fails, **read the full error** and reason about it before patching
- `set_time_limit()` in application code affects the ENTIRE PHP process, including post-test coverage generation
- Before assuming infra issues (PCOV settings, php.ini, GitHub runner), **grep the codebase for `set_time_limit`**
- Any `set_time_limit()` in controller/job code must be guarded with `if (PHP_SAPI !== 'cli')` to avoid polluting test/CLI runs
- When you find a best practice, **apply it immediately** — don't keep patching the old approach

## Filament v5 Testing Patterns (2026-03-03)

### Repeater in Wizard/Form Tests
- `Repeater::fake()` MUST be called before `fillForm()` when the form contains a Repeater component
- Without it, Repeater items have UUID keys and `fillForm()` with numeric array keys won't match
- Always call `$undoRepeaterFake()` after the test assertion

### Wizard Tests (HasWizard)
- `fillForm()` fills ALL wizard steps at once — no need to navigate step by step
- `call('create')` triggers the full wizard submission
- Custom submit methods like `createAsDraft()` internally call `create()`, so `call('create')` works

### Hidden/Conditional Fields
- Fields with `->hidden(fn (Get $get) => ...)` are NOT validated when hidden
- To test validation of a conditionally-visible field, you must also set the parent field that makes it visible
- Example: `wine_variant_id` is hidden when `wine_master_id` is null, so validation test must set a valid `wine_master_id`

### CustomerObserver Side-Effect
- `CustomerObserver::updating()` throws `ValidationException` when status changes to Active without billing address
- This exception uses an empty Validator, so it doesn't map to Filament form errors
- Factory default is `Prospect` (safe). Tests changing status should avoid `Active` unless testing address flow

### Factory Namespace Resolution
- Laravel auto-resolves `App\Models\{Module}\Model` to `Database\Factories\{Module}\ModelFactory`
- All models already have `HasFactory` trait — no `newFactory()` override needed
- Factory subdirectories must match model subdirectories (e.g., `Pim/`, `Customer/`, `Inventory/`)

### Table Filters with Multi-Select
- `SelectFilter` with `->multiple()` expects array values in `filterTable()`: `filterTable('status', ['draft'])`
- Single-value SelectFilter expects scalar: `filterTable('status', 'draft')`

### Edit Page Tests
- Filament v5 docs pattern: `fillForm([...]) -> call('save') -> assertHasNoFormErrors()`
- Avoid changing status to values that trigger Observer side-effects (e.g., Active without billing address)
- `EditRecord::authorizeAccess()` may abort 403 — test via HTTP request `$this->get(Page::getUrl(...))->assertForbidden()`

## Filament v5 Property Types (2026-03-19)

### Static Property Type Declarations
- `$navigationGroup` must be declared as `\UnitEnum|string|null` (not `?string`)
- `$navigationIcon` must be `string|\BackedEnum|null`
- PHPStan level 8 catches these as fatal errors — always check parent class types before overriding

## Xero SDK Gotchas (2026-03-19)

### Date Parameters
- `setDate()` / `setDueDate()` expect `string|null` (e.g., `'2026-03-19'`), NOT `\DateTime` objects
- PHPStan catches this at level 8

### Return Type Unions
- `createInvoices()` returns `Invoices|Error` — must use `@var` annotation for PHPStan
- `getInvoices()` returns `array|null` — always null-check: `$result->getInvoices() ?? []`
- `Payment` model has no `setCurrencyCode()` — currency is set at invoice level

### OAuth2 Integration Pattern
- Store tokens encrypted at rest (`encrypted` cast on `access_token`/`refresh_token`)
- Singleton pattern: deactivate old tokens when storing new ones
- Auto-refresh: check `expiresWithin(5)` before each API call
- Graceful degradation: `isConnected()` check → fall back to stub if not connected

## Laravel Auth Testing (2026-03-19)

### Route `[login]` Not Defined
- Filament handles authentication via its own panel routes, not `Route::get('/login')`
- Testing unauthenticated access to `auth` middleware routes throws `RouteNotFoundException` for `[login]`
- Don't use `assertRedirect()` — instead assert `$response->getStatusCode() !== 200`
