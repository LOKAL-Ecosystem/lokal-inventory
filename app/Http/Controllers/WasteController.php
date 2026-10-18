<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Enums\WasteReason;
use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WasteController extends Controller
{
    public function index(): View
    {
        return view('waste.index', [
            'movements' => StockMovement::with(['ingredient', 'creator'])
                ->where('type', StockMovementType::Waste->value)
                ->latest('created_at')->latest('id')->paginate(25),
            'ingredients' => Ingredient::where('is_active', true)->orderBy('name')->get(),
            'reasons' => WasteReason::cases(),
        ]);
    }

    public function store(Request $request, StockService $stock): RedirectResponse
    {
        $data = $request->validate([
            'ingredient_id' => ['required', Rule::exists('ingredients', 'id')->where('outlet_id', $request->user()->outlet_id)],
            'qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'unit' => ['required', 'in:base,purchase'],
            'reason' => ['required', Rule::enum(WasteReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $stock->recordWaste(
            Ingredient::findOrFail($data['ingredient_id']),
            (float) $data['qty'],
            WasteReason::from($data['reason']),
            $data['note'] ?? null,
            $request->user()->id,
            $data['unit'] === 'purchase',
        );

        return back()->with('success', 'Bahan terbuang dicatat.');
    }
}
