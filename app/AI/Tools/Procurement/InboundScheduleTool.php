<?php

namespace App\AI\Tools\Procurement;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Procurement\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class InboundScheduleTool extends BaseTool implements Tool
{
    public function description(): \Stringable|string
    {
        return 'Check expected inbound deliveries and incoming stock schedule.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days_ahead' => $schema->integer()->min(1)->max(90)->default(30),
            'include_draft' => $schema->string()
                ->enum(['true', 'false'])
                ->default('false'),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Standard;
    }

    public function handle(Request $request): \Stringable|string
    {
        $daysAhead = (int) ($request['days_ahead'] ?? 30);
        $includeDraft = ($request['include_draft'] ?? 'false') === 'true';

        $now = Carbon::now();
        $endDate = $now->copy()->addDays($daysAhead);

        $statuses = [PurchaseOrderStatus::Sent, PurchaseOrderStatus::Confirmed];
        if ($includeDraft) {
            $statuses[] = PurchaseOrderStatus::Draft;
        }

        $orders = PurchaseOrder::query()
            ->whereIn('status', $statuses)
            ->whereNotNull('expected_delivery_start')
            ->whereBetween('expected_delivery_start', [$now->toDateString(), $endDate->toDateString()])
            ->with(['supplier', 'procurementIntent'])
            ->withCount('inbounds')
            ->orderBy('expected_delivery_start', 'asc')
            ->get();

        $orderList = [];
        foreach ($orders as $order) {
            $orderList[] = [
                'id' => $order->id,
                'expected_delivery_start' => $this->formatDate($order->expected_delivery_start),
                'expected_delivery_end' => $order->expected_delivery_end !== null ? $this->formatDate($order->expected_delivery_end) : null,
                'supplier_name' => $order->supplier !== null ? $order->supplier->legal_name : 'Unknown',
                'quantity' => $order->quantity,
                'unit_cost' => $this->formatCurrency((string) $order->unit_cost, $order->currency ?? 'EUR'),
                'currency' => $order->currency,
                'status' => $order->status->label(),
                'inbound_count' => (int) $order->getAttribute('inbounds_count'),
            ];
        }

        return (string) json_encode([
            'total_expected' => $orders->count(),
            'purchase_orders' => $orderList,
        ]);
    }
}
