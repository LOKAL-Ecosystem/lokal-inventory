<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\StockInController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\PosIntegrationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Auto-login mock user for smooth testing/demo if unauthenticated
Route::get('/login-dev', function () {
    $user = \App\Models\User::firstOrCreate(
        ['email' => 'admin@lokal.id'],
        ['name' => 'Admin Inventory', 'password' => bcrypt('password'), 'role' => 'admin']
    );
    Auth::login($user);
    return redirect('/');
});

Route::middleware(['web'])->group(function () {
    // Ensure demo user is logged in for seamless presentation
    Route::get('/', function() {
        if (!Auth::check()) {
            $user = \App\Models\User::first();
            if ($user) Auth::login($user);
        }
        return app(DashboardController::class)->index();
    })->name('dashboard');

    Route::resource('items', ItemController::class)->except(['create', 'edit', 'show']);
    Route::resource('suppliers', SupplierController::class)->except(['create', 'edit', 'show']);

    Route::resource('stock-in', StockInController::class)->only(['index', 'create', 'store']);

    Route::resource('adjustments', StockAdjustmentController::class)->only(['index', 'create', 'store']);
    Route::post('adjustments/{adjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('adjustments.approve');

    Route::get('movements', [StockMovementController::class, 'index'])->name('movements.index');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    Route::get('pos-integration', [PosIntegrationController::class, 'index'])->name('pos-integration.index');
    Route::post('pos-integration/mapping', [PosIntegrationController::class, 'updateMapping'])->name('pos-integration.update-mapping');

    // Webhook Monitoring & Recipe (BOM) Management
    Route::get('webhooks', [\App\Http\Controllers\WebhookMonitoringController::class, 'index'])->name('webhooks.index');
    Route::get('webhooks/unmapped', [\App\Http\Controllers\WebhookMonitoringController::class, 'unmapped'])->name('webhooks.unmapped');
    Route::post('webhooks/recipe', [\App\Http\Controllers\WebhookMonitoringController::class, 'storeRecipe'])->name('webhooks.recipe.store');
});
