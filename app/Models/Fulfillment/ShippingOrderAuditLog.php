<?php

namespace App\Models\Fulfillment;

use App\Models\User;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * ShippingOrderAuditLog Model
 *
 * Immutable audit log for all Shipping Order actions.
 * Once created, audit logs cannot be modified or deleted.
 *
 * NOTE: This model does NOT use SoftDeletes as audit logs must be immutable.
 *
 * @property string $id
 * @property string $shipping_order_id
 * @property string $event_type
 * @property string $description
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property int|null $user_id
 * @property Carbon $created_at
 */
class ShippingOrderAuditLog extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * Disable timestamps since we only have created_at.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'shipping_order_audit_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'shipping_order_id',
        'event_type',
        'description',
        'old_values',
        'new_values',
        'user_id',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Prevent updates - audit logs are immutable
        static::updating(function (): void {
            throw new InvalidArgumentException(
                'Audit logs are immutable and cannot be updated.'
            );
        });

        // Prevent deletes - audit logs are immutable
        static::deleting(function (): void {
            throw new InvalidArgumentException(
                'Audit logs are immutable and cannot be deleted.'
            );
        });
    }

    /**
     * Get the shipping order this audit log belongs to.
     *
     * @return BelongsTo<ShippingOrder, $this>
     */
    public function shippingOrder(): BelongsTo
    {
        return $this->belongsTo(ShippingOrder::class);
    }

    /**
     * Get the user who triggered this event.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this is a system event (no user).
     */
    public function isSystemEvent(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Check if this event has change tracking data.
     */
    public function hasChangeTracking(): bool
    {
        return $this->old_values !== null || $this->new_values !== null;
    }
}
