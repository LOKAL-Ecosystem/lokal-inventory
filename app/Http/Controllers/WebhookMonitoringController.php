<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Recipe;
use App\Models\UnmappedProduct;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;

class WebhookMonitoringController extends Controller
{
    /**
     * List incoming webhooks history
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        
        $query = WebhookEvent::latest();
        if ($status) {
            $query->where('status', $status);
        }

        $webhooks = $query->paginate(20);

        return view('webhooks.index', compact('webhooks', 'status'));
    }

    /**
     * List unmapped products received from POS
     */
    public function unmapped()
    {
        $unmappedProducts = UnmappedProduct::latest('last_seen_at')->paginate(20);
        $stockItems = Item::where('is_active', true)->orderBy('name')->get();

        return view('webhooks.unmapped', compact('unmappedProducts', 'stockItems'));
    }

    /**
     * Store recipe mapping for an unmapped product
     */
    public function storeRecipe(Request $request)
    {
        $request->validate([
            'pos_product_id' => 'required|string',
            'pos_product_name' => 'nullable|string',
            'stock_item_id' => 'required|exists:items,id',
            'quantity_needed' => 'required|numeric|min:0.0001',
            'unit' => 'nullable|string',
        ]);

        Recipe::updateOrCreate(
            [
                'pos_product_id' => $request->input('pos_product_id'),
                'stock_item_id' => $request->input('stock_item_id'),
            ],
            [
                'pos_product_name' => $request->input('pos_product_name'),
                'quantity_needed' => $request->input('quantity_needed'),
                'unit' => $request->input('unit'),
            ]
        );

        // Remove from unmapped products table after successful mapping
        UnmappedProduct::where('pos_product_id', $request->input('pos_product_id'))->delete();

        return redirect()->route('webhooks.unmapped')->with('success', 'Resep/BOM berhasil di-mapping!');
    }
}
