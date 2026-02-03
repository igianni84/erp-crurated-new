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
