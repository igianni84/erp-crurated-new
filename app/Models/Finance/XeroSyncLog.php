<?php

namespace App\Models\Finance;

use App\Enums\Finance\XeroSyncStatus;
use App\Enums\Finance\XeroSyncType;
use App\Services\Finance\LogSanitizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

/**
 * XeroSyncLog Model
 *
 * Immutable log of all Xero synchronization attempts.
 * Used for tracking sync status, debugging, and retry management.
 *
 * IMPORTANT: This model does NOT use soft deletes - logs are immutable.
 *
 * @property int $id
 * @property XeroSyncType $sync_type
 * @property string $syncable_type
 * @property int $syncable_id
 * @property string|null $xero_id
 * @property XeroSyncStatus $status
 * @property array<string, mixed>|null $request_payload
 * @property array<string, mixed>|null $response_payload
 * @property string|null $error_message
 * @property Carbon|null $synced_at
 * @property int $retry_count
 * @property Carbon|null $created_at
 * @property-read Model|Invoice|CreditNote|Payment $syncable
 */
class XeroSyncLog extends Model
{
    use HasFactory;

    /**
     * Disable updated_at timestamp since logs are immutable.
     */
    public const UPDATED_AT = null;

    protected $table = 'xero_sync_logs';

    protected $fillable = [
        'sync_type',
        'syncable_type',
        'syncable_id',
        'xero_id',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'synced_at',
        'retry_count',
    ];

