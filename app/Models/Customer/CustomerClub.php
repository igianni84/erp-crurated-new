<?php

namespace App\Models\Customer;

use App\Enums\Customer\AffiliationStatus;
use App\Models\AuditLog;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * CustomerClub Model (Pivot)
 *
 * Represents the affiliation between a Customer and a Club.
 * A Customer can belong to multiple Clubs, and a Club can have multiple Customers.
 * The affiliation status is independent of the Club status.
 *
 * @property string $id
 * @property string $customer_id
 * @property string $club_id
 * @property AffiliationStatus $affiliation_status
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CustomerClub extends Model
{
    use Auditable;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customer_clubs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'club_id',
        'affiliation_status',
        'start_date',
        'end_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'affiliation_status' => AffiliationStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Get the customer that belongs to this affiliation.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the club that belongs to this affiliation.
     *
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * Get the audit logs for this customer-club affiliation.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Check if the affiliation is active.
     */
    public function isActive(): bool
    {
        return $this->affiliation_status === AffiliationStatus::Active;
    }

    /**
     * Check if the affiliation is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->affiliation_status === AffiliationStatus::Suspended;
    }

    /**
     * Check if the affiliation has ended (end_date is set and in the past).
     */
    public function hasEnded(): bool
    {
        return $this->end_date !== null && $this->end_date->isPast();
    }

    /**
     * Check if the affiliation is currently effective.
     * An affiliation is effective if it's active, has started, and hasn't ended.
     */
    public function isEffective(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->start_date->isFuture()) {
            return false;
        }

        if ($this->end_date !== null && $this->end_date->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Get the affiliation status label for UI display.
     */
    public function getStatusLabel(): string
    {
        return $this->affiliation_status->label();
    }

    /**
     * Get the affiliation status color for UI display.
     */
    public function getStatusColor(): string
    {
        return $this->affiliation_status->color();
    }

    /**
     * Get the affiliation status icon for UI display.
     */
    public function getStatusIcon(): string
    {
        return $this->affiliation_status->icon();
    }

    /**
     * Scope to get only active affiliations.
     *
     * @param  Builder<CustomerClub>  $query
     * @return Builder<CustomerClub>
     */
    public function scopeActive($query)
    {
        return $query->where('affiliation_status', AffiliationStatus::Active);
    }

    /**
     * Scope to get only effective affiliations (active, started, not ended).
     *
     * @param  Builder<CustomerClub>  $query
     * @return Builder<CustomerClub>
     */
    public function scopeEffective($query)
    {
        return $query
            ->where('affiliation_status', AffiliationStatus::Active)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }
}
