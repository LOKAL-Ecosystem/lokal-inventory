@extends('layouts.app')

@section('title', 'Input Restock / Stock In Baru')

@section('content')
<header class="flex items-center justify-between border-b border-base-300/60 pb-5">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral tracking-tight">Form Stock In / Restock Pembelian</h1>
        <p class="text-xs text-gray-500 font-medium mt-1">Catat jumlah stok masuk, supplier, harga beli per item, dan tanggal transaksi.</p>
    </div>
    <a href="{{ route('stock-in.index') }}" class="btn btn-ghost btn-sm rounded-lg text-gray-500">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
    </a>
</header>

<form method="POST" action="{{ route('stock-in.store') }}" class="space-y-6">
    @csrf
    <!-- General Info Card -->
    <div class="card-premium p-6 space-y-4">
        <h2 class="text-base font-bold text-neutral flex items-center gap-2 border-b border-base-200 pb-3">
            <i data-lucide="file-text" class="w-4.5 h-4.5 text-primary"></i>
            Informasi Transaksi
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="space-y-1">
                <label class="text-xs font-bold text-neutral">Supplier</label>
                <select name="supplier_id" class="select w-full px-3 py-2 text-xs rounded-lg font-medium">
                    <option value="">-- Pilih Supplier --</option>
                    @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-bold text-neutral">Tanggal Transaksi *</label>
                <input type="date" name="transaction_date" value="{{ date('Y-m-d') }}" required class="input w-full px-3 py-2 text-xs rounded-lg font-medium" />
            </div>
            <div class="space-y-1">
                <label class="text-xs font-bold text-neutral">Catatan / No. Faktur</label>
                <input type="text" name="notes" placeholder="No. Invoice / catatan tambahan" class="input w-full px-3 py-2 text-xs rounded-lg font-medium" />
            </div>
        </div>
    </div>

    <!-- Items Detail Table Card -->
    <div class="card-premium p-6 space-y-4">
        <div class="flex justify-between items-center border-b border-base-200 pb-3">
            <h2 class="text-base font-bold text-neutral flex items-center gap-2">
                <i data-lucide="package" class="w-4.5 h-4.5 text-primary"></i>
                Daftar Item Restock
            </h2>
            <button type="button" onclick="addRow()" class="btn btn-sm btn-outline btn-primary rounded-lg">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah Baris
            </button>
        </div>

        <div class="overflow-x-auto rounded-lg border border-base-200">
            <table class="table-premium" id="items-table">
                <thead>
                    <tr>
                        <th class="w-2/5">Pilih Item Stok *</th>
                        <th class="w-1/6">Qty Masuk *</th>
                        <th class="w-1/3">Harga Beli / Unit (Rp) *</th>
                        <th class="w-1/6 text-right">Subtotal</th>
                        <th class="w-12 text-center">Hapus</th>
                    </tr>
                </thead>
                <tbody id="items-body">
                    <!-- Dynamic rows inserted via JS -->
                </tbody>
                <tfoot>
                    <tr class="bg-base-200/60 font-bold border-t-2 border-base-300">
                        <td colspan="3" class="text-right text-xs uppercase tracking-wider text-neutral py-3">Grand Total Biaya Pembelian:</td>
                        <td class="text-right text-base text-primary font-black py-3" id="grand-total">Rp 0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="flex justify-end gap-3 pt-2">
        <a href="{{ route('stock-in.index') }}" class="btn btn-ghost rounded-lg">Batal</a>
        <button type="submit" class="btn btn-primary text-white px-8 rounded-lg shadow-sm font-bold">Simpan Transaksi Stock In</button>
    </div>
</form>

