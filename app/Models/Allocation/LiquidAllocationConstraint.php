<?php

namespace App\Models\Allocation;

use App\Enums\Allocation\AllocationSupplyForm;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * LiquidAllocationConstraint Model
 *
 * Represents additional constraints for liquid allocations.
 * Only applicable when allocation.supply_form = 'liquid'.
 *
 * @property string $id
 * @property string $allocation_id
 * @property array<string>|null $allowed_bottling_formats
 * @property array<string>|null $allowed_case_configurations
 * @property \Carbon\Carbon|null $bottling_confirmation_deadline
 */
class LiquidAllocationConstraint extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'liquid_allocation_constraints';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'allocation_id',
        'allowed_bottling_formats',
        'allowed_case_configurations',
        'bottling_confirmation_deadline',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_bottling_formats' => 'array',
            'allowed_case_configurations' => 'array',
            'bottling_confirmation_deadline' => 'date',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Validate that allocation has supply_form = liquid on create/update
        static::saving(function (LiquidAllocationConstraint $constraint): void {
            $allocation = $constraint->allocation;
            if ($allocation !== null && $allocation->supply_form !== AllocationSupplyForm::Liquid) {
                throw new \InvalidArgumentException(
                    'LiquidAllocationConstraint can only be created for allocations with supply_form = liquid.'
                );
            }
        });

        // Prevent editing constraints if allocation is not in draft status
        static::updating(function (LiquidAllocationConstraint $constraint): void {
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
     * Get the allowed bottling formats as an array, or all if not specified.
     *
     * @return array<string>
     */
    public function getEffectiveBottlingFormats(): array
    {
        /** @var array<string>|null $formats */
        $formats = $this->allowed_bottling_formats;

        return $formats ?? [];
    }

    /**
     * Get the allowed case configurations as an array, or all if not specified.
     *
     * @return array<string>
     */
    public function getEffectiveCaseConfigurations(): array
    {
        /** @var array<string>|null $configs */
        $configs = $this->allowed_case_configurations;

        return $configs ?? [];
    }

    /**
     * Check if a bottling format is allowed.
     */
    public function isBottlingFormatAllowed(string $format): bool
    {
        // If no formats specified, all are allowed
        if ($this->allowed_bottling_formats === null || $this->allowed_bottling_formats === []) {
            return true;
        }

        return in_array($format, $this->allowed_bottling_formats, true);
    }

    /**
     * Check if a case configuration is allowed.
     */
    public function isCaseConfigurationAllowed(string $config): bool
    {
        // If no configurations specified, all are allowed
        if ($this->allowed_case_configurations === null || $this->allowed_case_configurations === []) {
            return true;
        }

        return in_array($config, $this->allowed_case_configurations, true);
    }

    /**
     * Check if there is a bottling deadline set and if it has passed.
     */
    public function isBottlingDeadlinePassed(): bool
    {
        if ($this->bottling_confirmation_deadline === null) {
            return false;
        }

        /** @var \Carbon\Carbon $deadline */
        $deadline = $this->bottling_confirmation_deadline;

        return $deadline->isPast();
    }

    /**
     * Get a summary of liquid constraints for display.
     */
    public function getSummary(): string
    {
        $parts = [];

        if ($this->allowed_bottling_formats !== null && $this->allowed_bottling_formats !== []) {
            $parts[] = count($this->allowed_bottling_formats).' bottling formats';
        }

        if ($this->allowed_case_configurations !== null && $this->allowed_case_configurations !== []) {
            $parts[] = count($this->allowed_case_configurations).' case configs';
        }

        if ($this->bottling_confirmation_deadline !== null) {
            /** @var \Carbon\Carbon $deadline */
            $deadline = $this->bottling_confirmation_deadline;
            $parts[] = 'deadline: '.$deadline->format('Y-m-d');
        }

        return $parts === [] ? 'No liquid constraints' : implode(', ', $parts);
    }
}
