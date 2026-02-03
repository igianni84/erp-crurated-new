<?php

namespace App\Models\Commercial;

use App\Models\Pim\SellableSku;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BundleComponent Model
 *
 * Represents a component within a Bundle.
 * Each component references a Sellable SKU and specifies a quantity.
 *
 * Components are Sellable SKUs (not Bottle SKUs), meaning they include
 * the full commercial packaging specification (wine variant + format + case configuration).
 *
 * @property string $id
 * @property string $bundle_id
 * @property string $sellable_sku_id
 * @property int $quantity
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class BundleComponent extends Model
{
    use HasFactory;
    use HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bundle_components';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'bundle_id',
        'sellable_sku_id',
        'quantity',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * Get the bundle that this component belongs to.
     *
     * @return BelongsTo<Bundle, $this>
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    /**
     * Get the sellable SKU for this component.
     *
     * @return BelongsTo<SellableSku, $this>
     */
    public function sellableSku(): BelongsTo
    {
        return $this->belongsTo(SellableSku::class);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Check if this component has a valid quantity.
     */
    public function hasValidQuantity(): bool
    {
        return $this->quantity > 0;
    }

    /**
     * Get the quantity as integer.
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * Check if the referenced SKU exists and is active.
     */
    public function hasActiveSku(): bool
    {
        $sku = $this->sellableSku;

        return $sku !== null && $sku->isActive();
    }

    /**
     * Check if the referenced SKU has an active allocation.
     */
    public function hasAllocation(): bool
    {
        $sku = $this->sellableSku;

        if ($sku === null) {
            return false;
        }

        // Check for active allocations matching this SKU's wine_variant_id and format_id
        return \App\Models\Allocation\Allocation::query()
            ->where('wine_variant_id', $sku->wine_variant_id)
            ->where('format_id', $sku->format_id)
            ->where('lifecycle_status', 'active')
            ->exists();
    }

    // =========================================================================
    // Display Helpers
    // =========================================================================

    /**
     * Get the SKU code of the component's sellable SKU.
     */
    public function getSkuCode(): ?string
    {
        return $this->sellableSku?->sku_code;
    }

    /**
     * Get a display string for this component (e.g., "2x SASS-2018-750-6OWC").
     */
    public function getDisplayString(): string
    {
        $skuCode = $this->getSkuCode() ?? 'Unknown SKU';

        return $this->quantity.'x '.$skuCode;
    }

    /**
     * Get the wine name for this component.
     */
    public function getWineName(): ?string
    {
        $sku = $this->sellableSku;

        if ($sku === null) {
            return null;
        }

        $wineVariant = $sku->wineVariant;

        if ($wineVariant === null) {
            return null;
        }

        $wineMaster = $wineVariant->wineMaster;

        if ($wineMaster === null) {
            return null;
        }

        return $wineMaster->name;
    }

    /**
     * Get the vintage year for this component.
     */
    public function getVintageYear(): ?int
    {
        return $this->sellableSku?->wineVariant?->vintage_year;
    }

    /**
     * Get a detailed description of this component.
     */
    public function getDetailedDescription(): string
    {
        $sku = $this->sellableSku;

        if ($sku === null) {
            return $this->quantity.'x [SKU not found]';
        }

        $wineName = $this->getWineName() ?? 'Unknown Wine';
        $vintage = $this->getVintageYear();
        $format = $sku->format;
        $caseConfig = $sku->caseConfiguration;

        $description = $this->quantity.'x '.$wineName;

        if ($vintage !== null) {
            $description .= ' '.$vintage;
        }

        if ($format !== null) {
            $description .= ' ('.$format->volume_ml.'ml)';
        }

        if ($caseConfig !== null) {
            $description .= ' - '.$caseConfig->bottles_per_case.' bottles/'.$caseConfig->case_type;
        }

        return $description;
    }

    // =========================================================================
    // Validation Helpers
    // =========================================================================

    /**
     * Validate this component for bundle activation.
     *
     * @return array<string, string> Validation errors keyed by field
     */
    public function validate(): array
    {
        $errors = [];

        if (! $this->hasValidQuantity()) {
            $errors['quantity'] = 'Quantity must be greater than 0';
        }

        if ($this->sellableSku === null) {
            $errors['sellable_sku_id'] = 'Sellable SKU not found';
        } elseif (! $this->sellableSku->isActive()) {
            $errors['sellable_sku_id'] = 'Sellable SKU "'.$this->getSkuCode().'" is not active';
        }

        return $errors;
    }

    /**
     * Check if this component is valid.
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }
}
