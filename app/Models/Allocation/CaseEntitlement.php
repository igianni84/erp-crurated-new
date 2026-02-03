<?php

namespace App\Models\Allocation;

use App\Enums\Allocation\CaseEntitlementStatus;
use App\Models\Customer\Customer;
use App\Models\Pim\SellableSku;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * CaseEntitlement Model
 *
 * Groups vouchers when a customer buys a fixed case.
 * A case entitlement represents ownership of a complete case with multiple bottles.
 *
 * Key behaviors:
 * - Status becomes 'broken' irreversibly when a voucher is transferred, traded, or redeemed individually
 * - Broken cases still have valid vouchers, but they behave as loose bottles
 *
 * @property CaseEntitlementStatus $status
 * @property Carbon|null $broken_at
 * @property string|null $broken_reason
 * @property-read Collection<int, Voucher> $vouchers
 */
class CaseEntitlement extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'case_entitlements';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'sellable_sku_id',
        'status',
        'broken_at',
        'broken_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CaseEntitlementStatus::class,
            'broken_at' => 'datetime',
        ];
    }

    /**
     * Get the customer who owns this case entitlement.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the sellable SKU (the case that was sold).
     *
     * @return BelongsTo<SellableSku, $this>
     */
    public function sellableSku(): BelongsTo
    {
        return $this->belongsTo(SellableSku::class);
    }

    /**
     * Get the vouchers that belong to this case entitlement.
     *
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * Get the audit logs for this case entitlement.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if the case is intact.
     */
    public function isIntact(): bool
    {
        return $this->status->isIntact();
    }

    /**
     * Check if the case is broken.
     */
    public function isBroken(): bool
    {
        return $this->status->isBroken();
    }

    /**
     * Check if the case can be broken.
     */
    public function canBeBroken(): bool
    {
        return $this->status->canBeBroken();
    }

    /**
     * Get the number of vouchers in this case.
     */
    public function getVouchersCount(): int
    {
        return $this->vouchers()->count();
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
     * Check if all vouchers in the case are still held by the same customer
     * and none have been redeemed.
     */
    public function checkIntegrity(): bool
    {
        if ($this->isBroken()) {
            return false;
        }

        $vouchers = $this->vouchers()->get();

        if ($vouchers->isEmpty()) {
            return true;
        }

        $originalCustomerId = $this->customer_id;

        foreach ($vouchers as $voucher) {
            // Check if voucher has been transferred to a different customer
            if ($voucher->customer_id !== $originalCustomerId) {
                return false;
            }

            // Check if voucher has been redeemed
            if ($voucher->isRedeemed()) {
                return false;
            }
        }

        return true;
    }
}
