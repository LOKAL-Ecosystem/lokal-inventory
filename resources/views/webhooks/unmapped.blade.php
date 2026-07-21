@extends('layouts.app')

@section('title', 'Daftar Produk Unmapped (BOM)')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-black text-neutral tracking-tight flex items-center gap-2.5">
                <i data-lucide="file-question" class="w-7 h-7 text-warning"></i>
                Produk Unmapped (Belum Ada Resep BOM)
            </h1>
            <p class="text-xs text-gray-500 font-medium mt-1">
                Daftar produk dari POS yang masuk via webhook tetapi belum di-mapping ke bahan baku. Lengkapi resepnya agar stok terpotong otomatis.
            </p>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-base-100 rounded-3xl border border-base-300 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full text-xs">
                <thead>
                    <tr class="bg-base-200/50 text-neutral uppercase font-black tracking-wider text-[10px]">
                        <th>POS Product ID</th>
                        <th>Nama Produk POS</th>
                        <th>Frekuensi Terdeteksi</th>
                        <th>Transaksi Terakhir</th>
                        <th>Terakhir Terlihat</th>
                        <th class="text-right">Aksi Mapping</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($unmappedProducts as $unmapped)
                        <tr class="hover:bg-base-200/40 transition-colors">
                            <td>
                                <span class="font-mono text-neutral bg-base-200 px-2 py-1 rounded-lg border border-base-300 font-extrabold">
                                    {{ $unmapped->pos_product_id }}
                                </span>
                            </td>
                            <td>
                                <div class="font-extrabold text-neutral text-sm">{{ $unmapped->product_name ?? '-' }}</div>
                            </td>
                            <td>
                                <span class="badge badge-warning text-white font-extrabold px-2.5 py-1 text-[11px]">
                                    {{ $unmapped->occurrence_count }}x transaksi
                                </span>
                            </td>
                            <td class="font-mono text-gray-500">
                                {{ $unmapped->last_transaction_id ?? '-' }}
                            </td>
                            <td class="text-gray-400 font-mono text-[11px]">
                                {{ $unmapped->last_seen_at ? $unmapped->last_seen_at->diffForHumans() : '-' }}
                            </td>
                            <td class="text-right">
                                <button onclick="openMapModal('{{ $unmapped->pos_product_id }}', '{{ addslashes($unmapped->product_name) }}')" class="btn btn-sm btn-primary text-white rounded-xl font-bold shadow-xs">
                                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Buat Resep / Mapping
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-12 text-gray-400">
                                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3 border border-emerald-200">
                                    <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                                </div>
                                <div class="font-bold text-sm text-neutral">Semua Produk POS Sudah Ter-mapping!</div>
                                <div class="text-xs text-gray-500 mt-1">Tidak ada produk gantung yang belum memiliki resep BOM.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($unmappedProducts->hasPages())
            <div class="p-4 border-t border-base-300">
                {{ $unmappedProducts->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal Mapping Resep -->
<dialog id="modal_map_recipe" class="modal">
    <div class="modal-box max-w-lg bg-base-100 rounded-3xl p-6">
        <h3 class="font-black text-lg text-neutral mb-1">Mapping Resep (BOM)</h3>
        <p class="text-xs text-gray-500 mb-4">Hubungkan produk POS ini dengan bahan baku inventori yang akan dipotong saat terjadi penjualan.</p>

        <form action="{{ route('webhooks.recipe.store') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="pos_product_id" id="map_pos_product_id">
            
            <div>
                <label class="label text-xs font-extrabold uppercase text-gray-500">Nama Produk POS</label>
                <input type="text" name="pos_product_name" id="map_pos_product_name" class="input input-bordered w-full rounded-xl text-xs font-bold bg-base-200" readonly>
            </div>

            <div>
                <label class="label text-xs font-extrabold uppercase text-gray-500">Bahan Baku (Item Master)</label>
                <select name="stock_item_id" class="select select-bordered w-full rounded-xl text-xs font-semibold" required>
                    <option value="">-- Pilih Item Bahan Baku --</option>
                    @foreach($stockItems as $item)
                        <option value="{{ $item->id }}">
                            {{ $item->name }} (SKU: {{ $item->sku }}) — Stok: {{ number_format($item->quantity_on_hand, 2) }} {{ $item->unit?->symbol }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label text-xs font-extrabold uppercase text-gray-500">Jumlah Dibutuhkan</label>
                    <input type="number" step="0.0001" min="0.0001" name="quantity_needed" class="input input-bordered w-full rounded-xl text-xs font-bold" placeholder="Misal: 0.02" required>
                </div>
                <div>
                    <label class="label text-xs font-extrabold uppercase text-gray-500">Satuan (Optional)</label>
                    <input type="text" name="unit" class="input input-bordered w-full rounded-xl text-xs font-bold" placeholder="kg, liter, gram, pcs">
                </div>
            </div>

            <div class="modal-action mt-6">
                <button type="button" onclick="document.getElementById('modal_map_recipe').close()" class="btn btn-sm btn-ghost rounded-xl">Batal</button>
                <button type="submit" class="btn btn-sm btn-primary text-white rounded-xl font-bold px-5">Simpan Mapping</button>
            </div>
        </form>
    </div>
</dialog>

<script>
function openMapModal(posProductId, posProductName) {
    document.getElementById('map_pos_product_id').value = posProductId;
    document.getElementById('map_pos_product_name').value = posProductName;
    document.getElementById('modal_map_recipe').showModal();
}
</script>
@endsection