@push('scripts')
<script>
    const availableItems = @json($items);
    let rowCount = 0;

    function addRow() {
        rowCount++;
        const tbody = document.getElementById('items-body');
        const tr = document.createElement('tr');
        tr.id = `row-${rowCount}`;
        
        let itemOptions = `<option value="">-- Pilih Item --</option>`;
        availableItems.forEach(i => {
            const cost = parseInt(i.unit_cost) || 0;
            itemOptions += `<option value="${i.id}" data-cost="${cost}" data-unit="${i.unit ? i.unit.symbol : ''}">${i.name} (${i.sku}) - Stok: ${i.quantity_on_hand} ${i.unit ? i.unit.symbol : ''}</option>`;
        });

        tr.innerHTML = `
            <td>
                <select name="items[${rowCount}][item_id]" required onchange="onItemSelect(this, ${rowCount})" class="select w-full px-3 py-2 text-xs rounded-lg font-medium">
                    ${itemOptions}
                </select>
            </td>
            <td>
                <input type="number" step="0.01" name="items[${rowCount}][quantity]" value="1" min="0.01" required oninput="calculateSubtotal(${rowCount})" class="input w-full px-3 py-2 text-xs rounded-lg font-bold" />
            </td>
            <td>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-xs font-bold text-gray-400">Rp</span>
                    <input type="text" id="unit-cost-display-${rowCount}" oninput="onCostInput(this, ${rowCount})" value="0" placeholder="0" class="input w-full pl-9 pr-3 py-2 text-xs rounded-lg font-bold" />
                    <input type="hidden" name="items[${rowCount}][unit_cost]" id="unit-cost-${rowCount}" value="0" />
                </div>
            </td>
            <td class="text-right font-black text-xs text-neutral" id="subtotal-${rowCount}">Rp 0</td>
            <td class="text-center">
                <button type="button" onclick="removeRow(${rowCount})" class="btn btn-ghost btn-xs text-error hover:bg-error/10 rounded-md">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        if (window.initLucideIcons) window.initLucideIcons();
    }

    function onItemSelect(selectElem, rowId) {
        const selectedOpt = selectElem.options[selectElem.selectedIndex];
        const cost = parseInt(selectedOpt.getAttribute('data-cost')) || 0;
        
        document.getElementById(`unit-cost-${rowId}`).value = cost;
        const displayInput = document.getElementById(`unit-cost-display-${rowId}`);
        displayInput.value = cost > 0 ? cost.toLocaleString('id-ID') : '0';
        
        calculateSubtotal(rowId);
    }

    function onCostInput(inputElem, rowId) {
        let rawValue = inputElem.value.replace(/[^0-9]/g, '');
        let numValue = parseInt(rawValue, 10) || 0;
        
        document.getElementById(`unit-cost-${rowId}`).value = numValue;
        inputElem.value = numValue > 0 ? numValue.toLocaleString('id-ID') : '0';
        
        calculateSubtotal(rowId);
    }

    function calculateSubtotal(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        const qty = parseFloat(row.querySelector(`input[name="items[${rowId}][quantity]"]`).value) || 0;
        const cost = parseFloat(document.getElementById(`unit-cost-${rowId}`).value) || 0;
        const subtotal = qty * cost;
        document.getElementById(`subtotal-${rowId}`).innerText = 'Rp ' + subtotal.toLocaleString('id-ID');
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let total = 0;
        const rows = document.querySelectorAll('#items-body tr');
        rows.forEach(tr => {
            const rowId = tr.id.replace('row-', '');
            const qtyInput = tr.querySelector(`input[name="items[${rowId}][quantity]"]`);
            const costHidden = document.getElementById(`unit-cost-${rowId}`);
            if (qtyInput && costHidden) {
                total += (parseFloat(qtyInput.value) || 0) * (parseFloat(costHidden.value) || 0);
            }
        });
        document.getElementById('grand-total').innerText = 'Rp ' + total.toLocaleString('id-ID');
    }

    function removeRow(rowId) {
        const row = document.getElementById(`row-${rowId}`);
        if (row) {
            row.remove();
            calculateGrandTotal();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        addRow();
    });
</script>
@endpush
@endsection
