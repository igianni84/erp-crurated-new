<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * AuditLog Model
 *
 * Immutable audit log record for tracking changes to models.
 * Audit logs cannot be updated or deleted once created - this ensures
 * a complete and tamper-proof audit trail.
 *
 * @property string $id
 * @property string $auditable_type
 * @property string $auditable_id
 * @property string $event
 * @property array|null $old_values
 * @property array|null $new_values
 * @property int|null $user_id
 * @property \Carbon\Carbon $created_at
 */
class AuditLog extends Model
{
    use HasUuid;

    /**
     * Event types for audit logs.
     */
    public const EVENT_CREATED = 'created';

    public const EVENT_UPDATED = 'updated';

    public const EVENT_DELETED = 'deleted';

    public const EVENT_STATUS_CHANGE = 'status_change';

    public const EVENT_LIFECYCLE_CHANGE = 'lifecycle_change';

    public const EVENT_FLAG_CHANGE = 'flag_change';

    public const EVENT_VOUCHER_ISSUED = 'voucher_issued';

    public const EVENT_VOUCHER_SUSPENDED = 'voucher_suspended';

    public const EVENT_VOUCHER_REACTIVATED = 'voucher_reactivated';

    public const EVENT_TRANSFER_INITIATED = 'transfer_initiated';

    public const EVENT_TRANSFER_ACCEPTED = 'transfer_accepted';

    public const EVENT_TRANSFER_CANCELLED = 'transfer_cancelled';

    public const EVENT_TRANSFER_EXPIRED = 'transfer_expired';

    public const EVENT_TRADING_SUSPENDED = 'trading_suspended';

    public const EVENT_TRADING_COMPLETED = 'trading_completed';

    public const EVENT_DUPLICATE_VOUCHER_REQUEST = 'duplicate_voucher_request';

    public const EVENT_VOUCHER_QUARANTINED = 'voucher_quarantined';

    public const EVENT_VOUCHER_UNQUARANTINED = 'voucher_unquarantined';

    /**
     * Indicates if the model should be timestamped.
     * We only use created_at for immutability.
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'user_id',
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
     * Get the auditable model (polymorphic).
     *
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who performed the action.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the human-readable event label.
     */
    public function getEventLabel(): string
    {
        return match ($this->event) {
            self::EVENT_CREATED => 'Created',
            self::EVENT_UPDATED => 'Updated',
            self::EVENT_DELETED => 'Deleted',
            self::EVENT_STATUS_CHANGE => 'Status Changed',
            self::EVENT_LIFECYCLE_CHANGE => 'Lifecycle Changed',
            self::EVENT_FLAG_CHANGE => 'Flag Changed',
            self::EVENT_VOUCHER_ISSUED => 'Voucher Issued',
            self::EVENT_VOUCHER_SUSPENDED => 'Voucher Suspended',
            self::EVENT_VOUCHER_REACTIVATED => 'Voucher Reactivated',
            self::EVENT_TRANSFER_INITIATED => 'Transfer Initiated',
            self::EVENT_TRANSFER_ACCEPTED => 'Transfer Accepted',
            self::EVENT_TRANSFER_CANCELLED => 'Transfer Cancelled',
            self::EVENT_TRANSFER_EXPIRED => 'Transfer Expired',
            self::EVENT_TRADING_SUSPENDED => 'Suspended for Trading',
            self::EVENT_TRADING_COMPLETED => 'Trading Completed',
            self::EVENT_DUPLICATE_VOUCHER_REQUEST => 'Duplicate Request Detected',
            self::EVENT_VOUCHER_QUARANTINED => 'Voucher Quarantined',
            self::EVENT_VOUCHER_UNQUARANTINED => 'Voucher Unquarantined',
            default => ucfirst((string) $this->event),
        };
    }

