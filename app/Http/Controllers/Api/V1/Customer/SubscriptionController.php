<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ApiResponseTrait;
use App\Http\Resources\Api\V1\Customer\SubscriptionResource;
use App\Models\Customer\CustomerUser;
use App\Models\Finance\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
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

        $subscriptions = $customer->subscriptions()
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Subscriptions retrieved.',
            'data' => SubscriptionResource::collection($subscriptions),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ],
        ]);
    }

    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');
        $customer = $customerUser->customer;

        if ($customer === null || $subscription->customer_id !== $customer->id) {
            abort(403, 'Access denied.');
        }

        return $this->success(
            (new SubscriptionResource($subscription))->resolve(),
            'Subscription retrieved.',
        );
    }
}
