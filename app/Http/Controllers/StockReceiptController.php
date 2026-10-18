<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockReceiptRequest;
use App\Models\Ingredient;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Services\StockReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockReceiptController extends Controller
{
    public function index(): View
    {
        return view('stock_receipts.index', [
            'receipts' => StockReceipt::with(['supplier', 'creator'])->withCount('items')
                ->latest('received_at')->latest('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('stock_receipts.create', [
            'ingredients' => Ingredient::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function store(StockReceiptRequest $request, StockReceiptService $service): RedirectResponse
    {
        $data = $request->validated();
        $receipt = $service->create($data, $data['items'], $request->user()->id);

        return redirect()->route('stock-receipts.show', $receipt)->with('success', 'Stok masuk dicatat.');
    }

    public function show(StockReceipt $receipt): View
    {
        return view('stock_receipts.show', ['receipt' => $receipt->load(['items.ingredient', 'supplier', 'creator'])]);
    }
}
