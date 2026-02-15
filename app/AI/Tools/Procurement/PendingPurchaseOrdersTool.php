<?php

namespace App\AI\Tools\Procurement;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class PendingPurchaseOrdersTool extends BaseTool implements Tool
{
    public function description(): \Stringable|string
    {
        return 'Get the list of open (non-closed) purchase orders for procurement monitoring.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(['draft', 'sent', 'confirmed']),
            'limit' => $schema->integer()->min(1)->max(50)->default(20),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Standard;
    }

    public function handle(Request $request): \Stringable|string
    {
        $limit = (int) ($request['limit'] ?? 20);

        $query = PurchaseOrder::query()
            ->where('status', '!=', PurchaseOrderStatus::Closed);

        if (isset($request['status'])) {
            $status = PurchaseOrderStatus::tryFrom((string) $request['status']);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        $totalPending = (clone $query)->count();

        $orders = (clone $query)
            ->with(['supplier', 'procurementIntent'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $orderList = [];
        foreach ($orders as $order) {
            $orderList[] = [
                'id' => $order->id,
                'status' => $order->status->label(),
                'supplier_name' => $order->supplier !== null ? $order->supplier->legal_name : 'Unknown',
                'quantity' => $order->quantity,
                'unit_cost' => $this->formatCurrency((string) $order->unit_cost, $order->currency ?? 'EUR'),
                'currency' => $order->currency,
                'expected_delivery_start' => $order->expected_delivery_start !== null ? $this->formatDate($order->expected_delivery_start) : null,
            ];
        }

        return (string) json_encode([
            'total_pending' => $totalPending,
            'orders' => $orderList,
        ]);
    }
}
