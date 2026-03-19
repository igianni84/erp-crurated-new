<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\Allocation\VoucherLifecycleState;
use App\Enums\Fulfillment\ShippingOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CreateShippingOrderRequest;
use App\Http\Resources\Api\ApiResponseTrait;
use App\Http\Resources\Api\V1\Customer\ShippingOrderResource;
use App\Models\Customer\CustomerUser;
use App\Models\Fulfillment\ShippingOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingOrderController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');
        $customer = $customerUser->customer;

        if ($customer === null) {
            return $this->error('Customer not found.', 404);
        }

        $query = $customer->shippingOrders()
            ->with(['lines', 'shipments']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $orders = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Shipping orders retrieved.',
            'data' => ShippingOrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, ShippingOrder $shippingOrder): JsonResponse
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');
        $customer = $customerUser->customer;

        if ($customer === null || $shippingOrder->customer_id !== $customer->id) {
            abort(403, 'Access denied.');
        }

        $shippingOrder->load(['lines', 'shipments']);

        return $this->success(
            (new ShippingOrderResource($shippingOrder))->resolve(),
            'Shipping order retrieved.',
        );
    }

    public function store(CreateShippingOrderRequest $request): JsonResponse
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');
        $customer = $customerUser->customer;

        if ($customer === null) {
            return $this->error('Customer not found.', 404);
        }

        /** @var array{voucher_ids: list<string>, shipping_address_id: string, special_instructions?: string} $validated */
        $validated = $request->validated();

        // Verify all vouchers belong to this customer and are in Issued state
        $vouchers = $customer->vouchers()
            ->whereIn('id', $validated['voucher_ids'])
            ->get();

        if ($vouchers->count() !== count($validated['voucher_ids'])) {
            return $this->error('One or more vouchers were not found or do not belong to you.', 422);
        }

        $nonIssuedVouchers = $vouchers->filter(
            fn ($v) => $v->lifecycle_state !== VoucherLifecycleState::Issued
        );

        if ($nonIssuedVouchers->isNotEmpty()) {
            return $this->error('One or more vouchers are not eligible for shipping (not in Issued state).', 422);
        }

        // Verify address belongs to this customer
        $address = $customer->addresses()
            ->where('id', $validated['shipping_address_id'])
            ->first();

        if ($address === null) {
            return $this->error('The selected shipping address was not found.', 422);
        }

        $shippingOrder = ShippingOrder::create([
            'customer_id' => $customer->id,
            'status' => ShippingOrderStatus::Draft,
            'destination_address' => $address->getFormattedAddress(),
            'special_instructions' => $validated['special_instructions'] ?? null,
        ]);

        // Create shipping order lines for each voucher
        foreach ($vouchers as $voucher) {
            $shippingOrder->lines()->create([
                'voucher_id' => $voucher->id,
                'allocation_id' => $voucher->allocation_id,
            ]);
        }

        $shippingOrder->load(['lines', 'shipments']);

        return $this->success(
            (new ShippingOrderResource($shippingOrder))->resolve(),
            'Shipping order created.',
            201,
        );
    }
}
