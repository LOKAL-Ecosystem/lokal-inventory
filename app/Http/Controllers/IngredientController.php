<?php

namespace App\Http\Controllers;

use App\Enums\BaseUnit;
use App\Enums\StockMovementType;
use App\Http\Requests\IngredientRequest;
use App\Jobs\PushStockAlerts;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Services\MenuAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IngredientController extends Controller
{
    public function index(Request $request): View
    {
        $ingredients = Ingredient::with('category')
            ->when($request->query('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->query('category'), fn ($q, $id) => $q->where('ingredient_category_id', $id))
            ->when($request->boolean('low'), fn ($q) => $q->lowStock())
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('ingredients.index', [
            'ingredients' => $ingredients,
            'categories' => IngredientCategory::orderBy('name')->get(),
            'units' => BaseUnit::cases(),
        ]);
    }

    public function show(Ingredient $ingredient): View
    {
        return view('ingredients.show', [
            'ingredient' => $ingredient->load('category'),
            'movements' => $ingredient->movements()->with('creator')->latest('created_at')->latest('id')->paginate(30),
        ]);
    }

    public function store(IngredientRequest $request): RedirectResponse
    {
        $ingredient = Ingredient::create($this->payload($request));
        PushStockAlerts::dispatch($ingredient->outlet_id)->afterCommit();

        return redirect()->route('ingredients.index')->with('success', "Bahan {$ingredient->name} ditambahkan.");
    }

    public function update(IngredientRequest $request, Ingredient $ingredient, MenuAvailabilityService $availability): RedirectResponse
    {
        $ingredient->update($this->payload($request) + ['is_active' => $request->boolean('is_active')]);
        PushStockAlerts::dispatch($ingredient->outlet_id)->afterCommit();

        return redirect()->route('ingredients.index')->with('success', "Bahan {$ingredient->name} diperbarui.");
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        // Ledger tidak boleh kehilangan referensi: bahan yang sudah punya pergerakan atau dipakai resep dinonaktifkan.
        if ($ingredient->movements()->exists() || $ingredient->recipeItems()->exists()) {
            $ingredient->update(['is_active' => false]);
            PushStockAlerts::dispatch($ingredient->outlet_id)->afterCommit();

            return redirect()->route('ingredients.index')
                ->with('success', "Bahan {$ingredient->name} sudah punya riwayat/resep, jadi dinonaktifkan (tidak dihapus).");
        }

        $ingredient->delete();
        PushStockAlerts::dispatch($ingredient->outlet_id)->afterCommit();

        return redirect()->route('ingredients.index')->with('success', 'Bahan dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(IngredientRequest $request): array
    {
        $data = $request->validated();

        $categoryName = trim((string) ($data['category_name'] ?? ''));
        $data['ingredient_category_id'] = $categoryName !== ''
            ? IngredientCategory::firstOrCreate(['name' => $categoryName])->id
            : null;

        unset($data['category_name']);

        return $data;
    }
}
