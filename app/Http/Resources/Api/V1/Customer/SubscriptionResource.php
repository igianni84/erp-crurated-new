<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Finance\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_type' => $this->plan_type->value,
            'plan_name' => $this->plan_name,
            'billing_cycle' => $this->billing_cycle->value,
            'amount' => (string) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'started_at' => $this->started_at->toDateString(),
            'next_billing_date' => $this->next_billing_date->toDateString(),
            'cancelled_at' => $this->cancelled_at?->toDateString(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
