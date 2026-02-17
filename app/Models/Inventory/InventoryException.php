<?php

namespace App\Models\Inventory;

use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * InventoryException Model
 *
 * Records inventory exceptions for audit trail.
 * Used to track discrepancies, issues, and exceptional situations
 * in the inventory system.
 *
 * @property string $id
 * @property string $exception_type
 * @property string|null $serialized_bottle_id
 * @property string|null $case_id
 * @property string|null $inbound_batch_id
 * @property string $reason
 * @property string|null $resolution
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property int $created_by
 */
class InventoryException extends Model
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
    protected $table = 'inventory_exceptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'exception_type',
        'serialized_bottle_id',
        'case_id',
        'inbound_batch_id',
        'reason',
        'resolution',
        'resolved_at',
        'resolved_by',
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
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Get the serialized bottle associated with this exception.
     *
     * @return BelongsTo<SerializedBottle, $this>
     */
    public function serializedBottle(): BelongsTo
    {
        return $this->belongsTo(SerializedBottle::class);
    }

    /**
     * Get the case associated with this exception.
     *
     * @return BelongsTo<InventoryCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(InventoryCase::class, 'case_id');
    }

    /**
     * Get the inbound batch associated with this exception.
     *
     * @return BelongsTo<InboundBatch, $this>
     */
    public function inboundBatch(): BelongsTo
    {
        return $this->belongsTo(InboundBatch::class);
    }

    /**
     * Get the user who created this exception.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who resolved this exception.
     *
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Check if the exception has been resolved.
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Check if the exception is pending resolution.
     */
    public function isPending(): bool
    {
        return ! $this->isResolved();
    }

    /**
     * Check if this exception is related to a bottle.
     */
    public function hasBottle(): bool
    {
        return $this->serialized_bottle_id !== null;
    }

    /**
     * Check if this exception is related to a case.
     */
    public function hasCase(): bool
    {
        return $this->case_id !== null;
    }

    /**
     * Check if this exception is related to an inbound batch.
     */
    public function hasInboundBatch(): bool
    {
        return $this->inbound_batch_id !== null;
    }

    /**
     * Get a display label for the exception.
     */
    public function getDisplayLabelAttribute(): string
    {
        return "Exception #{$this->id} ({$this->exception_type})";
    }
}
