<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Jobs\Finance\ProcessStripeWebhookJob;
use App\Models\Finance\StripeWebhook;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Controller for handling Stripe webhook events.
 *
 * This controller handles incoming webhooks from Stripe, verifies their
 * signatures, creates immutable log entries for idempotency, and dispatches
 * async jobs for processing.
 *
 * IMPORTANT: This endpoint returns 200 immediately after logging the webhook
 * to prevent Stripe from retrying. Actual processing happens asynchronously.
 */
class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhook.
     *
     * POST /api/webhooks/stripe
     *
     * The webhook is processed in 4 steps:
     * 1. Verify Stripe signature to ensure authenticity
     * 2. Check for duplicate event_id (idempotency)
     * 3. Create StripeWebhook log record
     * 4. Dispatch async job for processing
     * 5. Return 200 immediately
     */
    public function handle(Request $request): JsonResponse
    {
        // Get the raw payload and signature header
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        // Step 1: Verify Stripe signature
        $stripeWebhookSecret = config('services.stripe.webhook_secret');

        if (empty($stripeWebhookSecret)) {
            Log::channel('finance')->error('Stripe webhook secret is not configured');

            // Still return 200 to prevent Stripe from retrying
            // but log the configuration error
            return response()->json([
                'success' => false,
                'message' => 'Webhook secret not configured',
            ], 200);
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                (string) $sigHeader,
                $stripeWebhookSecret
            );
        } catch (SignatureVerificationException $e) {
            Log::channel('finance')->warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
                'sig_header' => $sigHeader,
            ]);

            // Return 400 for signature verification failures
            // Stripe will retry with proper signature
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature',
            ], 400);
        } catch (Exception $e) {
            Log::channel('finance')->error('Stripe webhook construction failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid payload',
            ], 400);
        }

        $eventId = $event->id;
        $eventType = $event->type;

        // Step 2: Check for duplicate event (idempotency)
        if (StripeWebhook::hasEvent($eventId)) {
            Log::channel('finance')->info('Stripe webhook already received (idempotent skip)', [
                'event_id' => $eventId,
                'event_type' => $eventType,
            ]);

            // Return 200 for duplicate events - Stripe should not retry
            return response()->json([
                'success' => true,
                'message' => 'Event already processed',
                'duplicate' => true,
            ]);
        }

        // Step 3: Create StripeWebhook log record
        /** @var array<string, mixed> $payloadArray */
        $payloadArray = json_decode($payload, true) ?? [];

        $stripeWebhook = StripeWebhook::createFromStripeEvent(
            $eventId,
            $eventType,
            $payloadArray
        );

        Log::channel('finance')->info('Stripe webhook received and logged', [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'webhook_id' => $stripeWebhook->id,
        ]);

        // Step 4: Dispatch async job for processing
        ProcessStripeWebhookJob::dispatch($stripeWebhook);

        // Step 5: Return 200 immediately
        return response()->json([
            'success' => true,
            'message' => 'Webhook received and queued for processing',
            'webhook_id' => $stripeWebhook->id,
        ]);
    }
}
