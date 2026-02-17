<?php

namespace App\Models\Commercial;

use App\Enums\Commercial\BenefitType;
use App\Models\AuditLog;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * OfferBenefit Model
 *
 * Defines the benefits applied by an Offer.
 * Determines how the final price is calculated from the base Price Book price.
 *
 * Benefit types:
 * - none: Use Price Book price without modifications
 * - percentage_discount: Apply a percentage discount off the base price
 * - fixed_discount: Subtract a fixed amount from the base price
 * - fixed_price: Override the base price with a fixed amount
 *
 * Notes:
 * - benefit_type = none means the price comes directly from the Price Book
 * - discount_rule_id references reusable discount rules (optional)
 * - Benefit definitions are attached to offers, not applied standalone
 *
 * @property string $id
 * @property string $offer_id
 * @property BenefitType $benefit_type
 * @property string|null $benefit_value
 * @property string|null $discount_rule_id
 */
class OfferBenefit extends Model
{
    use Auditable;
    use HasFactory;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'offer_benefits';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'offer_id',
        'benefit_type',
        'benefit_value',
        'discount_rule_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'benefit_type' => BenefitType::class,
            'benefit_value' => 'decimal:2',
        ];
    }

    /**
     * Get the offer that owns this benefit.
     *
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Get the discount rule referenced by this benefit.
     *
     * @return BelongsTo<DiscountRule, $this>
     */
    public function discountRule(): BelongsTo
    {
        return $this->belongsTo(DiscountRule::class);
    }

    /**
     * Get the audit logs for this offer benefit.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    // =========================================================================
    // Benefit Type Helpers
    // =========================================================================

    /**
     * Check if the benefit type is "none" (use Price Book price directly).
     */
    public function isNone(): bool
    {
        return $this->benefit_type === BenefitType::None;
    }

    /**
     * Check if the benefit type is percentage discount.
     */
    public function isPercentageDiscount(): bool
    {
        return $this->benefit_type === BenefitType::PercentageDiscount;
    }

    /**
     * Check if the benefit type is fixed discount.
     */
    public function isFixedDiscount(): bool
    {
        return $this->benefit_type === BenefitType::FixedDiscount;
    }

    /**
     * Check if the benefit type is fixed price.
     */
    public function isFixedPrice(): bool
    {
        return $this->benefit_type === BenefitType::FixedPrice;
    }

    /**
     * Check if this benefit has any discount applied.
     */
    public function hasDiscount(): bool
    {
        return ! $this->isNone();
    }

    // =========================================================================
    // Value Helpers
    // =========================================================================

    /**
     * Check if the benefit has a value defined.
     */
    public function hasValue(): bool
    {
        return $this->benefit_value !== null;
    }

    /**
     * Get the benefit value as a float.
     */
    public function getValueAsFloat(): ?float
    {
        if ($this->benefit_value === null) {
            return null;
        }

        return (float) $this->benefit_value;
    }

    /**
     * Get the percentage value (for percentage discount type).
     * Returns null if not a percentage discount.
     */
    public function getPercentageValue(): ?float
    {
        if (! $this->isPercentageDiscount()) {
            return null;
        }

        return $this->getValueAsFloat();
    }

    /**
     * Get the fixed discount amount (for fixed discount type).
     * Returns null if not a fixed discount.
     */
    public function getFixedDiscountAmount(): ?float
    {
        if (! $this->isFixedDiscount()) {
            return null;
        }

        return $this->getValueAsFloat();
    }

    /**
     * Get the fixed price (for fixed price type).
     * Returns null if not a fixed price.
     */
    public function getFixedPrice(): ?float
    {
        if (! $this->isFixedPrice()) {
            return null;
        }

        return $this->getValueAsFloat();
    }

    // =========================================================================
    // Discount Rule Helpers
    // =========================================================================

    /**
     * Check if this benefit references a discount rule.
     */
    public function hasDiscountRule(): bool
    {
        return $this->discount_rule_id !== null;
    }

    /**
     * Get the discount rule ID.
     */
    public function getDiscountRuleId(): ?string
    {
        return $this->discount_rule_id;
    }

    // =========================================================================
    // Price Calculation
    // =========================================================================

    /**
     * Calculate the final price based on the benefit type and base price.
     *
     * @param  float  $basePrice  The base price from the Price Book
     * @return float The final price after applying the benefit
     */
    public function calculateFinalPrice(float $basePrice): float
    {
        return match ($this->benefit_type) {
            BenefitType::None => $basePrice,
            BenefitType::PercentageDiscount => $this->applyPercentageDiscount($basePrice),
            BenefitType::FixedDiscount => $this->applyFixedDiscount($basePrice),
            BenefitType::FixedPrice => $this->applyFixedPrice(),
        };
    }

    /**
     * Apply percentage discount to the base price.
     */
    private function applyPercentageDiscount(float $basePrice): float
    {
        $percentage = $this->getValueAsFloat() ?? 0;
        $discount = $basePrice * ($percentage / 100);

        return max(0, $basePrice - $discount);
    }

    /**
     * Apply fixed discount to the base price.
     */
    private function applyFixedDiscount(float $basePrice): float
    {
        $discount = $this->getValueAsFloat() ?? 0;

        return max(0, $basePrice - $discount);
    }

    /**
     * Apply fixed price override.
     */
    private function applyFixedPrice(): float
    {
        return max(0, $this->getValueAsFloat() ?? 0);
    }

    /**
     * Get the discount amount for a given base price.
     * Returns 0 if no discount is applied.
     */
    public function getDiscountAmount(float $basePrice): float
    {
        if ($this->isNone()) {
            return 0;
        }

        if ($this->isFixedPrice()) {
            $fixedPrice = $this->getValueAsFloat() ?? 0;

            return max(0, $basePrice - $fixedPrice);
        }

        $finalPrice = $this->calculateFinalPrice($basePrice);

        return max(0, $basePrice - $finalPrice);
    }

    /**
     * Get the discount percentage relative to the base price.
     * Returns 0 if no discount or base price is 0.
     */
    public function getDiscountPercentage(float $basePrice): float
    {
        if ($basePrice <= 0 || $this->isNone()) {
            return 0;
        }

        $discountAmount = $this->getDiscountAmount($basePrice);

        return ($discountAmount / $basePrice) * 100;
    }

    // =========================================================================
    // UI Helper Methods
    // =========================================================================

    /**
     * Get the benefit type label for UI display.
     */
    public function getBenefitTypeLabel(): string
    {
        return $this->benefit_type->label();
    }

    /**
     * Get the benefit type color for UI display.
     */
    public function getBenefitTypeColor(): string
    {
        return $this->benefit_type->color();
    }

    /**
     * Get the benefit type icon for UI display.
     */
    public function getBenefitTypeIcon(): string
    {
        return $this->benefit_type->icon();
    }

    /**
     * Get a display-friendly value with unit.
     */
    public function getFormattedValue(?string $currency = 'EUR'): string
    {
        if (! $this->hasValue()) {
            return '-';
        }

        $value = $this->getValueAsFloat() ?? 0;

        return match ($this->benefit_type) {
            BenefitType::None => '-',
            BenefitType::PercentageDiscount => number_format($value, 0).'%',
            BenefitType::FixedDiscount => $currency.' '.number_format($value, 2),
            BenefitType::FixedPrice => $currency.' '.number_format($value, 2),
        };
    }

    /**
     * Get a summary description of the benefit.
     */
    public function getBenefitSummary(?string $currency = 'EUR'): string
    {
        if ($this->isNone()) {
            return 'Price Book price (no discount)';
        }

        $value = $this->getValueAsFloat() ?? 0;

        return match ($this->benefit_type) {
            BenefitType::None => 'Price Book price (no discount)',
            BenefitType::PercentageDiscount => number_format($value, 0).'% off',
            BenefitType::FixedDiscount => $currency.' '.number_format($value, 2).' off',
            BenefitType::FixedPrice => 'Fixed price: '.$currency.' '.number_format($value, 2),
        };
    }

    /**
     * Get a detailed description of the benefit for display.
     */
    public function getDetailedDescription(?string $currency = 'EUR'): string
    {
        $lines = [];
        $lines[] = 'Benefit Type: '.$this->getBenefitTypeLabel();

        if ($this->hasValue()) {
            $lines[] = 'Value: '.$this->getFormattedValue($currency);
        }

        if ($this->hasDiscountRule()) {
            $lines[] = 'Discount Rule: '.$this->discount_rule_id;
        }

        return implode("\n", $lines);
    }
}
