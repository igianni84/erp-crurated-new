<?php

namespace App\AI\Tools\Customer;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Models\Customer\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CustomerSearchTool extends BaseTool implements Tool
{
    public function description(): \Stringable|string
    {
        return 'Search for a customer by name or email.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Basic;
    }

    public function handle(Request $request): \Stringable|string
    {
        $search = (string) $request['query'];

        if (mb_strlen($search) < 2) {
            return (string) json_encode(['error' => 'Search query must be at least 2 characters.']);
        }

        $like = '%'.$search.'%';

        $customers = Customer::query()
            ->where(function ($q) use ($like): void {
                $q->where('name', 'LIKE', $like)
                    ->orWhere('email', 'LIKE', $like)
                    ->orWhereHas('party', function ($p) use ($like): void {
                        $p->where('legal_name', 'LIKE', $like);
                    });
            })
            ->with(['party', 'activeMembership'])
            ->withCount(['vouchers', 'shippingOrders'])
            ->limit(10)
            ->get();

        $results = $customers->map(function (Customer $customer): array {
            return [
                'customer_name' => $customer->getName(),
                'email' => $customer->email,
                'status' => $customer->status->label(),
                'customer_type' => $customer->customer_type->label(),
                'membership_tier' => $customer->activeMembership?->tier?->label() ?? 'None',
                'voucher_count' => (int) $customer->getAttribute('vouchers_count'),
                'shipping_order_count' => (int) $customer->getAttribute('shipping_orders_count'),
            ];
        })->values()->all();

        return (string) json_encode([
            'results' => $results,
            'count' => count($results),
        ]);
    }
}
