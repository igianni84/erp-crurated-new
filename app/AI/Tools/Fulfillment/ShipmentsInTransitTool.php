<?php

namespace App\AI\Tools\Fulfillment;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Fulfillment\ShipmentStatus;
use App\Models\Fulfillment\Shipment;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ShipmentsInTransitTool extends BaseTool implements Tool
{
    public function description(): \Stringable|string
    {
        return 'Get count and details of shipments currently in transit (non-terminal states).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Overview;
    }

    public function handle(Request $request): \Stringable|string
    {
        $nonTerminalStatuses = array_filter(
            ShipmentStatus::cases(),
            fn (ShipmentStatus $s): bool => ! $s->isTerminal()
        );

        $shipments = Shipment::query()
            ->whereIn('status', $nonTerminalStatuses)
            ->with('shippingOrder.customer')
            ->orderBy('shipped_at', 'desc')
            ->get();

        $list = [];
        $now = Carbon::now();
        foreach ($shipments as $shipment) {
            $customer = $shipment->shippingOrder->customer ?? null;
            $daysSinceDispatch = $shipment->shipped_at !== null
                ? (int) $shipment->shipped_at->diffInDays($now)
                : null;

            $list[] = [
                'tracking_number' => $shipment->tracking_number,
                'status' => $shipment->status->label(),
                'customer_name' => $customer !== null ? $customer->getName() : 'Unknown',
                'carrier' => $shipment->carrier,
                'shipped_at' => $shipment->shipped_at !== null ? $this->formatDate($shipment->shipped_at) : null,
                'days_since_dispatch' => $daysSinceDispatch,
            ];
        }

        return (string) json_encode([
            'count_in_transit' => $shipments->count(),
            'shipments' => $list,
        ]);
    }
}
