<?php

namespace App\AI\Tools\Customer;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Models\Customer\Customer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CustomerVoucherCountTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get voucher counts for a specific customer, grouped by lifecycle state.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->string(),
            'customer_name' => $schema->string(),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Basic;
    }

    public function handle(Request $request): Stringable|string
    {
        $customerId = $request['customer_id'] ?? null;
        $customerName = $request['customer_name'] ?? null;

        if ($customerId === null && $customerName === null) {
            return (string) json_encode(['error' => 'Please provide either customer_id or customer_name.']);
        }

        $customer = null;

        if ($customerId !== null) {
            $customer = Customer::with('party')->find($customerId);
            if ($customer === null) {
                return (string) json_encode(['error' => "No customer found with ID '{$customerId}'."]);
            }
        } else {
            $like = '%'.$customerName.'%';
            $matches = Customer::query()
                ->where(function ($q) use ($like): void {
                    $q->where('name', 'LIKE', $like)
                        ->orWhereHas('party', function ($p) use ($like): void {
                            $p->where('legal_name', 'LIKE', $like);
                        });
                })
                ->with('party')
                ->limit(10)
                ->get();

            $disambiguation = $this->disambiguateResults($matches, (string) $customerName, 'name');
            if ($disambiguation !== null) {
                return (string) json_encode(['message' => $disambiguation]);
            }

            $customer = $matches->first();
        }

        if ($customer === null) {
            return (string) json_encode(['error' => 'Customer not found.']);
        }

        $voucherCounts = $customer->vouchers()
            ->select('lifecycle_state', DB::raw('COUNT(*) as count'))
            ->groupBy('lifecycle_state')
            ->pluck('count', 'lifecycle_state');

        $byState = [];
        $total = 0;
        foreach (VoucherLifecycleState::cases() as $state) {
            $count = (int) ($voucherCounts[$state->value] ?? 0);
            $byState[$state->label()] = $count;
            $total += $count;
        }

        return (string) json_encode([
            'customer_name' => $customer->getName(),
            'total_vouchers' => $total,
            'by_state' => $byState,
        ]);
    }
}
