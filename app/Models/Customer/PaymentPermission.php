<?php

namespace App\Models\Customer;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * PaymentPermission Model
 *
 * Defines which payment methods a Customer can use.
 * Created automatically with defaults when Customer becomes active.
 * A Customer has one PaymentPermission record (unique FK).
 *
 * @property string $id
 * @property string $customer_id
 * @property bool $card_allowed
 * @property bool $bank_transfer_allowed
 * @property string|null $credit_limit
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PaymentPermission extends Model
{
    use Auditable;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payment_permissions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'card_allowed',
        'bank_transfer_allowed',
        'credit_limit',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'card_allowed' => 'boolean',
            'bank_transfer_allowed' => 'boolean',
            'credit_limit' => 'decimal:2',
        ];
    }

    /**
     * Get the customer that owns this payment permission.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the audit logs for this payment permission.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if card payments are allowed.
     */
    public function isCardAllowed(): bool
    {
        return $this->card_allowed;
    }

    /**
     * Check if bank transfer payments are allowed.
     */
    public function isBankTransferAllowed(): bool
    {
        return $this->bank_transfer_allowed;
    }

    /**
     * Check if credit is approved (credit_limit is set).
     */
    public function hasCreditApproved(): bool
    {
        return $this->credit_limit !== null;
    }

    /**
     * Get the credit limit as a float, or null if not set.
     */
    public function getCreditLimitAmount(): ?float
    {
        return $this->credit_limit !== null ? (float) $this->credit_limit : null;
    }

    /**
     * Check if a specific payment method is allowed.
     */
    public function isPaymentMethodAllowed(string $method): bool
    {
        if ($method === 'card') {
            return $this->card_allowed;
        }

        if ($method === 'bank_transfer') {
            return $this->bank_transfer_allowed;
        }

        return false;
    }

    /**
     * Check if any payment method is blocked.
     * Used by EligibilityEngine to check for payment restrictions.
     */
    public function hasPaymentBlock(): bool
    {
        // Customer has a payment block if card payments are not allowed
        // Card is the default payment method, so blocking it affects all channels
        return ! $this->card_allowed;
    }

    /**
     * Enable card payments.
     */
    public function allowCard(): void
    {
        $this->update(['card_allowed' => true]);
    }

    /**
     * Disable card payments.
     */
    public function blockCard(): void
    {
        $this->update(['card_allowed' => false]);
    }

    /**
     * Enable bank transfer payments.
     */
    public function allowBankTransfer(): void
    {
        $this->update(['bank_transfer_allowed' => true]);
    }

    /**
     * Disable bank transfer payments.
     */
    public function blockBankTransfer(): void
    {
        $this->update(['bank_transfer_allowed' => false]);
    }

    /**
     * Set the credit limit.
     *
     * @param  float|string|null  $limit  The credit limit (null to remove)
     */
    public function setCreditLimit(float|string|null $limit): void
    {
        $this->update(['credit_limit' => $limit]);
    }

    /**
     * Remove the credit limit (set to null).
     */
    public function removeCreditLimit(): void
    {
        $this->update(['credit_limit' => null]);
    }

    /**
     * Get a summary of the current payment permissions.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return [
            'card_allowed' => $this->card_allowed,
            'bank_transfer_allowed' => $this->bank_transfer_allowed,
            'credit_limit' => $this->credit_limit,
            'has_credit_approved' => $this->hasCreditApproved(),
            'has_payment_block' => $this->hasPaymentBlock(),
        ];
    }
}
