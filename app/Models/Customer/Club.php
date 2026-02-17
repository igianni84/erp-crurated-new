<?php

namespace App\Models\Customer;

use App\Enums\Customer\AffiliationStatus;
use App\Enums\Customer\ClubStatus;
use App\Models\AuditLog;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
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
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
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

    /**
     * Get the customers affiliated with this club.
     *
     * @return BelongsToMany<Customer, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_clubs')
            ->withPivot(['id', 'affiliation_status', 'start_date', 'end_date', 'created_by', 'updated_by'])
            ->withTimestamps();
    }

    /**
     * Get the customer affiliations (pivot records) for this club.
     *
     * @return HasMany<CustomerClub, $this>
     */
    public function customerAffiliations(): HasMany
    {
        return $this->hasMany(CustomerClub::class);
    }

    /**
     * Get only the active customer affiliations for this club.
     *
     * @return HasMany<CustomerClub, $this>
     */
    public function activeCustomerAffiliations(): HasMany
    {
        return $this->hasMany(CustomerClub::class)
            ->where('affiliation_status', AffiliationStatus::Active);
    }

    /**
     * Get the count of active members in this club.
     */
    public function getActiveMembersCount(): int
    {
        return $this->activeCustomerAffiliations()->count();
    }

    /**
     * Check if a customer is affiliated with this club.
     */
    public function hasCustomer(Customer $customer): bool
    {
        return $this->customerAffiliations()
            ->where('customer_id', $customer->id)
            ->exists();
    }
}
