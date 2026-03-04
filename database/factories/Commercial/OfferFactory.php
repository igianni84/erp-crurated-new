<?php

namespace Database\Factories\Commercial;

use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\OfferType;
use App\Enums\Commercial\OfferVisibility;
use App\Models\Commercial\Channel;
use App\Models\Commercial\Offer;
use App\Models\Commercial\PriceBook;
use App\Models\Pim\SellableSku;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).' Offer',
            'sellable_sku_id' => SellableSku::factory(),
            'channel_id' => Channel::factory(),
            'price_book_id' => PriceBook::factory(),
            'offer_type' => OfferType::Standard,
            'visibility' => OfferVisibility::Public,
            'valid_from' => now(),
            'status' => OfferStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OfferStatus::Active,
        ]);
    }

    public function promotion(): static
    {
        return $this->state(fn (array $attributes) => [
            'offer_type' => OfferType::Promotion,
        ]);
    }
}
