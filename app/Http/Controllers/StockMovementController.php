<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $query = StockMovement::with(['item.unit', 'item.category', 'user']);

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $movements = $query->latest()->paginate(20);
        $items = Item::where('is_active', true)->get();

        $selectedItem = $request->filled('item_id') ? Item::with('unit')->find($request->item_id) : null;

        return view('movements.index', compact('movements', 'items', 'selectedItem'));
    }
}
