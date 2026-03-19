<?php

use App\Http\Controllers\Api\Finance\StripeWebhookController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\V1\Customer\AddressController;
use App\Http\Controllers\Api\V1\Customer\AuthController;
use App\Http\Controllers\Api\V1\Customer\CellarController;
use App\Http\Controllers\Api\V1\Customer\InvoiceController;
use App\Http\Controllers\Api\V1\Customer\OfferController;
use App\Http\Controllers\Api\V1\Customer\ProfileController;
use App\Http\Controllers\Api\V1\Customer\ShippingOrderController;
use App\Http\Controllers\Api\V1\Customer\SubscriptionController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Middleware\CustomerApiEnabled;
use App\Http\Middleware\EnsureCustomerActive;
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
| Health Check
|--------------------------------------------------------------------------
*/

Route::get('/health', HealthController::class)
    ->middleware(['throttle:api'])
    ->name('api.health');

Route::get('/metrics', MetricsController::class)
    ->middleware(['throttle:api'])
    ->name('api.metrics');

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

/*
|--------------------------------------------------------------------------
| Customer API v1
|--------------------------------------------------------------------------
|
| Customer-facing REST API with Sanctum token authentication.
| All endpoints are behind the CustomerApiEnabled feature flag.
|
*/

Route::prefix('v1/customer')
    ->middleware([CustomerApiEnabled::class])
    ->group(function (): void {

        // Public: login (rate-limited)
        Route::post('/auth/login', [AuthController::class, 'login'])
            ->middleware('throttle:customer-login')
            ->name('customer.auth.login');

        // Protected: requires Sanctum token + active customer
        Route::middleware(['auth:customer', EnsureCustomerActive::class, 'throttle:customer-api'])
            ->group(function (): void {

                // Auth
                Route::post('/auth/logout', [AuthController::class, 'logout'])
                    ->name('customer.auth.logout');
                Route::get('/auth/me', [AuthController::class, 'me'])
                    ->name('customer.auth.me');

                // Profile
                Route::get('/profile', [ProfileController::class, 'show'])
                    ->name('customer.profile.show');
                Route::patch('/profile', [ProfileController::class, 'update'])
                    ->name('customer.profile.update');

                // Cellar (Vouchers)
                Route::get('/cellar', [CellarController::class, 'index'])
                    ->name('customer.cellar.index');
                Route::get('/cellar/{voucher}', [CellarController::class, 'show'])
                    ->name('customer.cellar.show');

                // Offers
                Route::get('/offers', [OfferController::class, 'index'])
                    ->name('customer.offers.index');
                Route::get('/offers/{offer}', [OfferController::class, 'show'])
                    ->name('customer.offers.show');

                // Shipping Orders
                Route::get('/shipping-orders', [ShippingOrderController::class, 'index'])
                    ->name('customer.shipping-orders.index');
                Route::get('/shipping-orders/{shippingOrder}', [ShippingOrderController::class, 'show'])
                    ->name('customer.shipping-orders.show');
                Route::post('/shipping-orders', [ShippingOrderController::class, 'store'])
                    ->name('customer.shipping-orders.store');

                // Invoices
                Route::get('/invoices', [InvoiceController::class, 'index'])
                    ->name('customer.invoices.index');
                Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
                    ->name('customer.invoices.show');
                Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])
                    ->name('customer.invoices.pdf');

                // Subscriptions
                Route::get('/subscriptions', [SubscriptionController::class, 'index'])
                    ->name('customer.subscriptions.index');
                Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])
                    ->name('customer.subscriptions.show');

                // Addresses
                Route::get('/addresses', [AddressController::class, 'index'])
                    ->name('customer.addresses.index');
                Route::post('/addresses', [AddressController::class, 'store'])
                    ->name('customer.addresses.store');
                Route::patch('/addresses/{address}', [AddressController::class, 'update'])
                    ->name('customer.addresses.update');
                Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])
                    ->name('customer.addresses.destroy');
            });
    });
