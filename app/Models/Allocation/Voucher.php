<?php

namespace App\Models\Allocation;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Models\Customer\Customer;
use App\Models\Pim\Format;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineVariant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Voucher Model
 *
 * Represents an atomic customer entitlement for one bottle (or bottle-equivalent).
 * A voucher is the record of what a customer is owed from a specific allocation lineage.
 *
 * Key invariants:
 * - quantity is always 1 (1 voucher = 1 bottle)
 * - allocation_id is immutable after creation (lineage cannot be changed)
 *
 * @property VoucherLifecycleState $lifecycle_state
 * @property bool $tradable
 * @property bool $giftable
 * @property bool $suspended
 * @property string|null $external_trading_reference
 * @property int $quantity
 */
class Voucher extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vouchers';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'allocation_id',
        'wine_variant_id',
        'format_id',
        'sellable_sku_id',
        'case_entitlement_id',
        'quantity',
        'lifecycle_state',
        'tradable',
        'giftable',
        'suspended',
        'external_trading_reference',
        'sale_reference',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'lifecycle_state' => VoucherLifecycleState::class,
            'tradable' => 'boolean',
            'giftable' => 'boolean',
            'suspended' => 'boolean',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Set created_by on creation
        static::creating(function (Voucher $voucher): void {
            if (Auth::check() && empty($voucher->created_by)) {
                $voucher->created_by = Auth::id();
            }

            // Enforce quantity = 1 invariant
            $voucher->quantity = 1;
        });

        // Enforce quantity = 1 invariant on updates
        static::saving(function (Voucher $voucher): void {
            if ($voucher->quantity !== 1) {
                throw new \InvalidArgumentException(
                    'Voucher quantity must always be 1. One voucher represents one bottle.'
                );
            }
        });

        // Prevent modification of allocation_id after creation
        static::updating(function (Voucher $voucher): void {
            if ($voucher->isDirty('allocation_id')) {
                throw new \InvalidArgumentException(
                    'Allocation lineage cannot be modified after voucher creation. This is an immutable field.'
                );
            }
        });
    }

    /**
     * Get the customer who owns this voucher.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the allocation this voucher was issued from.
     *
     * @return BelongsTo<Allocation, $this>
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class);
    }

    /**
     * Get the wine variant for this voucher.
     *
     * @return BelongsTo<WineVariant, $this>
     */
    public function wineVariant(): BelongsTo
    {
        return $this->belongsTo(WineVariant::class);
    }

    /**
     * Get the format for this voucher (bottle size).
     *
     * @return BelongsTo<Format, $this>
     */
    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class);
    }

    /**
     * Get the sellable SKU this voucher was sold as (if applicable).
     *
     * @return BelongsTo<SellableSku, $this>
     */
    public function sellableSku(): BelongsTo
    {
        return $this->belongsTo(SellableSku::class);
    }

    /**
     * Get the case entitlement this voucher belongs to (if part of a case).
     *
     * @return BelongsTo<CaseEntitlement, $this>
     */
    public function caseEntitlement(): BelongsTo
    {
        return $this->belongsTo(CaseEntitlement::class);
    }

    /**
     * Get the user who created this voucher.
     *
     * @return BelongsTo<\App\Models\User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Get the audit logs for this voucher.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Get all transfers for this voucher.
     *
     * @return HasMany<VoucherTransfer, $this>
     */
    public function voucherTransfers(): HasMany
    {
        return $this->hasMany(VoucherTransfer::class);
    }

    /**
     * Get pending transfers for this voucher.
     *
     * @return HasMany<VoucherTransfer, $this>
     */
    public function pendingTransfers(): HasMany
    {
        return $this->hasMany(VoucherTransfer::class)->pending();
    }

    /**
     * Check if the voucher has a pending transfer.
     */
    public function hasPendingTransfer(): bool
    {
        return $this->pendingTransfers()->exists();
    }

    /**
     * Get the current pending transfer, if any.
     */
    public function getPendingTransfer(): ?VoucherTransfer
    {
        return $this->pendingTransfers()->first();
    }

    /**
     * Check if the voucher is in issued state.
     */
    public function isIssued(): bool
    {
        return $this->lifecycle_state === VoucherLifecycleState::Issued;
    }

    /**
     * Check if the voucher is locked (for fulfillment).
     */
    public function isLocked(): bool
    {
        return $this->lifecycle_state === VoucherLifecycleState::Locked;
    }

    /**
     * Check if the voucher has been redeemed.
     */
    public function isRedeemed(): bool
    {
        return $this->lifecycle_state === VoucherLifecycleState::Redeemed;
    }

    /**
     * Check if the voucher has been cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->lifecycle_state === VoucherLifecycleState::Cancelled;
    }

    /**
     * Check if the voucher is in a terminal state (redeemed or cancelled).
     */
    public function isTerminal(): bool
    {
        return $this->lifecycle_state->isTerminal();
    }

    /**
     * Check if the voucher is currently active (not terminal).
     */
    public function isActive(): bool
    {
        return $this->lifecycle_state->isActive();
    }

    /**
     * Check if the voucher can be traded.
     */
    public function canBeTradedOrTransferred(): bool
    {
        return $this->lifecycle_state->allowsTrading()
            && $this->tradable
            && ! $this->suspended;
    }

    /**
     * Check if the voucher can be gifted.
     */
    public function canBeGifted(): bool
    {
        return $this->lifecycle_state->allowsTrading()
            && $this->giftable
            && ! $this->suspended;
    }

    /**
     * Get a display label for the bottle SKU.
     */
    public function getBottleSkuLabel(): string
    {
        $wineVariant = $this->wineVariant;
        $format = $this->format;

        if (! $wineVariant || ! $format) {
            return 'Unknown';
        }

        $wineMaster = $wineVariant->wineMaster;
        $wineName = $wineMaster ? $wineMaster->name : 'Unknown Wine';
        $vintage = $wineVariant->vintage_year ?? 'NV';
        $formatLabel = $format->volume_ml.'ml';

        return "{$wineName} {$vintage} - {$formatLabel}";
    }

    /**
     * Get the lifecycle state label for UI display.
     */
    public function getLifecycleStateLabel(): string
    {
        return $this->lifecycle_state->label();
    }

    /**
     * Get the lifecycle state color for UI display.
     */
    public function getLifecycleStateColor(): string
    {
        return $this->lifecycle_state->color();
    }

    /**
     * Get the lifecycle state icon for UI display.
     */
    public function getLifecycleStateIcon(): string
    {
        return $this->lifecycle_state->icon();
    }

    /**
     * Check if a transition to the given state is allowed.
     */
    public function canTransitionTo(VoucherLifecycleState $target): bool
    {
        return $this->lifecycle_state->canTransitionTo($target);
    }

    /**
     * Get the allowed transitions from the current state.
     *
     * @return list<VoucherLifecycleState>
     */
    public function getAllowedTransitions(): array
    {
        return $this->lifecycle_state->allowedTransitions();
    }

    /**
     * Get behavioral flags as an array.
     *
     * @return array<string, bool>
     */
    public function getBehavioralFlags(): array
    {
        return [
            'tradable' => $this->tradable,
            'giftable' => $this->giftable,
            'suspended' => $this->suspended,
        ];
    }

    /**
     * Check if the voucher is part of a case entitlement.
     */
    public function isPartOfCase(): bool
    {
        return $this->case_entitlement_id !== null;
    }

    /**
     * Check if the voucher is part of an intact case.
     */
    public function isPartOfIntactCase(): bool
    {
        if (! $this->isPartOfCase()) {
            return false;
        }

        $caseEntitlement = $this->caseEntitlement;

        return $caseEntitlement !== null && $caseEntitlement->isIntact();
    }

    /**
     * Check if the voucher is suspended for external trading.
     */
    public function isSuspendedForTrading(): bool
    {
        return $this->suspended && $this->external_trading_reference !== null;
    }

    /**
     * Check if the voucher has an external trading reference.
     */
    public function hasExternalTradingReference(): bool
    {
        return $this->external_trading_reference !== null;
    }

    /**
     * Get the suspension reason display text.
     */
    public function getSuspensionReason(): string
    {
        if (! $this->suspended) {
            return '';
        }

        if ($this->isSuspendedForTrading()) {
            return 'Suspended for external trading';
        }

        return 'Suspended (manual)';
    }
}
