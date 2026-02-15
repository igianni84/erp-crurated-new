<?php

namespace App\AI\Agents;

use App\AI\Tools\Allocation\AllocationStatusOverviewTool;
use App\AI\Tools\Allocation\BottlesSoldByProducerTool;
use App\AI\Tools\Allocation\VoucherCountsByStateTool;
use App\AI\Tools\Commercial\ActiveOffersTool;
use App\AI\Tools\Commercial\EmpAlertsTool;
use App\AI\Tools\Commercial\PriceBookCoverageTool;
use App\AI\Tools\Customer\CustomerSearchTool;
use App\AI\Tools\Customer\CustomerStatusSummaryTool;
use App\AI\Tools\Customer\CustomerVoucherCountTool;
use App\AI\Tools\Customer\TopCustomersByRevenueTool;
use App\AI\Tools\Finance\CreditNoteSummaryTool;
use App\AI\Tools\Finance\OutstandingInvoicesTool;
use App\AI\Tools\Finance\OverdueInvoicesTool;
use App\AI\Tools\Finance\PaymentReconciliationStatusTool;
use App\AI\Tools\Finance\RevenueSummaryTool;
use App\AI\Tools\Fulfillment\PendingShippingOrdersTool;
use App\AI\Tools\Fulfillment\ShipmentsInTransitTool;
use App\AI\Tools\Fulfillment\ShipmentStatusTool;
use App\AI\Tools\Inventory\CaseIntegrityStatusTool;
use App\AI\Tools\Inventory\StockLevelsByLocationTool;
use App\AI\Tools\Inventory\TotalBottlesCountTool;
use App\AI\Tools\Pim\DataQualityIssuesTool;
use App\AI\Tools\Pim\ProductCatalogSearchTool;
use App\AI\Tools\Procurement\InboundScheduleTool;
use App\AI\Tools\Procurement\PendingPurchaseOrdersTool;
use App\AI\Tools\Procurement\ProcurementIntentsStatusTool;
use App\Models\User;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

#[Provider('anthropic')]
#[Model('claude-sonnet-4-5-20250929')]
#[MaxSteps(10)]
#[Temperature(0.3)]
class ErpAssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        protected User $user
    ) {}

    public function instructions(): \Stringable|string
    {
        return (string) file_get_contents(app_path('AI/Prompts/erp-system-prompt.md'));
    }

    /**
     * @return array<\Laravel\Ai\Contracts\Tool>
     */
    public function tools(): array
    {
        if ($this->user->role === null) {
            return [];
        }

        $allowed = [];
        $denied = [];

        foreach ($this->allTools() as $tool) {
            if (method_exists($tool, 'authorizeForUser') && ! $tool->authorizeForUser($this->user)) {
                $denied[] = class_basename($tool);
            } else {
                $allowed[] = $tool;
            }
        }

        if ($denied !== []) {
            \Illuminate\Support\Facades\Log::info('AI tools denied for user', [
                'user_id' => $this->user->id,
                'role' => $this->user->role->value,
                'denied_tools' => $denied,
            ]);
        }

        return $allowed;
    }

    /**
     * @return array<\Laravel\Ai\Contracts\Tool>
     */
    protected function allTools(): array
    {
        return [
            // Allocation tools
            new AllocationStatusOverviewTool,
            new BottlesSoldByProducerTool,
            new VoucherCountsByStateTool,
            // Commercial tools
            new ActiveOffersTool,
            new EmpAlertsTool,
            new PriceBookCoverageTool,
            // Customer tools
            new CustomerSearchTool,
            new CustomerStatusSummaryTool,
            new CustomerVoucherCountTool,
            new TopCustomersByRevenueTool,
            // Inventory tools
            new CaseIntegrityStatusTool,
            new StockLevelsByLocationTool,
            new TotalBottlesCountTool,
            // Fulfillment tools
            new PendingShippingOrdersTool,
            new ShipmentStatusTool,
            new ShipmentsInTransitTool,
            // PIM tools
            new DataQualityIssuesTool,
            new ProductCatalogSearchTool,
            // Procurement tools
            new InboundScheduleTool,
            new PendingPurchaseOrdersTool,
            new ProcurementIntentsStatusTool,
            // Finance tools
            new CreditNoteSummaryTool,
            new OutstandingInvoicesTool,
            new OverdueInvoicesTool,
            new PaymentReconciliationStatusTool,
            new RevenueSummaryTool,
        ];
    }

    protected function maxConversationMessages(): int
    {
        return (int) config('ai-assistant.max_context_messages', 30);
    }
}
