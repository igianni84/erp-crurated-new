<?php

namespace App\AI\Tools\Finance;

use App\AI\Tools\BaseTool;
use App\Enums\AI\ToolAccessLevel;
use App\Enums\Finance\CreditNoteStatus;
use App\Models\Finance\CreditNote;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreditNoteSummaryTool extends BaseTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get a summary of credit notes for a given period, grouped by status.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()
                ->enum(['today', 'this_week', 'this_month', 'last_month', 'this_quarter', 'this_year', 'last_7_days', 'last_30_days'])
                ->default('this_month'),
            'status' => $schema->string()
                ->enum(array_map(fn (CreditNoteStatus $s): string => $s->value, CreditNoteStatus::cases())),
        ];
    }

    protected function requiredAccessLevel(): ToolAccessLevel
    {
        return ToolAccessLevel::Full;
    }

    public function handle(Request $request): Stringable|string
    {
        [$from, $to] = $this->parsePeriod($request['period'] ?? 'this_month');

        $query = CreditNote::query()
            ->whereBetween('created_at', [$from, $to]);

        if (isset($request['status'])) {
            $status = CreditNoteStatus::tryFrom((string) $request['status']);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        $total = (clone $query)->count();
        $totalAmount = (string) ((clone $query)->sum('amount'));

        $byStatus = [];
        $statusData = (clone $query)
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('status')
            ->get();

        foreach (CreditNoteStatus::cases() as $status) {
            $row = $statusData->firstWhere('status', $status->value);
            $byStatus[$status->label()] = [
                'count' => $row !== null ? (int) $row->getAttribute('count') : 0,
                'amount' => $this->formatCurrency((string) ($row?->getAttribute('total_amount') ?? '0')),
            ];
        }

        return (string) json_encode([
            'total_credit_notes' => $total,
            'total_amount' => $this->formatCurrency($totalAmount),
            'by_status' => $byStatus,
        ]);
    }
}
