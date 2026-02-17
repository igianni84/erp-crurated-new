<?php

namespace App\AI\Tools\Finance;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Finance\ReconciliationStatus;
use App\Models\Finance\Payment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PaymentReconciliationStatusTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get payment reconciliation status with breakdown by state.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(array_map(fn (ReconciliationStatus $s): string => $s->value, ReconciliationStatus::cases())),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Full;
    }

    public function handle(Request $request): Stringable|string
    {
        $total = Payment::count();

        $byStatus = [];
        $statusCounts = Payment::query()
            ->select('reconciliation_status', DB::raw('COUNT(*) as count'))
            ->groupBy('reconciliation_status')
            ->pluck('count', 'reconciliation_status');

        foreach (ReconciliationStatus::cases() as $status) {
            $byStatus[$status->label()] = (int) ($statusCounts[$status->value] ?? 0);
        }

        $result = [
            'total_payments' => $total,
            'by_reconciliation_status' => $byStatus,
        ];

        $filterStatus = isset($request['status']) ? ReconciliationStatus::tryFrom((string) $request['status']) : null;

        if ($filterStatus === ReconciliationStatus::Mismatched) {
            $mismatched = Payment::query()
                ->where('reconciliation_status', ReconciliationStatus::Mismatched)
                ->with('customer.party')
                ->limit(20)
                ->get();

            $result['mismatched_details'] = $mismatched->map(function (Payment $payment): array {
                return [
                    'payment_reference' => $payment->payment_reference,
                    'customer_name' => $payment->customer?->getName() ?? 'Unknown',
                    'amount' => $this->formatCurrency((string) $payment->amount),
                    'source' => $payment->source->label(),
                    'received_at' => $this->formatDate($payment->received_at),
                ];
            })->values()->all();
        }

        return (string) json_encode($result);
    }
}
