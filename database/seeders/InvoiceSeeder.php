<?php

namespace Database\Seeders;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\InvoiceType;
use App\Models\Customer\Customer;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoiceLine;
use App\Models\Finance\Subscription;
use App\Models\Pim\WineMaster;
use Illuminate\Database\Seeder;

/**
 * InvoiceSeeder - Creates invoices of all 5 types (INV0-INV4)
 */
class InvoiceSeeder extends Seeder
{
    private int $invoiceCounter = 1;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = Customer::where('status', Customer::STATUS_ACTIVE)->get();

        if ($customers->isEmpty()) {
            $this->command->warn('No active customers found. Run CustomerSeeder first.');

            return;
        }

        // Create INV0 - Membership Service invoices (from subscriptions)
        $this->createMembershipInvoices($customers);

        // Create INV1 - Voucher Sale invoices
        $this->createVoucherSaleInvoices($customers);

        // Create INV2 - Shipping Redemption invoices
        $this->createShippingInvoices($customers);

        // Create INV3 - Storage Fee invoices
        $this->createStorageFeeInvoices($customers);

        // Create INV4 - Service Events invoices
        $this->createServiceEventInvoices($customers);
    }

    /**
     * Create INV0 - Membership Service invoices
     */
    private function createMembershipInvoices($customers): void
    {
        $subscriptions = Subscription::with('customer')->get();

        foreach ($subscriptions as $subscription) {
            // Create 1-3 past invoices for active subscriptions
            $numInvoices = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $numInvoices; $i++) {
                $issuedAt = fake()->dateTimeBetween('-12 months', '-1 month');
                $dueDate = (clone $issuedAt)->modify('+30 days');

                $status = fake()->randomElement([
                    InvoiceStatus::Paid,
                    InvoiceStatus::Paid,
                    InvoiceStatus::Paid,
                    InvoiceStatus::Issued, // Some still unpaid
                ]);

                $subtotal = $subscription->amount;
                $taxRate = '22.00'; // Italian VAT
                $taxAmount = bcmul($subtotal, '0.22', 2);
                $totalAmount = bcadd($subtotal, $taxAmount, 2);

                $amountPaid = $status === InvoiceStatus::Paid ? $totalAmount : '0.00';

                // Use a unique UUID for each invoice to avoid constraint violations
                // while keeping subscription reference in metadata
                $sourceId = (string) fake()->uuid();

                $invoice = Invoice::create([
                    'invoice_number' => $this->generateInvoiceNumber(),
                    'invoice_type' => InvoiceType::MembershipService,
                    'customer_id' => $subscription->customer_id,
                    'currency' => 'EUR',
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'amount_paid' => $amountPaid,
                    'status' => $status,
                    'source_type' => 'subscription',
                    'source_id' => $sourceId,
                    'issued_at' => $issuedAt,
                    'due_date' => $dueDate,
                    'notes' => "Membership fee for {$subscription->plan_name}",
                    'xero_invoice_id' => $status === InvoiceStatus::Paid ? 'INV-'.fake()->numerify('######') : null,
                    'xero_synced_at' => $status === InvoiceStatus::Paid ? $issuedAt : null,
                    'xero_sync_pending' => $status !== InvoiceStatus::Paid,
                ]);

                InvoiceLine::create([
                    'invoice_id' => $invoice->id,
                    'description' => "{$subscription->plan_name} - {$subscription->billing_cycle->label()} Membership",
                    'quantity' => '1.00',
                    'unit_price' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'metadata' => [
                        'subscription_id' => $subscription->id,
                        'plan_name' => $subscription->plan_name,
                        'billing_cycle' => $subscription->billing_cycle->value,
                        'billing_period' => $issuedAt->format('Y-m'),
                    ],
                ]);
            }
        }
    }

    /**
     * Create INV1 - Voucher Sale invoices
     */
    private function createVoucherSaleInvoices($customers): void
    {
        $wines = WineMaster::all();

        if ($wines->isEmpty()) {
            return;
        }

        // Wine prices (realistic market prices in EUR)
        $winePrices = [
            'Barolo Monfortino' => 850.00,
            'Romanee-Conti Grand Cru' => 15000.00,
            'La Tache Grand Cru' => 4500.00,
            'Chateau Margaux' => 650.00,
            'Chateau Latour' => 750.00,
            'Sassicaia' => 280.00,
            'Ornellaia' => 220.00,
            'Tignanello' => 120.00,
            'Solaia' => 350.00,
            'Brunello di Montalcino' => 85.00,
            'Barbaresco Asili' => 180.00,
            'Amarone della Valpolicella Classico' => 150.00,
            'default' => 150.00,
        ];

        foreach ($customers->take(8) as $customer) {
            // Create 2-5 voucher sale invoices per customer
            $numInvoices = fake()->numberBetween(2, 5);

            for ($i = 0; $i < $numInvoices; $i++) {
                $issuedAt = fake()->dateTimeBetween('-6 months', '-1 week');

                $status = fake()->randomElement([
                    InvoiceStatus::Paid,
                    InvoiceStatus::Paid,
                    InvoiceStatus::Paid,
                    InvoiceStatus::Issued,
                ]);

                // Create invoice with 1-6 wine lines
                $numLines = fake()->numberBetween(1, 6);
                $subtotal = '0.00';
                $lines = [];

                for ($j = 0; $j < $numLines; $j++) {
                    $wine = $wines->random();
                    $price = $winePrices[$wine->name] ?? $winePrices['default'];
                    $quantity = fake()->numberBetween(1, 6);
                    $lineSubtotal = bcmul((string) $quantity, (string) $price, 2);
                    $subtotal = bcadd($subtotal, $lineSubtotal, 2);

                    $vintage = fake()->numberBetween(2015, 2021);

                    $lines[] = [
                        'description' => "{$wine->name} {$vintage} - 750ml x{$quantity}",
                        'quantity' => (string) $quantity,
                        'unit_price' => number_format($price, 2, '.', ''),
                        'metadata' => [
                            'wine_master_id' => $wine->id,
                            'vintage' => $vintage,
                            'format' => '750ml',
                            'line_type' => 'wine',
                        ],
                    ];
                }

                $taxRate = '22.00';
                $taxAmount = bcmul($subtotal, '0.22', 2);
                $totalAmount = bcadd($subtotal, $taxAmount, 2);
                $amountPaid = $status === InvoiceStatus::Paid ? $totalAmount : '0.00';

                $invoice = Invoice::create([
                    'invoice_number' => $this->generateInvoiceNumber(),
                    'invoice_type' => InvoiceType::VoucherSale,
                    'customer_id' => $customer->id,
                    'currency' => 'EUR',
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'amount_paid' => $amountPaid,
                    'status' => $status,
                    'source_type' => 'voucher_sale',
                    'source_id' => (string) fake()->uuid(),
                    'issued_at' => $issuedAt,
                    'due_date' => null, // Immediate payment
                    'xero_invoice_id' => $status === InvoiceStatus::Paid ? 'INV-'.fake()->numerify('######') : null,
                    'xero_synced_at' => $status === InvoiceStatus::Paid ? $issuedAt : null,
                    'xero_sync_pending' => $status !== InvoiceStatus::Paid,
                ]);

                foreach ($lines as $lineData) {
                    $lineTaxAmount = bcmul(bcmul($lineData['quantity'], $lineData['unit_price'], 2), '0.22', 2);

                    InvoiceLine::create([
                        'invoice_id' => $invoice->id,
                        'description' => $lineData['description'],
                        'quantity' => $lineData['quantity'],
                        'unit_price' => $lineData['unit_price'],
                        'tax_rate' => $taxRate,
                        'tax_amount' => $lineTaxAmount,
                        'metadata' => $lineData['metadata'],
                    ]);
                }
            }
        }
    }

    /**
     * Create INV2 - Shipping Redemption invoices
     */
    private function createShippingInvoices($customers): void
    {
        $shippingRates = [
            'IT' => ['base' => 15.00, 'per_bottle' => 2.50, 'country' => 'Italy'],
            'FR' => ['base' => 25.00, 'per_bottle' => 3.00, 'country' => 'France'],
            'DE' => ['base' => 25.00, 'per_bottle' => 3.00, 'country' => 'Germany'],
            'UK' => ['base' => 35.00, 'per_bottle' => 4.00, 'country' => 'United Kingdom'],
            'CH' => ['base' => 45.00, 'per_bottle' => 5.00, 'country' => 'Switzerland'],
            'US' => ['base' => 85.00, 'per_bottle' => 8.00, 'country' => 'United States'],
        ];

        foreach ($customers->take(6) as $customer) {
            // Create 1-3 shipping invoices
            $numInvoices = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $numInvoices; $i++) {
                $issuedAt = fake()->dateTimeBetween('-4 months', '-1 week');
                $countryCode = fake()->randomElement(array_keys($shippingRates));
                $rate = $shippingRates[$countryCode];

                $numBottles = fake()->numberBetween(3, 12);
                $shippingCost = bcadd(
                    number_format($rate['base'], 2, '.', ''),
                    bcmul((string) $numBottles, number_format($rate['per_bottle'], 2, '.', ''), 2),
                    2
                );

                // Add redemption fee (handling)
                $redemptionFee = bcmul((string) $numBottles, '5.00', 2);

                $subtotal = bcadd($shippingCost, $redemptionFee, 2);
                $taxRate = $countryCode === 'CH' || $countryCode === 'US' ? '0.00' : '22.00';
                $taxAmount = bcmul($subtotal, bcdiv($taxRate, '100', 4), 2);
                $totalAmount = bcadd($subtotal, $taxAmount, 2);

                $status = fake()->randomElement([
                    InvoiceStatus::Paid,
                    InvoiceStatus::Paid,
                    InvoiceStatus::Issued,
                ]);

                $amountPaid = $status === InvoiceStatus::Paid ? $totalAmount : '0.00';

                $invoice = Invoice::create([
                    'invoice_number' => $this->generateInvoiceNumber(),
                    'invoice_type' => InvoiceType::ShippingRedemption,
                    'customer_id' => $customer->id,
                    'currency' => 'EUR',
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'amount_paid' => $amountPaid,
                    'status' => $status,
                    'source_type' => 'shipping_order',
                    'source_id' => (string) fake()->uuid(),
                    'issued_at' => $issuedAt,
                    'due_date' => null,
                    'xero_invoice_id' => $status === InvoiceStatus::Paid ? 'INV-'.fake()->numerify('######') : null,
                    'xero_synced_at' => $status === InvoiceStatus::Paid ? $issuedAt : null,
                    'xero_sync_pending' => $status !== InvoiceStatus::Paid,
                ]);

                // Shipping line
                InvoiceLine::create([
                    'invoice_id' => $invoice->id,
                    'description' => "Shipping to {$rate['country']} ({$numBottles} bottles)",
                    'quantity' => '1.00',
                    'unit_price' => $shippingCost,
                    'tax_rate' => $taxRate,
                    'tax_amount' => bcmul($shippingCost, bcdiv($taxRate, '100', 4), 2),
                    'metadata' => [
                        'line_type' => 'shipping',
                        'destination_country' => $countryCode,
                        'carrier_name' => fake()->randomElement(['DHL Express', 'UPS', 'FedEx', 'TNT']),
                        'bottle_count' => $numBottles,
                    ],
                ]);

                // Redemption/handling fee line
                InvoiceLine::create([
                    'invoice_id' => $invoice->id,
                    'description' => "Redemption & Handling Fee ({$numBottles} bottles @ €5.00)",
                    'quantity' => (string) $numBottles,
                    'unit_price' => '5.00',
                    'tax_rate' => $taxRate,
                    'tax_amount' => bcmul($redemptionFee, bcdiv($taxRate, '100', 4), 2),
                    'metadata' => [
                        'line_type' => 'redemption',
                        'shipment_type' => 'redemption',
                    ],
                ]);
            }
        }
    }

    /**
     * Create INV3 - Storage Fee invoices
     */
    private function createStorageFeeInvoices($customers): void
    {
        // Storage rate: €0.03 per bottle per day (approx €11/year per bottle)
        $dailyRate = '0.03';

        foreach ($customers->take(6) as $customer) {
            // Create 1-4 storage invoices (quarterly billing)
            $numInvoices = fake()->numberBetween(1, 4);

            for ($i = 0; $i < $numInvoices; $i++) {
                $periodEnd = fake()->dateTimeBetween('-6 months', '-1 month');
                $periodStart = (clone $periodEnd)->modify('-3 months');
                $issuedAt = (clone $periodEnd)->modify('+5 days');
                $dueDate = (clone $issuedAt)->modify('+30 days');

                $bottleCount = fake()->numberBetween(12, 120);
                $daysInPeriod = 90; // Quarterly
                $bottleDays = $bottleCount * $daysInPeriod;

                $subtotal = bcmul((string) $bottleDays, $dailyRate, 2);
                $taxRate = '22.00';
                $taxAmount = bcmul($subtotal, '0.22', 2);
                $totalAmount = bcadd($subtotal, $taxAmount, 2);

                $status = fake()->randomElement([
                    InvoiceStatus::Paid,
                    InvoiceStatus::Paid,
                    InvoiceStatus::Issued,
                    InvoiceStatus::PartiallyPaid,
                ]);

                if ($status === InvoiceStatus::Paid) {
                    $amountPaid = $totalAmount;
                } elseif ($status === InvoiceStatus::PartiallyPaid) {
                    $amountPaid = bcmul($totalAmount, '0.5', 2);
                } else {
                    $amountPaid = '0.00';
                }

                $invoice = Invoice::create([
                    'invoice_number' => $this->generateInvoiceNumber(),
                    'invoice_type' => InvoiceType::StorageFee,
                    'customer_id' => $customer->id,
                    'currency' => 'EUR',
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'amount_paid' => $amountPaid,
                    'status' => $status,
                    'source_type' => 'storage_billing_period',
                    'source_id' => (string) fake()->uuid(),
                    'issued_at' => $issuedAt,
                    'due_date' => $dueDate,
                    'notes' => 'Quarterly storage fees',
                    'xero_invoice_id' => $status === InvoiceStatus::Paid ? 'INV-'.fake()->numerify('######') : null,
                    'xero_synced_at' => $status === InvoiceStatus::Paid ? $issuedAt : null,
                    'xero_sync_pending' => $status !== InvoiceStatus::Paid,
                ]);

                InvoiceLine::create([
                    'invoice_id' => $invoice->id,
                    'description' => "Wine Storage - {$bottleCount} bottles for {$daysInPeriod} days",
                    'quantity' => (string) $bottleDays,
                    'unit_price' => $dailyRate,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'metadata' => [
                        'line_type' => 'storage_fee',
                        'bottle_count' => $bottleCount,
                        'bottle_days' => $bottleDays,
                        'period_start' => $periodStart->format('Y-m-d'),
                        'period_end' => $periodEnd->format('Y-m-d'),
                        'unit_rate' => $dailyRate,
                        'location_name' => fake()->randomElement(['Milano Central Warehouse', 'London Docklands Warehouse', 'Geneva Free Port']),
                    ],
                ]);
            }
        }
    }

    /**
     * Create INV4 - Service Events invoices
     */
    private function createServiceEventInvoices($customers): void
    {
        $events = [
            [
                'name' => 'Barolo Grand Tasting 2025',
                'type' => 'tasting_event',
                'price' => 150.00,
                'service_type' => 'event_attendance',
            ],
            [
                'name' => 'Private Cellar Consultation',
                'type' => 'consultation',
                'price' => 350.00,
                'service_type' => 'consultation',
            ],
            [
                'name' => 'Burgundy Masterclass',
                'type' => 'masterclass',
                'price' => 250.00,
                'service_type' => 'event_attendance',
            ],
            [
                'name' => 'Wine Investment Advisory Session',
                'type' => 'advisory',
                'price' => 500.00,
                'service_type' => 'consultation',
            ],
            [
                'name' => 'Vertical Tasting: Sassicaia 2010-2020',
                'type' => 'tasting',
                'price' => 200.00,
                'service_type' => 'tasting_fee',
            ],
            [
                'name' => 'Annual Collector\'s Dinner',
                'type' => 'dinner_event',
                'price' => 450.00,
                'service_type' => 'event_attendance',
            ],
        ];

        foreach ($customers->take(5) as $customer) {
            // Create 1-2 event invoices
            $numInvoices = fake()->numberBetween(1, 2);

            for ($i = 0; $i < $numInvoices; $i++) {
                $event = fake()->randomElement($events);
                $issuedAt = fake()->dateTimeBetween('-3 months', '-1 week');

                $attendees = fake()->numberBetween(1, 2);
                $subtotal = bcmul((string) $attendees, number_format($event['price'], 2, '.', ''), 2);
                $taxRate = '22.00';
                $taxAmount = bcmul($subtotal, '0.22', 2);
                $totalAmount = bcadd($subtotal, $taxAmount, 2);

                $status = fake()->randomElement([
                    InvoiceStatus::Paid,
                    InvoiceStatus::Paid,
                    InvoiceStatus::Issued,
                ]);

                $amountPaid = $status === InvoiceStatus::Paid ? $totalAmount : '0.00';

                $invoice = Invoice::create([
                    'invoice_number' => $this->generateInvoiceNumber(),
                    'invoice_type' => InvoiceType::ServiceEvents,
                    'customer_id' => $customer->id,
                    'currency' => 'EUR',
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'amount_paid' => $amountPaid,
                    'status' => $status,
                    'source_type' => 'event_booking',
                    'source_id' => (string) fake()->uuid(),
                    'issued_at' => $issuedAt,
                    'due_date' => null, // Immediate payment
                    'xero_invoice_id' => $status === InvoiceStatus::Paid ? 'INV-'.fake()->numerify('######') : null,
                    'xero_synced_at' => $status === InvoiceStatus::Paid ? $issuedAt : null,
                    'xero_sync_pending' => $status !== InvoiceStatus::Paid,
                ]);

                InvoiceLine::create([
                    'invoice_id' => $invoice->id,
                    'description' => "{$event['name']}".($attendees > 1 ? " x{$attendees} attendees" : ''),
                    'quantity' => (string) $attendees,
                    'unit_price' => number_format($event['price'], 2, '.', ''),
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'metadata' => [
                        'service_type' => $event['service_type'],
                        'event_name' => $event['name'],
                        'event_type' => $event['type'],
                        'attendee_count' => $attendees,
                        'event_date' => fake()->dateTimeBetween('-1 month', '+2 months')->format('Y-m-d'),
                        'venue' => fake()->randomElement(['Palazzo Versace Tasting Room', 'Vinitaly Event Space', 'Private Estate']),
                    ],
                ]);
            }
        }

        // Create a draft invoice
        $customer = $customers->random();
        $event = $events[0];

        $subtotal = number_format($event['price'], 2, '.', '');
        $taxAmount = bcmul($subtotal, '0.22', 2);
        $totalAmount = bcadd($subtotal, $taxAmount, 2);

        $invoice = Invoice::create([
            'invoice_number' => null, // Drafts don't have numbers
            'invoice_type' => InvoiceType::ServiceEvents,
            'customer_id' => $customer->id,
            'currency' => 'EUR',
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'amount_paid' => '0.00',
            'status' => InvoiceStatus::Draft,
            'source_type' => 'event_booking',
            'source_id' => (string) fake()->uuid(),
            'issued_at' => null,
            'due_date' => null,
            'notes' => 'Pending confirmation',
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => $event['name'],
            'quantity' => '1.00',
            'unit_price' => $subtotal,
            'tax_rate' => '22.00',
            'tax_amount' => $taxAmount,
            'metadata' => [
                'service_type' => $event['service_type'],
                'event_name' => $event['name'],
            ],
        ]);
    }

    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber(): string
    {
        $number = str_pad((string) $this->invoiceCounter, 6, '0', STR_PAD_LEFT);
        $this->invoiceCounter++;

        return 'CRU-'.date('Y').'-'.$number;
    }
}
