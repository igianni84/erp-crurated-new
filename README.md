# Crurated ERP

Enterprise Resource Planning system built with Laravel 12 and Filament 5.

## Requirements

- PHP 8.2+
- MySQL 8.0+
- Composer 2.x
- Node.js 18+ (for asset compilation)

## Setup

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Start development server
php artisan serve
```

## Environment Configuration

The `.env.example` file contains all available environment variables with comments explaining recommended values for each environment.

### Development Environment

Optimized for local development with minimal setup:

```bash
APP_ENV=local
APP_DEBUG=true
LOG_STACK=single
LOG_LEVEL=debug
CACHE_STORE=file        # No Redis required
QUEUE_CONNECTION=sync   # Jobs run immediately (easier debugging)
MAIL_MAILER=log         # Emails written to log file
```

### Staging Environment

Similar to production but with more verbose logging:

```bash
APP_ENV=staging
APP_DEBUG=false
LOG_STACK=daily
LOG_LEVEL=debug
CACHE_STORE=database    # Uses existing MySQL
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

Run queue workers in staging to test async behavior:

```bash
php artisan queue:work --tries=3
```

### Production Environment

Optimized for performance and security:

```bash
APP_ENV=production
APP_DEBUG=false
LOG_STACK=daily,slack   # Daily files + Slack alerts
LOG_LEVEL=warning       # Only warnings and above
CACHE_STORE=redis       # Fast in-memory cache
QUEUE_CONNECTION=redis  # Fast async processing
SESSION_DRIVER=redis    # Fast session handling
SESSION_ENCRYPT=true    # Encrypt session data
```

**Production requirements:**
- Redis server for cache, queue, and sessions
- Queue worker process (use Supervisor)
- Proper SSL certificate (HTTPS)
- Strong `APP_KEY` (never share between environments)
- Database credentials in secrets manager (not in `.env`)

### Queue Workers

For staging/production environments using database or redis queues:

```bash
# Run a single worker (development/testing)
php artisan queue:work --tries=3

# Run with timeout and memory limits (production)
php artisan queue:work --tries=3 --timeout=60 --memory=128

# Process specific queue
php artisan queue:work --queue=high,default,low
```

For production, use [Supervisor](https://laravel.com/docs/queues#supervisor-configuration) to manage workers.

## Code Quality

```bash
# Run all quality checks
composer quality

# Individual commands
composer lint        # Fix code style
composer lint:test   # Check code style (no fix)
composer analyse     # Run PHPStan
composer test        # Run tests
```

## Project Structure

```
app/
├── Enums/              # Application enumerations
├── Filament/
│   └── Resources/
│       └── Pim/        # PIM module Filament resources
├── Models/
│   └── Pim/            # PIM module models
├── Services/           # Business logic services
└── Traits/             # Reusable model traits
```

## Reusable Traits

### HasUuid

Provides UUID as primary key functionality for Eloquent models.

**Usage:**

```php
use App\Traits\HasUuid;

class MyModel extends Model
{
    use HasUuid;
}
```

**Migration:**

```php
$table->uuid('id')->primary();
// Remove: $table->id();
```

### Auditable

Tracks who created and last updated the model.

**Usage:**

```php
use App\Traits\Auditable;

class MyModel extends Model
{
    use Auditable;
}
```

**Migration:**

```php
$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
```

**Methods:**

- `$model->creator` - Returns the User who created the model
- `$model->updater` - Returns the User who last updated the model

### HasLifecycleStatus

Provides lifecycle status management with standard states: draft, active, inactive, archived.

**Usage:**

```php
use App\Traits\HasLifecycleStatus;
use App\Enums\LifecycleStatus;

class MyModel extends Model
{
    use HasLifecycleStatus;

    protected $casts = [
        'status' => LifecycleStatus::class,
    ];
}
```

**Migration:**

```php
$table->string('status')->default('draft');
```

**Query Scopes:**

```php
MyModel::active()->get();      // Only active records
MyModel::draft()->get();       // Only draft records
MyModel::inactive()->get();    // Only inactive records
MyModel::archived()->get();    // Only archived records
MyModel::notArchived()->get(); // Exclude archived records
```

**Status Checks:**

```php
$model->isActive();
$model->isDraft();
$model->isInactive();
$model->isArchived();
```

**Status Transitions:**

```php
$model->activate();       // Set to active
$model->deactivate();     // Set to inactive
$model->archive();        // Set to archived
$model->restoreToDraft(); // Set to draft
```

## Enums

### LifecycleStatus

Standard lifecycle states for models.

```php
use App\Enums\LifecycleStatus;

LifecycleStatus::Draft;    // 'draft'
LifecycleStatus::Active;   // 'active'
LifecycleStatus::Inactive; // 'inactive'
LifecycleStatus::Archived; // 'archived'

// Helpers for Filament UI
$status->label(); // Human-readable label
$status->color(); // Filament color (gray, success, warning, danger)
$status->icon();  // Heroicon name
```

## License

Proprietary - Crurated
