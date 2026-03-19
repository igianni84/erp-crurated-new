<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\UpdateProfileRequest;
use App\Http\Resources\Api\ApiResponseTrait;
use App\Http\Resources\Api\V1\Customer\CustomerProfileResource;
use App\Models\Customer\CustomerUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponseTrait;

    public function show(Request $request): JsonResponse
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');
        $customer = $customerUser->customer;

        if ($customer === null) {
            return $this->error('Customer not found.', 404);
        }

        $customer->load(['party', 'membership']);

        return $this->success(
            (new CustomerProfileResource($customer))->resolve(),
            'Profile retrieved.',
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');

        $customerUser->update($request->validated());

        return $this->success(
            ['name' => $customerUser->name],
            'Profile updated.',
        );
    }
}
