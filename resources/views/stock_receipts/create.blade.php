@extends('layouts.app')

@section('title', 'Catat Stok Masuk')

@section('content')
<x-page-header title="Catat Stok Masuk" subtitle="Isi jumlah dalam satuan beli dan total harga baris. Konversi ke satuan dasar otomatis.">
    <x-slot:actions>
        <a href="{{ route('stock-receipts.index') }}" class="btn btn-ghost btn-sm min-h-11">Batal</a>
    </x-slot:actions>
</x-page-header>

<form method="POST" action="{{ route('stock-receipts.store') }}" class="space-y-6" id="receipt-form">
    @csrf

    <section class="bg-base-100 border border-base-300 rounded-2xl p-5 grid sm:grid-cols-3 gap-4">
        <x-field label="Tanggal" name="received_at">
            <input type="date" name="received_at" value="{{ old('received_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="input w-full min-h-11 text-sm rounded-xl" />
        </x-field>
        <x-field label="Supplier" name="supplier_id">
            <select name="supplier_id" class="select w-full min-h-11 text-sm rounded-xl">
                <option value="">— tanpa supplier —</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </x-field>
        <x-field label="Catatan" name="note">
            <input type="text" name="note" value="{{ old('note') }}" maxlength="500" class="input w-full min-h-11 text-sm rounded-xl" />
        </x-field>
    </section>

    <section class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead class="bg-base-200/20 text-[10px] uppercase tracking-wider font-extrabold text-base-content/50">
                    <tr><th class="min-w-56">Bahan</th><th class="min-w-36">Jumlah (satuan beli)</th><th class="min-w-40">Total harga (Rp)</th><th class="text-right min-w-36">Setara</th><th></th></tr>
                </thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
        <div class="p-4 border-t border-base-200 flex justify-between items-center">
            <button type="button" id="add-row" class="btn btn-outline btn-sm min-h-11 gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Tambah baris</button>
            <div class="text-sm font-extrabold">Total: Rp <span id="grand-total">0</span></div>
        </div>
    </section>

    <div class="flex justify-end">
        <button type="submit" class="btn btn-primary min-h-11 px-8">Simpan Stok Masuk</button>
    </div>
</form>
@endsection

@php
    $ingredientOptions = $ingredients->map(fn ($i) => [
        'id' => $i->id,
        'name' => $i->name,
        'unit' => $i->base_unit->value,
        'purchase_unit' => $i->purchase_unit ?: $i->base_unit->value,
        'factor' => (float) $i->purchase_unit_qty,
    ])->values();
    $oldItems = old('items', [[]]);
@endphp

@push('scripts')
<script>
    const ingredients = @json($ingredientOptions);
    const oldItems = @json($oldItems);
    const rows = document.getElementById('rows');
    let index = 0;

    function addRow(values = {}) {
        const i = index++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="items[${i}][ingredient_id]" required class="select w-full min-h-11 text-sm rounded-xl">
                    <option value="">Pilih bahan…</option>
                    ${ingredients.map((x) => `<option value="${x.id}">${x.name}</option>`).join('')}
                </select>
            </td>
            <td>
                <div class="flex items-center gap-2">
                    <input type="number" name="items[${i}][purchase_qty]" min="0.001" step="any" required class="input w-full min-h-11 text-sm rounded-xl" />
                    <span class="text-xs font-bold text-base-content/60 unit-label"></span>
                </div>
            </td>
            <td><input type="number" name="items[${i}][total_price]" min="0" step="1" required class="input w-full min-h-11 text-sm rounded-xl" /></td>
            <td class="text-right text-xs font-bold equivalent">-</td>
            <td><button type="button" class="btn btn-ghost btn-square btn-sm text-error hover:bg-error/10 remove" aria-label="Hapus baris"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>`;
        rows.appendChild(tr);

        const select = tr.querySelector('select');
        const qty = tr.querySelector('[name$="[purchase_qty]"]');
        const price = tr.querySelector('[name$="[total_price]"]');
        select.value = values.ingredient_id ?? '';
        qty.value = values.purchase_qty ?? '';
        price.value = values.total_price ?? '';

        const refresh = () => {
            const item = ingredients.find((x) => String(x.id) === select.value);
            tr.querySelector('.unit-label').textContent = item ? item.purchase_unit : '';
            tr.querySelector('.equivalent').textContent = item && qty.value
                ? `${(qty.value * item.factor).toLocaleString('id-ID', { maximumFractionDigits: 3 })} ${item.unit}`
                : '-';
            const total = [...rows.querySelectorAll('[name$="[total_price]"]')].reduce((sum, el) => sum + (parseInt(el.value) || 0), 0);
            document.getElementById('grand-total').textContent = total.toLocaleString('id-ID');
        };

        [select, qty, price].forEach((el) => el.addEventListener('input', refresh));
        tr.querySelector('.remove').addEventListener('click', () => { tr.remove(); refresh(); });
        refresh();
        window.initLucideIcons?.();
    }

    document.getElementById('add-row').addEventListener('click', () => addRow());
    oldItems.forEach((item) => addRow(item));
</script>
@endpush
