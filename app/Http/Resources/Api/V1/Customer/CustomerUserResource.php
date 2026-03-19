<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Customer\CustomerUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerUser
 */
class CustomerUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'status' => $this->status->value,
            'customer' => new CustomerProfileResource($this->whenLoaded('customer')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
