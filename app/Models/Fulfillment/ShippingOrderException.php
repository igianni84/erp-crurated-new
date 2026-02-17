<?php

namespace App\Models\Fulfillment;

use App\Enums\Fulfillment\ShippingOrderExceptionStatus;
use App\Enums\Fulfillment\ShippingOrderExceptionType;
use App\Models\AuditLog;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ShippingOrderException Model
 *
 * Records fulfillment exceptions for audit and resolution.
 * Exceptions can be linked to a specific line or be order-level.
 *
 * @property string $id
 * @property string $shipping_order_id
 * @property string|null $shipping_order_line_id
 * @property ShippingOrderExceptionType $exception_type
 * @property string $description
 * @property string|null $resolution_path
 * @property ShippingOrderExceptionStatus $status
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class ShippingOrderException extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'shipping_order_exceptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'shipping_order_id',
        'shipping_order_line_id',
        'exception_type',
        'description',
        'resolution_path',
        'status',
        'resolved_at',
        'resolved_by',
        'created_by',
        'updated_by',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ShippingOrderExceptionStatus::Active,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exception_type' => ShippingOrderExceptionType::class,
            'status' => ShippingOrderExceptionStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Get the shipping order this exception belongs to.
     *
     * @return BelongsTo<ShippingOrder, $this>
     */
    public function shippingOrder(): BelongsTo
    {
        return $this->belongsTo(ShippingOrder::class);
    }

    /**
     * Get the shipping order line this exception is linked to (if any).
     *
     * @return BelongsTo<ShippingOrderLine, $this>
     */
    public function shippingOrderLine(): BelongsTo
    {
        return $this->belongsTo(ShippingOrderLine::class);
    }

    /**
     * Get the user who resolved this exception.
     *
     * @return BelongsTo<User, $this>
     */
    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get the user who created this exception.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the audit logs for this exception.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Check if this exception is active.
     */
    public function isActive(): bool
    {
        return $this->status === ShippingOrderExceptionStatus::Active;
    }

    /**
     * Check if this exception is resolved.
     */
    public function isResolved(): bool
    {
        return $this->status === ShippingOrderExceptionStatus::Resolved;
    }

    /**
     * Check if this exception is linked to a specific line.
     */
    public function isLineLevelException(): bool
    {
        return $this->shipping_order_line_id !== null;
    }

    /**
     * Check if this exception blocks SO progression.
     */
    public function isBlocking(): bool
    {
        return $this->isActive() && $this->exception_type->isBlocking();
    }

    /**
     * Get the exception type label for UI display.
     */
    public function getExceptionTypeLabel(): string
    {
        return $this->exception_type->label();
    }

    /**
     * Get the exception type color for UI display.
     */
    public function getExceptionTypeColor(): string
    {
        return $this->exception_type->color();
    }

    /**
     * Get the exception type icon for UI display.
     */
    public function getExceptionTypeIcon(): string
    {
        return $this->exception_type->icon();
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
}
