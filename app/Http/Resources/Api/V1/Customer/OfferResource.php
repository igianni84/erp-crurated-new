<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Commercial\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Offer
 */
class OfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sku = $this->sellableSku;
        $channel = $this->channel;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'offer_type' => $this->offer_type->value,
            'visibility' => $this->visibility->value,
            'status' => $this->status->value,
            'valid_from' => $this->valid_from->toIso8601String(),
            'valid_to' => $this->valid_to?->toIso8601String(),
            'campaign_tag' => $this->campaign_tag,
            'sellable_sku' => ($this->relationLoaded('sellableSku') && $sku !== null)
                ? ['id' => $sku->id, 'sku_code' => $sku->sku_code]
                : null,
            'channel' => ($this->relationLoaded('channel') && $channel !== null)
                ? ['id' => $channel->id, 'name' => $channel->name]
                : null,
        ];
    }
}
