<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\PosProduct;
use App\Models\RecipeItem;
use App\Models\WebhookInbox;
use App\Services\CostingService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(CostingService $costing): View
    {
        $outlet = auth()->user()->outlet;

        return view('dashboard', [
            'outlet' => $outlet,
            'lowStock' => Ingredient::lowStock()->orderByRaw('current_stock - min_stock')->limit(10)->get(),
            'lowStockCount' => Ingredient::lowStock()->count(),
            'negativeStock' => Ingredient::where('current_stock', '<', 0)->orderBy('current_stock')->get(),
            'noRecipe' => PosProduct::query()
                ->whereNotIn('pos_product_id', RecipeItem::query()->select('pos_product_id'))
                ->orderByDesc('missing_recipe_hits')
                ->orderBy('name')
                ->get(),
            'soldOut' => PosProduct::where('auto_unavailable', true)->orderBy('name')->get(),
            'stockValue' => auth()->user()->can('view-costs') ? $costing->stockValue() : null,
            'failedEvents' => WebhookInbox::whereNull('processed_at')->whereNotNull('last_error')->count(),
            'pendingEvents' => WebhookInbox::whereNull('processed_at')->count(),
        ]);
    }
}
