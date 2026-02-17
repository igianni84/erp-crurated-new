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
use Stringable;

class InboundScheduleTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Check expected inbound deliveries for goods NOT yet received. '
            .'Returns only purchase orders where goods are still pending arrival '
            .'(no inbound records yet). Includes destination warehouse. '
            .'PO status meanings: Sent = PO communicated to supplier, awaiting confirmation; '
            .'Confirmed = supplier confirmed the order, goods expected.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days_ahead' => $schema->integer()->min(1)->max(90)->default(30),
            'include_confirmed_with_inbounds' => $schema->string()
                ->enum(['true', 'false'])
                ->default('false'),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Standard;
    }

    public function handle(Request $request): Stringable|string
    {
        $daysAhead = (int) ($request['days_ahead'] ?? 30);
        $includeAlreadyReceived = ($request['include_confirmed_with_inbounds'] ?? 'false') === 'true';

        $now = Carbon::now();
        $endDate = $now->copy()->addDays($daysAhead);

        $statuses = [PurchaseOrderStatus::Sent, PurchaseOrderStatus::Confirmed];

        $query = PurchaseOrder::query()
            ->whereIn('status', $statuses)
            ->whereNotNull('expected_delivery_start')
            ->whereBetween('expected_delivery_start', [$now->toDateString(), $endDate->toDateString()])
            ->with(['supplier'])
            ->withCount('inbounds')
            ->orderBy('expected_delivery_start', 'asc');

        // By default, exclude POs that already have inbound records (goods already received)
        if (! $includeAlreadyReceived) {
            $query->whereDoesntHave('inbounds');
        }

        $orders = $query->get();

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
                'destination_warehouse' => $order->destination_warehouse ?? 'Not specified',
                'has_inbound' => ((int) $order->getAttribute('inbounds_count')) > 0,
            ];
        }

        // Group summary by warehouse
        $byWarehouse = [];
        foreach ($orders as $order) {
            $wh = $order->destination_warehouse ?? 'Not specified';
            if (! isset($byWarehouse[$wh])) {
                $byWarehouse[$wh] = ['count' => 0, 'total_bottles' => 0];
            }
            $byWarehouse[$wh]['count']++;
            $byWarehouse[$wh]['total_bottles'] += $order->quantity;
        }

        return (string) json_encode([
            'total_pending_arrival' => $orders->count(),
            'total_bottles' => $orders->sum('quantity'),
            'note' => 'Only shows POs where goods have NOT yet been received (no inbound records). '
                .'Sent = PO sent TO the supplier (awaiting their confirmation). '
                .'Confirmed = supplier confirmed, goods expected to arrive.',
            'by_warehouse' => $byWarehouse,
            'purchase_orders' => $orderList,
        ]);
    }
}
