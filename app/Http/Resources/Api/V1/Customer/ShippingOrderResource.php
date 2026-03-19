<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Fulfillment\ShippingOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShippingOrder
 */
class ShippingOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'packaging_preference' => $this->packaging_preference->value,
            'shipping_method' => $this->shipping_method,
            'carrier' => $this->carrier,
            'requested_ship_date' => $this->requested_ship_date?->toDateString(),
            'special_instructions' => $this->special_instructions,
            'lines' => $this->when($this->relationLoaded('lines'), fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'voucher_id' => $line->voucher_id,
                'status' => $line->status->value,
            ])),
            'shipments' => $this->when($this->relationLoaded('shipments'), fn () => $this->shipments->map(fn ($shipment) => [
                'id' => $shipment->id,
                'status' => $shipment->status->value,
                'tracking_number' => $shipment->tracking_number,
                'shipped_at' => $shipment->shipped_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
