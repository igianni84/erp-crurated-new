<?php

namespace Database\Factories\Finance;

use App\Models\Finance\XeroToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<XeroToken>
 */
class XeroTokenFactory extends Factory
{
    protected $model = XeroToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'access_token' => $this->faker->sha256(),
            'refresh_token' => $this->faker->sha256(),
            'tenant_id' => $this->faker->uuid(),
            'token_type' => 'Bearer',
            'expires_in' => 1800,
            'expires_at' => now()->addMinutes(30),
            'scopes' => ['accounting.transactions', 'accounting.contacts'],
            'is_active' => true,
        ];
    }

    /**
     * Token that has expired.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinutes(10),
        ]);
    }

    /**
     * Token that is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    /**
     * Token expiring soon (within 5 minutes).
     */
    public function expiringSoon(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->addMinutes(3),
        ]);
    }
}
