@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<!-- Page Header -->
<header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-base-300/60 pb-5">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral tracking-tight">Ringkasan Stok & Inventori</h1>
        <p class="text-xs text-gray-500 font-medium mt-1">Pantau ketersediaan bahan baku, mutasi transaksi terkini, dan peringatan stok kritis secara real-time.</p>
    </div>
    <div class="flex items-center gap-2.5">
        <a href="{{ route('stock-in.create') }}" class="btn btn-primary text-white btn-sm flex items-center gap-2 shadow-xs rounded-lg px-4">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            Tambah Stock In
        </a>
        <a href="{{ route('adjustments.create') }}" class="btn btn-outline border-base-300 hover:bg-base-200 text-neutral btn-sm flex items-center gap-2 rounded-lg px-4">
            <i data-lucide="sliders" class="w-4 h-4 text-warning"></i>
            Opname Stok
        </a>
    </div>
</header>

<!-- Metrics Grid -->
<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
    <!-- Stat 1 -->
    <div class="card-premium p-5 flex flex-row items-center justify-between relative overflow-hidden group">
        <div class="space-y-1">
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total Item Stok</div>
            <div class="text-3xl font-black text-neutral tracking-tight">{{ number_format($totalItems) }}</div>
            <div class="text-[11px] text-gray-500 font-medium">Bahan & produk terdaftar</div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold shadow-xs border border-primary/20 group-hover:scale-110 transition-transform">
            <i data-lucide="package" class="w-6 h-6"></i>
        </div>
    </div>

    <!-- Stat 2 -->
    <div class="card-premium p-5 flex flex-row items-center justify-between relative overflow-hidden group">
        <div class="space-y-1">
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Valuasi Stok</div>
            <div class="text-xl font-black text-neutral tracking-tight">Rp {{ number_format($totalValuation, 0, ',', '.') }}</div>
            <div class="text-[11px] text-gray-500 font-medium">Total modal HPP fisik</div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-success/10 text-success flex items-center justify-center font-bold shadow-xs border border-success/20 group-hover:scale-110 transition-transform">
            <i data-lucide="wallet" class="w-6 h-6"></i>
        </div>
    </div>

    <!-- Stat 3 -->
    <div class="card-premium p-5 flex flex-row items-center justify-between relative overflow-hidden group {{ count($lowStockItems) > 0 ? 'border-error/40 bg-error/5' : '' }}">
        <div class="space-y-1">
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Item Low Stock</div>
            <div class="text-3xl font-black text-error tracking-tight">{{ number_format(count($lowStockItems)) }}</div>
            <div class="text-[11px] text-error font-semibold">Di bawah threshold min.</div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-error/10 text-error flex items-center justify-center font-bold shadow-xs border border-error/20 group-hover:scale-110 transition-transform">
            <i data-lucide="alert-triangle" class="w-6 h-6"></i>
        </div>
    </div>

    <!-- Stat 4 -->
    <div class="card-premium p-5 flex flex-row items-center justify-between relative overflow-hidden group">
        <div class="space-y-1">
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total Supplier</div>
            <div class="text-3xl font-black text-neutral tracking-tight">{{ number_format($totalSuppliers) }}</div>
            <div class="text-[11px] text-gray-500 font-medium">Pemasok bahan aktif</div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-neutral/10 text-neutral flex items-center justify-center font-bold shadow-xs border border-neutral/20 group-hover:scale-110 transition-transform">
            <i data-lucide="truck" class="w-6 h-6"></i>
        </div>
    </div>
</section>

