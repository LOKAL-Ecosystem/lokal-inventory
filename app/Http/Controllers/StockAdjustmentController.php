<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockAdjustment;
use App\Services\StockAdjustmentService;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    protected StockAdjustmentService $adjustmentService;

    public function __construct(StockAdjustmentService $adjustmentService)
    {
        $this->adjustmentService = $adjustmentService;
    }

    public function index(Request $request)
    {
        $query = StockAdjustment::with(['user', 'approver', 'items.item.unit']);

        if ($request->filled('search')) {
            $query->where('reference_no', 'like', "%{$request->search}%");
        }

        $adjustments = $query->latest()->paginate(15);

        return view('adjustments.index', compact('adjustments'));
    }

    public function create()
    {
        $items = Item::with(['unit', 'category'])->where('is_active', true)->get();

        return view('adjustments.create', compact('items'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reason' => 'required|in:damaged,lost,stock_opname_discrepancy,other',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.actual_quantity' => 'required|numeric|min:0',
        ]);

        $user = auth()->user();
        // If admin, auto approve. If staff, status becomes pending.
        $autoApprove = $user->isAdmin();

        $adjustment = $this->adjustmentService->createAdjustment(
            $validated,
            $validated['items'],
            $user->id,
            $autoApprove
        );

        $msg = $autoApprove
            ? 'Stock adjustment berhasil disimpan & stok langsung diperbarui.'
            : 'Stock adjustment berhasil dibuat dan menunggu persetujuan (approval) Admin.';

        return redirect()->route('adjustments.index')->with('success', $msg);
    }

    public function approve(StockAdjustment $adjustment)
    {
        if (!auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', 'Hanya Admin yang berhak menyetujui Stock Adjustment.');
        }

        $this->adjustmentService->approveAdjustment($adjustment, auth()->id());

        return redirect()->route('adjustments.index')->with('success', 'Stock adjustment berhasil disetujui & stok diperbarui.');
    }
}
