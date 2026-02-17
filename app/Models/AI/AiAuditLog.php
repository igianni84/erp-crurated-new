<?php

namespace App\Models\AI;

use App\Models\User;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class AiAuditLog extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $table = 'ai_audit_logs';

    protected $fillable = [
        'user_id',
        'conversation_id',
        'message_text',
        'tools_invoked',
        'tokens_input',
        'tokens_output',
        'estimated_cost_eur',
        'duration_ms',
        'error',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tools_invoked' => 'array',
            'tokens_input' => 'integer',
            'tokens_output' => 'integer',
            'estimated_cost_eur' => 'decimal:6',
            'duration_ms' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (): void {
            throw new InvalidArgumentException(
                'AI audit logs are immutable and cannot be updated.'
            );
        });

        static::deleting(function (): void {
            throw new InvalidArgumentException(
                'AI audit logs are immutable and cannot be deleted.'
            );
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
