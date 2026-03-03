# Lessons Learned

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
