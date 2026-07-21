<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockIn;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function index()
    {
        $totalItems = Item::count();
        $totalSuppliers = Supplier::count();
        $totalValuation = Item::selectRaw('SUM(quantity_on_hand * unit_cost) as total')->value('total') ?? 0;
        $lowStockItems = $this->stockService->getLowStockItems();
        
        $recentMovements = StockMovement::with(['item.unit', 'user'])
            ->latest()
            ->take(8)
            ->get();

        $recentStockIns = StockIn::with(['supplier', 'user'])
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard', compact(
            'totalItems',
            'totalSuppliers',
            'totalValuation',
            'lowStockItems',
            'recentMovements',
            'recentStockIns'
        ));
    }
}
