<?php

namespace Database\Seeders;

use App\Enums\Commercial\PriceBookStatus;
use App\Models\Commercial\Channel;
use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PriceBookEntry;
use App\Models\Pim\SellableSku;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * PriceBookSeeder - Creates price books and price entries
 *
 * Price Books contain base prices for Sellable SKUs and are
 * scoped to specific markets, channels, and currencies.
 */
class PriceBookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get channels
        $channels = Channel::all();

        if ($channels->isEmpty()) {
            $this->command->warn('No channels found. Run ChannelSeeder first.');

            return;
        }

        // Get active SKUs
        $skus = SellableSku::where('lifecycle_status', SellableSku::STATUS_ACTIVE)
            ->with(['wineVariant.wineMaster', 'format', 'caseConfiguration'])
            ->get();

        if ($skus->isEmpty()) {
            $this->command->warn('No active SellableSku found. Run SellableSkuSeeder first.');

            return;
        }

        // Get admin user for approval
        $admin = User::first();

        // Define price book configurations
        $priceBookConfigs = [
            // EU Market - EUR
            [
                'name' => 'EU Standard Price Book 2024',
                'market' => 'EU',
                'channel_name' => 'Crurated B2C',
                'currency' => 'EUR',
                'status' => PriceBookStatus::Active,
                'valid_from' => now()->subMonths(6),
                'valid_to' => now()->addMonths(6),
                'price_multiplier' => 1.0,
            ],
            [
                'name' => 'EU Premium Price Book 2024',
                'market' => 'EU',
                'channel_name' => 'Crurated Collectors Club',
                'currency' => 'EUR',
                'status' => PriceBookStatus::Active,
                'valid_from' => now()->subMonths(3),
                'valid_to' => null,
                'price_multiplier' => 0.95, // 5% discount for club members
            ],
            [
                'name' => 'EU B2B Hospitality Price Book',
                'market' => 'EU',
                'channel_name' => 'Crurated Hospitality',
                'currency' => 'EUR',
                'status' => PriceBookStatus::Active,
                'valid_from' => now()->subMonths(2),
                'valid_to' => now()->addMonths(10),
                'price_multiplier' => 0.85, // 15% B2B discount
            ],
            // US Market - USD
            [
                'name' => 'US Standard Price Book 2024',
                'market' => 'US',
                'channel_name' => 'Crurated US',
                'currency' => 'USD',
                'status' => PriceBookStatus::Active,
                'valid_from' => now()->subMonths(4),
                'valid_to' => now()->addMonths(8),
                'price_multiplier' => 1.12, // USD pricing (slightly higher)
            ],
            // UK Market - GBP
            [
                'name' => 'UK Standard Price Book 2024',
                'market' => 'UK',
                'channel_name' => 'Crurated UK',
                'currency' => 'GBP',
                'status' => PriceBookStatus::Active,
                'valid_from' => now()->subMonths(5),
                'valid_to' => now()->addMonths(7),
                'price_multiplier' => 0.88, // GBP pricing
            ],
            // Swiss Market - CHF
            [
                'name' => 'CH Standard Price Book 2024',
                'market' => 'CH',
                'channel_name' => 'Crurated Switzerland',
                'currency' => 'CHF',
                'status' => PriceBookStatus::Active,
                'valid_from' => now()->subMonths(3),
                'valid_to' => now()->addMonths(9),
                'price_multiplier' => 0.98, // CHF pricing
            ],
            // Draft price book for 2025
            [
                'name' => 'EU Standard Price Book 2025',
                'market' => 'EU',
                'channel_name' => 'Crurated B2C',
                'currency' => 'EUR',
                'status' => PriceBookStatus::Draft,
                'valid_from' => now()->addMonths(6),
                'valid_to' => now()->addMonths(18),
                'price_multiplier' => 1.03, // 3% price increase planned
            ],
            // Expired price book (historical)
            [
                'name' => 'EU Standard Price Book 2023',
                'market' => 'EU',
                'channel_name' => 'Crurated B2C',
                'currency' => 'EUR',
                'status' => PriceBookStatus::Expired,
                'valid_from' => now()->subYears(2),
                'valid_to' => now()->subMonths(6),
                'price_multiplier' => 0.95, // Lower prices last year
            ],
        ];

        // Base prices by wine type (EUR per bottle in 6-pack OWC)
        $basePrices = [
            // Ultra Premium (>1000 EUR)
            'Romanee-Conti Grand Cru' => 15000.00,
            'La Tache Grand Cru' => 5500.00,
            'Barolo Monfortino' => 1800.00,
            'Musigny Grand Cru' => 2200.00,
            // Premium (500-1000 EUR)
            'Chateau Margaux' => 750.00,
            'Chateau Latour' => 800.00,
            'Brunello di Montalcino Riserva' => 350.00,
            'Barolo Cannubi' => 280.00,
            'Barolo Falletto' => 260.00,
            'Barbaresco Asili' => 220.00,
            'Barbaresco Sori Tildin' => 320.00,
            // Super Tuscan (200-500 EUR)
            'Sassicaia' => 280.00,
            'Ornellaia' => 250.00,
            'Solaia' => 350.00,
            'Tignanello' => 120.00,
            // Standard Premium (100-200 EUR)
            'Brunello di Montalcino' => 90.00,
            'Brunello di Montalcino Poggio alle Mura' => 85.00,
            'Amarone della Valpolicella Classico' => 150.00,
        ];

        foreach ($priceBookConfigs as $config) {
            $channel = $channels->firstWhere('name', $config['channel_name']);

            if (! $channel) {
                $this->command->warn("Channel '{$config['channel_name']}' not found. Skipping price book.");

                continue;
            }

            // Create price book
            $priceBook = PriceBook::firstOrCreate(
                [
                    'name' => $config['name'],
                    'market' => $config['market'],
                    'channel_id' => $channel->id,
                    'currency' => $config['currency'],
                ],
                [
                    'valid_from' => $config['valid_from'],
                    'valid_to' => $config['valid_to'],
                    'status' => $config['status'],
                    'approved_at' => $config['status'] === PriceBookStatus::Active
                        ? now()->subDays(fake()->numberBetween(7, 60))
                        : null,
                    'approved_by' => $config['status'] === PriceBookStatus::Active
                        ? $admin?->id
                        : null,
                ]
            );

            // Create price entries for this price book
            foreach ($skus as $sku) {
                $wineName = $sku->wineVariant?->wineMaster?->name ?? '';
                $basePrice = $basePrices[$wineName] ?? 100.00;

                // Adjust price based on format
                $formatMultiplier = match ($sku->format->volume_ml) {
                    375 => 0.55,   // Half bottles are slightly more expensive per ml
                    750 => 1.0,
                    1500 => 2.2,   // Magnums have premium
                    3000 => 4.5,   // Double magnums
                    6000 => 10.0,  // Imperials
                    default => 1.0,
                };

                // Adjust price based on case configuration
                $caseMultiplier = match ($sku->caseConfiguration->bottles_per_case) {
                    1 => 1.05,    // Single bottles slightly more expensive
                    3 => 1.02,
                    6 => 1.0,     // Standard
                    12 => 0.98,   // Slight discount for larger cases
                    default => 1.0,
                };

                // Calculate final price with all multipliers
                $finalPrice = $basePrice
                    * $formatMultiplier
                    * $caseMultiplier
                    * $config['price_multiplier']
                    * $sku->caseConfiguration->bottles_per_case;

                // Add some random variation (+/- 3%)
                $variation = fake()->randomFloat(2, 0.97, 1.03);
                $finalPrice = round($finalPrice * $variation, 2);

                // Create price entry
                PriceBookEntry::firstOrCreate(
                    [
                        'price_book_id' => $priceBook->id,
                        'sellable_sku_id' => $sku->id,
                    ],
                    [
                        'base_price' => number_format($finalPrice, 2, '.', ''),
                    ]
                );
            }
        }
    }
}
