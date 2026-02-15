<?php

namespace Database\Seeders;

use App\Enums\Customer\CustomerStatus;
use App\Enums\Finance\InvoiceType;
use App\Models\Customer\Customer;
use App\Models\Finance\Subscription;
use App\Models\Pim\WineMaster;
use App\Services\Finance\InvoiceService;
use Illuminate\Database\Seeder;

/**
 * InvoiceSeeder - Creates invoices of all 5 types (INV0-INV4) via InvoiceService.
 *
 * Uses InvoiceService::createDraft() which auto-generates invoice lines, then
 * InvoiceService::issue() which generates the invoice number (INV-YYYY-NNNNNN).
 * Some invoices are left as Draft for testing.
 */
class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $invoiceService = app(InvoiceService::class);

        $customers = Customer::where('status', CustomerStatus::Active)->get();

        if ($customers->isEmpty()) {
            $this->command->warn('No active customers found. Run CustomerSeeder first.');

            return;
        }

        $this->createMembershipInvoices($invoiceService, $customers);
        $this->createVoucherSaleInvoices($invoiceService, $customers);
        $this->createShippingInvoices($invoiceService, $customers);
        $this->createStorageFeeInvoices($invoiceService, $customers);
        $this->createServiceEventInvoices($invoiceService, $customers);
    }

    private function createMembershipInvoices(InvoiceService $invoiceService, $customers): void
    {
        $subscriptions = Subscription::with('customer')->get();

        foreach ($subscriptions as $subscription) {
            $numInvoices = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $numInvoices; $i++) {
                $taxRate = '22.00';
                $taxAmount = bcmul($subscription->amount, '0.22', 2);

                $lines = [
                    [
                        'description' => "{$subscription->plan_name} - {$subscription->billing_cycle->label()} Membership",
                        'quantity' => '1.00',
                        'unit_price' => $subscription->amount,
                        'tax_rate' => $taxRate,
                        'tax_amount' => $taxAmount,
                        'metadata' => [
                            'subscription_id' => $subscription->id,
                            'plan_name' => $subscription->plan_name,
                            'billing_cycle' => $subscription->billing_cycle->value,
                        ],
                    ],
                ];

                $dueDate = now()->subMonths(fake()->numberBetween(0, 11))->addDays(30);

                try {
                    $invoice = $invoiceService->createDraft(
                        InvoiceType::MembershipService,
                        $subscription->customer,
                        $lines,
                        'subscription',
                        $subscription->id,
                        'EUR',
                        $dueDate,
                    );

                    // Issue most invoices (keep some as draft)
                    if (fake()->boolean(90)) {
                        $invoiceService->issue($invoice);
                    }
                } catch (\Throwable $e) {
                    $this->command->warn("Membership invoice failed: {$e->getMessage()}");
                }
            }
        }
    }

    private function createVoucherSaleInvoices(InvoiceService $invoiceService, $customers): void
    {
        $wines = WineMaster::all();
        if ($wines->isEmpty()) {
            return;
        }

        $winePrices = [
            'Barolo Monfortino' => '850.00',
            'Romanee-Conti Grand Cru' => '15000.00',
            'La Tache Grand Cru' => '4500.00',
            'Chateau Margaux' => '650.00',
            'Chateau Latour' => '750.00',
            'Sassicaia' => '280.00',
            'Ornellaia' => '220.00',
            'Tignanello' => '120.00',
            'Solaia' => '350.00',
            'Brunello di Montalcino' => '85.00',
            'Barbaresco Asili' => '180.00',
            'Amarone della Valpolicella Classico' => '150.00',
        ];

        foreach ($customers->take(8) as $customer) {
            $numInvoices = fake()->numberBetween(2, 5);

            for ($i = 0; $i < $numInvoices; $i++) {
                $numLines = fake()->numberBetween(1, 6);
                $lines = [];

                for ($j = 0; $j < $numLines; $j++) {
                    $wine = $wines->random();
                    $price = $winePrices[$wine->name] ?? '150.00';
                    $quantity = (string) fake()->numberBetween(1, 6);
                    $vintage = fake()->numberBetween(2015, 2021);

                    $lineTaxAmount = bcmul(bcmul($quantity, $price, 2), '0.22', 2);

                    $lines[] = [
                        'description' => "{$wine->name} {$vintage} - 750ml x{$quantity}",
                        'quantity' => $quantity,
                        'unit_price' => $price,
                        'tax_rate' => '22.00',
                        'tax_amount' => $lineTaxAmount,
                        'metadata' => [
                            'wine_master_id' => $wine->id,
                            'vintage' => $vintage,
                            'format' => '750ml',
                            'line_type' => 'wine',
                        ],
                    ];
                }

                try {
                    $invoice = $invoiceService->createDraft(
                        InvoiceType::VoucherSale,
                        $customer,
                        $lines,
                        'voucher_sale',
                        (string) fake()->uuid(),
                    );

                    if (fake()->boolean(90)) {
                        $invoiceService->issue($invoice);
                    }
                } catch (\Throwable $e) {
                    $this->command->warn("Voucher sale invoice failed: {$e->getMessage()}");
                }
            }
        }
    }

    private function createShippingInvoices(InvoiceService $invoiceService, $customers): void
    {
        $shippingRates = [
            'IT' => ['base' => '15.00', 'per_bottle' => '2.50', 'country' => 'Italy'],
            'FR' => ['base' => '25.00', 'per_bottle' => '3.00', 'country' => 'France'],
            'DE' => ['base' => '25.00', 'per_bottle' => '3.00', 'country' => 'Germany'],
            'UK' => ['base' => '35.00', 'per_bottle' => '4.00', 'country' => 'United Kingdom'],
            'CH' => ['base' => '45.00', 'per_bottle' => '5.00', 'country' => 'Switzerland'],
            'US' => ['base' => '85.00', 'per_bottle' => '8.00', 'country' => 'United States'],
        ];

        foreach ($customers->take(6) as $customer) {
            $numInvoices = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $numInvoices; $i++) {
                $countryCode = fake()->randomElement(array_keys($shippingRates));
                $rate = $shippingRates[$countryCode];

                $numBottles = (string) fake()->numberBetween(3, 12);
                $shippingCost = bcadd($rate['base'], bcmul($numBottles, $rate['per_bottle'], 2), 2);
                $redemptionFee = bcmul($numBottles, '5.00', 2);
                $taxRate = in_array($countryCode, ['CH', 'US']) ? '0.00' : '22.00';

                $lines = [
                    [
                        'description' => "Shipping to {$rate['country']} ({$numBottles} bottles)",
                        'quantity' => '1.00',
                        'unit_price' => $shippingCost,
                        'tax_rate' => $taxRate,
                        'tax_amount' => bcmul($shippingCost, bcdiv($taxRate, '100', 4), 2),
                        'metadata' => [
                            'line_type' => 'shipping',
                            'destination_country' => $countryCode,
                            'carrier_name' => fake()->randomElement(['DHL Express', 'UPS', 'FedEx', 'TNT']),
                            'bottle_count' => (int) $numBottles,
                        ],
                    ],
                    [
                        'description' => "Redemption & Handling Fee ({$numBottles} bottles @ 5.00)",
                        'quantity' => $numBottles,
                        'unit_price' => '5.00',
                        'tax_rate' => $taxRate,
                        'tax_amount' => bcmul($redemptionFee, bcdiv($taxRate, '100', 4), 2),
                        'metadata' => ['line_type' => 'redemption'],
                    ],
                ];

                try {
                    $invoice = $invoiceService->createDraft(
                        InvoiceType::ShippingRedemption,
                        $customer,
                        $lines,
                        'shipping_order',
                        (string) fake()->uuid(),
                    );

                    if (fake()->boolean(85)) {
                        $invoiceService->issue($invoice);
                    }
                } catch (\Throwable $e) {
                    $this->command->warn("Shipping invoice failed: {$e->getMessage()}");
                }
            }
        }
    }

    private function createStorageFeeInvoices(InvoiceService $invoiceService, $customers): void
    {
        $dailyRate = '0.03';

        foreach ($customers->take(6) as $customer) {
            $numInvoices = fake()->numberBetween(1, 4);

            for ($i = 0; $i < $numInvoices; $i++) {
                $bottleCount = fake()->numberBetween(12, 120);
                $daysInPeriod = 90;
                $bottleDays = (string) ($bottleCount * $daysInPeriod);
                $taxRate = '22.00';

                $subtotal = bcmul($bottleDays, $dailyRate, 2);
                $taxAmount = bcmul($subtotal, '0.22', 2);

                $dueDate = now()->subMonths(fake()->numberBetween(0, 5))->addDays(30);

                $lines = [
                    [
                        'description' => "Wine Storage - {$bottleCount} bottles for {$daysInPeriod} days",
                        'quantity' => $bottleDays,
                        'unit_price' => $dailyRate,
                        'tax_rate' => $taxRate,
                        'tax_amount' => $taxAmount,
                        'metadata' => [
                            'line_type' => 'storage_fee',
                            'bottle_count' => $bottleCount,
                            'bottle_days' => (int) $bottleDays,
                            'unit_rate' => $dailyRate,
                            'location_name' => fake()->randomElement(['Milano Central Warehouse', 'London Docklands Warehouse', 'Geneva Free Port']),
                        ],
                    ],
                ];

                try {
                    $invoice = $invoiceService->createDraft(
                        InvoiceType::StorageFee,
                        $customer,
                        $lines,
                        'storage_billing_period',
                        (string) fake()->uuid(),
                        'EUR',
                        $dueDate,
                        'Quarterly storage fees',
                    );

                    if (fake()->boolean(85)) {
                        $invoiceService->issue($invoice);
                    }
                } catch (\Throwable $e) {
                    $this->command->warn("Storage invoice failed: {$e->getMessage()}");
                }
            }
        }
    }

    private function createServiceEventInvoices(InvoiceService $invoiceService, $customers): void
    {
        $events = [
            ['name' => 'Barolo Grand Tasting 2025', 'price' => '150.00', 'service_type' => 'event_attendance'],
            ['name' => 'Private Cellar Consultation', 'price' => '350.00', 'service_type' => 'consultation'],
            ['name' => 'Burgundy Masterclass', 'price' => '250.00', 'service_type' => 'event_attendance'],
            ['name' => 'Wine Investment Advisory Session', 'price' => '500.00', 'service_type' => 'consultation'],
            ['name' => 'Vertical Tasting: Sassicaia 2010-2020', 'price' => '200.00', 'service_type' => 'tasting_fee'],
            ['name' => 'Annual Collector\'s Dinner', 'price' => '450.00', 'service_type' => 'event_attendance'],
        ];

        foreach ($customers->take(5) as $customer) {
            $numInvoices = fake()->numberBetween(1, 2);

            for ($i = 0; $i < $numInvoices; $i++) {
                $event = fake()->randomElement($events);
                $attendees = (string) fake()->numberBetween(1, 2);
                $taxAmount = bcmul(bcmul($attendees, $event['price'], 2), '0.22', 2);

                $lines = [
                    [
                        'description' => $event['name'].((int) $attendees > 1 ? " x{$attendees} attendees" : ''),
                        'quantity' => $attendees,
                        'unit_price' => $event['price'],
                        'tax_rate' => '22.00',
                        'tax_amount' => $taxAmount,
                        'metadata' => [
                            'service_type' => $event['service_type'],
                            'event_name' => $event['name'],
                            'attendee_count' => (int) $attendees,
                        ],
                    ],
                ];

                try {
                    $invoice = $invoiceService->createDraft(
                        InvoiceType::ServiceEvents,
                        $customer,
                        $lines,
                        'event_booking',
                        (string) fake()->uuid(),
                    );

                    if (fake()->boolean(85)) {
                        $invoiceService->issue($invoice);
                    }
                } catch (\Throwable $e) {
                    $this->command->warn("Event invoice failed: {$e->getMessage()}");
                }
            }
        }
    }
}
