<?php

namespace App\Models\Allocation;

use App\Enums\Allocation\VoucherTransferStatus;
use App\Models\AuditLog;
use App\Models\Customer\Customer;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * VoucherTransfer Model
 *
 * Tracks the transfer of vouchers between customers.
 * A transfer does NOT create a new voucher - it only changes the holder.
 * A transfer does NOT consume allocation - it's purely a customer-to-customer operation.
 *
 * Key behaviors:
 * - Only one pending transfer per voucher at a time
 * - Transfer can be accepted, cancelled, or expired
 * - On accept, the voucher's customer_id is updated to the recipient
 *
 * @property VoucherTransferStatus $status
 * @property Carbon $initiated_at
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $cancelled_at
 */
class VoucherTransfer extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'voucher_transfers';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'voucher_id',
        'from_customer_id',
        'to_customer_id',
        'status',
        'initiated_at',
        'expires_at',
        'accepted_at',
        'cancelled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VoucherTransferStatus::class,
            'initiated_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Get the voucher being transferred.
     *
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Get the customer who initiated the transfer (current holder).
     *
     * @return BelongsTo<Customer, $this>
     */
    public function fromCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'from_customer_id');
    }

    /**
     * Get the customer who will receive the voucher.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function toCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'to_customer_id');
    }

    /**
     * Get the audit logs for this transfer.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Scope: Get only pending transfers.
     *
     * @param  Builder<VoucherTransfer>  $query
     * @return Builder<VoucherTransfer>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', VoucherTransferStatus::Pending);
    }

    /**
     * Scope: Get transfers that need to be expired.
     *
     * @param  Builder<VoucherTransfer>  $query
     * @return Builder<VoucherTransfer>
     */
    public function scopeNeedsExpiration(Builder $query): Builder
    {
        return $query
            ->where('status', VoucherTransferStatus::Pending)
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope: Get transfers for a specific voucher.
     *
     * @param  Builder<VoucherTransfer>  $query
     * @return Builder<VoucherTransfer>
     */
    public function scopeForVoucher(Builder $query, string $voucherId): Builder
    {
        return $query->where('voucher_id', $voucherId);
    }

    /**
     * Scope: Get transfers where the given customer is the sender.
     *
     * @param  Builder<VoucherTransfer>  $query
     * @return Builder<VoucherTransfer>
     */
    public function scopeFromCustomer(Builder $query, string $customerId): Builder
    {
        return $query->where('from_customer_id', $customerId);
    }

    /**
     * Scope: Get transfers where the given customer is the recipient.
     *
     * @param  Builder<VoucherTransfer>  $query
     * @return Builder<VoucherTransfer>
     */
    public function scopeToCustomer(Builder $query, string $customerId): Builder
    {
        return $query->where('to_customer_id', $customerId);
    }

    /**
     * Check if the transfer is pending.
     */
    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    /**
     * Check if the transfer was accepted.
     */
    public function isAccepted(): bool
    {
        return $this->status->isAccepted();
    }

    /**
     * Check if the transfer is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Check if the transfer can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    /**
     * Check if the transfer can be accepted.
     */
    public function canBeAccepted(): bool
    {
        return $this->status->canBeAccepted();
    }

    /**
     * Check if the transfer has expired (past expires_at but not yet marked as expired).
     */
    public function hasExpired(): bool
    {
        return $this->isPending() && $this->expires_at->isPast();
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
     * Get the status description for UI display.
     */
    public function getStatusDescription(): string
    {
        return $this->status->description();
    }

    /**
     * Check if a transition to the given status is allowed.
     */
    public function canTransitionTo(VoucherTransferStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    /**
     * Get the allowed transitions from the current status.
     *
     * @return list<VoucherTransferStatus>
     */
    public function getAllowedTransitions(): array
    {
        return $this->status->allowedTransitions();
    }

    /**
     * Check if transfer acceptance is blocked because the voucher is locked.
     *
     * This is a race condition scenario: the voucher was locked for fulfillment
     * while the transfer was pending. The transfer cannot be accepted until
     * the voucher is unlocked.
     */
    public function isAcceptanceBlockedByLock(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        return $this->voucher?->isLocked() ?? false;
    }

    /**
     * Check if the transfer can currently be accepted.
     *
     * This is a stricter check than canBeAccepted() as it also considers
     * the voucher's current state (e.g., locked, suspended, terminal).
     */
    public function canCurrentlyBeAccepted(): bool
    {
        // Basic status check
        if (! $this->canBeAccepted()) {
            return false;
        }

        // Cannot accept if expired
        if ($this->hasExpired()) {
            return false;
        }

        $voucher = $this->voucher;
        if (! $voucher) {
            return false;
        }

        // Cannot accept if voucher is locked
        if ($voucher->isLocked()) {
            return false;
        }

        // Cannot accept if voucher is suspended
        if ($voucher->suspended) {
            return false;
        }

        // Cannot accept if voucher is in terminal state
        if ($voucher->isTerminal()) {
            return false;
        }

        return true;
    }

    /**
     * Get the reason why transfer acceptance is blocked, if any.
     *
     * Returns null if the transfer can be accepted.
     */
    public function getAcceptanceBlockedReason(): ?string
    {
        if (! $this->isPending()) {
            return "Transfer is in status '{$this->status->label()}' and cannot be accepted.";
        }

        if ($this->hasExpired()) {
            return 'Transfer has expired and cannot be accepted.';
        }

        $voucher = $this->voucher;
        if (! $voucher) {
            return 'Voucher not found.';
        }

        if ($voucher->isLocked()) {
            return 'Locked during transfer - acceptance blocked. The voucher has been locked for fulfillment while the transfer was pending.';
        }

        if ($voucher->suspended) {
            return 'Voucher is suspended. Transfer acceptance is blocked until the voucher is reactivated.';
        }

        if ($voucher->isTerminal()) {
            return "Voucher is in terminal state '{$voucher->lifecycle_state->label()}' and transfer cannot be accepted.";
        }

        return null;
    }
}
