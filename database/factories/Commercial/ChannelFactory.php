<?php

namespace Database\Factories\Commercial;

use App\Enums\Commercial\ChannelStatus;
use App\Enums\Commercial\ChannelType;
use App\Models\Commercial\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Channel',
            'channel_type' => ChannelType::B2c,
            'default_currency' => 'EUR',
            'allowed_commercial_models' => ['voucher_based', 'sell_through'],
            'status' => ChannelStatus::Active,
        ];
    }

    public function b2b(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel_type' => ChannelType::B2b,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChannelStatus::Inactive,
        ]);
    }
}
