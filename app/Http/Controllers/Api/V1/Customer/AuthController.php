<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\LoginRequest;
use App\Http\Resources\Api\ApiResponseTrait;
use App\Http\Resources\Api\V1\Customer\AuthTokenResource;
use App\Http\Resources\Api\V1\Customer\CustomerUserResource;
use App\Models\Customer\CustomerUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function login(LoginRequest $request): JsonResponse
    {
        /** @var array{email: string, password: string} $validated */
        $validated = $request->validated();

        $customerUser = CustomerUser::query()
            ->where('email', $validated['email'])
            ->first();

        if ($customerUser === null || ! Hash::check($validated['password'], $customerUser->password)) {
            return $this->error('Invalid credentials.', 401);
        }

        if (! $customerUser->isActive()) {
            return $this->error('Your account has been '.$customerUser->status->value.'. Please contact support.', 403);
        }

        $customer = $customerUser->customer;

        if ($customer === null || ! $customer->isActive()) {
            return $this->error('Your customer account is not active. Please contact support.', 403);
        }

        $token = $customerUser->createToken('customer-api')->plainTextToken;

        $customerUser->load(['customer.party', 'customer.membership']);

        return $this->success(
            (new AuthTokenResource(['token' => $token, 'customer_user' => $customerUser]))->resolve(),
            'Login successful.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');
        $customerUser->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var CustomerUser $customerUser */
        $customerUser = $request->user('customer');
        $customerUser->load(['customer.party', 'customer.membership']);

        return $this->success(
            (new CustomerUserResource($customerUser))->resolve(),
            'User profile retrieved.',
        );
    }
}
