<?php

namespace App\Models\Customer;

use App\Enums\Customer\ClubStatus;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Club Model
 *
 * Represents a Club entity for grouping customers.
 * Clubs are independent entities that can have customer affiliations.
 *
 * @property string $id
 * @property string $partner_name
 * @property ClubStatus $status
 * @property array|null $branding_metadata
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Club extends Model
{
    use Auditable;
    use HasUuid;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'clubs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'partner_name',
        'status',
        'branding_metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClubStatus::class,
            'branding_metadata' => 'array',
        ];
    }

    /**
     * Get the audit logs for this club.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if the club is active.
     */
    public function isActive(): bool
    {
        return $this->status === ClubStatus::Active;
    }

    /**
     * Check if the club is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === ClubStatus::Suspended;
    }

    /**
     * Check if the club has ended.
     */
    public function isEnded(): bool
    {
        return $this->status === ClubStatus::Ended;
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status label for UI display.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get the status icon for UI display.
     */
    public function getStatusIcon(): string
    {
        return $this->status->icon();
    }
}