    protected $attributes = [
        'retry_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'sync_type' => XeroSyncType::class,
            'status' => XeroSyncStatus::class,
            'request_payload' => 'array',
            'response_payload' => 'array',
            'synced_at' => 'datetime',
            'retry_count' => 'integer',
        ];
    }

    // =========================================================================
    // Boot Methods - Immutability Enforcement
    // =========================================================================

    protected static function boot(): void
    {
        parent::boot();

        // Set default status on creation and sanitize payloads
        static::creating(function (XeroSyncLog $log): void {
            if (! isset($log->attributes['status'])) {
                $log->status = XeroSyncStatus::Pending;
            }

            // Sanitize request payload to remove sensitive data
            if (isset($log->attributes['request_payload']) && is_array($log->request_payload)) {
                $sanitizer = app(LogSanitizer::class);
                $log->request_payload = $sanitizer->sanitize($log->request_payload);
            }
        });

        // Prevent deletion of sync logs - they must be retained
        static::deleting(function (XeroSyncLog $log): void {
            throw new InvalidArgumentException(
                'Xero sync logs cannot be deleted. They are immutable for audit purposes.'
            );
        });

        // Allow limited updates only for status-related fields
        static::updating(function (XeroSyncLog $log): void {
            // Only allow updates to these fields
            $allowedFields = [
                'xero_id',
                'status',
                'response_payload',
                'error_message',
                'synced_at',
                'retry_count',
            ];
            $changedFields = array_keys($log->getDirty());

            foreach ($changedFields as $field) {
                if (! in_array($field, $allowedFields)) {
                    throw new InvalidArgumentException(
                        "Cannot modify field '{$field}' on Xero sync logs. Only status and result fields can be updated."
                    );
                }
            }

            // Once successfully synced, cannot be changed back to pending
            $originalStatus = $log->getOriginal('status');
            if ($originalStatus === XeroSyncStatus::Synced && $log->isDirty('status')) {
                throw new InvalidArgumentException(
                    'Cannot modify status of a successfully synced log.'
                );
            }

            // Validate status transitions
            if ($log->isDirty('status') && $originalStatus !== null) {
                if (! $originalStatus->canTransitionTo($log->status)) {
                    throw new InvalidArgumentException(
                        "Cannot transition Xero sync status from {$originalStatus->value} to {$log->status->value}."
                    );
                }
            }
        });
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * Get the syncable entity (Invoice, CreditNote, or Payment).
     *
     * @return MorphTo<Model, $this>
     */
    public function syncable(): MorphTo
    {
        return $this->morphTo();
    }

    // =========================================================================
    // Status Methods
    // =========================================================================

    /**
     * Check if the sync is pending.
     */
    public function isPending(): bool
    {
        return $this->status === XeroSyncStatus::Pending;
    }

    /**
     * Check if the sync was successful.
     */
    public function isSynced(): bool
    {
        return $this->status === XeroSyncStatus::Synced;
    }

    /**
     * Check if the sync failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === XeroSyncStatus::Failed;
    }

    /**
     * Check if the sync can be retried.
     */
    public function canRetry(): bool
    {
        return $this->status->allowsRetry();
    }

    /**
     * Check if this sync requires attention.
     */
    public function requiresAttention(): bool
    {
        return $this->status->requiresAttention();
    }

    // =========================================================================
    // Processing Methods
    // =========================================================================

    /**
     * Mark the sync as successful.
     *
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function markSynced(string $xeroId, ?array $responsePayload = null): void
    {
        // Sanitize response payload to remove sensitive data
        $sanitizedPayload = $responsePayload !== null
            ? app(LogSanitizer::class)->sanitize($responsePayload)
            : null;

        $this->update([
            'status' => XeroSyncStatus::Synced,
            'xero_id' => $xeroId,
            'response_payload' => $sanitizedPayload,
            'synced_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark the sync as failed.
     *
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function markFailed(string $errorMessage, ?array $responsePayload = null): void
    {
        // Sanitize response payload to remove sensitive data
        $sanitizedPayload = $responsePayload !== null
            ? app(LogSanitizer::class)->sanitize($responsePayload)
            : null;

        $this->update([
            'status' => XeroSyncStatus::Failed,
            'error_message' => $errorMessage,
            'response_payload' => $sanitizedPayload,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Reset for retry (changes status back to pending).
     */
    public function resetForRetry(): void
    {
        if (! $this->canRetry()) {
            throw new InvalidArgumentException(
                'Cannot retry a sync that is not in failed status.'
            );
        }

        $this->update([
            'status' => XeroSyncStatus::Pending,
            'error_message' => null,
        ]);
    }

    // =========================================================================
    // Query Scopes
    // =========================================================================
    /**
     * Scope to get only pending syncs.
     *
     * @param  Builder<XeroSyncLog>  $query
     * @return Builder<XeroSyncLog>
     */
    public function scopePending($query)
    {
        return $query->where('status', XeroSyncStatus::Pending);
    }

    /**
     * Scope to get only synced logs.
     *
     * @param  Builder<XeroSyncLog>  $query
     * @return Builder<XeroSyncLog>
     */
    public function scopeSynced($query)
    {
        return $query->where('status', XeroSyncStatus::Synced);
    }

    /**
     * Scope to get only failed syncs.
     *
     * @param  Builder<XeroSyncLog>  $query
     * @return Builder<XeroSyncLog>
     */
    public function scopeFailed($query)
    {
        return $query->where('status', XeroSyncStatus::Failed);
    }

    /**
     * Scope to filter by sync type.
     *
     * @param  Builder<XeroSyncLog>  $query
     * @return Builder<XeroSyncLog>
     */
    public function scopeOfType($query, XeroSyncType $syncType)
    {
        return $query->where('sync_type', $syncType);
    }

    /**
     * Scope to filter by syncable entity.
     *
     * @param  Builder<XeroSyncLog>  $query
     * @return Builder<XeroSyncLog>
     */
    public function scopeForSyncable($query, Model $syncable)
    {
        return $query->where('syncable_type', $syncable->getMorphClass())
            ->where('syncable_id', $syncable->getKey());
    }

    /**
     * Scope to get logs that require attention.
     *
     * @param  Builder<XeroSyncLog>  $query
     * @return Builder<XeroSyncLog>
     */
    public function scopeRequiresAttention($query)
    {
        return $query->where('status', XeroSyncStatus::Failed);
    }

    /**
     * Scope to get logs with retry count below a threshold.
     *
     * @param  Builder<XeroSyncLog>  $query
     * @return Builder<XeroSyncLog>
     */
    public function scopeRetryable($query, int $maxRetries = 3)
    {
        return $query->failed()
            ->where('retry_count', '<', $maxRetries);
    }

    // =========================================================================
    // Static Methods
    // =========================================================================

    /**
     * Create a new sync log for an entity.
     *
     * @param  array<string, mixed>|null  $requestPayload
     */
    public static function createForEntity(
        XeroSyncType $syncType,
        Model $syncable,
        ?array $requestPayload = null
    ): self {
        return self::create([
            'sync_type' => $syncType,
            'syncable_type' => $syncable->getMorphClass(),
            'syncable_id' => $syncable->getKey(),
            'status' => XeroSyncStatus::Pending,
            'request_payload' => $requestPayload,
        ]);
    }

    /**
     * Get the latest sync log for an entity.
     */
    public static function getLatestForEntity(Model $syncable): ?self
    {
        return self::forSyncable($syncable)
            ->latest('created_at')
            ->first();
    }

    /**
     * Check if an entity has been successfully synced.
     */
    public static function hasSuccessfulSync(Model $syncable): bool
    {
        return self::forSyncable($syncable)
            ->synced()
            ->exists();
    }

    /**
     * Get the Xero ID for an entity if synced.
     */
    public static function getXeroIdForEntity(Model $syncable): ?string
    {
        $log = self::forSyncable($syncable)
            ->synced()
            ->latest('synced_at')
            ->first();

        return $log?->xero_id;
    }

    // =========================================================================
    // Display Methods
    // =========================================================================

    /**
     * Get the sync type label for display.
     */
    public function getSyncTypeLabel(): string
    {
        return $this->sync_type->label();
    }

    /**
     * Get the status label for display.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get the status color for display.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status icon for display.
     */
    public function getStatusIcon(): string
    {
        return $this->status->icon();
    }

    /**
     * Get the sync type icon for display.
     */
    public function getSyncTypeIcon(): string
    {
        return $this->sync_type->icon();
    }

    /**
     * Get a summary of the sync log for display.
     */
    public function getSummary(): string
    {
        $typeLabel = $this->sync_type->label();
        $statusLabel = $this->status->label();

        return "{$typeLabel} sync - {$statusLabel}";
    }
}
