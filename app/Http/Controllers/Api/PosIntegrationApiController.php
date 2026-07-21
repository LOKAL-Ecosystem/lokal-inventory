<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LokalPosIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosIntegrationApiController extends Controller
{
    protected LokalPosIntegrationService $integrationService;

    public function __construct(LokalPosIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    /**
     * Endpoint for POS to query current stock levels
     * GET /api/v1/pos/stock-sync
     */
    public function getStockSync(): JsonResponse
    {
        $data = $this->integrationService->getStockExportForPos();

        return response()->json([
            'status' => 'success',
            'message' => 'Data stok terbaru berhasil di-retrieve.',
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ]);
    }

    /**
     * Webhook/API Endpoint for POS when order occurs to deduct stock automatically
     * POST /api/v1/pos/order-deduct
     */
    public function processOrderDeduction(Request $request): JsonResponse
    {
        $request->validate([
            'reference_no' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $result = $this->integrationService->processOrderDeduction($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Pengurangan stok otomatis dari transaksi POS selesai diproses.',
            'result' => $result,
        ]);
    }
}
