@extends('layouts.app')

@section('title', 'Detail Resep & Modifier')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('recipes.index') }}" class="btn btn-xs btn-ghost gap-1 text-gray-500 font-bold">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Kembali ke Resep
                </a>
            </div>
            <h1 class="text-2xl font-black text-neutral tracking-tight flex items-center gap-2.5 mt-2">
                <i data-lucide="chef-hat" class="w-7 h-7 text-primary"></i>
                Detail Resep: {{ $productName }}
            </h1>
            <p class="text-xs text-gray-500 font-medium mt-1">
                Kelola resep dasar serta penyesuaian kebutuhan bahan baku berdasarkan modifier/pilihan tambahan kasir.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Base Recipe List (2/3 width on large screen) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Base Recipe Card -->
            <div class="bg-base-100 rounded-3xl border border-base-300 shadow-xs overflow-hidden">
                <div class="p-5 border-b border-base-200 flex justify-between items-center bg-base-200/30">
                    <h2 class="font-extrabold text-sm text-neutral flex items-center gap-2">
                        <i data-lucide="layers" class="w-4 h-4 text-primary"></i>
                        Bahan Baku Dasar (Normal Base)
                    </h2>
                    <span class="badge badge-neutral text-[10px] font-black uppercase">Default Recipe</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="table w-full text-xs">
                        <thead>
                            <tr class="bg-base-200/20 text-neutral uppercase font-black tracking-wider text-[10px]">
                                <th>Bahan Baku</th>
                                <th>Jumlah per Produk</th>
                                <th>Satuan Resep</th>
                                <th>Stok Saat Ini</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($baseRecipes as $recipe)
                                <tr>
                                    <td>
                                        <div class="font-bold text-neutral">{{ $recipe->stockItem?->name ?? '-' }}</div>
                                        <div class="text-[10px] text-gray-400 font-mono">SKU: {{ $recipe->stockItem?->sku ?? '-' }}</div>
                                    </td>
                                    <td class="font-bold text-sm text-neutral">
                                        {{ number_format($recipe->quantity_needed, 4) }}
                                    </td>
                                    <td>
                                        <span class="badge badge-ghost font-bold">{{ $recipe->unit ?? ($recipe->stockItem?->unit?->symbol ?? '-') }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $recipe->stockItem?->quantity_on_hand <= $recipe->stockItem?->minimum_stock ? 'badge-error' : 'badge-success' }} text-white font-extrabold px-2 py-0.5">
                                            {{ number_format($recipe->stockItem?->quantity_on_hand ?? 0, 2) }} {{ $recipe->stockItem?->unit?->symbol ?? '' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-8 text-gray-400">
                                        Tidak ada bahan baku dasar untuk resep ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modifiers Adjustment Card -->
            <div class="bg-base-100 rounded-3xl border border-base-300 shadow-xs overflow-hidden">
                <div class="p-5 border-b border-base-200 flex justify-between items-center bg-base-200/30">
                    <h2 class="font-extrabold text-sm text-neutral flex items-center gap-2">
                        <i data-lucide="sliders" class="w-4 h-4 text-warning"></i>
                        Penyesuaian per Modifier
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="table w-full text-xs">
                        <thead>
                            <tr class="bg-base-200/20 text-neutral uppercase font-black tracking-wider text-[10px]">
                                <th>Modifier Option</th>
                                <th>Bahan Baku Terpengaruh</th>
                                <th>Jenis Penyesuaian</th>
                                <th>Jumlah Penyesuaian</th>
                                <th>Unit</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($modifierAdjustments as $adj)
                                <tr>
                                    <td>
                                        <div class="font-bold text-neutral">{{ $adj->pos_modifier_name }}</div>
                                        <div class="text-[10px] text-gray-400 font-mono">ID: {{ $adj->pos_modifier_id }}</div>
                                    </td>
                                    <td>
                                        <span class="font-semibold text-neutral">{{ $adj->stockItem?->name ?? 'Unknown Item' }}</span>
                                    </td>
                                    <td>
                                        @if($adj->adjustment_type === 'override')
                                            <span class="badge badge-neutral text-white font-bold text-[10px] px-2 py-0.5">OVERRIDE</span>
                                        @elseif($adj->adjustment_type === 'add')
                                            <span class="badge badge-success text-white font-bold text-[10px] px-2 py-0.5">+ ADD</span>
                                        @else
                                            <span class="badge badge-warning text-white font-bold text-[10px] px-2 py-0.5">- SUBTRACT</span>
                                        @endif
                                    </td>
                                    <td class="font-bold text-neutral">
                                        {{ number_format($adj->adjustment_qty, 4) }}
                                    </td>
                                    <td>
                                        <span class="badge badge-ghost">{{ $adj->unit ?? '-' }}</span>
                                    </td>
                                    <td class="text-right">
                                        <form action="{{ route('recipes.adjustments.destroy', $adj->id) }}" method="POST" onsubmit="return confirm('Hapus aturan penyesuaian ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-ghost text-error">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-12 text-gray-400">
                                        <i data-lucide="info" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                        <div class="font-bold">Belum ada aturan modifier</div>
                                        <div class="text-[10px]">Gunakan panel sebelah kanan untuk menambahkan aturan penyesuaian per modifier.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Side controls (1/3 width) -->
        <div class="space-y-6">
            <!-- Add Modifier Rule Card -->
            <div class="bg-base-100 rounded-3xl border border-base-300 shadow-xs p-5 space-y-4">
                <h3 class="font-black text-sm text-neutral flex items-center gap-2 pb-2 border-b border-base-200">
                    <i data-lucide="plus-circle" class="w-4 h-4 text-primary"></i>
                    Tambah Penyesuaian Modifier
                </h3>

                <form action="{{ route('recipes.adjustments.store', $posProductId) }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="label font-extrabold text-gray-500 uppercase tracking-wider text-[10px] pb-1">Pilih Modifier (POS)</label>
                        <select id="modifier_selector" class="select select-bordered select-sm w-full rounded-xl font-bold" required onchange="updateModifierName()">
                            <option value="">-- Pilih Modifier --</option>
                            @foreach($modifiersList as $mod)
                                <option value="{{ $mod['id'] }}" data-name="{{ $mod['name'] }}">{{ $mod['name'] }} (ID: {{ $mod['id'] }})</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="pos_modifier_id" id="pos_modifier_id">
                        <input type="hidden" name="pos_modifier_name" id="pos_modifier_name">
                    </div>

                    <div>
                        <label class="label font-extrabold text-gray-500 uppercase tracking-wider text-[10px] pb-1">Bahan Baku (Inventory)</label>
                        <select name="stock_item_id" class="select select-bordered select-sm w-full rounded-xl font-bold" required>
                            <option value="">-- Pilih Bahan Baku --</option>
                            @foreach($stockItems as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} (SKU: {{ $item->sku }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label font-extrabold text-gray-500 uppercase tracking-wider text-[10px] pb-1">Jenis Aksi</label>
                            <select name="adjustment_type" class="select select-bordered select-sm w-full rounded-xl font-bold" required>
                                <option value="override">Override (Ganti)</option>
                                <option value="add">Add (Tambah)</option>
                                <option value="subtract">Subtract (Kurang)</option>
                            </select>
                        </div>
                        <div>
                            <label class="label font-extrabold text-gray-500 uppercase tracking-wider text-[10px] pb-1">Jumlah</label>
                            <input type="number" step="0.0001" min="0" name="adjustment_qty" class="input input-bordered input-sm w-full rounded-xl font-black text-center" placeholder="0.00" required>
                        </div>
                    </div>

                    <div>
                        <label class="label font-extrabold text-gray-500 uppercase tracking-wider text-[10px] pb-1">Satuan (Optional)</label>
                        <input type="text" name="unit" class="input input-bordered input-sm w-full rounded-xl font-bold" placeholder="pcs, ml, gram, lembar">
                    </div>

                    <button type="submit" class="btn btn-sm btn-primary text-white w-full rounded-xl font-extrabold shadow-sm mt-2">
                        Simpan Aturan Modifier
                    </button>
                </form>
            </div>

            <!-- Calculation Preview Card -->
            <div class="bg-base-100 rounded-3xl border border-base-300 shadow-xs p-5 space-y-4">
                <h3 class="font-black text-sm text-neutral flex items-center gap-2 pb-2 border-b border-base-200">
                    <i data-lucide="calculator" class="w-4 h-4 text-info"></i>
                    Simulasi Preview Potong Stok
                </h3>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="label font-extrabold text-gray-500 uppercase tracking-wider text-[10px] pb-1">Jumlah Pembelian (Qty)</label>
                        <input type="number" min="1" id="preview_qty" value="1" class="input input-bordered input-sm w-full rounded-xl font-black text-center" oninput="runSimulation()">
                    </div>

                    <div>
                        <label class="label font-extrabold text-gray-500 uppercase tracking-wider text-[10px] pb-1">Aktifkan Modifier</label>
                        <div class="space-y-2 max-h-40 overflow-y-auto pr-1">
                            @foreach(collect($modifierAdjustments)->unique('pos_modifier_id') as $mod)
                                <label class="flex items-center gap-2.5 p-2 rounded-xl bg-base-200/50 hover:bg-base-200 cursor-pointer border border-base-300/40">
                                    <input type="checkbox" name="active_mods[]" value="{{ $mod->pos_modifier_id }}" class="checkbox checkbox-xs checkbox-primary" onchange="runSimulation()">
                                    <span class="font-semibold text-neutral text-[11px]">{{ $mod->pos_modifier_name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-3 border-t border-base-200">
                        <div class="font-bold text-[10px] text-gray-400 uppercase mb-2">Hasil Perbandingan:</div>
                        <div id="sim_results" class="space-y-2">
                            <div class="text-center py-4 text-gray-400 text-[11px]">Silakan pilih qty/modifier untuk melihat perbandingan.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function updateModifierName() {
    const sel = document.getElementById('modifier_selector');
    const opt = sel.options[sel.selectedIndex];
    
    if (opt.value) {
        document.getElementById('pos_modifier_id').value = opt.value;
        document.getElementById('pos_modifier_name').value = opt.getAttribute('data-name');
    } else {
        document.getElementById('pos_modifier_id').value = '';
        document.getElementById('pos_modifier_name').value = '';
    }
}

function runSimulation() {
    const qty = document.getElementById('preview_qty').value || 1;
    const checked = Array.from(document.querySelectorAll('input[name="active_mods[]"]:checked')).map(el => el.value);

    fetch("{{ route('recipes.preview', $posProductId) }}", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": "{{ csrf_token() }}"
        },
        body: JSON.stringify({
            qty_order: qty,
            modifier_ids: checked
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) return;

        const resultsContainer = document.getElementById('sim_results');
        resultsContainer.innerHTML = '';

        if (data.comparison.length === 0) {
            resultsContainer.innerHTML = '<div class="text-center py-4 text-gray-400 text-[11px]">Tidak ada bahan baku terpengaruh.</div>';
            return;
        }

        data.comparison.forEach(item => {
            const row = document.createElement('div');
            row.className = 'p-3 rounded-2xl bg-base-200/60 border border-base-300/80 flex flex-col gap-1';
            
            let diffBadge = '';
            if (item.diff > 0) {
                diffBadge = `<span class="badge badge-success text-[10px] text-white font-extrabold">+${item.diff.toFixed(2)} ${item.unit}</span>`;
            } else if (item.diff < 0) {
                diffBadge = `<span class="badge badge-error text-[10px] text-white font-extrabold">${item.diff.toFixed(2)} ${item.unit}</span>`;
            } else {
                diffBadge = `<span class="badge badge-ghost text-[10px] text-neutral font-semibold">no change</span>`;
            }

            row.innerHTML = `
                <div class="flex justify-between items-center">
                    <span class="font-extrabold text-neutral text-[11px]">${item.item_name}</span>
                    ${diffBadge}
                </div>
                <div class="flex justify-between text-[10px] text-gray-500 font-medium">
                    <span>Base: ${item.base_qty.toFixed(2)} ${item.unit}</span>
                    <span class="font-bold text-neutral">Modified: ${item.modified_qty.toFixed(2)} ${item.unit}</span>
                </div>
            `;
            resultsContainer.appendChild(row);
        });
    })
    .catch(err => console.error("Simulation failed:", err));
}

// Initial simulation load
document.addEventListener('DOMContentLoaded', () => {
    runSimulation();
});
</script>
@endpush
@endsection
