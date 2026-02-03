<?php

namespace App\Models\Allocation;

use App\Models\Customer\Customer;
use App\Models\Pim\Format;
use App\Models\Pim\SellableSku;
use App\Models\Pim\WineVariant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property string $lifecycle_state
 * @property bool $tradable
 * @property bool $giftable
 * @property bool $suspended
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
     * Lifecycle state constants (enum will be added in US-016).
     */
    public const STATE_ISSUED = 'issued';

    public const STATE_LOCKED = 'locked';

    public const STATE_REDEEMED = 'redeemed';

    public const STATE_CANCELLED = 'cancelled';

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
        'quantity',
        'lifecycle_state',
        'tradable',
        'giftable',
        'suspended',
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
     * Check if the voucher is in issued state.
     */
    public function isIssued(): bool
    {
        return $this->lifecycle_state === self::STATE_ISSUED;
    }

    /**
     * Check if the voucher is locked (for fulfillment).
     */
    public function isLocked(): bool
    {
        return $this->lifecycle_state === self::STATE_LOCKED;
    }

    /**
     * Check if the voucher has been redeemed.
     */
    public function isRedeemed(): bool
    {
        return $this->lifecycle_state === self::STATE_REDEEMED;
    }

    /**
     * Check if the voucher has been cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->lifecycle_state === self::STATE_CANCELLED;
    }

    /**
     * Check if the voucher is in a terminal state (redeemed or cancelled).
     */
    public function isTerminal(): bool
    {
        return $this->isRedeemed() || $this->isCancelled();
    }

    /**
     * Check if the voucher is currently active (not terminal).
     */
    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    /**
     * Check if the voucher can be traded.
     */
    public function canBeTradedOrTransferred(): bool
    {
        return $this->isIssued()
            && $this->tradable
            && ! $this->suspended;
    }

    /**
     * Check if the voucher can be gifted.
     */
    public function canBeGifted(): bool
    {
        return $this->isIssued()
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
        return match ($this->lifecycle_state) {
            self::STATE_ISSUED => 'Issued',
            self::STATE_LOCKED => 'Locked',
            self::STATE_REDEEMED => 'Redeemed',
            self::STATE_CANCELLED => 'Cancelled',
            default => 'Unknown',
        };
    }

    /**
     * Get the lifecycle state color for UI display.
     */
    public function getLifecycleStateColor(): string
    {
        return match ($this->lifecycle_state) {
            self::STATE_ISSUED => 'success',
            self::STATE_LOCKED => 'warning',
            self::STATE_REDEEMED => 'info',
            self::STATE_CANCELLED => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get the lifecycle state icon for UI display.
     */
    public function getLifecycleStateIcon(): string
    {
        return match ($this->lifecycle_state) {
            self::STATE_ISSUED => 'heroicon-o-ticket',
            self::STATE_LOCKED => 'heroicon-o-lock-closed',
            self::STATE_REDEEMED => 'heroicon-o-check-badge',
            self::STATE_CANCELLED => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
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
}
