<?php

use App\Http\Controllers\Api\VoucherController;
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

Route::prefix('vouchers')->group(function (): void {
    // Trading completion callback from external trading platforms
    Route::post('/{voucher}/trading-complete', [VoucherController::class, 'tradingComplete'])
        ->name('api.vouchers.trading-complete');
});
