<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
            default => 'gray',
        };
    }
}
