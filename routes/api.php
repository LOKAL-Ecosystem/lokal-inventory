<?php

use App\Http\Controllers\Api\PosIntegrationApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Lokal-POS Integration & Sanctum Auth
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1/pos')->group(function () {
    // Synchronize stock balances for Lokal-POS
    Route::get('/stock-sync', [PosIntegrationApiController::class, 'getStockSync'])->name('api.pos.stock-sync');

    // Deduct stock automatically when order is placed in Lokal-POS
    Route::post('/order-deduct', [PosIntegrationApiController::class, 'processOrderDeduction'])->name('api.pos.order-deduct');
});
