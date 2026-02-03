<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Voucher\TradingCompleteRequest;
use App\Models\Allocation\Voucher;
use App\Models\Customer\Customer;
use App\Services\Allocation\VoucherService;
use Illuminate\Http\JsonResponse;

/**
 * API Controller for Voucher operations.
 *
 * Handles external system callbacks and API interactions for vouchers.
 */
class VoucherController extends Controller
{
    public function __construct(
        protected VoucherService $voucherService
    ) {}

    /**
     * Complete external trading by transferring the voucher to a new customer.
     *
     * POST /api/vouchers/{voucher}/trading-complete
     *
     * This endpoint is called by external trading platforms when a trade
     * has been completed. It validates the trading reference and transfers
     * the voucher to the new customer while preserving allocation lineage.
     */
    public function tradingComplete(TradingCompleteRequest $request, Voucher $voucher): JsonResponse
    {
        /** @var int $newCustomerId */
        $newCustomerId = $request->validated('new_customer_id');

        /** @var string $tradingReference */
        $tradingReference = $request->validated('trading_reference');

        /** @var Customer $newCustomer */
        $newCustomer = Customer::findOrFail($newCustomerId);

        try {
            $voucher = $this->voucherService->completeTrading(
                $voucher,
                $tradingReference,
                $newCustomer
            );

            return response()->json([
                'success' => true,
                'message' => 'Trading completed successfully.',
                'data' => [
                    'voucher_id' => $voucher->id,
                    'new_customer_id' => $voucher->customer_id,
                    'lifecycle_state' => $voucher->lifecycle_state->value,
                    'suspended' => $voucher->suspended,
                    'external_trading_reference' => $voucher->external_trading_reference,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'validation_error',
            ], 422);
        }
    }
}
