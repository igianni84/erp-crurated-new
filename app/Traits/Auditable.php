<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Trait Auditable
 *
 * Provides automatic audit logging for model changes.
 * Tracks who created and last updated the model, with timestamps.
 * Also logs creation, update, and deletion events to the AuditLog table.
 *
 * Usage:
 *   1. Add `use Auditable;` in your model
 *   2. Add migration columns:
 *      - $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
 *      - $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
 *   3. Add auditLogs() MorphMany relationship to the model:
 *      public function auditLogs(): MorphMany
 *      {
 *          return $this->morphMany(AuditLog::class, 'auditable');
 *      }
 */
trait Auditable
{
    /**
     * Default fields to exclude from audit logging.
     */
    private const AUDIT_EXCLUDE_DEFAULT = ['updated_at', 'updated_by'];

    /**
     * Boot the trait.
     * Automatically sets created_by and updated_by when saving.
     * Logs creation, update, and deletion events to AuditLog.
     */
    public static function bootAuditable(): void
    {
        static::creating(function ($model): void {
            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        static::created(function ($model): void {
            self::createAuditLogEntry($model, AuditLog::EVENT_CREATED);
        });

        static::updating(function ($model): void {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        static::updated(function ($model): void {
            self::createAuditLogEntry($model, AuditLog::EVENT_UPDATED);
        });

        static::deleted(function ($model): void {
            self::createAuditLogEntry($model, AuditLog::EVENT_DELETED);
        });
    }

    /**
     * Create an audit log entry for the model.
     */
    protected static function createAuditLogEntry(Model $model, string $event): void
    {
        // Use model's auditExcludeFields property if defined, otherwise use defaults
        /** @var array<string> $excludeFields */
        $excludeFields = property_exists($model, 'auditExcludeFields')
            ? $model->auditExcludeFields
            : self::AUDIT_EXCLUDE_DEFAULT;

        $oldValues = [];
        $newValues = [];

        if ($event === AuditLog::EVENT_CREATED) {
            // For creation, all attributes are "new"
            $newValues = self::filterAuditableAttributes($model->getAttributes(), $excludeFields);
        } elseif ($event === AuditLog::EVENT_UPDATED) {
            // For updates, track only changed fields
            $dirty = $model->getDirty();
            $original = $model->getOriginal();

            foreach ($dirty as $key => $value) {
                if (! in_array($key, $excludeFields, true)) {
                    $oldValues[$key] = $original[$key] ?? null;
                    $newValues[$key] = $value;
                }
            }

            // Skip logging if no auditable fields changed
            if (empty($newValues)) {
                return;
            }
        } elseif ($event === AuditLog::EVENT_DELETED) {
            // For deletion, record what was deleted
            $oldValues = self::filterAuditableAttributes($model->getAttributes(), $excludeFields);
        }

        // Cast enum values to their scalar representation for storage
        $oldValues = self::castEnumsForStorage($oldValues);
        $newValues = self::castEnumsForStorage($newValues);

        AuditLog::create([
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Filter attributes to exclude specified fields.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string>  $excludeFields
     * @return array<string, mixed>
     */
    protected static function filterAuditableAttributes(array $attributes, array $excludeFields): array
    {
        return array_diff_key($attributes, array_flip($excludeFields));
    }

    /**
     * Cast enum values to their scalar representation for JSON storage.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected static function castEnumsForStorage(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if ($value instanceof \BackedEnum) {
                $result[$key] = $value->value;
            } elseif ($value instanceof \UnitEnum) {
                $result[$key] = $value->name;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Get the user who created this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Get the user who last updated this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}
