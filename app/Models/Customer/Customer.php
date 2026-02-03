<?php

namespace App\Models\Customer;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Customer Model
 *
 * Placeholder model for Module A (Allocations/Vouchers).
 * This will be enhanced by Module K (Parties, Customers & Eligibility) implementation.
 *
 * @property string $name
 * @property string $email
 * @property string $status
 */
class Customer extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customers';

    /**
     * Status constants.
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CLOSED = 'closed';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    /**
     * Get the vouchers owned by this customer.
     *
     * @return HasMany<\App\Models\Allocation\Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(\App\Models\Allocation\Voucher::class);
    }

    /**
     * Get the case entitlements owned by this customer.
     *
     * @return HasMany<\App\Models\Allocation\CaseEntitlement, $this>
     */
    public function caseEntitlements(): HasMany
    {
        return $this->hasMany(\App\Models\Allocation\CaseEntitlement::class);
    }

    /**
     * Check if the customer is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the customer is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Check if the customer is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
