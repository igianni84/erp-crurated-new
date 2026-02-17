<?php

namespace App\AI\Tools\Fulfillment;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Models\Fulfillment\ShippingOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PendingShippingOrdersTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get active (non-terminal) shipping orders to monitor fulfillment progress.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(['draft', 'planned', 'picking', 'shipped', 'on_hold']),
            'limit' => $schema->integer()->min(1)->max(50)->default(20),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Basic;
    }

    public function handle(Request $request): Stringable|string
    {
        $limit = (int) ($request['limit'] ?? 20);

        $query = ShippingOrder::query()
            ->whereNotIn('status', [ShippingOrderStatus::Completed, ShippingOrderStatus::Cancelled]);

        if (isset($request['status'])) {
            $status = ShippingOrderStatus::tryFrom((string) $request['status']);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        $totalPending = (clone $query)->count();

        $statusCounts = (clone $query)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $byStatus = [];
        foreach (ShippingOrderStatus::cases() as $status) {
            if (! $status->isTerminal()) {
                $byStatus[$status->label()] = (int) ($statusCounts[$status->value] ?? 0);
            }
        }

        $orders = (clone $query)
            ->with('customer')
            ->withCount('lines')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $orderList = [];
        foreach ($orders as $order) {
            $orderList[] = [
                'id' => $order->id,
                'customer_name' => $order->customer !== null ? $order->customer->getName() : 'Unknown',
                'status' => $order->status->label(),
                'line_count' => (int) $order->getAttribute('lines_count'),
                'created_at' => $this->formatDate($order->created_at),
                'requested_ship_date' => $order->requested_ship_date !== null ? $this->formatDate($order->requested_ship_date) : null,
            ];
        }

        return (string) json_encode([
            'total_pending' => $totalPending,
            'by_status' => $byStatus,
            'orders' => $orderList,
        ]);
    }
}
