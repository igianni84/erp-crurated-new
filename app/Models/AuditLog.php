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

    public const EVENT_DUPLICATE_VOUCHER_REQUEST = 'duplicate_voucher_request';

    public const EVENT_VOUCHER_QUARANTINED = 'voucher_quarantined';

    public const EVENT_VOUCHER_UNQUARANTINED = 'voucher_unquarantined';

    // Inventory Module (Module B) event types
    public const EVENT_BOTTLE_SERIALIZED = 'bottle_serialized';

    public const EVENT_BOTTLE_STATE_CHANGE = 'bottle_state_change';

    public const EVENT_BOTTLE_LOCATION_CHANGE = 'bottle_location_change';

    public const EVENT_BOTTLE_CUSTODY_CHANGE = 'bottle_custody_change';

    public const EVENT_BOTTLE_DESTROYED = 'bottle_destroyed';

    public const EVENT_BOTTLE_MISSING = 'bottle_missing';

    public const EVENT_BOTTLE_MIS_SERIALIZED = 'bottle_mis_serialized';

    public const EVENT_CASE_CREATED = 'case_created';

    public const EVENT_CASE_LOCATION_CHANGE = 'case_location_change';

    public const EVENT_CASE_BROKEN = 'case_broken';

    public const EVENT_CASE_BOTTLE_ADDED = 'case_bottle_added';

    public const EVENT_CASE_BOTTLE_REMOVED = 'case_bottle_removed';

    public const EVENT_BATCH_CREATED = 'batch_created';

    public const EVENT_BATCH_QUANTITY_UPDATE = 'batch_quantity_update';

    public const EVENT_BATCH_DISCREPANCY_FLAGGED = 'batch_discrepancy_flagged';

    public const EVENT_BATCH_DISCREPANCY_RESOLVED = 'batch_discrepancy_resolved';

    public const EVENT_BATCH_SERIALIZATION_STARTED = 'batch_serialization_started';

    public const EVENT_BATCH_SERIALIZATION_COMPLETED = 'batch_serialization_completed';

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
            // Inventory Module events
            self::EVENT_BOTTLE_SERIALIZED => 'Bottle Serialized',
            self::EVENT_BOTTLE_STATE_CHANGE => 'State Changed',
            self::EVENT_BOTTLE_LOCATION_CHANGE => 'Location Changed',
            self::EVENT_BOTTLE_CUSTODY_CHANGE => 'Custody Changed',
            self::EVENT_BOTTLE_DESTROYED => 'Bottle Destroyed',
            self::EVENT_BOTTLE_MISSING => 'Marked Missing',
            self::EVENT_BOTTLE_MIS_SERIALIZED => 'Flagged Mis-serialized',
            self::EVENT_CASE_CREATED => 'Case Created',
            self::EVENT_CASE_LOCATION_CHANGE => 'Case Location Changed',
            self::EVENT_CASE_BROKEN => 'Case Broken',
            self::EVENT_CASE_BOTTLE_ADDED => 'Bottle Added to Case',
            self::EVENT_CASE_BOTTLE_REMOVED => 'Bottle Removed from Case',
            self::EVENT_BATCH_CREATED => 'Batch Created',
            self::EVENT_BATCH_QUANTITY_UPDATE => 'Quantity Updated',
            self::EVENT_BATCH_DISCREPANCY_FLAGGED => 'Discrepancy Flagged',
            self::EVENT_BATCH_DISCREPANCY_RESOLVED => 'Discrepancy Resolved',
            self::EVENT_BATCH_SERIALIZATION_STARTED => 'Serialization Started',
            self::EVENT_BATCH_SERIALIZATION_COMPLETED => 'Serialization Completed',
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
            // Inventory Module events
            self::EVENT_BOTTLE_SERIALIZED => 'heroicon-o-qr-code',
            self::EVENT_BOTTLE_STATE_CHANGE => 'heroicon-o-arrow-path',
            self::EVENT_BOTTLE_LOCATION_CHANGE => 'heroicon-o-map-pin',
            self::EVENT_BOTTLE_CUSTODY_CHANGE => 'heroicon-o-user-circle',
            self::EVENT_BOTTLE_DESTROYED => 'heroicon-o-x-circle',
            self::EVENT_BOTTLE_MISSING => 'heroicon-o-question-mark-circle',
            self::EVENT_BOTTLE_MIS_SERIALIZED => 'heroicon-o-exclamation-triangle',
            self::EVENT_CASE_CREATED => 'heroicon-o-archive-box',
            self::EVENT_CASE_LOCATION_CHANGE => 'heroicon-o-map-pin',
            self::EVENT_CASE_BROKEN => 'heroicon-o-scissors',
            self::EVENT_CASE_BOTTLE_ADDED => 'heroicon-o-plus',
            self::EVENT_CASE_BOTTLE_REMOVED => 'heroicon-o-minus',
            self::EVENT_BATCH_CREATED => 'heroicon-o-inbox-arrow-down',
            self::EVENT_BATCH_QUANTITY_UPDATE => 'heroicon-o-calculator',
            self::EVENT_BATCH_DISCREPANCY_FLAGGED => 'heroicon-o-exclamation-triangle',
            self::EVENT_BATCH_DISCREPANCY_RESOLVED => 'heroicon-o-check-badge',
            self::EVENT_BATCH_SERIALIZATION_STARTED => 'heroicon-o-play-circle',
            self::EVENT_BATCH_SERIALIZATION_COMPLETED => 'heroicon-o-check-circle',
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
            // Inventory Module events
            self::EVENT_BOTTLE_SERIALIZED => 'success',
            self::EVENT_BOTTLE_STATE_CHANGE => 'warning',
            self::EVENT_BOTTLE_LOCATION_CHANGE => 'info',
            self::EVENT_BOTTLE_CUSTODY_CHANGE => 'info',
            self::EVENT_BOTTLE_DESTROYED => 'danger',
            self::EVENT_BOTTLE_MISSING => 'danger',
            self::EVENT_BOTTLE_MIS_SERIALIZED => 'danger',
            self::EVENT_CASE_CREATED => 'success',
            self::EVENT_CASE_LOCATION_CHANGE => 'info',
            self::EVENT_CASE_BROKEN => 'danger',
            self::EVENT_CASE_BOTTLE_ADDED => 'success',
            self::EVENT_CASE_BOTTLE_REMOVED => 'warning',
            self::EVENT_BATCH_CREATED => 'success',
            self::EVENT_BATCH_QUANTITY_UPDATE => 'info',
            self::EVENT_BATCH_DISCREPANCY_FLAGGED => 'danger',
            self::EVENT_BATCH_DISCREPANCY_RESOLVED => 'success',
            self::EVENT_BATCH_SERIALIZATION_STARTED => 'info',
            self::EVENT_BATCH_SERIALIZATION_COMPLETED => 'success',
            default => 'gray',
        };
    }

    /**
     * Check if this is an inventory module event.
     */
    public function isInventoryEvent(): bool
    {
        return in_array($this->event, [
            self::EVENT_BOTTLE_SERIALIZED,
            self::EVENT_BOTTLE_STATE_CHANGE,
            self::EVENT_BOTTLE_LOCATION_CHANGE,
            self::EVENT_BOTTLE_CUSTODY_CHANGE,
            self::EVENT_BOTTLE_DESTROYED,
            self::EVENT_BOTTLE_MISSING,
            self::EVENT_BOTTLE_MIS_SERIALIZED,
            self::EVENT_CASE_CREATED,
            self::EVENT_CASE_LOCATION_CHANGE,
            self::EVENT_CASE_BROKEN,
            self::EVENT_CASE_BOTTLE_ADDED,
            self::EVENT_CASE_BOTTLE_REMOVED,
            self::EVENT_BATCH_CREATED,
            self::EVENT_BATCH_QUANTITY_UPDATE,
            self::EVENT_BATCH_DISCREPANCY_FLAGGED,
            self::EVENT_BATCH_DISCREPANCY_RESOLVED,
            self::EVENT_BATCH_SERIALIZATION_STARTED,
            self::EVENT_BATCH_SERIALIZATION_COMPLETED,
        ], true);
    }

    /**
     * Get all inventory-related event types.
     *
     * @return list<string>
     */
    public static function getInventoryEventTypes(): array
    {
        return [
            self::EVENT_BOTTLE_SERIALIZED,
            self::EVENT_BOTTLE_STATE_CHANGE,
            self::EVENT_BOTTLE_LOCATION_CHANGE,
            self::EVENT_BOTTLE_CUSTODY_CHANGE,
            self::EVENT_BOTTLE_DESTROYED,
            self::EVENT_BOTTLE_MISSING,
            self::EVENT_BOTTLE_MIS_SERIALIZED,
            self::EVENT_CASE_CREATED,
            self::EVENT_CASE_LOCATION_CHANGE,
            self::EVENT_CASE_BROKEN,
            self::EVENT_CASE_BOTTLE_ADDED,
            self::EVENT_CASE_BOTTLE_REMOVED,
            self::EVENT_BATCH_CREATED,
            self::EVENT_BATCH_QUANTITY_UPDATE,
            self::EVENT_BATCH_DISCREPANCY_FLAGGED,
            self::EVENT_BATCH_DISCREPANCY_RESOLVED,
            self::EVENT_BATCH_SERIALIZATION_STARTED,
            self::EVENT_BATCH_SERIALIZATION_COMPLETED,
        ];
    }
}
