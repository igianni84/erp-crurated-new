<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Customer\CustomerUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthTokenResource extends JsonResource
{
    /**
     * @var array{token: string, customer_user: CustomerUser}
     */
    private array $tokenData;

    /**
     * @param  array{token: string, customer_user: CustomerUser}  $tokenData
     */
    public function __construct(array $tokenData)
    {
        $this->tokenData = $tokenData;
        parent::__construct($tokenData);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->tokenData['token'],
            'token_type' => 'Bearer',
            'user' => new CustomerUserResource($this->tokenData['customer_user']),
        ];
    }
}
