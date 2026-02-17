<?php

use App\Http\Controllers\Api\Finance\StripeWebhookController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Middleware\VerifyHmacSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

/*
|--------------------------------------------------------------------------
| Voucher API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('vouchers')
    ->middleware(['throttle:trading-api', VerifyHmacSignature::class])
    ->group(function (): void {
        // Trading completion callback from external trading platforms
        Route::post('/{voucher}/trading-complete', [VoucherController::class, 'tradingComplete'])
            ->name('api.vouchers.trading-complete');
    });

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
|
| Webhook endpoints for external service integrations.
| These routes are typically not authenticated via standard API auth
| but use signature verification specific to each service.
|
*/

Route::prefix('webhooks')
    ->middleware(['throttle:api'])
    ->group(function (): void {
        // Stripe webhook endpoint
        // Signature verification is handled in the controller
        Route::post('/stripe', [StripeWebhookController::class, 'handle'])
            ->name('api.webhooks.stripe');
    });
