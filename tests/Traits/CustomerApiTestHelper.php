<?php

namespace Tests\Traits;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerUser;
use Illuminate\Testing\TestResponse;

trait CustomerApiTestHelper
{
    /**
     * Create an authenticated customer user with an active customer.
     *
     * @param  array<string, mixed>  $customerUserOverrides
     * @param  array<string, mixed>  $customerOverrides
     * @return array{customer_user: CustomerUser, customer: Customer, token: string}
     */
    protected function createAuthenticatedCustomerUser(array $customerUserOverrides = [], array $customerOverrides = []): array
    {
        $customer = Customer::factory()
            ->active()
            ->create($customerOverrides);

        $customerUser = CustomerUser::factory()
            ->create(array_merge(
                ['customer_id' => $customer->id],
                $customerUserOverrides,
            ));

        $token = $customerUser->createToken('test-token')->plainTextToken;

        return [
            'customer_user' => $customerUser,
            'customer' => $customer,
            'token' => $token,
        ];
    }

    /**
     * @return TestResponse<\Illuminate\Http\Response>
     */
    protected function customerGet(string $uri, string $token): TestResponse
    {
        return $this->getJson($uri, [
            'Authorization' => 'Bearer '.$token,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TestResponse<\Illuminate\Http\Response>
     */
    protected function customerPost(string $uri, array $data, string $token): TestResponse
    {
        return $this->postJson($uri, $data, [
            'Authorization' => 'Bearer '.$token,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TestResponse<\Illuminate\Http\Response>
     */
    protected function customerPatch(string $uri, array $data, string $token): TestResponse
    {
        return $this->patchJson($uri, $data, [
            'Authorization' => 'Bearer '.$token,
        ]);
    }

    /**
     * @return TestResponse<\Illuminate\Http\Response>
     */
    protected function customerDelete(string $uri, string $token): TestResponse
    {
        return $this->deleteJson($uri, [], [
            'Authorization' => 'Bearer '.$token,
        ]);
    }
}
