<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Http\Request;

class StockInController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function index(Request $request)
    {
        $query = StockIn::with(['supplier', 'user', 'items.item.unit']);

        if ($request->filled('search')) {
            $query->where('reference_no', 'like', "%{$request->search}%");
        }

        $stockIns = $query->latest()->paginate(15);

        return view('stock_in.index', compact('stockIns'));
    }

    public function create()
    {
        $suppliers = Supplier::all();
        $items = Item::with(['unit', 'category'])->where('is_active', true)->get();

        return view('stock_in.create', compact('suppliers', 'items'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        $this->stockService->recordStockIn(
            $validated,
            $validated['items'],
            auth()->id()
        );

        return redirect()->route('stock-in.index')->with('success', 'Transaksi Stock In / Pembelian berhasil dicatat & stok telah diupdate.');
    }
}
