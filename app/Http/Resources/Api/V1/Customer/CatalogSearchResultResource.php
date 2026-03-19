<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Pim\WineVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WineVariant
 */
class CatalogSearchResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $master = $this->wineMaster;

        return [
            'id' => $this->id,
            'wine_name' => $master?->name,
            'producer' => $master?->producer_name,
            'vintage_year' => $this->vintage_year,
            'country' => $master?->country_name,
            'region' => $master?->region_name,
            'appellation' => $master?->appellation_name,
            'classification' => $master?->classification,
            'description' => $this->description ?? $master?->description,
            'formats' => ($this->relationLoaded('sellableSkus'))
                ? $this->sellableSkus
                    ->map(fn ($sku) => [
                        'id' => $sku->id,
                        'sku_code' => $sku->sku_code,
                        'format' => $sku->format?->name,
                    ])
                    ->values()
                    ->all()
                : [],
        ];
    }
}
