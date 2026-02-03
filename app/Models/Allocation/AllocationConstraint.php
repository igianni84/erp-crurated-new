<?php

namespace App\Models\Allocation;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * AllocationConstraint Model
 *
 * Represents the authoritative commercial constraints for an Allocation.
 * Constraints define where and to whom the allocation can be sold.
 *
 * @property string $id
 * @property string $allocation_id
 * @property array<string>|null $allowed_channels
 * @property array<string>|null $allowed_geographies
 * @property array<string>|null $allowed_customer_types
 * @property string|null $composition_constraint_group
 * @property bool $fungibility_exception
 */
class AllocationConstraint extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'allocation_constraints';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'allocation_id',
        'allowed_channels',
        'allowed_geographies',
        'allowed_customer_types',
        'composition_constraint_group',
        'fungibility_exception',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_channels' => 'array',
            'allowed_geographies' => 'array',
            'allowed_customer_types' => 'array',
            'fungibility_exception' => 'boolean',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Prevent editing constraints if allocation is not in draft status
        static::updating(function (AllocationConstraint $constraint): void {
            $allocation = $constraint->allocation;
            if ($allocation !== null && ! $allocation->isDraft()) {
                throw new \InvalidArgumentException(
                    'Constraints can only be edited when the allocation is in Draft status.'
                );
            }
        });
    }

    /**
     * Get the allocation this constraint belongs to.
     *
     * @return BelongsTo<Allocation, $this>
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    /**
     * Get the audit logs for this constraint.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if the constraint is editable.
     */
    public function isEditable(): bool
    {
        $allocation = $this->allocation;

        return $allocation !== null && $allocation->isDraft();
    }

    /**
     * Get the allowed channels as an array, or all channels if not specified.
     *
     * @return array<string>
     */
    public function getEffectiveChannels(): array
    {
        /** @var array<string>|null $channels */
        $channels = $this->allowed_channels;

        return $channels ?? self::getAllChannels();
    }

    /**
     * Get the allowed geographies as an array, or all if not specified.
     *
     * @return array<string>
     */
    public function getEffectiveGeographies(): array
    {
        /** @var array<string>|null $geographies */
        $geographies = $this->allowed_geographies;

        return $geographies ?? [];
    }

    /**
     * Get the allowed customer types as an array, or all if not specified.
     *
     * @return array<string>
     */
    public function getEffectiveCustomerTypes(): array
    {
        /** @var array<string>|null $types */
        $types = $this->allowed_customer_types;

        return $types ?? self::getAllCustomerTypes();
    }

    /**
     * Get all available channel options.
     *
     * @return array<string>
     */
    public static function getAllChannels(): array
    {
        return ['b2c', 'b2b', 'private_sales', 'wholesale', 'club'];
    }

    /**
     * Get all available customer type options.
     *
     * @return array<string>
     */
    public static function getAllCustomerTypes(): array
    {
        return ['retail', 'trade', 'private_client', 'club_member', 'internal'];
    }

    /**
     * Check if a channel is allowed.
     */
    public function isChannelAllowed(string $channel): bool
    {
        $effectiveChannels = $this->getEffectiveChannels();

        return in_array($channel, $effectiveChannels, true);
    }

    /**
     * Check if a geography is allowed.
     */
    public function isGeographyAllowed(string $geography): bool
    {
        // If no geographies specified, all are allowed
        if ($this->allowed_geographies === null || $this->allowed_geographies === []) {
            return true;
        }

        return in_array($geography, $this->allowed_geographies, true);
    }

    /**
     * Check if a customer type is allowed.
     */
    public function isCustomerTypeAllowed(string $customerType): bool
    {
        $effectiveTypes = $this->getEffectiveCustomerTypes();

        return in_array($customerType, $effectiveTypes, true);
    }

    /**
     * Get a summary of constraints for display.
     */
    public function getSummary(): string
    {
        $parts = [];

        if ($this->allowed_channels !== null && $this->allowed_channels !== []) {
            $parts[] = count($this->allowed_channels).' channels';
        }

        if ($this->allowed_geographies !== null && $this->allowed_geographies !== []) {
            $parts[] = count($this->allowed_geographies).' geographies';
        }

        if ($this->allowed_customer_types !== null && $this->allowed_customer_types !== []) {
            $parts[] = count($this->allowed_customer_types).' customer types';
        }

        if ($this->composition_constraint_group !== null) {
            $parts[] = 'composition group';
        }

        if ($this->fungibility_exception) {
            $parts[] = 'fungibility exception';
        }

        return $parts === [] ? 'No restrictions' : implode(', ', $parts);
    }
}
