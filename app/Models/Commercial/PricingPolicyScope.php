<?php

namespace App\Models\Commercial;

use App\Enums\Commercial\PolicyScopeType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PricingPolicyScope Model
 *
 * Defines the application scope of a Pricing Policy.
 * Determines which Sellable SKUs are affected by the policy.
 *
 * Scope types:
 * - all: Applies to all commercially available SKUs
 * - category: Applies to SKUs within specific categories
 * - product: Applies to SKUs for specific products (all formats)
 * - sku: Applies to specific individual SKUs
 *
 * Scope resolution:
 * - Scopes always resolve to Sellable SKUs (not Bottle SKUs)
 * - Policies only apply to SKUs with active allocations
 * - Markets and channels can further filter the scope
 *
 * @property string $id
 * @property string $pricing_policy_id
 * @property PolicyScopeType $scope_type
 * @property string|null $scope_reference Reference ID (category, product, or SKU ID based on scope_type)
 * @property array<int, string>|null $markets Market codes to filter by
 * @property array<int, string>|null $channels Channel IDs to filter by
 */
class PricingPolicyScope extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pricing_policy_scopes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pricing_policy_id',
        'scope_type',
        'scope_reference',
        'markets',
        'channels',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_type' => PolicyScopeType::class,
            'markets' => 'array',
            'channels' => 'array',
        ];
    }

    /**
     * Get the pricing policy that owns this scope.
     *
     * @return BelongsTo<PricingPolicy, $this>
     */
    public function pricingPolicy(): BelongsTo
    {
        return $this->belongsTo(PricingPolicy::class);
    }

    /**
     * Check if the scope applies to all SKUs.
     */
    public function isAllScope(): bool
    {
        return $this->scope_type === PolicyScopeType::All;
    }

    /**
     * Check if the scope targets a specific category.
     */
    public function isCategoryScope(): bool
    {
        return $this->scope_type === PolicyScopeType::Category;
    }

    /**
     * Check if the scope targets a specific product.
     */
    public function isProductScope(): bool
    {
        return $this->scope_type === PolicyScopeType::Product;
    }

    /**
     * Check if the scope targets a specific SKU.
     */
    public function isSkuScope(): bool
    {
        return $this->scope_type === PolicyScopeType::Sku;
    }

    /**
     * Check if the scope has market restrictions.
     */
    public function hasMarketRestrictions(): bool
    {
        return ! empty($this->markets);
    }

    /**
     * Check if the scope has channel restrictions.
     */
    public function hasChannelRestrictions(): bool
    {
        return ! empty($this->channels);
    }

    /**
     * Check if a market is within the scope.
     */
    public function isMarketInScope(string $market): bool
    {
        if (! $this->hasMarketRestrictions()) {
            return true;
        }

        return in_array($market, $this->markets ?? [], true);
    }

    /**
     * Check if a channel is within the scope.
     */
    public function isChannelInScope(string $channelId): bool
    {
        if (! $this->hasChannelRestrictions()) {
            return true;
        }

        return in_array($channelId, $this->channels ?? [], true);
    }

    /**
     * Get the scope type label for UI display.
     */
    public function getScopeTypeLabel(): string
    {
        return $this->scope_type->label();
    }

    /**
     * Get the scope type color for UI display.
     */
    public function getScopeTypeColor(): string
    {
        return $this->scope_type->color();
    }

    /**
     * Get the scope type icon for UI display.
     */
    public function getScopeTypeIcon(): string
    {
        return $this->scope_type->icon();
    }

    /**
     * Get a plain-language description of the scope.
     */
    public function getScopeDescription(): string
    {
        $description = match ($this->scope_type) {
            PolicyScopeType::All => 'All commercially available SKUs',
            PolicyScopeType::Category => $this->scope_reference
                ? "Category: {$this->scope_reference}"
                : 'Specific category (not configured)',
            PolicyScopeType::Product => $this->scope_reference
                ? "Product: {$this->scope_reference}"
                : 'Specific product (not configured)',
            PolicyScopeType::Sku => $this->scope_reference
                ? "SKU: {$this->scope_reference}"
                : 'Specific SKU (not configured)',
        };

        $restrictions = [];
        if ($this->hasMarketRestrictions()) {
            $marketCount = count($this->markets ?? []);
            $restrictions[] = "{$marketCount} market(s)";
        }
        if ($this->hasChannelRestrictions()) {
            $channelCount = count($this->channels ?? []);
            $restrictions[] = "{$channelCount} channel(s)";
        }

        if (! empty($restrictions)) {
            $description .= ' (restricted to '.implode(', ', $restrictions).')';
        }

        return $description;
    }

    /**
     * Get the market codes as a comma-separated string.
     */
    public function getMarketsDisplayString(): string
    {
        if (! $this->hasMarketRestrictions()) {
            return 'All markets';
        }

        return implode(', ', $this->markets ?? []);
    }

    /**
     * Get the count of markets in scope.
     */
    public function getMarketsCount(): int
    {
        return count($this->markets ?? []);
    }

    /**
     * Get the count of channels in scope.
     */
    public function getChannelsCount(): int
    {
        return count($this->channels ?? []);
    }
}
