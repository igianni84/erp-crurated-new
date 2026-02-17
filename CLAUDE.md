## Workflow Orchestration

### 1. Plan Mode Default
- Enter plan mode for ANY non-trivial task (3+ steps or architectural decisions)
- If something goes sideways, STOP and re-plan immediately – don't keep pushing
- Use plan mode for verification steps, not just building
- Write detailed specs upfront to reduce ambiguity

### 2. Subagent Strategy
- Use subagents liberally to keep main context window clean
- Offload research, exploration, and parallel analysis to subagents
- For complex problems, throw more compute at it via subagents
- One task per subagent for focused execution

### 3. Self-Improvement Loop
- After ANY correction from the user: update `tasks/lessons.md` with the pattern
- Write rules for yourself that prevent the same mistake
- Ruthlessly iterate on these lessons until mistake rate drops
- Review lessons at session start for relevant project

### 4. Verification Before Done
- Non inventare nomi di variabili, funzioni, classi o altro, verifica sempre la loro esistenza prima di usarle
- Never mark a task complete without proving it works
- Diff behavior between main and your changes when relevant
- Ask yourself: "Would a staff engineer approve this?"
- Run tests, check logs, demonstrate correctness

### 5. Demand Elegance (Balanced)
- For non-trivial changes: pause and ask "is there a more elegant way?"
- If a fix feels hacky: "Knowing everything I know now, implement the elegant solution"
- Skip this for simple, obvious fixes – don't over-engineer
- Challenge your own work before presenting it

### 6. Autonomous Bug Fixing
- When I report a bug, don't start by trying to fix it. Instead, start by writing a test that reproduces the bug. Then, have subagents try to fix the bug and prove it with a passing test.
- Point at logs, errors, failing tests – then resolve them
- Zero context switching required from the user
- Go fix failing CI tests without being told how

## Task Management

1. **Plan First:** Write plan to `tasks/todo.md` with checkable items
2. **Verify Plan:** Check in before starting implementation
3. **Track Progress:** Mark items complete as you go
4. **Explain Changes:** High-level summary at each step
5. **Document Results:** Add review section to `tasks/todo.md`
6. **Capture Lessons:** Update `tasks/lessons.md` after corrections

## Core Principles

- **Simplicity First:** Make every change as simple as possible. Impact minimal code.
- **No Laziness:** Find root causes. No temporary fixes. Senior developer standards.
- **Minimal Impact:** Changes should only touch what's necessary. Avoid introducing bugs.

## Project Context

### What This Is
**Crurated ERP** — ERP per il trading di vini pregiati e beni di lusso. Multi-modulo, event-driven, sviluppato incrementalmente.

### Tech Stack
- **Backend:** Laravel 12, PHP 8.5, MySQL (SQLite dev)
- **Admin UI:** Filament 5 (45 resources, 33 custom pages, 11 widgets)
- **Frontend:** Tailwind CSS 4, Vite 7
- **Integrations:** Stripe (payments), Xero (accounting), WMS (warehouse), Liv-ex (wine data)
- **Quality:** PHPStan level 5, Laravel Pint, PHPUnit

### Module Map (dependency order)
| Code | Module | Purpose | Status |
|------|--------|---------|--------|
| — | Infrastructure | Auth, roles (super_admin/admin/manager/editor/viewer), base setup | ✅ Done |
| 0 | PIM | Wine Master → Variant → SellableSku, Formats, CaseConfig, Liquid Products | ✅ Done |
| K | Customers | Party/PartyRole (multi-role), Customer, Account, Membership, Clubs, Blocks | ✅ Done |
| S | Commercial | Channels, PriceBooks, PricingPolicies, Offers, Bundles, DiscountRules, EMP | ✅ ~97% (CSV import + PIM sync TODO) |
| A | Allocations | Allocations, Vouchers (1 voucher = 1 bottiglia), CaseEntitlements, Transfers | ✅ Done |
| D | Procurement | ProcurementIntents → PurchaseOrders → Inbounds, BottlingInstructions | ✅ Done (68/68) |
| B | Inventory | Locations, InboundBatches, SerializedBottles, Cases, Movements (append-only) | ✅ Done |
| C | Fulfillment | ShippingOrders → Late Binding → Shipments, Voucher Redemption | ✅ Done |
| E | Finance | Invoices (INV0-INV4), Payments, CreditNotes, Refunds, Subscriptions, Storage | ✅ Done (132/132) |
| — | Admin Panel | Dashboards, Alert Center, Audit Viewer, System Health | 📋 PRD ready |

### Architecture Patterns
- **Domain folders:** `app/{Models,Services,Enums,Events,Listeners,Jobs,Filament}/Module/`
- **UUID PKs everywhere** via `HasUuid` trait
- **Audit trail:** Immutable `AuditLog` + `Auditable`/`AuditLoggable` traits
- **Soft deletes** on ~95% of models
- **Enums:** String-backed PHP 8.4+ with `label()`, `color()`, `icon()`, `allowedTransitions()`
- **Event-driven cross-module:** Events trigger listeners (e.g., VoucherIssued → CreateProcurementIntent)
- **Service layer:** Business logic in Services, not Controllers or Models
- **Immutability guards:** Model `boot()` with `static::updating()` for critical fields

