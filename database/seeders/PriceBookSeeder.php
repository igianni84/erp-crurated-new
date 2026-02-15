<?php

namespace Database\Seeders;

use App\Enums\Commercial\PriceBookStatus;
use App\Models\Commercial\Channel;
use App\Models\Commercial\PriceBook;
use App\Models\Commercial\PriceBookEntry;
use App\Models\Pim\SellableSku;
use App\Models\User;
use App\Services\Commercial\PriceBookService;
use Illuminate\Database\Seeder;

/**
 * PriceBookSeeder - Creates price books and price entries using service lifecycle.
 *
 * All PriceBooks are created as Draft, entries are added, then activated via
 * PriceBookService::activate() which validates entries exist and sets approval metadata.
 * All price arithmetic uses bcmul/bcadd — no float operations.
 */
class PriceBookSeeder extends Seeder
{
    public function run(): void
    {
        $priceBookService = app(PriceBookService::class);

        $channels = Channel::all();

        if ($channels->isEmpty()) {
            $this->command->warn('No channels found. Run ChannelSeeder first.');

            return;
        }

        $skus = SellableSku::where('lifecycle_status', SellableSku::STATUS_ACTIVE)
            ->with(['wineVariant.wineMaster', 'format', 'caseConfiguration'])
            ->get();

        if ($skus->isEmpty()) {
            $this->command->warn('No active SellableSku found. Run SellableSkuSeeder first.');

            return;
        }

        $admin = User::first();

        // Base prices as strings (EUR per bottle in 6-pack OWC)
        $basePrices = [
            'Romanee-Conti Grand Cru' => '15000.00',
            'La Tache Grand Cru' => '5500.00',
            'Barolo Monfortino' => '1800.00',
            'Musigny Grand Cru' => '2200.00',
            'Chateau Margaux' => '750.00',
            'Chateau Latour' => '800.00',
            'Brunello di Montalcino Riserva' => '350.00',
            'Barolo Cannubi' => '280.00',
            'Barolo Falletto' => '260.00',
            'Barbaresco Asili' => '220.00',
            'Barbaresco Sori Tildin' => '320.00',
            'Sassicaia' => '280.00',
            'Ornellaia' => '250.00',
            'Solaia' => '350.00',
            'Tignanello' => '120.00',
            'Brunello di Montalcino' => '90.00',
            'Brunello di Montalcino Poggio alle Mura' => '85.00',
            'Amarone della Valpolicella Classico' => '150.00',
        ];

        $priceBookConfigs = [
            [
                'name' => 'EU Standard Price Book 2024',
                'market' => 'EU',
                'channel_name' => 'Crurated B2C',
                'currency' => 'EUR',
                'target_status' => 'active',
                'valid_from' => now()->subMonths(6),
                'valid_to' => now()->addMonths(6),
                'price_multiplier' => '1.0000',
            ],
            [
                'name' => 'EU Premium Price Book 2024',
                'market' => 'EU',
                'channel_name' => 'Crurated Collectors Club',
                'currency' => 'EUR',
                'target_status' => 'active',
                'valid_from' => now()->subMonths(3),
                'valid_to' => null,
                'price_multiplier' => '0.9500',
            ],
            [
                'name' => 'EU B2B Hospitality Price Book',
                'market' => 'EU',
                'channel_name' => 'Crurated Hospitality',
                'currency' => 'EUR',
                'target_status' => 'active',
                'valid_from' => now()->subMonths(2),
                'valid_to' => now()->addMonths(10),
                'price_multiplier' => '0.8500',
            ],
            [
                'name' => 'US Standard Price Book 2024',
                'market' => 'US',
                'channel_name' => 'Crurated US',
                'currency' => 'USD',
                'target_status' => 'active',
                'valid_from' => now()->subMonths(4),
                'valid_to' => now()->addMonths(8),
                'price_multiplier' => '1.1200',
            ],
            [
                'name' => 'UK Standard Price Book 2024',
                'market' => 'UK',
                'channel_name' => 'Crurated UK',
                'currency' => 'GBP',
                'target_status' => 'active',
                'valid_from' => now()->subMonths(5),
                'valid_to' => now()->addMonths(7),
                'price_multiplier' => '0.8800',
            ],
            [
                'name' => 'CH Standard Price Book 2024',
                'market' => 'CH',
                'channel_name' => 'Crurated Switzerland',
                'currency' => 'CHF',
                'target_status' => 'active',
                'valid_from' => now()->subMonths(3),
                'valid_to' => now()->addMonths(9),
                'price_multiplier' => '0.9800',
            ],
            [
                'name' => 'EU Standard Price Book 2025',
                'market' => 'EU',
                'channel_name' => 'Crurated B2C',
                'currency' => 'EUR',
                'target_status' => 'draft',
                'valid_from' => now()->addMonths(6),
                'valid_to' => now()->addMonths(18),
                'price_multiplier' => '1.0300',
            ],
            [
                'name' => 'EU Standard Price Book 2023',
                'market' => 'EU',
                'channel_name' => 'Crurated B2C',
                'currency' => 'EUR',
                'target_status' => 'expired',
                'valid_from' => now()->subYears(2),
                'valid_to' => now()->subMonths(6),
                'price_multiplier' => '0.9500',
            ],
        ];

        foreach ($priceBookConfigs as $config) {
            $channel = $channels->firstWhere('name', $config['channel_name']);

            if (! $channel) {
                $this->command->warn("Channel '{$config['channel_name']}' not found. Skipping price book.");

                continue;
            }

            // Always create as Draft first
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
                    'status' => PriceBookStatus::Draft,
                ]
            );

