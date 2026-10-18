<?php

use App\Http\Controllers\Webhooks\LokalPosWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook dari Lokal POS (kontrak v2: WEBHOOK_CONTRACT.md di repo lokal_pos)
|--------------------------------------------------------------------------
| Rute utama ada di routes/web.php (POST /webhooks/lokal-pos). Dua prefix di bawah
| dipertahankan agar URL webhook yang sudah didaftarkan di POS tetap berfungsi.
*/
Route::post('webhooks/pos', LokalPosWebhookController::class)->name('api.webhooks.pos');
Route::post('v1/webhooks/pos', LokalPosWebhookController::class)->name('api.v1.webhooks.pos');
