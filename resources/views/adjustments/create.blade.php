@extends('layouts.app')

@section('title', 'Form Stock Adjustment Baru')

@section('content')
<header class="flex items-center justify-between border-b border-base-300/60 pb-5">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral tracking-tight">Form Stock Adjustment / Opname</h1>
        <p class="text-xs text-gray-500 font-medium mt-1">Masukkan jumlah riil (actual quantity) hasil opname fisik atau barang hilang/rusak.</p>
    </div>
    <a href="{{ route('adjustments.index') }}" class="btn btn-ghost btn-sm rounded-lg text-gray-500">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
    </a>
</header>

<form method="POST" action="{{ route('adjustments.store') }}" class="space-y-6">
    @csrf
    <!-- General Adjustment Info -->
    <div class="card-premium p-6 space-y-4">
        <h2 class="text-base font-bold text-neutral flex items-center gap-2 border-b border-base-200 pb-3">
            <i data-lucide="info" class="w-4.5 h-4.5 text-warning"></i>
            Informasi Koreksi
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1">
                <label class="text-xs font-bold text-neutral">Alasan Koreksi *</label>
                <select name="reason" required class="select w-full px-3 py-2 text-xs rounded-lg font-medium">
                    <option value="stock_opname_discrepancy">Selisih Hitung (Stock Opname)</option>
                    <option value="damaged">Barang Rusak / Kadaluarsa</option>
                    <option value="lost">Barang Hilang / Bocor</option>
                    <option value="other">Alasan Lainnya</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-bold text-neutral">Keterangan / Detail Alasan</label>
                <input type="text" name="notes" placeholder="e.g. Botol pecah saat bongkar muat..." class="input w-full px-3 py-2 text-xs rounded-lg font-medium" />
            </div>
        </div>
    </div>

    <!-- Items Detail Table Card -->
    <div class="card-premium p-6 space-y-4">
        <div class="flex justify-between items-center border-b border-base-200 pb-3">
            <h2 class="text-base font-bold text-neutral flex items-center gap-2">
                <i data-lucide="list" class="w-4.5 h-4.5 text-warning"></i>
                Item yang Disesuaikan
            </h2>
            <button type="button" onclick="addAdjRow()" class="btn btn-sm btn-outline btn-warning rounded-lg font-extrabold">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah Item
            </button>
        </div>

        <div class="overflow-x-auto rounded-lg border border-base-200">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th class="w-1/2">Item Stok *</th>
                        <th class="w-1/5 text-right">Stok Sistem</th>
                        <th class="w-1/5">Stok Fisik / Riil *</th>
                        <th class="w-1/5 text-right">Selisih</th>
                        <th class="w-12 text-center">Hapus</th>
                    </tr>
                </thead>
                <tbody id="adj-body">
                    <!-- Dynamic rows via JS -->
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex justify-end gap-3 pt-2">
        <a href="{{ route('adjustments.index') }}" class="btn btn-ghost rounded-lg">Batal</a>
        <button type="submit" class="btn btn-warning text-neutral font-black px-8 rounded-lg shadow-sm">Simpan Adjustment</button>
    </div>
</form>

@push('scripts')
<script>
    const availableItems = @json($items);
    let adjRowCount = 0;

    function addAdjRow() {
        adjRowCount++;
        const tbody = document.getElementById('adj-body');
        const tr = document.createElement('tr');
        tr.id = `adj-row-${adjRowCount}`;

        let itemOptions = `<option value="">-- Pilih Item --</option>`;
        availableItems.forEach(i => {
            itemOptions += `<option value="${i.id}" data-sysqty="${i.quantity_on_hand}" data-unit="${i.unit ? i.unit.symbol : ''}">${i.name} (${i.sku})</option>`;
        });

        tr.innerHTML = `
            <td>
                <select name="items[${adjRowCount}][item_id]" required onchange="onAdjItemSelect(this, ${adjRowCount})" class="select w-full px-3 py-2 text-xs rounded-lg font-medium">
                    ${itemOptions}
                </select>
            </td>
            <td class="text-right font-bold text-xs text-neutral" id="sys-qty-${adjRowCount}">0</td>
            <td>
                <input type="number" step="0.01" name="items[${adjRowCount}][actual_quantity]" value="0" min="0" required oninput="calcDiff(${adjRowCount})" class="input w-full px-3 py-2 text-xs rounded-lg font-bold" />
            </td>
            <td class="text-right font-black text-xs" id="diff-qty-${adjRowCount}">0</td>
            <td class="text-center">
                <button type="button" onclick="removeAdjRow(${adjRowCount})" class="btn btn-ghost btn-xs text-error hover:bg-error/10 rounded-md">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        if (window.initLucideIcons) window.initLucideIcons();
    }

    function onAdjItemSelect(selectElem, rowId) {
        const selectedOpt = selectElem.options[selectElem.selectedIndex];
        const sysQty = parseFloat(selectedOpt.getAttribute('data-sysqty')) || 0;
        const unit = selectedOpt.getAttribute('data-unit') || '';
        document.getElementById(`sys-qty-${rowId}`).innerText = `${sysQty} ${unit}`;
        
        const row = document.getElementById(`adj-row-${rowId}`);
        const actualInput = row.querySelector(`input[name="items[${rowId}][actual_quantity]"]`);
        actualInput.value = sysQty;
        calcDiff(rowId);
    }

    function calcDiff(rowId) {
        const row = document.getElementById(`adj-row-${rowId}`);
        const selectElem = row.querySelector('select');
        const selectedOpt = selectElem.options[selectElem.selectedIndex];
        const sysQty = parseFloat(selectedOpt.getAttribute('data-sysqty')) || 0;
        const actualQty = parseFloat(row.querySelector(`input[name="items[${rowId}][actual_quantity]"]`).value) || 0;
        const diff = actualQty - sysQty;
        
        const diffElem = document.getElementById(`diff-qty-${rowId}`);
        diffElem.innerText = (diff > 0 ? '+' : '') + diff.toFixed(2);
        if (diff < 0) {
            diffElem.className = 'text-right font-black text-xs text-error';
        } else if (diff > 0) {
            diffElem.className = 'text-right font-black text-xs text-success';
        } else {
            diffElem.className = 'text-right font-black text-xs text-neutral';
        }
    }

    function removeAdjRow(rowId) {
        const row = document.getElementById(`adj-row-${rowId}`);
        if (row) row.remove();
    }

    document.addEventListener('DOMContentLoaded', () => {
        addAdjRow();
    });
</script>
@endpush
@endsection
