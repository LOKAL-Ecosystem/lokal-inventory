<?php

namespace App\Http\Controllers;

use App\Enums\RecipeAdjustmentAction;
use App\Models\Ingredient;
use App\Models\ModifierRecipeAdjustment;
use App\Models\PosModifier;
use App\Models\PosModifierGroup;
use App\Models\PosProduct;
use App\Models\RecipeItem;
use App\Services\CostingService;
use App\Services\MenuAvailabilityService;
use App\Services\RecipeUsageCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function __construct(
        protected CostingService $costing,
        protected MenuAvailabilityService $availability,
    ) {
    }

    public function index(Request $request): View
    {
        $products = PosProduct::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->query('filter') === 'no_recipe', fn ($q) => $q->whereNotIn('pos_product_id', RecipeItem::query()->select('pos_product_id')))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $rows = $products->getCollection()->map(fn (PosProduct $p) => [
            'product' => $p,
            'summary' => $this->costing->summary($p),
            'portions' => $this->availability->portions($p->pos_product_id),
        ]);

        return view('recipes.index', ['products' => $products, 'rows' => $rows]);
    }

    public function show(int $posProductId): View
    {
        $product = PosProduct::where('pos_product_id', $posProductId)->firstOrFail();

        $modifierIds = DB::table('pos_product_modifiers')
            ->where('outlet_id', auth()->user()->outlet_id)
            ->where('pos_product_id', $posProductId)
            ->pluck('pos_modifier_id');

        $modifiers = PosModifier::whereIn('pos_modifier_id', $modifierIds)->orderBy('name')->get();
        $groups = PosModifierGroup::whereIn('pos_group_id', $modifiers->pluck('pos_group_id')->unique())->get()->keyBy('pos_group_id');

        return view('recipes.show', [
            'product' => $product,
            'items' => RecipeItem::with('ingredient')->where('pos_product_id', $posProductId)->get(),
            'ingredients' => Ingredient::where('is_active', true)->orderBy('name')->get(),
            'summary' => $this->costing->summary($product),
            'portions' => $this->availability->portions($posProductId),
            'modifiers' => $modifiers->groupBy('pos_group_id'),
            'groups' => $groups,
            'adjustments' => ModifierRecipeAdjustment::with(['ingredient', 'replacesIngredient'])
                ->whereIn('pos_modifier_id', $modifierIds)->get()->groupBy('pos_modifier_id'),
            'actions' => RecipeAdjustmentAction::cases(),
        ]);
    }

    /**
     * Ganti seluruh resep dasar produk.
     */
    public function update(Request $request, int $posProductId): RedirectResponse
    {
        PosProduct::where('pos_product_id', $posProductId)->firstOrFail();

        $data = $request->validate([
            'items' => ['array'],
            'items.*.ingredient_id' => ['required', 'distinct', Rule::exists('ingredients', 'id')->where('outlet_id', $request->user()->outlet_id)],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ], [], ['items.*.ingredient_id' => 'bahan', 'items.*.qty' => 'takaran']);

        $items = collect($data['items'] ?? []);

        DB::transaction(function () use ($posProductId, $items) {
            RecipeItem::where('pos_product_id', $posProductId)
                ->whereNotIn('ingredient_id', $items->pluck('ingredient_id'))
                ->delete();

            foreach ($items as $row) {
                RecipeItem::updateOrCreate(
                    ['pos_product_id' => $posProductId, 'ingredient_id' => $row['ingredient_id']],
                    ['qty' => $row['qty']]
                );
            }
        });

        $this->availability->refreshProducts(collect([$posProductId]));

        return back()->with('success', 'Resep disimpan.');
    }

    public function storeAdjustment(Request $request, int $posProductId): RedirectResponse
    {
        $outletId = $request->user()->outlet_id;
        $ingredientExists = Rule::exists('ingredients', 'id')->where('outlet_id', $outletId);

        $data = $request->validate([
            'pos_modifier_id' => ['required', Rule::exists('pos_modifiers', 'pos_modifier_id')->where('outlet_id', $outletId)],
            'action' => ['required', Rule::enum(RecipeAdjustmentAction::class)],
            'ingredient_id' => [Rule::requiredIf(fn () => $request->input('action') !== RecipeAdjustmentAction::Multiply->value), 'nullable', $ingredientExists],
            'qty' => [Rule::requiredIf(fn () => $request->input('action') === RecipeAdjustmentAction::Add->value), 'nullable', 'numeric', 'gt:0'],
            'factor' => [Rule::requiredIf(fn () => $request->input('action') === RecipeAdjustmentAction::Multiply->value), 'nullable', 'numeric', 'gt:0', 'max:100'],
            'replaces_ingredient_id' => [Rule::requiredIf(fn () => $request->input('action') === RecipeAdjustmentAction::Replace->value), 'nullable', $ingredientExists],
        ], [], ['ingredient_id' => 'bahan', 'replaces_ingredient_id' => 'bahan yang diganti', 'qty' => 'takaran', 'factor' => 'faktor']);

        // Simpan hanya field yang relevan untuk aksinya.
        $action = RecipeAdjustmentAction::from($data['action']);
        $data['qty'] = $action === RecipeAdjustmentAction::Multiply ? null : ($data['qty'] ?? null);
        $data['factor'] = $action === RecipeAdjustmentAction::Multiply ? $data['factor'] : null;
        $data['replaces_ingredient_id'] = $action === RecipeAdjustmentAction::Replace ? $data['replaces_ingredient_id'] : null;

        ModifierRecipeAdjustment::create($data);

        return back()->with('success', 'Penyesuaian opsi disimpan.');
    }

    public function destroyAdjustment(ModifierRecipeAdjustment $adjustment): RedirectResponse
    {
        $adjustment->delete();

        return back()->with('success', 'Penyesuaian dihapus.');
    }

    /**
     * Simulasi pemakaian bahan & HPP untuk kombinasi opsi tertentu.
     */
    public function preview(Request $request, int $posProductId, RecipeUsageCalculator $usage): JsonResponse
    {
        $modifierIds = array_map('intval', (array) $request->query('modifier_ids', []));
        $result = $usage->usage($posProductId, $modifierIds);
        $ingredients = Ingredient::whereIn('id', array_keys($result))->get()->keyBy('id');

        return response()->json([
            'usage' => collect($result)->map(fn ($qty, $id) => [
                'ingredient' => $ingredients[$id]->name ?? "#{$id}",
                'qty' => $ingredients[$id]->formatQty($qty) ?? $qty,
            ])->values(),
            'hpp' => (int) round($this->costing->costForSelection($posProductId, $modifierIds)),
        ]);
    }
}
