<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class PosIntegrationController extends Controller
{
    public function index()
    {
        $items = Item::with(['category', 'unit'])->where('is_active', true)->get();
        $mappedCount = Item::whereNotNull('pos_product_id')->count();

        return view('pos_integration.index', compact('items', 'mappedCount'));
    }

    public function updateMapping(Request $request)
    {
        $validated = $request->validate([
            'mappings' => 'required|array',
            'mappings.*.item_id' => 'required|exists:items,id',
            'mappings.*.pos_product_id' => 'nullable|string',
        ]);

        foreach ($validated['mappings'] as $row) {
            Item::where('id', $row['item_id'])->update([
                'pos_product_id' => $row['pos_product_id'] ?: null,
            ]);
        }

        return redirect()->route('pos-integration.index')->with('success', 'Mapping POS Product ID berhasil disimpan.');
    }
}
