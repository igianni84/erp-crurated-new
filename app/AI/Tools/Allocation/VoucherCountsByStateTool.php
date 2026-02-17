<?php

namespace App\AI\Tools\Allocation;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Allocation\VoucherLifecycleState;
use App\Models\Allocation\Voucher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class VoucherCountsByStateTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get voucher distribution across lifecycle states.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->string(),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Basic;
    }

    public function handle(Request $request): Stringable|string
    {
        $query = Voucher::query();

        if (isset($request['customer_id'])) {
            $query->where('customer_id', (string) $request['customer_id']);
        }

        $total = (clone $query)->count();

        $stateCounts = (clone $query)
            ->select('lifecycle_state', DB::raw('COUNT(*) as count'))
            ->groupBy('lifecycle_state')
            ->pluck('count', 'lifecycle_state');

        $byState = [];
        $active = 0;
        foreach (VoucherLifecycleState::cases() as $state) {
            $count = (int) ($stateCounts[$state->value] ?? 0);
            $byState[$state->label()] = $count;
            if ($state === VoucherLifecycleState::Issued || $state === VoucherLifecycleState::Locked) {
                $active += $count;
            }
        }

        return (string) json_encode([
            'total_vouchers' => $total,
            'by_state' => $byState,
            'active_vouchers' => $active,
        ]);
    }
}
