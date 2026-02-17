<?php

namespace App\Models\Customer;

use App\Models\AuditLog;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * PaymentPermission Model
 *
 * Defines which payment methods a Customer can use.
 * Created automatically with defaults when Customer becomes active.
 * A Customer has one PaymentPermission record (unique FK).
 *
 * Credit Limit Rules:
 *   - credit_limit = null: No credit allowed (cash/card only)
 *   - credit_limit = value: Maximum credit amount the customer can use
 *   - Credit is required for B2B channel eligibility
 *
 * Bank Transfer Authorization:
 *   - bank_transfer_allowed requires Finance approval (Manager role or higher)
 *   - Defaults to false until explicitly authorized
 *
 * All modifications are logged via the Auditable trait.
 * Only users with canManagePaymentPermissions() can modify these settings.
 *
 * @property string $id
 * @property string $customer_id
 * @property bool $card_allowed
 * @property bool $bank_transfer_allowed
 * @property string|null $credit_limit
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
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
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
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

    /**
     * Check if a user can modify payment permissions.
     * Requires Finance role (Manager or higher).
     */
    public static function canBeModifiedBy(?User $user): bool
    {
        return $user?->canManagePaymentPermissions() ?? false;
    }

    /**
     * Update payment permissions with authorization check.
     * Throws an exception if the user is not authorized.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws AuthorizationException
     */
    public function updateWithAuthorization(array $attributes, ?User $user = null): bool
    {
        $user = $user ?? Auth::user();

        if (! self::canBeModifiedBy($user)) {
            throw new AuthorizationException(
                'Only Finance roles (Manager or higher) can modify payment permissions.'
            );
        }

        return $this->update($attributes);
    }

    /**
     * Enable bank transfer payments with authorization check.
     * Bank transfers require Finance approval.
     *
     * @throws AuthorizationException
     */
    public function authorizeBankTransfer(?User $user = null): void
    {
        $this->updateWithAuthorization(['bank_transfer_allowed' => true], $user);
    }

    /**
     * Revoke bank transfer authorization.
     *
     * @throws AuthorizationException
     */
    public function revokeBankTransfer(?User $user = null): void
    {
        $this->updateWithAuthorization(['bank_transfer_allowed' => false], $user);
    }

    /**
     * Set or update the credit limit with authorization check.
     *
     * @param  float|string|null  $limit  The credit limit (null to remove credit)
     *
     * @throws AuthorizationException
     */
    public function setCreditLimitWithAuthorization(float|string|null $limit, ?User $user = null): void
    {
        $this->updateWithAuthorization(['credit_limit' => $limit], $user);
    }

    /**
     * Get a human-readable explanation of the credit limit status.
     */
    public function getCreditLimitExplanation(): string
    {
        if ($this->credit_limit === null) {
            return 'No credit approved. Customer must pay by card or cash.';
        }

        return 'Credit approved up to '.number_format((float) $this->credit_limit, 2).' EUR.';
    }

    /**
     * Get a human-readable explanation of bank transfer status.
     */
    public function getBankTransferExplanation(): string
    {
        if ($this->bank_transfer_allowed) {
            return 'Bank transfers authorized by Finance.';
        }

        return 'Bank transfers not authorized. Requires Finance approval.';
    }
}
