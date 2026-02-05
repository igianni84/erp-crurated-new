<?php

namespace App\Models\Commercial;

use App\Enums\Commercial\PriceBookStatus;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PriceBook Model
 *
 * Represents an authoritative pricing decision document.
 * Price Books contain base prices for Sellable SKUs and are
 * scoped to specific markets, channels, and currencies.
 *
 * Status transitions:
 * - draft → active (requires approval)
 * - active → expired (automatic or manual)
 * - expired → archived
 *
 * @property string $id
 * @property string $name
 * @property string $market
 * @property string|null $channel_id
 * @property string $currency
 * @property \Carbon\Carbon $valid_from
 * @property \Carbon\Carbon|null $valid_to
 * @property PriceBookStatus $status
 * @property \Carbon\Carbon|null $approved_at
 * @property int|null $approved_by
 */
class PriceBook extends Model
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
    protected $table = 'price_books';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'market',
        'channel_id',
        'currency',
        'valid_from',
        'valid_to',
        'status',
        'approved_at',
        'approved_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PriceBookStatus::class,
            'valid_from' => 'date',
            'valid_to' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the channel that this price book is associated with (optional).
     *
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the user who approved this price book.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the audit logs for this price book.
     *
     * @return MorphMany<\App\Models\AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(\App\Models\AuditLog::class, 'auditable');
    }

    /**
     * Get the price entries in this price book.
     *
     * @return HasMany<PriceBookEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(PriceBookEntry::class);
    }

    /**
     * Get the pricing policies that target this price book.
     *
     * @return HasMany<PricingPolicy, $this>
     */
    public function pricingPolicies(): HasMany
    {
        return $this->hasMany(PricingPolicy::class, 'target_price_book_id');
    }

    /**
     * Get the offers that use this price book.
     *
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * Check if the price book is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === PriceBookStatus::Draft;
    }

    /**
     * Check if the price book is active.
     */
    public function isActive(): bool
    {
        return $this->status === PriceBookStatus::Active;
    }

    /**
     * Check if the price book is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === PriceBookStatus::Expired;
    }

    /**
     * Check if the price book is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === PriceBookStatus::Archived;
    }

    /**
     * Check if the price book has been approved.
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null && $this->approved_by !== null;
    }

    /**
     * Check if the price book can be activated.
     * Must be in draft status to be activated.
     */
    public function canBeActivated(): bool
    {
        return $this->isDraft();
    }

    /**
     * Check if the price book has at least one entry (required for activation).
     */
    public function hasEntries(): bool
    {
        return $this->entries()->exists();
    }

    /**
     * Check if activation is valid (has entries and is draft).
     */
    public function canBeActivatedWithEntries(): bool
    {
        return $this->isDraft() && $this->hasEntries();
    }

    /**
     * Find overlapping active price books for the same market/channel/currency.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PriceBook>
     */
    public function findOverlappingActivePriceBooks(): \Illuminate\Database\Eloquent\Collection
    {
        return self::query()
            ->where('id', '!=', $this->id)
            ->where('status', PriceBookStatus::Active)
            ->where('market', $this->market)
            ->where('channel_id', $this->channel_id)
            ->where('currency', $this->currency)
            ->where(function ($query): void {
                // Check for date overlap
                $query->where(function ($q): void {
                    // Existing price book ends after new one starts (or has no end)
                    $q->whereNull('valid_to')
                        ->orWhere('valid_to', '>=', $this->valid_from);
                });
                // Existing price book starts before new one ends (or new has no end)
                if ($this->valid_to !== null) {
                    $query->where('valid_from', '<=', $this->valid_to);
                }
            })
            ->get();
    }

    /**
     * Check if there are overlapping active price books.
     */
    public function hasOverlappingActivePriceBooks(): bool
    {
        return $this->findOverlappingActivePriceBooks()->isNotEmpty();
    }

    /**
     * Check if the price book can be archived.
     * Must be active or expired to be archived.
     */
    public function canBeArchived(): bool
    {
        return $this->isActive() || $this->isExpired();
    }

    /**
     * Check if the price book is editable.
     * Only draft price books can be edited.
     */
    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    /**
     * Check if the price book is within its validity period.
     */
    public function isWithinValidityPeriod(): bool
    {
        $now = now()->startOfDay();
        $validFrom = $this->valid_from->startOfDay();

        if ($now->lt($validFrom)) {
            return false;
        }

        if ($this->valid_to !== null) {
            $validTo = $this->valid_to->startOfDay();

            return $now->lte($validTo);
        }

        return true;
    }

    /**
     * Check if the price book is about to expire (within 30 days).
     */
    public function isExpiringSoon(): bool
    {
        if ($this->valid_to === null) {
            return false;
        }

        $now = now();
        $daysUntilExpiry = $now->diffInDays($this->valid_to, false);

        return $daysUntilExpiry >= 0 && $daysUntilExpiry <= 30;
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
     * Scope a query to only include active price books for a specific context.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<PriceBook>  $query
     * @return \Illuminate\Database\Eloquent\Builder<PriceBook>
     */
    public function scopeActiveForContext($query, string $market, ?string $channelId, string $currency)
    {
        return $query
            ->where('status', PriceBookStatus::Active)
            ->where('market', $market)
            ->where('channel_id', $channelId)
            ->where('currency', $currency)
            ->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_to')
                    ->orWhere('valid_to', '>=', now());
            });
    }
}
