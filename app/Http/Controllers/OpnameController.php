<?php

namespace App\Http\Controllers;

use App\Models\StockOpname;
use App\Services\OpnameService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class OpnameController extends Controller
{
    public function index(): View
    {
        return view('opnames.index', [
            'opnames' => StockOpname::with('creator')->withCount('items')->latest('opname_date')->latest('id')->paginate(20),
        ]);
    }

    public function store(Request $request, OpnameService $service): RedirectResponse
    {
        $data = $request->validate([
            'opname_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $opname = $service->start($data['opname_date'], $data['note'] ?? null, $request->user()->id);

        return redirect()->route('opnames.show', $opname)->with('success', 'Opname dibuat. Isi hitungan fisik tiap bahan.');
    }

    public function show(StockOpname $opname): View
    {
        return view('opnames.show', [
            'opname' => $opname->load(['items.ingredient', 'creator']),
        ]);
    }

    public function update(Request $request, StockOpname $opname, OpnameService $service): RedirectResponse
    {
        $data = $request->validate([
            'counts' => ['array'],
            'counts.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        try {
            $service->saveCounts($opname, $data['counts'] ?? []);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Hitungan disimpan.');
    }

    public function complete(Request $request, StockOpname $opname, OpnameService $service): RedirectResponse
    {
        try {
            $service->complete($opname, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('opnames.show', $opname)->with('success', 'Opname selesai. Selisih dicatat sebagai penyesuaian stok.');
    }
}
