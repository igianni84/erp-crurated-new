<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ApiResponseTrait;
use App\Http\Resources\Api\V1\Customer\VoucherCollection;
use App\Http\Resources\Api\V1\Customer\VoucherResource;
use App\Models\Allocation\Voucher;
use App\Models\Customer\CustomerUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CellarController extends Controller
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

        $query = $customer->vouchers()
            ->with(['wineVariant.wineMaster', 'format']);

        // Filter by lifecycle state
        if ($request->has('lifecycle_state')) {
            $query->where('lifecycle_state', $request->input('lifecycle_state'));
        }

        // Filter by tradable
        if ($request->has('tradable')) {
            $query->where('tradable', filter_var($request->input('tradable'), FILTER_VALIDATE_BOOLEAN));
        }

        $vouchers = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Cellar retrieved.',
            ...(new VoucherCollection($vouchers))->response($request)->getData(true),
        ]);
    }

    public function show(Request $request, Voucher $voucher): JsonResponse
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');
        $customer = $customerUser->customer;

        if ($customer === null || $voucher->customer_id !== $customer->id) {
            abort(403, 'Access denied.');
        }

        $voucher->load(['wineVariant.wineMaster', 'format']);

        return $this->success(
            (new VoucherResource($voucher))->resolve(),
            'Voucher retrieved.',
        );
    }
}