### Key Invariants (NEVER violate)
1. **1 voucher = 1 bottiglia** (quantity always 1)
2. **Allocation lineage immutable** (allocation_id never changes after creation)
3. **Late Binding ONLY in Module C** (voucher→bottle binding)
4. **Voucher redemption ONLY at shipment confirmation**
5. **Case breaking is IRREVERSIBLE** (Intact → Broken, never back)
6. **Finance is consequence, not cause** (events from other modules generate invoices)
7. **Invoice type immutable** after creation (INV0-INV4)
8. **Every PurchaseOrder requires a ProcurementIntent**
9. **ERP authorizes, WMS executes** (never the reverse)

### Codebase Numbers
- 78 Models, 41 Services, 95 Enums, 17 Jobs, 9 Events, 6 Listeners, 12 Policies
- 2 Observers, 2 Notifications, 1 Mailable
- 107 migrations, 45 Filament Resources, 33 custom pages, 11 widgets, 6 RelationManagers
- 35 seeders, 1 factory, 27 test files (11 Feature + 16 Unit), 327 tests
- 10 scheduled tasks in routes/console.php
- PRDs totali: 542 user stories across 10 modules

### Migration Numbering
- Module 0 (PIM): 200000+
- Module E (Finance): 300000+
- Module S (Commercial): 380000+
- Module K (Customers): 390000+
- Module D (Procurement): 400000+

### Coding Conventions
- Model: `app/Models/{Module}/ModelName.php`
- Enum: `app/Enums/{Module}/EnumName.php` — string-backed, with `label()`/`color()`/`icon()`
- Service: `app/Services/{Module}/ServiceName.php`
- Filament: `app/Filament/Resources/{Module}/ResourceName.php`
- Traits: `HasUuid`, `Auditable`, `AuditLoggable`, `HasLifecycleStatus`, `HasProductLifecycle`
- Decimal math: use `bcadd()`, `bcsub()`, `bcmul()` — never float arithmetic
- Immutability: enforce in model `boot()` with `static::updating()`
- PHPStan: explicit null checks, no nullsafe + null coalescing combos

### PRD & Task Files
- PRDs: `tasks/prd-{module}.md` (detailed specs with user stories)
- Progress: `tasks/progress-{module}.txt` (implementation logs)
- Ralph JSONs: `tasks/prd-{module}.json` (task runner format)
- Project plan: `tasks/project-plan.md`

## Server & Infrastructure

### Production Server (Ploi)
- **Host:** `46.224.207.175`
- **SSH:** `ssh ploi@46.224.207.175` (key `~/.ssh/id_ed25519` già registrata)
- **Site path:** `/home/ploi/crurated.giovannibroegg.it`
- **URL:** `https://crurated.giovannibroegg.it`
- **PHP:** 8.5 (FPM: `sudo -S service php8.5-fpm reload`)
- **DB:** MySQL, host `127.0.0.1:3306`, database `erpcrurated`
- **Ploi panel:** `ploi.io/panel/servers/106731/sites/342059`

### Git Repos
- **Locale (dev):** `github.com/igianni84/erp-crurated-new` ← repo attivo
- **Vecchio (deprecato):** `github.com/igianni84/erpcrurated` ← NON usare
- Il server Ploi DEVE puntare a `erp-crurated-new`

### Deploy Script (Ploi)
```bash
cd /home/ploi/crurated.giovannibroegg.it
git fetch origin main
git reset --hard origin/main
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
echo "" | sudo -S service php8.5-fpm reload
php artisan migrate --force
php artisan filament:optimize
php artisan optimize
```

### Quick SSH Commands
```bash
# Logs
ssh ploi@46.224.207.175 "tail -50 /home/ploi/crurated.giovannibroegg.it/storage/logs/laravel.log"

# Tinker
ssh ploi@46.224.207.175 "cd /home/ploi/crurated.giovannibroegg.it && php artisan tinker"

# Migration status
ssh ploi@46.224.207.175 "cd /home/ploi/crurated.giovannibroegg.it && php artisan migrate:status"

# Fresh seed (DESTRUCTIVE — solo staging/dev)
ssh ploi@46.224.207.175 "cd /home/ploi/crurated.giovannibroegg.it && php artisan migrate:fresh --force --seed"

# Clear all caches (including Filament)
ssh ploi@46.224.207.175 "cd /home/ploi/crurated.giovannibroegg.it && php artisan filament:optimize-clear && php artisan optimize:clear"
```

### Known Gotchas
- Seeders usano `fake()` → `fakerphp/faker` è in `require` (non `require-dev`)
- Deploy Ploi usa la sua config di repo, non il git remote del server — cambiare repo va fatto dal pannello Ploi
- Dopo cambio Filament version: sempre `php artisan filament:upgrade` + `php8.5-fpm reload`

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5.2
- filament/filament (FILAMENT) - v5
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

</laravel-boost-guidelines>
