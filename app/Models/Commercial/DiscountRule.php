<?php

namespace App\Models\Commercial;

use App\Enums\Commercial\DiscountRuleStatus;
use App\Enums\Commercial\DiscountRuleType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DiscountRule Model
 *
 * Defines reusable discount rules that can be referenced by Offers.
 * Discount rules are definitions, not applied directly - they are referenced
 * by OfferBenefit entities to provide consistent discount logic.
 *
 * Rule types:
 * - percentage: Simple percentage discount (e.g., 15% off)
 * - fixed_amount: Fixed amount discount (e.g., €10 off)
 * - tiered: Different discounts based on price/quantity tiers
 * - volume_based: Discounts based on order quantity thresholds
 *
 * The logic_definition JSON contains the rule configuration:
 * - For percentage: { "value": 15 }
 * - For fixed_amount: { "value": 10.00 }
 * - For tiered: { "tiers": [{ "min": 0, "max": 100, "value": 10 }, ...] }
 * - For volume_based: { "thresholds": [{ "min_qty": 6, "value": 10 }, ...] }
 *
 * @property string $id
 * @property string $name
 * @property DiscountRuleType $rule_type
 * @property array<string, mixed> $logic_definition
 * @property DiscountRuleStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class DiscountRule extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'discount_rules';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'rule_type',
        'logic_definition',
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
            'rule_type' => DiscountRuleType::class,
            'logic_definition' => 'array',
            'status' => DiscountRuleStatus::class,
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * Get the offer benefits that reference this discount rule.
     *
     * @return HasMany<OfferBenefit, $this>
     */
    public function offerBenefits(): HasMany
    {
        return $this->hasMany(OfferBenefit::class, 'discount_rule_id');
    }

    // =========================================================================
    // Status Helpers
    // =========================================================================

    /**
     * Check if the discount rule is active.
     */
    public function isActive(): bool
    {
        return $this->status === DiscountRuleStatus::Active;
    }

    /**
     * Check if the discount rule is inactive.
     */
    public function isInactive(): bool
    {
        return $this->status === DiscountRuleStatus::Inactive;
    }

    /**
     * Check if this rule can be deactivated.
     * Rules can be deactivated only if no active Offers are using them.
     */
    public function canBeDeactivated(): bool
    {
        if ($this->isInactive()) {
            return false;
        }

        return ! $this->hasActiveOffersUsing();
    }

    /**
     * Check if this rule can be edited.
     * Rules can be edited only if no active Offers are using them.
     */
    public function canBeEdited(): bool
    {
        return ! $this->hasActiveOffersUsing();
    }

    /**
     * Check if this rule can be deleted.
     * Rules can be deleted only if not referenced by any Offer.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->isReferencedByAnyOffer();
    }

    // =========================================================================
    // Rule Type Helpers
    // =========================================================================

    /**
     * Check if this is a percentage discount rule.
     */
    public function isPercentage(): bool
    {
        return $this->rule_type === DiscountRuleType::Percentage;
    }

    /**
     * Check if this is a fixed amount discount rule.
     */
    public function isFixedAmount(): bool
    {
        return $this->rule_type === DiscountRuleType::FixedAmount;
    }

    /**
     * Check if this is a tiered discount rule.
     */
    public function isTiered(): bool
    {
        return $this->rule_type === DiscountRuleType::Tiered;
    }

    /**
     * Check if this is a volume-based discount rule.
     */
    public function isVolumeBased(): bool
    {
        return $this->rule_type === DiscountRuleType::VolumeBased;
    }

    // =========================================================================
    // Logic Definition Accessors
    // =========================================================================

    /**
     * Get the base value from logic_definition (for percentage/fixed_amount types).
     */
    public function getValue(): ?float
    {
        $value = $this->logic_definition['value'] ?? null;

        return $value !== null ? (float) $value : null;
    }

    /**
     * Get the tiers from logic_definition (for tiered type).
     *
     * @return array<int, array{min?: float, max?: float, value: float}>
     */
    public function getTiers(): array
    {
        return $this->logic_definition['tiers'] ?? [];
    }

    /**
     * Get the thresholds from logic_definition (for volume_based type).
     *
     * @return array<int, array{min_qty: int, value: float}>
     */
    public function getThresholds(): array
    {
        return $this->logic_definition['thresholds'] ?? [];
    }

    // =========================================================================
    // Discount Calculation
    // =========================================================================

    /**
     * Calculate the discount amount for a given base price and quantity.
     *
     * @param  float  $basePrice  The base price to discount
     * @param  int  $quantity  The quantity (for volume-based rules)
     * @return float The discount amount
     */
    public function calculateDiscount(float $basePrice, int $quantity = 1): float
    {
        return match ($this->rule_type) {
            DiscountRuleType::Percentage => $this->calculatePercentageDiscount($basePrice),
            DiscountRuleType::FixedAmount => $this->calculateFixedAmountDiscount(),
            DiscountRuleType::Tiered => $this->calculateTieredDiscount($basePrice),
            DiscountRuleType::VolumeBased => $this->calculateVolumeBasedDiscount($basePrice, $quantity),
        };
    }

    /**
     * Calculate the final price after applying the discount.
     *
     * @param  float  $basePrice  The base price
     * @param  int  $quantity  The quantity (for volume-based rules)
     * @return float The final price after discount
     */
    public function calculateFinalPrice(float $basePrice, int $quantity = 1): float
    {
        $discount = $this->calculateDiscount($basePrice, $quantity);

        return max(0, $basePrice - $discount);
    }

    /**
     * Calculate percentage discount.
     */
    private function calculatePercentageDiscount(float $basePrice): float
    {
        $percentage = $this->getValue() ?? 0;

        return $basePrice * ($percentage / 100);
    }

    /**
     * Calculate fixed amount discount.
     */
    private function calculateFixedAmountDiscount(): float
    {
        return $this->getValue() ?? 0;
    }

    /**
     * Calculate tiered discount based on price ranges.
     */
    private function calculateTieredDiscount(float $basePrice): float
    {
        $tiers = $this->getTiers();

        foreach ($tiers as $tier) {
            $min = $tier['min'] ?? 0;
            $max = $tier['max'] ?? PHP_FLOAT_MAX;

            if ($basePrice >= $min && $basePrice <= $max) {
                $value = (float) $tier['value'];

                // Determine if value is percentage or fixed based on context
                // For tiered discounts, we assume percentage unless explicitly marked
                return $basePrice * ($value / 100);
            }
        }

        return 0;
    }

    /**
     * Calculate volume-based discount based on quantity thresholds.
     */
    private function calculateVolumeBasedDiscount(float $basePrice, int $quantity): float
    {
        $thresholds = $this->getThresholds();
        $applicableThreshold = null;

        // Find the highest threshold that applies
        foreach ($thresholds as $threshold) {
            $minQty = (int) $threshold['min_qty'];

            if ($quantity >= $minQty) {
                if ($applicableThreshold === null || $minQty > $applicableThreshold['min_qty']) {
                    $applicableThreshold = $threshold;
                }
            }
        }

        if ($applicableThreshold === null) {
            return 0;
        }

        $value = (float) $applicableThreshold['value'];

        // Volume-based typically uses fixed amount per unit or percentage
        // We assume it's a fixed amount per order
        return $value;
    }

    // =========================================================================
    // Usage Tracking
    // =========================================================================

    /**
     * Check if this rule is referenced by any Offer.
     */
    public function isReferencedByAnyOffer(): bool
    {
        return $this->offerBenefits()->exists();
    }

    /**
     * Check if this rule is used by any active Offer.
     */
    public function hasActiveOffersUsing(): bool
    {
        return $this->offerBenefits()
            ->whereHas('offer', function (Builder $query) {
                $query->where('status', 'active');
            })
            ->exists();
    }

    /**
     * Get the count of Offers using this rule.
     */
    public function getOffersUsingCount(): int
    {
        return $this->offerBenefits()->count();
    }

    /**
     * Get the count of active Offers using this rule.
     */
    public function getActiveOffersUsingCount(): int
    {
        return $this->offerBenefits()
            ->whereHas('offer', function (Builder $query) {
                $query->where('status', 'active');
            })
            ->count();
    }

    // =========================================================================
    // UI Helper Methods
    // =========================================================================

    /**
     * Get the rule type label for UI display.
     */
    public function getRuleTypeLabel(): string
    {
        return $this->rule_type->label();
    }

    /**
     * Get the rule type color for UI display.
     */
    public function getRuleTypeColor(): string
    {
        return $this->rule_type->color();
    }

    /**
     * Get the rule type icon for UI display.
     */
    public function getRuleTypeIcon(): string
    {
        return $this->rule_type->icon();
    }

    /**
     * Get the status label for UI display.
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Get the status icon for UI display.
     */
    public function getStatusIcon(): string
    {
        return $this->status->icon();
    }

    /**
     * Get a plain-language summary of the discount rule.
     */
    public function getSummary(?string $currency = 'EUR'): string
    {
        return match ($this->rule_type) {
            DiscountRuleType::Percentage => $this->getPercentageSummary(),
            DiscountRuleType::FixedAmount => $this->getFixedAmountSummary($currency),
            DiscountRuleType::Tiered => $this->getTieredSummary(),
            DiscountRuleType::VolumeBased => $this->getVolumeBasedSummary($currency),
        };
    }

    /**
     * Get summary for percentage discount.
     */
    private function getPercentageSummary(): string
    {
        $value = $this->getValue();

        if ($value === null) {
            return 'Percentage discount (not configured)';
        }

        return number_format($value, 0).'% off';
    }

    /**
     * Get summary for fixed amount discount.
     */
    private function getFixedAmountSummary(string $currency): string
    {
        $value = $this->getValue();

        if ($value === null) {
            return 'Fixed amount discount (not configured)';
        }

        return $currency.' '.number_format($value, 2).' off';
    }

    /**
     * Get summary for tiered discount.
     */
    private function getTieredSummary(): string
    {
        $tiers = $this->getTiers();

        if (empty($tiers)) {
            return 'Tiered discount (no tiers configured)';
        }

        return count($tiers).' tier(s) configured';
    }

    /**
     * Get summary for volume-based discount.
     */
    private function getVolumeBasedSummary(string $currency): string
    {
        $thresholds = $this->getThresholds();

        if (empty($thresholds)) {
            return 'Volume-based discount (no thresholds configured)';
        }

        // Get the first threshold as example
        $first = $thresholds[0];
        $minQty = $first['min_qty'];
        $value = $first['value'];

        return $currency.' '.number_format($value, 2).' off when qty >= '.$minQty;
    }

    /**
     * Get a detailed description of the discount rule logic.
     */
    public function getDetailedDescription(?string $currency = 'EUR'): string
    {
        $lines = [];
        $lines[] = 'Rule Type: '.$this->getRuleTypeLabel();
        $lines[] = 'Status: '.$this->getStatusLabel();
        $lines[] = '';
        $lines[] = 'Logic: '.$this->getSummary($currency);

        if ($this->isTiered()) {
            $tiers = $this->getTiers();
            foreach ($tiers as $i => $tier) {
                $min = $tier['min'] ?? 0;
                $max = $tier['max'] ?? '∞';
                $value = $tier['value'];
                $lines[] = sprintf('  Tier %d: %s - %s → %s%%', $i + 1, $currency.' '.number_format((float) $min, 2), is_numeric($max) ? $currency.' '.number_format((float) $max, 2) : $max, number_format($value, 0));
            }
        }

        if ($this->isVolumeBased()) {
            $thresholds = $this->getThresholds();
            foreach ($thresholds as $i => $threshold) {
                $minQty = $threshold['min_qty'];
                $value = $threshold['value'];
                $lines[] = sprintf('  Threshold %d: qty >= %d → %s off', $i + 1, $minQty, $currency.' '.number_format($value, 2));
            }
        }

        return implode("\n", $lines);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * Scope query to active rules only.
     *
     * @param  Builder<DiscountRule>  $query
     * @return Builder<DiscountRule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', DiscountRuleStatus::Active);
    }

    /**
     * Scope query to rules of a specific type.
     *
     * @param  Builder<DiscountRule>  $query
     * @return Builder<DiscountRule>
     */
    public function scopeOfType(Builder $query, DiscountRuleType $type): Builder
    {
        return $query->where('rule_type', $type);
    }
}
