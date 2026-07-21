@extends('layouts.app')

@section('title', 'Integrasi dengan Lokal-POS')

@section('content')
<header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-base-300/60 pb-5">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral tracking-tight">Integrasi API & Webhook dengan Lokal-POS</h1>
        <p class="text-xs text-gray-500 font-medium mt-1">Hubungkan data stok bahan baku/produk di Lokal-Inventory dengan transaksi kasir Lokal-POS.</p>
    </div>
    <div class="badge badge-success text-white p-3 font-bold flex items-center gap-1.5 shadow-xs">
        <i data-lucide="check-circle-2" class="w-4 h-4"></i> Single Source of Truth Active
    </div>
</header>

<!-- API Endpoint Documentation Box -->
<div class="card-premium p-6 space-y-4 shadow-xs">
    <h2 class="text-base font-bold text-neutral flex items-center gap-2 border-b border-base-200 pb-3">
        <i data-lucide="code" class="w-5 h-5 text-primary"></i>
        Endpoint Integrasi POS yang Tersedia
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Endpoint 1 -->
        <div class="p-4 rounded-xl bg-base-200/50 border border-base-300/80 space-y-2 hover:bg-base-200/80 transition-colors">
            <div class="flex items-center gap-2">
                <span class="badge badge-success text-white font-mono font-bold text-[10px] px-2 py-0.5 shadow-xs">GET</span>
                <span class="font-mono text-xs font-extrabold text-neutral">/api/v1/pos/stock-sync</span>
            </div>
            <p class="text-xs text-gray-600 font-medium">
                Dikonsumsi oleh <strong>Lokal-POS</strong> untuk mengambil ketersediaan stok real-time (saldo stok, threshold, status in_stock).
            </p>
            <div class="text-[10px] text-gray-400 font-mono font-semibold">Auth: Sanctum Bearer Token</div>
        </div>

        <!-- Endpoint 2 -->
        <div class="p-4 rounded-xl bg-base-200/50 border border-base-300/80 space-y-2 hover:bg-base-200/80 transition-colors">
            <div class="flex items-center gap-2">
                <span class="badge badge-primary text-white font-mono font-bold text-[10px] px-2 py-0.5 shadow-xs">POST</span>
                <span class="font-mono text-xs font-extrabold text-neutral">/api/v1/pos/order-deduct</span>
            </div>
            <p class="text-xs text-gray-600 font-medium">
                Webhook Listener saat transaksi selesai di <strong>Lokal-POS</strong>. Stok item akan otomatis berkurang & mutasi `pos_deduction` dicatat.
            </p>
            <div class="text-[10px] text-gray-400 font-mono font-semibold">Auth: Sanctum Bearer Token</div>
        </div>
    </div>
</div>

<!-- POS Product ID Mapping Table -->
<div class="card-premium p-6 space-y-4 shadow-xs">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 border-b border-base-200 pb-3">
        <div>
            <h2 class="text-base font-bold text-neutral flex items-center gap-2">
                <i data-lucide="link-2" class="w-5 h-5 text-primary"></i>
                Mapping Product ID / SKU ke Lokal-POS
            </h2>
            <p class="text-xs text-gray-500 font-medium">Hubungkan item stok dengan Product ID / SKU yang digunakan pada menu POS ({{ $mappedCount }} dari {{ count($items) }} terhubung).</p>
        </div>
    </div>

    <form method="POST" action="{{ route('pos-integration.update-mapping') }}">
        @csrf
        <div class="overflow-x-auto rounded-lg border border-base-200">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th>SKU Inventory</th>
                        <th>Nama Item Stok</th>
                        <th>Kategori</th>
                        <th class="text-right">Stok saat ini</th>
                        <th>POS Product ID / SKU Match *</th>
                        <th class="text-center">Status Link</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $index => $item)
                    <tr>
                        <td class="font-mono text-xs font-bold text-neutral">{{ $item->sku }}</td>
                        <td class="font-bold text-neutral text-xs">{{ $item->name }}</td>
                        <td class="text-xs text-gray-500 font-medium">{{ $item->category?->name ?? '-' }}</td>
                        <td class="text-right font-black text-xs">
                            {{ number_format($item->quantity_on_hand, 2) }} {{ $item->unit?->symbol }}
                        </td>
                        <td>
                            <input type="hidden" name="mappings[{{ $index }}][item_id]" value="{{ $item->id }}">
                            <input type="text" name="mappings[{{ $index }}][pos_product_id]" value="{{ $item->pos_product_id }}" placeholder="Match POS Product ID / SKU" class="input w-full px-3 py-1.5 text-xs rounded-lg font-mono font-bold" />
                        </td>
                        <td class="text-center">
                            @if($item->pos_product_id)
                                <span class="badge badge-success text-white badge-xs font-bold shadow-xs">Terhubung</span>
                            @else
                                <span class="badge badge-ghost badge-xs text-gray-400 font-medium">Belum Link</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex justify-end pt-4">
            <button type="submit" class="btn btn-primary text-white btn-sm px-6 rounded-lg font-bold shadow-sm">Simpan Mapping POS</button>
        </div>
    </form>
</div>
@endsection
