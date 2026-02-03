<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Trait AuditLoggable
 *
 * Provides automatic audit logging for model changes.
 * Logs create, update, delete, and status change events to the audit_logs table.
 *
 * Usage:
 *   1. Add `use AuditLoggable;` in your model
 *   2. Optionally define AUDIT_EXCLUDE_FIELDS constant to exclude fields from logging
 *   3. Optionally define AUDIT_TRACK_STATUS_FIELD constant to track specific status changes
 */
trait AuditLoggable
{
    /**
     * Boot the trait.
     * Registers model event listeners for automatic audit logging.
     */
    public static function bootAuditLoggable(): void
    {
        static::created(function ($model): void {
            $model->logAuditEvent(AuditLog::EVENT_CREATED, [], $model->getAuditableAttributes());
        });

        static::updated(function ($model): void {
            $changes = $model->getChanges();
            $original = $model->getOriginal();

            // Filter out excluded fields
            $excludeFields = $model->getAuditExcludeFields();
            $changes = array_diff_key($changes, array_flip($excludeFields));

            if (empty($changes)) {
                return;
            }

            // Check if this is a status change
            $statusField = $model->getAuditStatusField();
            if ($statusField !== null && array_key_exists($statusField, $changes)) {
                $model->logAuditEvent(
                    AuditLog::EVENT_STATUS_CHANGE,
                    [$statusField => $original[$statusField] ?? null],
                    [$statusField => $changes[$statusField]]
                );
            }

            // Log the general update with all changed fields
            $oldValues = [];
            foreach (array_keys($changes) as $field) {
                $oldValues[$field] = $original[$field] ?? null;
            }

            $model->logAuditEvent(AuditLog::EVENT_UPDATED, $oldValues, $changes);
        });

        static::deleted(function ($model): void {
            $model->logAuditEvent(AuditLog::EVENT_DELETED, $model->getAuditableAttributes(), []);
        });
    }

    /**
     * Get the audit logs for this model.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->orderByDesc('created_at');
    }

    /**
     * Log an audit event for this model.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function logAuditEvent(string $event, array $oldValues, array $newValues): void
    {
        AuditLog::create([
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Get the fields to exclude from audit logging.
     *
     * @return list<string>
     */
    protected function getAuditExcludeFields(): array
    {
        $constantName = static::class.'::AUDIT_EXCLUDE_FIELDS';
        if (defined($constantName)) {
            /** @var list<string> */
            $value = constant($constantName);

            return $value;
        }

        return ['created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by'];
    }

    /**
     * Get the status field to track for status change events.
     */
    protected function getAuditStatusField(): ?string
    {
        $constantName = static::class.'::AUDIT_TRACK_STATUS_FIELD';
        if (defined($constantName)) {
            /** @var string */
            $value = constant($constantName);

            return $value;
        }

        return null;
    }

    /**
     * Get the auditable attributes (excluding sensitive/system fields).
     *
     * @return array<string, mixed>
     */
    protected function getAuditableAttributes(): array
    {
        $attributes = $this->getAttributes();
        $excludeFields = $this->getAuditExcludeFields();

        return array_diff_key($attributes, array_flip($excludeFields));
    }
}
