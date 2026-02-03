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
            default => ucfirst($this->event),
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
            default => 'gray',
        };
    }
}
