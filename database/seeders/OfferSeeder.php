<?php

namespace Database\Seeders;

use App\Enums\Commercial\BenefitType;
use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\OfferType;
use App\Enums\Commercial\OfferVisibility;
use App\Models\Commercial\Channel;
use App\Models\Commercial\Offer;
use App\Models\Commercial\OfferBenefit;
use App\Models\Commercial\OfferEligibility;
use App\Models\Commercial\PriceBook;
use App\Models\Pim\SellableSku;
use Illuminate\Database\Seeder;

/**
 * OfferSeeder - Creates commercial offers for products
 *
 * An Offer links a Sellable SKU to a Channel and Price Book, defining
 * when and how the product can be sold.
 */
class OfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get channels
        $channels = Channel::where('status', 'active')->get();

        if ($channels->isEmpty()) {
            $this->command->warn('No active channels found. Run ChannelSeeder first.');

            return;
        }

        // Get active price books
        $priceBooks = PriceBook::where('status', 'active')
            ->with('channel')
            ->get();

        if ($priceBooks->isEmpty()) {
            $this->command->warn('No active price books found. Run PriceBookSeeder first.');

            return;
        }

        // Get active SKUs (non-composite only)
        $skus = SellableSku::where('lifecycle_status', SellableSku::STATUS_ACTIVE)
            ->where('is_composite', false)
            ->with(['wineVariant.wineMaster'])
            ->get();

        if ($skus->isEmpty()) {
            $this->command->warn('No active SellableSku found. Run SellableSkuSeeder first.');

            return;
        }

        // Premium wines for special offers
        $premiumWines = ['Barolo Monfortino', 'Romanee-Conti Grand Cru', 'La Tache Grand Cru', 'Musigny Grand Cru'];
        $superTuscans = ['Sassicaia', 'Ornellaia', 'Tignanello', 'Solaia'];

        // Campaign tags for marketing
        $campaigns = [
            'spring_release_2024',
            'tuscan_collection',
            'piedmont_classics',
            'bordeaux_grands_crus',
            'burgundy_treasures',
            'holiday_2024',
            null, // Many offers have no campaign
        ];

        foreach ($skus as $sku) {
            $wineName = $sku->wineVariant?->wineMaster?->name ?? '';

            // Find matching price books for this SKU
            foreach ($priceBooks as $priceBook) {
                // Skip if SKU doesn't have an entry in this price book
                if (! $priceBook->entries()->where('sellable_sku_id', $sku->id)->exists()) {
                    continue;
                }

                $channel = $priceBook->channel;
                if (! $channel) {
                    continue;
                }

                // Determine offer type and visibility
                $offerType = OfferType::Standard;
                $visibility = OfferVisibility::Public;

                // Premium wines are restricted visibility
                if (in_array($wineName, $premiumWines)) {
                    $visibility = OfferVisibility::Restricted;
                }

                // Some offers are promotions (10%)
                if (fake()->boolean(10)) {
                    $offerType = OfferType::Promotion;
                }

                // Determine status: 70% active, 15% draft, 10% paused, 5% expired
                $statusRandom = fake()->numberBetween(1, 100);
                $status = match (true) {
                    $statusRandom <= 70 => OfferStatus::Active,
                    $statusRandom <= 85 => OfferStatus::Draft,
                    $statusRandom <= 95 => OfferStatus::Paused,
                    default => OfferStatus::Expired,
                };

                // Validity period
                $validFrom = now()->subMonths(fake()->numberBetween(1, 6));
                $validTo = $status === OfferStatus::Expired
                    ? now()->subDays(fake()->numberBetween(1, 30))
                    : (fake()->boolean(70) ? now()->addMonths(fake()->numberBetween(3, 12)) : null);

                // Campaign tag
                $campaignTag = fake()->randomElement($campaigns);
                if (in_array($wineName, $superTuscans)) {
                    $campaignTag = 'tuscan_collection';
                }

                // Create offer
                $offer = Offer::firstOrCreate(
                    [
                        'sellable_sku_id' => $sku->id,
                        'channel_id' => $channel->id,
                        'price_book_id' => $priceBook->id,
                    ],
                    [
                        'name' => $sku->sku_code.' - '.$channel->name,
                        'offer_type' => $offerType,
                        'visibility' => $visibility,
                        'valid_from' => $validFrom,
                        'valid_to' => $validTo,
                        'status' => $status,
                        'campaign_tag' => $campaignTag,
                    ]
                );

                // Create offer eligibility for restricted offers (30%)
                if ($visibility === OfferVisibility::Restricted || fake()->boolean(30)) {
                    $this->createOfferEligibility($offer, $wineName, $premiumWines);
                }

                // Create offer benefits for promotions and some standard offers (20%)
                if ($offerType === OfferType::Promotion || fake()->boolean(20)) {
                    $this->createOfferBenefit($offer);
                }
            }
        }
    }

    /**
     * Create offer eligibility restrictions.
     */
    private function createOfferEligibility(Offer $offer, string $wineName, array $premiumWines): void
    {
        // Premium wines have stricter eligibility
        $isPremium = in_array($wineName, $premiumWines);

        // Determine eligibility criteria
        $allowedMembershipTiers = $isPremium
            ? ['legacy', 'invitation_only']
            : ['legacy', 'member', 'invitation_only'];

        $allowedCustomerTypes = $isPremium
            ? ['b2c']
            : ['b2c', 'b2b'];

        // Market restrictions (some offers EU only)
        $allowedMarkets = fake()->boolean(30)
            ? ['IT', 'FR', 'DE', 'ES', 'NL', 'BE', 'AT', 'PT']
            : null;

        OfferEligibility::firstOrCreate(
            ['offer_id' => $offer->id],
            [
                'allowed_membership_tiers' => $allowedMembershipTiers,
                'allowed_customer_types' => $allowedCustomerTypes,
                'allowed_markets' => $allowedMarkets,
                'allocation_constraint_id' => null,
            ]
        );
    }

    /**
     * Create offer benefit (discount/promotion).
     */
    private function createOfferBenefit(Offer $offer): void
    {
        // Determine benefit type: 50% percentage, 30% fixed discount, 20% fixed price
        $benefitRandom = fake()->numberBetween(1, 100);

        if ($benefitRandom <= 50) {
            // Percentage discount (5-15%)
            $benefitType = BenefitType::PercentageDiscount;
            $benefitValue = fake()->randomElement([5, 7, 10, 12, 15]);
        } elseif ($benefitRandom <= 80) {
            // Fixed amount discount
            $benefitType = BenefitType::FixedDiscount;
            $benefitValue = fake()->randomElement([25, 50, 75, 100, 150]);
        } else {
            // Fixed price override
            $benefitType = BenefitType::FixedPrice;
            $benefitValue = fake()->randomFloat(2, 50, 500);
        }

        OfferBenefit::firstOrCreate(
            ['offer_id' => $offer->id],
            [
                'benefit_type' => $benefitType,
                'benefit_value' => $benefitValue,
                'discount_rule_id' => null,
            ]
        );
    }
}
