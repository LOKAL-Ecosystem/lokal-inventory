<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::with(['category', 'unit', 'supplier']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('pos_product_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('low_stock')) {
            $query->whereRaw('quantity_on_hand <= minimum_stock');
        }

        $items = $query->latest()->paginate(15);
        $categories = Category::all();
        $units = Unit::all();
        $suppliers = Supplier::all();

        return view('items.index', compact('items', 'categories', 'units', 'suppliers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|unique:items,sku',
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'unit_id' => 'nullable|exists:units,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'quantity_on_hand' => 'required|numeric|min:0',
            'minimum_stock' => 'required|numeric|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'pos_product_id' => 'nullable|string',
        ]);

        $item = Item::create($validated);

        if ($item->quantity_on_hand > 0) {
            StockMovement::create([
                'item_id' => $item->id,
                'type' => 'initial',
                'quantity_before' => 0,
                'quantity_change' => $item->quantity_on_hand,
                'quantity_after' => $item->quantity_on_hand,
                'reference_no' => 'INIT-NEW-ITEM',
                'description' => 'Saldo Awal Pembuatan Item Baru',
                'user_id' => auth()->id(),
            ]);
        }

        return redirect()->route('items.index')->with('success', 'Item stok berhasil ditambahkan.');
    }

    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'sku' => 'required|string|unique:items,sku,' . $item->id,
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'unit_id' => 'nullable|exists:units,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'minimum_stock' => 'required|numeric|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
            'pos_product_id' => 'nullable|string',
        ]);

        $item->update($validated);

        return redirect()->route('items.index')->with('success', 'Data item berhasil diperbarui.');
    }

    public function destroy(Item $item)
    {
        $item->delete();
        return redirect()->route('items.index')->with('success', 'Item berhasil dihapus.');
    }
}
