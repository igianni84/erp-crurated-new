<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Allocation\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Voucher
 */
class VoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $wineVariant = $this->wineVariant;
        $format = $this->format;

        $wineVariantData = null;
        if ($this->relationLoaded('wineVariant') && $wineVariant !== null) {
            $wineMaster = $wineVariant->relationLoaded('wineMaster') ? $wineVariant->wineMaster : null;
            $wineVariantData = [
                'id' => $wineVariant->id,
                'vintage_year' => $wineVariant->vintage_year,
                'wine_master' => $wineMaster !== null
                    ? ['name' => $wineMaster->name]
                    : null,
            ];
        }

        return [
            'id' => $this->id,
            'lifecycle_state' => $this->lifecycle_state->value,
            'tradable' => $this->tradable,
            'giftable' => $this->giftable,
            'suspended' => $this->suspended,
            'wine_variant' => $wineVariantData,
            'format' => ($this->relationLoaded('format') && $format !== null)
                ? ['id' => $format->id, 'name' => $format->name, 'volume_ml' => $format->volume_ml]
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
