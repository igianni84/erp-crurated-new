<?php

namespace App\Models\Customer;

use App\Enums\Customer\MembershipStatus;
use App\Enums\Customer\MembershipTier;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Membership Model
 *
 * Represents a customer's membership status and tier in the system.
 * A Customer has one active Membership at a time, but can have multiple historical records.
 *
 * @property string $id
 * @property string $customer_id
 * @property MembershipTier $tier
 * @property MembershipStatus $status
 * @property \Carbon\Carbon|null $effective_from
 * @property \Carbon\Carbon|null $effective_to
 * @property string|null $decision_notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Membership extends Model
{
    use Auditable;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'memberships';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'tier',
        'status',
        'effective_from',
        'effective_to',
        'decision_notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tier' => MembershipTier::class,
            'status' => MembershipStatus::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    /**
     * Get the customer that owns this membership.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the audit logs for this membership.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if this membership is active (approved and within effective dates).
     */
    public function isActive(): bool
    {
        if ($this->status !== MembershipStatus::Approved) {
            return false;
        }

        $now = now();

        // Check effective_from
        if ($this->effective_from !== null && $this->effective_from->isAfter($now)) {
            return false;
        }

        // Check effective_to
        if ($this->effective_to !== null && $this->effective_to->isBefore($now)) {
            return false;
        }

        return true;
    }

    /**
     * Check if this membership allows channel access.
     */
    public function allowsChannelAccess(): bool
    {
        return $this->isActive();
    }

    /**
     * Check if the membership is in applied status.
     */
    public function isApplied(): bool
    {
        return $this->status === MembershipStatus::Applied;
    }

    /**
     * Check if the membership is under review.
     */
    public function isUnderReview(): bool
    {
        return $this->status === MembershipStatus::UnderReview;
    }

    /**
     * Check if the membership is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === MembershipStatus::Approved;
    }

    /**
     * Check if the membership was rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === MembershipStatus::Rejected;
    }

    /**
     * Check if the membership is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === MembershipStatus::Suspended;
    }

    /**
     * Check if the membership is legacy tier.
     */
    public function isLegacy(): bool
    {
        return $this->tier === MembershipTier::Legacy;
    }

    /**
     * Check if the membership is standard member tier.
     */
    public function isMember(): bool
    {
        return $this->tier === MembershipTier::Member;
    }

    /**
     * Check if the membership is invitation-only tier.
     */
    public function isInvitationOnly(): bool
    {
        return $this->tier === MembershipTier::InvitationOnly;
    }

    /**
     * Get the tier label for UI display.
     */
    public function getTierLabel(): string
    {
        return $this->tier->label();
    }

    /**
     * Get the tier color for UI display.
     */
    public function getTierColor(): string
    {
        return $this->tier->color();
    }

    /**
     * Get the tier icon for UI display.
     */
    public function getTierIcon(): string
    {
        return $this->tier->icon();
    }

    /**
     * Get the status label for UI display.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status icon for UI display.
     */
    public function getStatusIcon(): string
    {
        return $this->status->icon();
    }

    /**
     * Check if transitioning to a new status is valid.
     */
    public function canTransitionTo(MembershipStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    /**
     * Get valid status transitions from current status.
     *
     * @return array<MembershipStatus>
     */
    public function getValidTransitions(): array
    {
        return $this->status->validTransitions();
    }

    /**
     * Scope query to only active memberships.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Membership>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Membership>
     */
    public function scopeActive($query)
    {
        $now = now();

        return $query->where('status', MembershipStatus::Approved)
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $now);
            });
    }

    /**
     * Scope query to only current memberships (latest per customer).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Membership>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Membership>
     */
    public function scopeCurrent($query)
    {
        return $query->whereIn('id', function ($subquery) {
            $subquery->selectRaw('MAX(id)')
                ->from('memberships')
                ->groupBy('customer_id');
        });
    }
}
