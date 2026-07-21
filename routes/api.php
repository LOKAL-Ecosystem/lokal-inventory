<?php

use App\Http\Controllers\Api\PosIntegrationApiController;
use App\Http\Controllers\Api\PosWebhookController;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Lokal-POS Integration & Webhooks
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Legacy / Direct REST API routes for POS
Route::prefix('v1/pos')->group(function () {
    Route::get('/stock-sync', [PosIntegrationApiController::class, 'getStockSync'])->name('api.pos.stock-sync');
    Route::post('/order-deduct', [PosIntegrationApiController::class, 'processOrderDeduction'])->name('api.pos.order-deduct');
});

/*
|--------------------------------------------------------------------------
| Webhook Receiver Routes (Protected by HMAC Signature Verification)
|--------------------------------------------------------------------------
*/
Route::prefix('webhooks/pos')->middleware(VerifyWebhookSignature::class)->group(function () {
    Route::post('/order-completed', [PosWebhookController::class, 'handleOrderCompleted'])->name('api.webhooks.pos.order-completed');
    Route::post('/stock-adjusted', [PosWebhookController::class, 'handleStockAdjusted'])->name('api.webhooks.pos.stock-adjusted');
    Route::post('/', [PosWebhookController::class, 'handleGenericWebhook'])->name('api.webhooks.pos.generic');
});
