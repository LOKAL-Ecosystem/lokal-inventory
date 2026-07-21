<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockInItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        // Current stock valuation report
        $stockSummary = Item::with(['category', 'unit'])
            ->select('items.*')
            ->selectRaw('(quantity_on_hand * unit_cost) as total_valuation')
            ->orderBy('name')
            ->get();

        $totalValuation = $stockSummary->sum('total_valuation');
        $totalItems = $stockSummary->count();
        $lowStockCount = $stockSummary->filter(fn($i) => $i->isLowStock())->count();

        // Stock movement summary per period
        $movementSummary = StockMovement::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('type', DB::raw('COUNT(*) as total_transactions'), DB::raw('SUM(ABS(quantity_change)) as total_qty'))
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        // Top restocked items (most frequently purchased)
        $frequentRestocks = StockInItem::with('item.unit')
            ->select('item_id', DB::raw('SUM(quantity) as total_restock_qty'), DB::raw('SUM(subtotal) as total_restock_cost'), DB::raw('COUNT(*) as restock_count'))
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->groupBy('item_id')
            ->orderByDesc('total_restock_qty')
            ->take(10)
            ->get();

        return view('reports.index', compact(
            'startDate',
            'endDate',
            'stockSummary',
            'totalValuation',
            'totalItems',
            'lowStockCount',
            'movementSummary',
            'frequentRestocks'
        ));
    }
}
