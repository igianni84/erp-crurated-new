<?php

namespace App\Models\Procurement;

use App\Models\AuditLog;
use App\Models\Customer\Party;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ProducerSupplierConfig Model
 *
 * Stores default configurations for producer/supplier parties.
 * This is an optional one-to-one relationship with Party - not all parties are suppliers/producers.
 *
 * @property string $id UUID primary key
 * @property string $party_id FK to Party (unique)
 * @property int|null $default_bottling_deadline_days Default deadline in days
 * @property array<int, string>|null $allowed_formats Allowed bottle formats
 * @property array<string, mixed>|null $serialization_constraints Serialization constraints
 * @property string|null $notes General notes
 * @property int|null $created_by FK to User
 * @property int|null $updated_by FK to User
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class ProducerSupplierConfig extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'producer_supplier_configs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'party_id',
        'default_bottling_deadline_days',
        'allowed_formats',
        'serialization_constraints',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_bottling_deadline_days' => 'integer',
            'allowed_formats' => 'array',
            'serialization_constraints' => 'array',
        ];
    }

    /**
     * Get the party this config belongs to.
     *
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    /**
     * Get the audit logs for this config.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Check if the config has a default bottling deadline.
     */
    public function hasDefaultBottlingDeadline(): bool
    {
        return $this->default_bottling_deadline_days !== null;
    }

    /**
     * Get the default bottling deadline date from today.
     */
    public function getDefaultBottlingDeadlineDate(): ?Carbon
    {
        if ($this->default_bottling_deadline_days === null) {
            return null;
        }

        return now()->addDays($this->default_bottling_deadline_days);
    }

    /**
     * Check if allowed formats are configured.
     */
    public function hasAllowedFormats(): bool
    {
        return ! empty($this->allowed_formats);
    }

    /**
     * Check if a specific format is allowed.
     */
    public function isFormatAllowed(string $format): bool
    {
        if ($this->allowed_formats === null) {
            // If no restrictions, all formats are allowed
            return true;
        }

        return in_array($format, $this->allowed_formats, true);
    }

    /**
     * Check if serialization constraints are configured.
     */
    public function hasSerializationConstraints(): bool
    {
        return ! empty($this->serialization_constraints);
    }

    /**
     * Get a specific serialization constraint.
     */
    public function getSerializationConstraint(string $key, mixed $default = null): mixed
    {
        if ($this->serialization_constraints === null) {
            return $default;
        }

        return $this->serialization_constraints[$key] ?? $default;
    }

    /**
     * Check if a location is authorized for serialization.
     */
    public function isSerializationLocationAuthorized(string $location): bool
    {
        $authorizedLocations = $this->getSerializationConstraint('authorized_locations');

        if ($authorizedLocations === null) {
            // If no restrictions, all locations are authorized
            return true;
        }

        if (! is_array($authorizedLocations)) {
            return true;
        }

        return in_array($location, $authorizedLocations, true);
    }

    /**
     * Get display label for the allowed formats.
     */
    public function getAllowedFormatsLabel(): string
    {
        if ($this->allowed_formats === null || count($this->allowed_formats) === 0) {
            return 'No restrictions';
        }

        return implode(', ', $this->allowed_formats);
    }

    /**
     * Get display label for the deadline.
     */
    public function getDeadlineDaysLabel(): string
    {
        if ($this->default_bottling_deadline_days === null) {
            return 'Not specified';
        }

        return $this->default_bottling_deadline_days.' days';
    }
}
