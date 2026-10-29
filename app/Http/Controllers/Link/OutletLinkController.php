<?php

namespace App\Http\Controllers\Link;

use App\Http\Controllers\Controller;
use App\Jobs\ReconcileOrders;
use App\Jobs\SyncCatalogFromPos;
use App\Services\OutletLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * POST /link/outlets: Lokal POS menautkan outletnya (kontrak v2.3). Tanpa CSRF; tanda tangan diperiksa
 * middleware link.signed. Idempoten pada pos_outlet_id. Outlet ditentukan dari pos_outlet_id yang
 * ditandatangani, tidak pernah dari sesi.
 */
class OutletLinkController extends Controller
{
    public function __invoke(Request $request, OutletLinkService $links): JsonResponse
    {
        $data = $request->validate([
            'pos_outlet_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:120'],
            'pos_base_url' => ['required', 'url', 'max:255'],
            'api_token' => ['required', 'string', 'max:255'],
            'webhook_secret' => ['required', 'string', 'max:255'],
            'staff' => ['sometimes', 'array'],
            'staff.*.pos_user_id' => ['required', 'integer', 'min:1'],
            'staff.*.name' => ['required', 'string', 'max:255'],
            'staff.*.email' => ['required', 'email', 'max:255'],
            'staff.*.role' => ['nullable', 'string', 'max:20'],
            'staff.*.is_active' => ['sometimes', 'boolean'],
        ]);

        $outlet = $links->link($data);

        // Katalog & pesanan terlewat menyusul lewat queue; kegagalan di sini tidak menggagalkan penautan.
        try {
            SyncCatalogFromPos::dispatch($outlet->id);
            ReconcileOrders::dispatch($outlet->id);
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json([
            'inventory_outlet_id' => $outlet->id,
            'webhook_url' => $outlet->webhookUrl(),
        ]);
    }
}
