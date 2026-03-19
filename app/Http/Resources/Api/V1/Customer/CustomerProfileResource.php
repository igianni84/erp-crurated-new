<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Customer\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $party = $this->party;
        $membership = $this->membership;

        return [
            'id' => $this->id,
            'customer_type' => $this->customer_type->value,
            'status' => $this->status->value,
            'party' => ($this->relationLoaded('party') && $party !== null)
                ? ['legal_name' => $party->legal_name, 'party_type' => $party->party_type->value]
                : null,
            'membership' => ($this->relationLoaded('membership') && $membership !== null)
                ? [
                    'tier' => $membership->tier->value,
                    'status' => $membership->status->value,
                    'effective_from' => $membership->effective_from?->toIso8601String(),
                    'effective_to' => $membership->effective_to?->toIso8601String(),
                ]
                : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
