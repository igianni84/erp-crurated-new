<?php

namespace App\Models\Customer;

use App\Enums\Customer\BlockStatus;
use App\Enums\Customer\BlockType;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * OperationalBlock Model
 *
 * Represents an operational block that prevents specific operations
 * for a Customer or Account. Blocks are polymorphic and can be applied
 * to either entity.
 *
 * @property string $id
 * @property string $blockable_type
 * @property string $blockable_id
 * @property BlockType $block_type
 * @property string $reason
 * @property int|null $applied_by
 * @property BlockStatus $status
 * @property \Carbon\Carbon|null $removed_at
 * @property int|null $removed_by
 * @property string|null $removal_reason
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class OperationalBlock extends Model
{
    use Auditable;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'operational_blocks';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'blockable_type',
        'blockable_id',
        'block_type',
        'reason',
        'applied_by',
        'status',
        'removed_at',
        'removed_by',
        'removal_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'block_type' => BlockType::class,
            'status' => BlockStatus::class,
            'removed_at' => 'datetime',
        ];
    }

    /**
     * Get the parent blockable model (Customer or Account).
     *
     * @return MorphTo<Model, $this>
     */
    public function blockable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who applied this block.
     *
     * @return BelongsTo<User, $this>
     */
    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    /**
     * Get the user who removed this block.
     *
     * @return BelongsTo<User, $this>
     */
    public function removedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    /**
     * Get the audit logs for this block.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if this block is active.
     */
    public function isActive(): bool
    {
        return $this->status === BlockStatus::Active;
    }

    /**
     * Check if this block has been removed.
     */
    public function isRemoved(): bool
    {
        return $this->status === BlockStatus::Removed;
    }

    /**
     * Remove this block with a reason.
     */
    public function remove(User $removedBy, string $reason): void
    {
        $this->update([
            'status' => BlockStatus::Removed,
            'removed_at' => now(),
            'removed_by' => $removedBy->id,
            'removal_reason' => $reason,
        ]);
    }

    /**
     * Get the block type label.
     */
    public function getBlockTypeLabel(): string
    {
        return $this->block_type->label();
    }

    /**
     * Get the block type color.
     */
    public function getBlockTypeColor(): string
    {
        return $this->block_type->color();
    }

    /**
     * Get the block type icon.
     */
    public function getBlockTypeIcon(): string
    {
        return $this->block_type->icon();
    }

    /**
     * Get the status label.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get the status color.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status icon.
     */
    public function getStatusIcon(): string
    {
        return $this->status->icon();
    }

    /**
     * Check if this block type affects eligibility.
     */
    public function affectsEligibility(): bool
    {
        return $this->block_type->affectsEligibility();
    }

    /**
     * Check if this is a critical block.
     */
    public function isCritical(): bool
    {
        return $this->block_type->isCritical();
    }

    /**
     * Scope a query to only include active blocks.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('status', BlockStatus::Active);
    }

    /**
     * Scope a query to only include removed blocks.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeRemoved($query)
    {
        return $query->where('status', BlockStatus::Removed);
    }

    /**
     * Scope a query to only include blocks of a specific type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOfType($query, BlockType $type)
    {
        return $query->where('block_type', $type);
    }

    /**
     * Scope a query to only include critical blocks.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCritical($query)
    {
        return $query->whereIn('block_type', [BlockType::Payment, BlockType::Compliance]);
    }
}
