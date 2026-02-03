<?php

namespace App\Models\Commercial;

use App\Enums\Commercial\ChannelStatus;
use App\Enums\Commercial\ChannelType;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Channel Model
 *
 * Represents a commercial channel as a stable sales context.
 * Channels define how products are sold (B2C, B2B, Private Club)
 * and which commercial models are allowed (voucher_based, sell_through).
 *
 * @property string $id
 * @property string $name
 * @property ChannelType $channel_type
 * @property string $default_currency
 * @property array<string> $allowed_commercial_models
 * @property ChannelStatus $status
 */
class Channel extends Model
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
    protected $table = 'channels';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'channel_type',
        'default_currency',
        'allowed_commercial_models',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel_type' => ChannelType::class,
            'status' => ChannelStatus::class,
            'allowed_commercial_models' => 'array',
        ];
    }

    /**
     * Get the audit logs for this channel.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Check if the channel is active.
     */
    public function isActive(): bool
    {
        return $this->status === ChannelStatus::Active;
    }

    /**
     * Check if the channel is inactive.
     */
    public function isInactive(): bool
    {
        return $this->status === ChannelStatus::Inactive;
    }

    /**
     * Check if a commercial model is allowed for this channel.
     *
     * @param  string  $model  The commercial model to check (e.g., 'voucher_based', 'sell_through')
     */
    public function allowsCommercialModel(string $model): bool
    {
        return in_array($model, $this->allowed_commercial_models, true);
    }

    /**
     * Check if voucher-based sales are allowed.
     */
    public function allowsVoucherBased(): bool
    {
        return $this->allowsCommercialModel('voucher_based');
    }

    /**
     * Check if sell-through sales are allowed.
     */
    public function allowsSellThrough(): bool
    {
        return $this->allowsCommercialModel('sell_through');
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status label for UI display.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get the channel type color for UI display.
     */
    public function getChannelTypeColor(): string
    {
        return $this->channel_type->color();
    }

    /**
     * Get the channel type label for UI display.
     */
    public function getChannelTypeLabel(): string
    {
        return $this->channel_type->label();
    }
}