    /**
     * Get the event icon for display.
     */
    public function getEventIcon(): string
    {
        return match ($this->event) {
            self::EVENT_CREATED => 'heroicon-o-plus-circle',
            self::EVENT_UPDATED => 'heroicon-o-pencil-square',
            self::EVENT_DELETED => 'heroicon-o-trash',
            self::EVENT_STATUS_CHANGE => 'heroicon-o-arrow-path',
            self::EVENT_LIFECYCLE_CHANGE => 'heroicon-o-arrow-path',
            self::EVENT_FLAG_CHANGE => 'heroicon-o-flag',
            self::EVENT_VOUCHER_ISSUED => 'heroicon-o-ticket',
            self::EVENT_VOUCHER_SUSPENDED => 'heroicon-o-pause-circle',
            self::EVENT_VOUCHER_REACTIVATED => 'heroicon-o-play-circle',
            self::EVENT_TRANSFER_INITIATED => 'heroicon-o-arrow-right-start-on-rectangle',
            self::EVENT_TRANSFER_ACCEPTED => 'heroicon-o-check-circle',
            self::EVENT_TRANSFER_CANCELLED => 'heroicon-o-x-circle',
            self::EVENT_TRANSFER_EXPIRED => 'heroicon-o-clock',
            self::EVENT_TRADING_SUSPENDED => 'heroicon-o-currency-dollar',
            self::EVENT_TRADING_COMPLETED => 'heroicon-o-banknotes',
            self::EVENT_DUPLICATE_VOUCHER_REQUEST => 'heroicon-o-document-duplicate',
            self::EVENT_VOUCHER_QUARANTINED => 'heroicon-o-exclamation-triangle',
            self::EVENT_VOUCHER_UNQUARANTINED => 'heroicon-o-check-badge',
            default => 'heroicon-o-document',
        };
    }

    /**
     * Get the event color for display.
     */
    public function getEventColor(): string
    {
        return match ($this->event) {
            self::EVENT_CREATED => 'success',
            self::EVENT_UPDATED => 'info',
            self::EVENT_DELETED => 'danger',
            self::EVENT_STATUS_CHANGE => 'warning',
            self::EVENT_LIFECYCLE_CHANGE => 'warning',
            self::EVENT_FLAG_CHANGE => 'info',
            self::EVENT_VOUCHER_ISSUED => 'success',
            self::EVENT_VOUCHER_SUSPENDED => 'danger',
            self::EVENT_VOUCHER_REACTIVATED => 'success',
            self::EVENT_TRANSFER_INITIATED => 'info',
            self::EVENT_TRANSFER_ACCEPTED => 'success',
            self::EVENT_TRANSFER_CANCELLED => 'danger',
            self::EVENT_TRANSFER_EXPIRED => 'gray',
            self::EVENT_TRADING_SUSPENDED => 'warning',
            self::EVENT_TRADING_COMPLETED => 'success',
            self::EVENT_DUPLICATE_VOUCHER_REQUEST => 'warning',
            self::EVENT_VOUCHER_QUARANTINED => 'danger',
            self::EVENT_VOUCHER_UNQUARANTINED => 'success',
            default => 'gray',
        };
    }

    /**
     * Boot the model.
     * Prevents updates and deletions to ensure audit log immutability.
     */
    protected static function booted(): void
    {
        static::updating(function (): bool {
            throw new \RuntimeException('Audit logs are immutable and cannot be updated.');
        });

        static::deleting(function (): bool {
            throw new \RuntimeException('Audit logs are immutable and cannot be deleted.');
        });
    }

    /**
     * Check if the audit log has old values.
     */
    public function hasOldValues(): bool
    {
        return ! empty($this->old_values);
    }

    /**
     * Check if the audit log has new values.
     */
    public function hasNewValues(): bool
    {
        return ! empty($this->new_values);
    }

    /**
     * Get a summary of the changes for display.
     */
    public function getChangesSummary(): string
    {
        if ($this->event === self::EVENT_CREATED) {
            return 'Record created';
        }

        if ($this->event === self::EVENT_DELETED) {
            return 'Record deleted';
        }

        if (! $this->hasNewValues()) {
            return 'No changes recorded';
        }

        $changes = [];
        foreach ($this->new_values as $field => $newValue) {
            $oldValue = $this->old_values[$field] ?? null;
            $oldDisplay = $oldValue === null ? 'null' : (is_array($oldValue) ? json_encode($oldValue) : (string) $oldValue);
            $newDisplay = $newValue === null ? 'null' : (is_array($newValue) ? json_encode($newValue) : (string) $newValue);
            $changes[] = "{$field}: {$oldDisplay} → {$newDisplay}";
        }

        return implode(', ', $changes);
    }
}
