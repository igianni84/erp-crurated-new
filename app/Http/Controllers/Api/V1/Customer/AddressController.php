<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreAddressRequest;
use App\Http\Requests\Api\V1\Customer\UpdateAddressRequest;
use App\Http\Resources\Api\ApiResponseTrait;
use App\Http\Resources\Api\V1\Customer\AddressResource;
use App\Models\Customer\Address;
use App\Models\Customer\CustomerUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
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

        $addresses = $customer->addresses()->latest()->get();

        return $this->success(
            AddressResource::collection($addresses)->resolve(),
            'Addresses retrieved.',
        );
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');
        $customer = $customerUser->customer;

        if ($customer === null) {
            return $this->error('Customer not found.', 404);
        }

        $address = $customer->addresses()->create($request->validated());

        // Set as default if requested
        if ($request->boolean('is_default')) {
            $address->setAsDefault();
        }

        return $this->success(
            (new AddressResource($address))->resolve(),
            'Address created.',
            201,
        );
    }

    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');
        $customer = $customerUser->customer;

        if ($customer === null || $address->addressable_id !== $customer->id) {
            abort(403, 'Access denied.');
        }

        $address->update($request->validated());

        if ($request->boolean('is_default')) {
            $address->setAsDefault();
        }

        return $this->success(
            (new AddressResource($address->fresh() ?? $address))->resolve(),
            'Address updated.',
        );
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');
        $customer = $customerUser->customer;

        if ($customer === null || $address->addressable_id !== $customer->id) {
            abort(403, 'Access denied.');
        }

        $address->delete();

        return $this->success(null, 'Address deleted.');
    }
}