            // Create price entries using bcmath
            foreach ($skus as $sku) {
                $wineName = $sku->wineVariant->wineMaster->name ?? '';
                $basePrice = $basePrices[$wineName] ?? '100.00';

                $formatMultiplier = match ($sku->format->volume_ml) {
                    375 => '0.5500',
                    750 => '1.0000',
                    1500 => '2.2000',
                    3000 => '4.5000',
                    6000 => '10.0000',
                    default => '1.0000',
                };

                $caseMultiplier = match ($sku->caseConfiguration->bottles_per_case) {
                    1 => '1.0500',
                    3 => '1.0200',
                    6 => '1.0000',
                    12 => '0.9800',
                    default => '1.0000',
                };

                $bottlesPerCase = (string) $sku->caseConfiguration->bottles_per_case;

                // Calculate: basePrice × formatMultiplier × caseMultiplier × priceMultiplier × bottlesPerCase
                $finalPrice = bcmul($basePrice, $formatMultiplier, 4);
                $finalPrice = bcmul($finalPrice, $caseMultiplier, 4);
                $finalPrice = bcmul($finalPrice, $config['price_multiplier'], 4);
                $finalPrice = bcmul($finalPrice, $bottlesPerCase, 4);
                // Round to 2 decimals
                $finalPrice = bcadd($finalPrice, '0', 2);

                PriceBookEntry::firstOrCreate(
                    [
                        'price_book_id' => $priceBook->id,
                        'sellable_sku_id' => $sku->id,
                    ],
                    [
                        'base_price' => $finalPrice,
                    ]
                );
            }

            // Transition to target status via service
            if ($config['target_status'] === 'active' && $priceBook->status === PriceBookStatus::Draft && $admin) {
                try {
                    $priceBookService->activate($priceBook, $admin);
                } catch (\Throwable $e) {
                    $this->command->warn("Could not activate '{$config['name']}': {$e->getMessage()}");
                }
            } elseif ($config['target_status'] === 'expired' && $admin) {
                try {
                    $priceBookService->activate($priceBook, $admin);
                    $priceBook->refresh();
                    $priceBookService->expirePriceBook($priceBook);
                } catch (\Throwable $e) {
                    $this->command->warn("Could not expire '{$config['name']}': {$e->getMessage()}");
                }
            }
            // 'draft' → leave as-is
        }
    }
}
