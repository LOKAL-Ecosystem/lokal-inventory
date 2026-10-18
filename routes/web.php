<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\OpnameController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockReceiptController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WasteController;
use App\Http\Controllers\Webhooks\LokalPosWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Penerima webhook Lokal POS (tanpa CSRF, diverifikasi lewat signature).
Route::post('webhooks/lokal-pos', LokalPosWebhookController::class)->name('webhooks.lokal-pos');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    // Bahan baku: semua peran boleh melihat; hanya pemilik yang mengubah.
    Route::get('ingredients', [IngredientController::class, 'index'])->name('ingredients.index');
    Route::get('ingredients/{ingredient}', [IngredientController::class, 'show'])->name('ingredients.show');
    Route::middleware('can:manage-inventory')->group(function () {
        Route::post('ingredients', [IngredientController::class, 'store'])->name('ingredients.store');
        Route::put('ingredients/{ingredient}', [IngredientController::class, 'update'])->name('ingredients.update');
        Route::delete('ingredients/{ingredient}', [IngredientController::class, 'destroy'])->name('ingredients.destroy');

        Route::resource('suppliers', SupplierController::class)->except(['create', 'edit', 'show']);

        Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
        Route::get('recipes/{posProductId}', [RecipeController::class, 'show'])->whereNumber('posProductId')->name('recipes.show');
        Route::put('recipes/{posProductId}', [RecipeController::class, 'update'])->whereNumber('posProductId')->name('recipes.update');
        Route::post('recipes/{posProductId}/adjustments', [RecipeController::class, 'storeAdjustment'])->whereNumber('posProductId')->name('recipes.adjustments.store');
        Route::delete('recipes/adjustments/{adjustment}', [RecipeController::class, 'destroyAdjustment'])->name('recipes.adjustments.destroy');
        Route::get('recipes/{posProductId}/preview', [RecipeController::class, 'preview'])->whereNumber('posProductId')->name('recipes.preview');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/test', [SettingsController::class, 'test'])->name('settings.test');
        Route::post('settings/sync', [SettingsController::class, 'sync'])->name('settings.sync');
        Route::post('settings/inbox/{inbox}/retry', [SettingsController::class, 'retry'])->name('settings.inbox.retry');
    });

    // Stok masuk, waste, opname: pemilik & kasir.
    Route::get('stock-receipts', [StockReceiptController::class, 'index'])->name('stock-receipts.index');
    Route::get('stock-receipts/create', [StockReceiptController::class, 'create'])->name('stock-receipts.create');
    Route::post('stock-receipts', [StockReceiptController::class, 'store'])->name('stock-receipts.store');
    Route::get('stock-receipts/{receipt}', [StockReceiptController::class, 'show'])->name('stock-receipts.show');

    Route::get('waste', [WasteController::class, 'index'])->name('waste.index');
    Route::post('waste', [WasteController::class, 'store'])->name('waste.store');

    Route::get('opnames', [OpnameController::class, 'index'])->name('opnames.index');
    Route::post('opnames', [OpnameController::class, 'store'])->name('opnames.store');
    Route::get('opnames/{opname}', [OpnameController::class, 'show'])->name('opnames.show');
    Route::put('opnames/{opname}', [OpnameController::class, 'update'])->name('opnames.update');
    Route::post('opnames/{opname}/complete', [OpnameController::class, 'complete'])->middleware('can:complete-opname')->name('opnames.complete');

    Route::middleware('can:view-costs')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });
});
