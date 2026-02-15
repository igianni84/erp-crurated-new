<?php

namespace Database\Seeders;

use App\Enums\Commercial\BenefitType;
use App\Enums\Commercial\ChannelStatus;
use App\Enums\Commercial\OfferStatus;
use App\Enums\Commercial\OfferType;
use App\Enums\Commercial\OfferVisibility;
use App\Enums\Commercial\PriceBookStatus;
use App\Models\Commercial\Channel;
use App\Models\Commercial\Offer;
use App\Models\Commercial\OfferBenefit;
use App\Models\Commercial\OfferEligibility;
use App\Models\Commercial\PriceBook;
use App\Models\Pim\SellableSku;
use App\Services\Commercial\OfferService;
use Illuminate\Database\Seeder;

/**
 * OfferSeeder - Creates commercial offers using service lifecycle.
 *
 * All Offers are created as Draft, then transitioned via OfferService:
 * - Active: activate()
 * - Paused: activate() → pause()
 * - Expired: activate() → expire()
 * - Draft: left as-is
 */
class OfferSeeder extends Seeder
{
    public function run(): void
    {
        $offerService = app(OfferService::class);

        $channels = Channel::where('status', ChannelStatus::Active)->get();

        if ($channels->isEmpty()) {
            $this->command->warn('No active channels found. Run ChannelSeeder first.');

            return;
        }

        $priceBooks = PriceBook::where('status', PriceBookStatus::Active)
            ->with('channel')
            ->get();

        if ($priceBooks->isEmpty()) {
            $this->command->warn('No active price books found. Run PriceBookSeeder first.');

            return;
        }

        $skus = SellableSku::where('lifecycle_status', SellableSku::STATUS_ACTIVE)
            ->where('is_composite', false)
            ->with(['wineVariant.wineMaster'])
            ->get();

        if ($skus->isEmpty()) {
            $this->command->warn('No active SellableSku found. Run SellableSkuSeeder first.');

            return;
        }

        $premiumWines = ['Barolo Monfortino', 'Romanee-Conti Grand Cru', 'La Tache Grand Cru', 'Musigny Grand Cru'];
        $superTuscans = ['Sassicaia', 'Ornellaia', 'Tignanello', 'Solaia'];

        $campaigns = [
            'spring_release_2024',
            'tuscan_collection',
            'piedmont_classics',
            'bordeaux_grands_crus',
            'burgundy_treasures',
            'holiday_2024',
            null,
        ];

        foreach ($skus as $sku) {
            $wineName = $sku->wineVariant->wineMaster->name ?? '';

            foreach ($priceBooks as $priceBook) {
                if (! $priceBook->entries()->where('sellable_sku_id', $sku->id)->exists()) {
                    continue;
                }

                $channel = $priceBook->channel;
                if (! $channel) {
                    continue;
                }

                $offerType = OfferType::Standard;
                $visibility = OfferVisibility::Public;

                if (in_array($wineName, $premiumWines)) {
                    $visibility = OfferVisibility::Restricted;
                }

                if (fake()->boolean(10)) {
                    $offerType = OfferType::Promotion;
                }

                // Determine target status: 70% active, 15% draft, 10% paused, 5% expired
                $statusRandom = fake()->numberBetween(1, 100);
                $targetStatus = match (true) {
                    $statusRandom <= 70 => 'active',
                    $statusRandom <= 85 => 'draft',
                    $statusRandom <= 95 => 'paused',
                    default => 'expired',
                };

                $validFrom = now()->subMonths(fake()->numberBetween(1, 6));
                $validTo = $targetStatus === 'expired'
                    ? now()->subDays(fake()->numberBetween(1, 30))
                    : (fake()->boolean(70) ? now()->addMonths(fake()->numberBetween(3, 12)) : null);

                $campaignTag = fake()->randomElement($campaigns);
                if (in_array($wineName, $superTuscans)) {
                    $campaignTag = 'tuscan_collection';
                }

                // Always create as Draft
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
                        'status' => OfferStatus::Draft,
                        'campaign_tag' => $campaignTag,
                    ]
                );

                // Create eligibility and benefits before activation
                if ($visibility === OfferVisibility::Restricted || fake()->boolean(30)) {
                    $this->createOfferEligibility($offer, $wineName, $premiumWines);
                }

                if ($offerType === OfferType::Promotion || fake()->boolean(20)) {
                    $this->createOfferBenefit($offer);
                }

                // Transition to target status via service
                if ($offer->status !== OfferStatus::Draft) {
                    // Already existed from a previous seed run
                    continue;
                }

                try {
                    if ($targetStatus === 'active') {
                        $offerService->activate($offer);
                    } elseif ($targetStatus === 'paused') {
                        $offerService->activate($offer);
                        $offer->refresh();
                        $offerService->pause($offer);
                    } elseif ($targetStatus === 'expired') {
                        $offerService->activate($offer);
                        $offer->refresh();
                        $offerService->expire($offer);
                    }
                    // 'draft' → leave as-is
                } catch (\Throwable $e) {
                    $this->command->warn("Offer transition failed for '{$offer->name}': {$e->getMessage()}");
                }
            }
        }
    }

    private function createOfferEligibility(Offer $offer, string $wineName, array $premiumWines): void
    {
        $isPremium = in_array($wineName, $premiumWines);

        $allowedMembershipTiers = $isPremium
            ? ['legacy', 'invitation_only']
            : ['legacy', 'member', 'invitation_only'];

        $allowedCustomerTypes = $isPremium
            ? ['b2c']
            : ['b2c', 'b2b'];

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

    private function createOfferBenefit(Offer $offer): void
    {
        $benefitRandom = fake()->numberBetween(1, 100);

        if ($benefitRandom <= 50) {
            $benefitType = BenefitType::PercentageDiscount;
            $benefitValue = (string) fake()->randomElement([5, 7, 10, 12, 15]);
        } elseif ($benefitRandom <= 80) {
            $benefitType = BenefitType::FixedDiscount;
            $benefitValue = (string) fake()->randomElement([25, 50, 75, 100, 150]);
        } else {
            $benefitType = BenefitType::FixedPrice;
            $benefitValue = number_format(rand(5000, 50000) / 100, 2, '.', '');
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
