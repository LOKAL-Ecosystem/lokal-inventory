@extends('layouts.app')

@section('title', 'Laporan Inventori & Analitik')

@section('content')
<!-- Header -->
<header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-base-300/60 pb-5">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral tracking-tight">Laporan Inventori & Analitik</h1>
        <p class="text-xs text-gray-500 font-medium mt-1">Ringkasan stok saat ini, mutasi per periode, dan item yang paling sering di-restock.</p>
    </div>
</header>

<!-- Filter Periode Laporan -->
<div class="card-premium p-5 shadow-xs">
    <form method="GET" action="{{ route('reports.index') }}" class="flex flex-col sm:flex-row gap-4 items-end">
        <div class="space-y-1">
            <label class="text-xs font-bold text-neutral">Dari Tanggal</label>
            <input type="date" name="start_date" value="{{ $startDate }}" class="input px-3.5 py-2 text-xs rounded-xl font-bold" />
        </div>
        <div class="space-y-1">
            <label class="text-xs font-bold text-neutral">Sampai Tanggal</label>
            <input type="date" name="end_date" value="{{ $endDate }}" class="input px-3.5 py-2 text-xs rounded-xl font-bold" />
        </div>
        <button type="submit" class="btn btn-primary text-white btn-sm px-6 py-2.5 h-auto rounded-xl font-extrabold flex items-center gap-2 shadow-md">
            <i data-lucide="filter" class="w-4 h-4"></i>
            Tampilkan Laporan
        </button>
    </form>
</div>

<!-- Executive Summary Cards -->
<section class="grid grid-cols-1 sm:grid-cols-3 gap-5">
    <!-- Card 1 -->
    <div class="card-premium p-5 flex flex-row items-center justify-between group">
        <div class="space-y-1">
            <div class="text-[11px] font-extrabold uppercase tracking-wider text-gray-400">Total Valuasi Aset Stok</div>
            <div class="text-2xl font-black text-primary tracking-tight">Rp {{ number_format($totalValuation, 0, ',', '.') }}</div>
            <div class="text-[11px] text-gray-400 font-medium">Berdasarkan HPP / harga beli unit</div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold border border-primary/20 group-hover:scale-110 transition-transform shadow-xs">
            <i data-lucide="wallet" class="w-6 h-6"></i>
        </div>
    </div>

    <!-- Card 2 -->
    <div class="card-premium p-5 flex flex-row items-center justify-between group">
        <div class="space-y-1">
            <div class="text-[11px] font-extrabold uppercase tracking-wider text-gray-400">Total Item Terdaftar</div>
            <div class="text-2xl font-black text-neutral tracking-tight">{{ number_format($totalItems) }} Item</div>
            <div class="text-[11px] text-gray-400 font-medium">Aktif di sistem inventori</div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-neutral/10 text-neutral flex items-center justify-center font-bold border border-neutral/20 group-hover:scale-110 transition-transform shadow-xs">
            <i data-lucide="package" class="w-6 h-6"></i>
        </div>
    </div>

    <!-- Card 3 -->
    <div class="card-premium p-5 flex flex-row items-center justify-between group {{ $lowStockCount > 0 ? 'border-error/40 bg-error/5' : '' }}">
        <div class="space-y-1">
            <div class="text-[11px] font-extrabold uppercase tracking-wider text-gray-400">Item Kritis (Low Stock)</div>
            <div class="text-2xl font-black text-error tracking-tight">{{ number_format($lowStockCount) }} Item</div>
            <div class="text-[11px] text-error font-semibold">Perlu segera dilakukan restock</div>
        </div>
        <div class="w-12 h-12 rounded-xl bg-error/10 text-error flex items-center justify-center font-bold border border-error/20 group-hover:scale-110 transition-transform shadow-xs">
            <i data-lucide="alert-triangle" class="w-6 h-6"></i>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Frequent Restock Items Table Card -->
    <div class="card-premium p-6 space-y-4">
        <h2 class="text-base font-bold text-neutral flex items-center gap-2 border-b border-base-200 pb-3">
            <i data-lucide="trending-up" class="w-4.5 h-4.5 text-success"></i>
            Item Paling Sering Restock (Periode Terpilih)
        </h2>
        <div class="overflow-x-auto rounded-xl border border-base-200">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th>Nama Item</th>
                        <th class="text-center">Frekuensi</th>
                        <th class="text-right">Total Qty Masuk</th>
                        <th class="text-right">Total Biaya (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($frequentRestocks as $fr)
                    <tr>
                        <td class="font-extrabold text-neutral text-xs">{{ $fr->item?->name ?? 'Deleted Item' }}</td>
                        <td class="text-center font-bold text-xs"><span class="badge badge-neutral text-xs">{{ $fr->restock_count }}x</span></td>
                        <td class="text-right font-black text-xs text-success">
                            +{{ number_format($fr->total_restock_qty, 2) }} <span class="text-[10px] text-gray-400">{{ $fr->item?->unit?->symbol }}</span>
                        </td>
                        <td class="text-right font-extrabold text-xs text-neutral">
                            Rp {{ number_format($fr->total_restock_cost, 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-10 text-gray-400 text-xs font-medium">
                            <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2 border border-slate-200">
                                <i data-lucide="inbox" class="w-5 h-5"></i>
                            </div>
                            <div>Tidak ada data restock di periode ini.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Ringkasan Mutasi per Tipe Card -->
    <div class="card-premium p-6 space-y-4">
        <h2 class="text-base font-bold text-neutral flex items-center gap-2 border-b border-base-200 pb-3">
            <i data-lucide="pie-chart" class="w-4.5 h-4.5 text-primary"></i>
            Ringkasan Mutasi per Tipe
        </h2>
        <div class="space-y-3">
            @php
                $typeMap = [
                    'stock_in' => ['label' => 'Stock In / Pembelian', 'color' => 'bg-success/10 text-success border-success/20', 'icon' => 'arrow-down-left'],
                    'stock_out_pos' => ['label' => 'Pengurangan Otomatis POS Order', 'color' => 'bg-primary/10 text-primary border-primary/20', 'icon' => 'shopping-cart'],
                    'adjustment_add' => ['label' => 'Adjustment Penambahan (+)', 'color' => 'bg-warning/10 text-warning border-warning/20', 'icon' => 'plus'],
                    'adjustment_sub' => ['label' => 'Adjustment Pengurangan (-)', 'color' => 'bg-error/10 text-error border-error/20', 'icon' => 'minus'],
                    'initial' => ['label' => 'Saldo Awal System', 'color' => 'bg-neutral/10 text-neutral border-neutral/20', 'icon' => 'flag'],
                ];
            @endphp

            @foreach($typeMap as $key => $meta)
                @php $data = $movementSummary->get($key); @endphp
                <div class="flex items-center justify-between p-3.5 rounded-xl border border-base-200 bg-base-200/40 hover:bg-base-200/80 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl border flex items-center justify-center font-bold shadow-xs {{ $meta['color'] }}">
                            <i data-lucide="{{ $meta['icon'] }}" class="w-4.5 h-4.5"></i>
                        </div>
                        <div>
                            <div class="font-extrabold text-xs text-neutral tracking-tight">{{ $meta['label'] }}</div>
                            <div class="text-[11px] text-gray-400 font-semibold">{{ $data ? $data->total_transactions : 0 }} transaksi tercatat</div>
                        </div>
                    </div>
                    <div class="font-black text-sm text-neutral">
                        {{ $data ? number_format($data->total_qty, 2) : '0' }} <span class="text-xs font-semibold text-gray-400">Qty</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
