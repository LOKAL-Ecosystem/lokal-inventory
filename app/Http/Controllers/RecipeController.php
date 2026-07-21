<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Recipe;
use App\Models\RecipeModifierAdjustment;
use App\Services\RecipeCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecipeController extends Controller
{
    protected RecipeCalculatorService $calculatorService;

    public function __construct(RecipeCalculatorService $calculatorService)
    {
        $this->calculatorService = $calculatorService;
    }

    /**
     * Display listing of products with BOM/Recipes
     */
    public function index()
    {
        $recipes = Recipe::with('stockItem')
            ->select('pos_product_id', 'pos_product_name')
            ->selectRaw('COUNT(*) as total_ingredients')
            ->groupBy('pos_product_id', 'pos_product_name')
            ->paginate(20);

        return view('recipes.index', compact('recipes'));
    }

    /**
     * Show recipe & modifier adjustments for a specific POS product
     */
    public function show($posProductId)
    {
        $baseRecipes = Recipe::with('stockItem')->where('pos_product_id', $posProductId)->get();
        $productName = $baseRecipes->first()?->pos_product_name ?? ('POS Product #' . $posProductId);

        $modifierAdjustments = RecipeModifierAdjustment::with('stockItem')
            ->forPosProduct($posProductId)
            ->get();

        $stockItems = Item::where('is_active', true)->orderBy('name')->get();

        // Attempt to fetch modifier options from lokal-pos API or fallback to pre-existing names
        $modifiersList = $this->fetchPosModifiers();

        return view('recipes.show', compact(
            'posProductId',
            'productName',
            'baseRecipes',
            'modifierAdjustments',
            'stockItems',
            'modifiersList'
        ));
    }

    /**
     * Save/update a modifier adjustment rule
     */
    public function storeAdjustment(Request $request, $posProductId)
    {
        $validated = $request->validate([
            'pos_modifier_id' => 'required|numeric',
            'pos_modifier_name' => 'required|string|max:255',
            'stock_item_id' => 'required|exists:items,id',
            'adjustment_type' => 'required|in:override,add,subtract',
            'adjustment_qty' => 'required|numeric|min:0',
            'unit' => 'nullable|string',
        ]);

        RecipeModifierAdjustment::updateOrCreate(
            [
                'pos_product_id' => $posProductId,
                'pos_modifier_id' => $validated['pos_modifier_id'],
                'stock_item_id' => $validated['stock_item_id'],
            ],
            [
                'pos_modifier_name' => $validated['pos_modifier_name'],
                'adjustment_type' => $validated['adjustment_type'],
                'adjustment_qty' => $validated['adjustment_qty'],
                'unit' => $validated['unit'] ?? null,
            ]
        );

        return redirect()->route('recipes.show', $posProductId)
            ->with('success', 'Penyesuaian resep modifier berhasil disimpan!');
    }

    /**
     * Delete a modifier adjustment rule
     */
    public function destroyAdjustment(RecipeModifierAdjustment $adjustment)
    {
        $posProductId = $adjustment->pos_product_id;
        $adjustment->delete();

        return redirect()->route('recipes.show', $posProductId)
            ->with('success', 'Aturan penyesuaian modifier berhasil dihapus.');
    }

    /**
     * AJAX Endpoint to preview deduction calculation comparison
     */
    public function preview(Request $request, $posProductId)
    {
        $qtyOrder = (float) $request->input('qty_order', 1);
        $selectedModifierIds = (array) $request->input('modifier_ids', []);

        $baseDeductions = $this->calculatorService->calculateDeduction($posProductId, $qtyOrder, []);
        $modifiedDeductions = $this->calculatorService->calculateDeduction($posProductId, $qtyOrder, $selectedModifierIds);

        $allStockItemIds = array_unique(array_merge(array_keys($baseDeductions), array_keys($modifiedDeductions)));
        $itemsMap = Item::with('unit')->whereIn('id', $allStockItemIds)->get()->keyBy('id');

        $comparison = [];
        foreach ($allStockItemIds as $id) {
            $item = $itemsMap->get($id);
            $baseQty = $baseDeductions[$id] ?? 0.0;
            $modQty = $modifiedDeductions[$id] ?? 0.0;

            $comparison[] = [
                'stock_item_id' => $id,
                'item_name' => $item?->name ?? ('Item #' . $id),
                'unit' => $item?->unit?->symbol ?? '',
                'base_qty' => $baseQty,
                'modified_qty' => $modQty,
                'diff' => $modQty - $baseQty,
            ];
        }

        return response()->json([
            'success' => true,
            'qty_order' => $qtyOrder,
            'selected_modifier_ids' => $selectedModifierIds,
            'comparison' => $comparison,
        ]);
    }

    /**
     * Helper to fetch active modifications from lokal-pos service or local cache
     */
    protected function fetchPosModifiers(): array
    {
        try {
            $posUrl = config('services.lokal_pos.url', 'http://127.0.0.1:8000');
            $response = Http::timeout(3)->get("{$posUrl}/api/v1/modifications");
            if ($response->successful() && isset($response->json()['data'])) {
                return $response->json()['data'];
            }
        } catch (\Exception $e) {
            Log::info("Could not fetch modifications from POS API: " . $e->getMessage());
        }

        // Return fallback default common modifiers list
        return [
            ['id' => 1, 'name' => 'Size Large (+50% Liquid)'],
            ['id' => 2, 'name' => 'Less Sugar (-10g Gula)'],
            ['id' => 3, 'name' => 'No Sugar (Gula = 0)'],
            ['id' => 4, 'name' => 'Extra Shot Espresso (+1 Shot)'],
            ['id' => 5, 'name' => 'Oat Milk Swap (Ganti Susu Sapi)'],
        ];
    }
}