<!-- Low Stock Alert Banner & Table -->
@if(count($lowStockItems) > 0)
<div class="card-premium border-error/30 p-6 space-y-4 shadow-sm bg-gradient-to-r from-error/5 via-base-100 to-base-100">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="p-2.5 rounded-lg bg-error text-white font-bold shadow-xs">
                <i data-lucide="alert-circle" class="w-5 h-5"></i>
            </div>
            <div>
                <h2 class="text-base font-extrabold text-error">
                    Peringatan Kritis: {{ count($lowStockItems) }} Item Perlu Restock Segera!
                </h2>
                <p class="text-xs text-gray-500">Stok fisik saat ini berada di bawah batas minimal ketersediaan.</p>
            </div>
        </div>
        <a href="{{ route('stock-in.create') }}" class="btn btn-error text-white btn-sm px-5 rounded-lg shadow-xs font-semibold">
            Input Restock Pembelian
        </a>
    </div>
    <div class="overflow-x-auto rounded-lg border border-error/20 bg-base-100">
        <table class="table-premium">
            <thead>
                <tr>
                    <th>SKU / Code</th>
                    <th>Nama Item Stok</th>
                    <th>Kategori</th>
                    <th class="text-right">Stok Fisik</th>
                    <th class="text-right">Min Threshold</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lowStockItems as $item)
                <tr>
                    <td class="font-mono text-xs font-bold text-gray-500">{{ $item->sku }}</td>
                    <td class="font-bold text-neutral">{{ $item->name }}</td>
                    <td><span class="badge badge-ghost badge-sm text-gray-500 font-semibold">{{ $item->category?->name ?? '-' }}</span></td>
                    <td class="text-right font-black text-sm text-error">
                        {{ number_format($item->quantity_on_hand, 2) }} <span class="text-xs font-medium text-gray-400">{{ $item->unit?->symbol }}</span>
                    </td>
                    <td class="text-right text-xs text-gray-500 font-semibold">
                        {{ number_format($item->minimum_stock, 2) }} {{ $item->unit?->symbol }}
                    </td>
                    <td class="text-center">
                        <span class="badge badge-error text-white badge-sm font-bold shadow-xs">Low Stock</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- Recent Activity Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Mutasi Stok Terbaru -->
    <div class="card-premium p-6 space-y-4">
        <div class="flex justify-between items-center border-b border-base-200 pb-3">
            <h2 class="text-base font-bold text-neutral flex items-center gap-2">
                <i data-lucide="activity" class="w-4.5 h-4.5 text-primary"></i>
                Riwayat Mutasi Terakhir
            </h2>
            <a href="{{ route('movements.index') }}" class="text-xs text-primary font-bold hover:underline flex items-center gap-1">
                Lihat Semua <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Item</th>
                        <th>Tipe</th>
                        <th class="text-right">Perubahan</th>
                        <th class="text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentMovements as $mov)
                    <tr>
                        <td class="text-[11px] text-gray-400 font-mono whitespace-nowrap">{{ $mov->created_at->format('d/m H:i') }}</td>
                        <td class="font-bold text-neutral text-xs">{{ $mov->item?->name ?? 'Deleted Item' }}</td>
                        <td>
                            @if($mov->type == 'stock_in')
                                <span class="badge badge-success text-white text-[10px] font-bold">Stock In</span>
                            @elseif($mov->type == 'stock_out_pos')
                                <span class="badge badge-primary text-white text-[10px] font-bold">POS Order</span>
                            @else
                                <span class="badge badge-warning text-neutral text-[10px] font-bold">Adjustment</span>
                            @endif
                        </td>
                        <td class="text-right font-black text-xs {{ $mov->quantity_change >= 0 ? 'text-success' : 'text-error' }}">
                            {{ $mov->quantity_change > 0 ? '+' : '' }}{{ number_format($mov->quantity_change, 2) }}
                        </td>
                        <td class="text-right font-bold text-xs text-neutral">
                            {{ number_format($mov->quantity_after, 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-6 text-gray-400 text-xs font-medium">Belum ada riwayat mutasi stok.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Restock / Stock In Terbaru -->
    <div class="card-premium p-6 space-y-4">
        <div class="flex justify-between items-center border-b border-base-200 pb-3">
            <h2 class="text-base font-bold text-neutral flex items-center gap-2">
                <i data-lucide="truck" class="w-4.5 h-4.5 text-primary"></i>
                Stock In / Pembelian Terakhir
            </h2>
            <a href="{{ route('stock-in.index') }}" class="text-xs text-primary font-bold hover:underline flex items-center gap-1">
                Lihat Semua <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>
        <div class="space-y-3">
            @forelse($recentStockIns as $stkIn)
            <div class="flex items-center justify-between p-3.5 bg-base-200/40 hover:bg-base-200/80 rounded-xl border border-base-200 transition-colors">
                <div class="space-y-0.5">
                    <div class="font-mono font-bold text-xs text-primary flex items-center gap-1.5">
                        <i data-lucide="file-text" class="w-3.5 h-3.5 text-gray-400"></i>
                        {{ $stkIn->reference_no }}
                    </div>
                    <div class="text-xs font-semibold text-neutral">Supplier: {{ $stkIn->supplier?->name ?? 'Umum' }}</div>
                    <div class="text-[10px] text-gray-400 font-medium">{{ $stkIn->transaction_date->format('d M Y') }}</div>
                </div>
                <div class="text-right">
                    <div class="font-black text-sm text-neutral">Rp {{ number_format($stkIn->total_cost, 0, ',', '.') }}</div>
                    <div class="text-[11px] text-gray-400 font-medium">{{ $stkIn->user?->name ?? 'Staff' }}</div>
                </div>
            </div>
            @empty
            <div class="text-center py-8 text-gray-400 text-xs font-medium">Belum ada transaksi Stock In.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
