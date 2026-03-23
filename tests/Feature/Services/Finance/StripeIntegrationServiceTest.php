<?php

namespace Tests\Feature\Services\Finance;

use App\Enums\Finance\PaymentSource;
use App\Enums\Finance\PaymentStatus;
use App\Jobs\Finance\ProcessStripeWebhookJob;
use App\Models\Customer\Customer;
use App\Models\Finance\Payment;
use App\Models\Finance\Refund;
use App\Models\Finance\StripeWebhook;
use App\Services\Finance\StripeIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class StripeIntegrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private StripeIntegrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StripeIntegrationService::class);
    }

    /**
     * Create a payment_intent.succeeded webhook with realistic payload.
     */
    private function createSucceededWebhook(?string $piId = null, int $amountCents = 15000, ?string $customerId = null): StripeWebhook
    {
        $piId = $piId ?? 'pi_'.Str::random(24);

        return StripeWebhook::factory()->create([
            'event_type' => 'payment_intent.succeeded',
            'payload' => [
                'type' => 'payment_intent.succeeded',
                'data' => [
                    'object' => [
                        'id' => $piId,
                        'amount' => $amountCents,
                        'currency' => 'eur',
                        'customer' => $customerId,
                        'metadata' => [],
                        'latest_charge' => 'ch_'.Str::random(24),
                    ],
                ],
            ],
        ]);
    }

    /**
     * Create a payment_intent.payment_failed webhook.
     */
    private function createFailedWebhook(?string $piId = null): StripeWebhook
    {
        return StripeWebhook::factory()->create([
            'event_type' => 'payment_intent.payment_failed',
            'payload' => [
                'type' => 'payment_intent.payment_failed',
                'data' => [
                    'object' => [
                        'id' => $piId ?? 'pi_'.Str::random(24),
                        'amount' => 10000,
                        'currency' => 'eur',
                        'last_payment_error' => [
                            'code' => 'card_declined',
                            'message' => 'Your card was declined.',
                            'decline_code' => 'generic_decline',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Create a charge.refunded webhook.
     */
    private function createRefundWebhook(string $chargeId, string $refundId = 're_test', int $amountCents = 5000): StripeWebhook
    {
        return StripeWebhook::factory()->create([
            'event_type' => 'charge.refunded',
            'payload' => [
                'type' => 'charge.refunded',
                'data' => [
                    'object' => [
                        'id' => $chargeId,
                        'amount' => $amountCents,
                        'currency' => 'eur',
                        'payment_intent' => 'pi_'.Str::random(24),
                        'refunds' => [
                            'data' => [
                                [
                                    'id' => $refundId,
                                    'amount' => $amountCents,
                                    'currency' => 'eur',
                                    'status' => 'succeeded',
                                    'reason' => 'requested_by_customer',
                                    'metadata' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    // --- processWebhook routing ---

    public function test_process_routes_payment_succeeded(): void
    {
        $customer = Customer::factory()->create(['stripe_customer_id' => 'cus_test_route']);
        $webhook = $this->createSucceededWebhook(customerId: 'cus_test_route');

        $this->service->processWebhook($webhook);

        $webhook->refresh();
        $this->assertTrue($webhook->isProcessed());
    }

    public function test_process_routes_payment_failed(): void
    {
        $webhook = $this->createFailedWebhook();

        $this->service->processWebhook($webhook);

        $webhook->refresh();
        $this->assertTrue($webhook->isProcessed());
    }

    public function test_process_skips_already_processed(): void
    {
        $webhook = StripeWebhook::factory()->processed()->create([
            'event_type' => 'payment_intent.succeeded',
        ]);

        // Should not throw, just skip
        $this->service->processWebhook($webhook);
        $this->assertTrue($webhook->isProcessed());
    }

    public function test_process_marks_processed(): void
    {
        $webhook = $this->createFailedWebhook();
        $this->assertFalse($webhook->isProcessed());

        $this->service->processWebhook($webhook);

        $webhook->refresh();
        $this->assertTrue($webhook->isProcessed());
        $this->assertNotNull($webhook->processed_at);
    }

    public function test_process_marks_failed_on_error(): void
    {
        // Webhook with missing pi_ ID should throw RuntimeException
        $webhook = StripeWebhook::factory()->create([
            'event_type' => 'payment_intent.succeeded',
            'payload' => [
                'data' => [
                    'object' => [
                        'id' => 'not_a_pi_id',
                        'amount' => 1000,
                        'currency' => 'eur',
                    ],
                ],
            ],
        ]);

        try {
            $this->service->processWebhook($webhook);
        } catch (RuntimeException) {
            // Expected
        }

        $webhook->refresh();
        $this->assertFalse($webhook->isProcessed());
        $this->assertNotNull($webhook->error_message);
    }

    public function test_process_handles_unknown_event(): void
    {
        $webhook = StripeWebhook::factory()->create([
            'event_type' => 'customer.created',
            'payload' => ['type' => 'customer.created', 'data' => ['object' => []]],
        ]);

        $this->service->processWebhook($webhook);

        $webhook->refresh();
        $this->assertTrue($webhook->isProcessed());
    }

    // --- handlePaymentSucceeded ---

    public function test_payment_succeeded_creates_payment(): void
    {
        $customer = Customer::factory()->create(['stripe_customer_id' => 'cus_test_pay']);
        $webhook = $this->createSucceededWebhook(amountCents: 25000, customerId: 'cus_test_pay');

        $payment = $this->service->handlePaymentSucceeded($webhook);

        $this->assertEquals('250.00', $payment->amount);
        $this->assertEquals(PaymentSource::Stripe, $payment->source);
        $this->assertEquals($customer->id, $payment->customer_id);
    }

    public function test_payment_succeeded_throws_without_intent_id(): void
    {
        $webhook = StripeWebhook::factory()->create([
            'event_type' => 'payment_intent.succeeded',
            'payload' => [
                'data' => [
                    'object' => [
                        'id' => 'ch_not_pi',
                        'amount' => 1000,
                        'currency' => 'eur',
                    ],
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment intent ID not found');

        $this->service->handlePaymentSucceeded($webhook);
    }

    // --- handlePaymentFailed ---

    public function test_payment_failed_updates_existing(): void
    {
        $piId = 'pi_fail_test_'.Str::random(16);
        $payment = Payment::factory()->create([
            'stripe_payment_intent_id' => $piId,
            'status' => PaymentStatus::Pending,
        ]);

        $webhook = $this->createFailedWebhook($piId);

        $this->service->handlePaymentFailed($webhook);

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Failed, $payment->status);
    }

    public function test_payment_failed_no_existing_graceful(): void
    {
        $webhook = $this->createFailedWebhook('pi_no_existing_'.Str::random(16));

        // Should not throw — just logs a warning
        $this->service->handlePaymentFailed($webhook);
        $this->addToAssertionCount(1);
    }

    // --- processRefund ---

    public function test_refund_processes_without_error(): void
    {
        $chargeId = 'ch_refund_test_'.Str::random(16);
        $payment = Payment::factory()->confirmed()->create([
            'stripe_charge_id' => $chargeId,
        ]);

        $webhook = $this->createRefundWebhook($chargeId, 're_test_'.Str::random(16), 5000);

        // processRefund should not throw — it gracefully handles missing invoice links
        $this->service->processRefund($webhook);
        $this->addToAssertionCount(1);
    }

    public function test_refund_skips_duplicate(): void
    {
        $chargeId = 'ch_dup_refund_'.Str::random(16);
        $refundId = 're_dup_'.Str::random(16);
        $payment = Payment::factory()->confirmed()->create([
            'stripe_charge_id' => $chargeId,
        ]);

        $webhook = $this->createRefundWebhook($chargeId, $refundId, 5000);

        // Process first time
        $this->service->processRefund($webhook);
        $countAfterFirst = Refund::where('payment_id', $payment->id)->count();

        // Process again — should skip duplicate
        $webhook2 = $this->createRefundWebhook($chargeId, $refundId, 5000);
        $this->service->processRefund($webhook2);
        $countAfterSecond = Refund::where('payment_id', $payment->id)->count();

        $this->assertEquals($countAfterFirst, $countAfterSecond);
    }

    public function test_refund_throws_without_charge_id(): void
    {
        $webhook = StripeWebhook::factory()->create([
            'event_type' => 'charge.refunded',
            'payload' => [
                'data' => [
                    'object' => [
                        'id' => 'not_a_charge',
                        'amount' => 1000,
                        'currency' => 'eur',
                    ],
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Charge ID not found');

        $this->service->processRefund($webhook);
    }

    // --- getIntegrationHealth ---

    public function test_health_healthy_status(): void
    {
        // Create recent processed webhook
        StripeWebhook::factory()->processed()->create([
            'created_at' => now()->subMinutes(5),
        ]);

        $health = $this->service->getIntegrationHealth();

        $this->assertEquals('healthy', $health['status']);
        $this->assertTrue($health['is_healthy']);
        $this->assertTrue($health['has_recent_activity']);
    }

    public function test_health_warning_no_activity(): void
    {
        // Only old webhooks
        StripeWebhook::factory()->processed()->create([
            'created_at' => now()->subHours(2),
        ]);

        $health = $this->service->getIntegrationHealth();

        $this->assertEquals('warning', $health['status']);
        $this->assertFalse($health['is_healthy']);
        $this->assertFalse($health['has_recent_activity']);
    }

    public function test_health_critical_failures(): void
    {
        // Recent activity to avoid the no-activity warning
        StripeWebhook::factory()->processed()->create([
            'created_at' => now()->subMinutes(5),
        ]);
        // >10 failed webhooks
        StripeWebhook::factory()->failed()->count(11)->create();

        $health = $this->service->getIntegrationHealth();

        $this->assertEquals('critical', $health['status']);
        $this->assertFalse($health['is_healthy']);
    }

    // --- retryFailedWebhook ---

    public function test_retry_dispatches_job(): void
    {
        Queue::fake([ProcessStripeWebhookJob::class]);

        $webhook = StripeWebhook::factory()->failed()->create();

        $result = $this->service->retryFailedWebhook($webhook);

        $this->assertTrue($result);
        Queue::assertPushed(ProcessStripeWebhookJob::class);
    }

    public function test_retry_rejects_non_failed(): void
    {
        $webhook = StripeWebhook::factory()->processed()->create();

        $result = $this->service->retryFailedWebhook($webhook);

        $this->assertFalse($result);
    }

    public function test_retry_increments_count(): void
    {
        Queue::fake([ProcessStripeWebhookJob::class]);

        $webhook = StripeWebhook::factory()->failed()->create([
            'retry_count' => 2,
        ]);

        $this->service->retryFailedWebhook($webhook);

        $webhook->refresh();
        $this->assertEquals(3, $webhook->retry_count);
        $this->assertNotNull($webhook->last_retry_at);
    }

    // --- retryAllFailedWebhooks ---

    public function test_retry_all_failed(): void
    {
        Queue::fake([ProcessStripeWebhookJob::class]);

        StripeWebhook::factory()->failed()->count(3)->create();

        $count = $this->service->retryAllFailedWebhooks();

        $this->assertEquals(3, $count);
    }

    // --- dispatchWebhookJob ---

    public function test_dispatch_webhook_job(): void
    {
        Queue::fake([ProcessStripeWebhookJob::class]);

        $webhook = StripeWebhook::factory()->create();

        $this->service->dispatchWebhookJob($webhook);

        Queue::assertPushed(ProcessStripeWebhookJob::class);
    }
}
