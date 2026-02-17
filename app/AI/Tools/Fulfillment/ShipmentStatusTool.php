<?php

namespace App\AI\Tools\Fulfillment;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Fulfillment\ShipmentStatus;
use App\Models\Fulfillment\Shipment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ShipmentStatusTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Check shipment status by tracking number or get overview filtered by status and period.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tracking_number' => $schema->string(),
            'status' => $schema->string()
                ->enum(['preparing', 'shipped', 'in_transit', 'delivered', 'failed']),
            'period' => $schema->string()
                ->enum(['today', 'this_week', 'this_month', 'last_month', 'this_quarter', 'this_year', 'last_7_days', 'last_30_days'])
                ->default('last_7_days'),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Basic;
    }

    public function handle(Request $request): Stringable|string
    {
        $trackingNumber = $request['tracking_number'] ?? null;

        if ($trackingNumber !== null) {
            $shipment = Shipment::query()
                ->where('tracking_number', (string) $trackingNumber)
                ->with('shippingOrder.customer')
                ->first();

            if ($shipment === null) {
                return (string) json_encode(['message' => "No shipment found with tracking number '{$trackingNumber}'."]);
            }

            return (string) json_encode([
                'shipment' => $this->formatShipment($shipment),
            ]);
        }

        [$from, $to] = $this->parsePeriod($request['period'] ?? 'last_7_days');

        $query = Shipment::query()
            ->whereBetween('created_at', [$from, $to])
            ->with('shippingOrder.customer');

        if (isset($request['status'])) {
            $status = ShipmentStatus::tryFrom((string) $request['status']);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        $shipments = $query->orderBy('created_at', 'desc')->limit(20)->get();

        $list = [];
        foreach ($shipments as $shipment) {
            $list[] = $this->formatShipment($shipment);
        }

        return (string) json_encode([
            'total' => $shipments->count(),
            'shipments' => $list,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatShipment(Shipment $shipment): array
    {
        $customer = $shipment->shippingOrder->customer ?? null;

        return [
            'tracking_number' => $shipment->tracking_number,
            'status' => $shipment->status->label(),
            'carrier' => $shipment->carrier,
            'customer_name' => $customer !== null ? $customer->getName() : 'Unknown',
            'shipped_at' => $shipment->shipped_at !== null ? $this->formatDate($shipment->shipped_at) : null,
            'delivered_at' => $shipment->delivered_at !== null ? $this->formatDate($shipment->delivered_at) : null,
            'shipping_order_id' => $shipment->shipping_order_id,
        ];
    }
}
