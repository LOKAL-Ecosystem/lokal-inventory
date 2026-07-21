@extends('layouts.app')

@section('title', 'Riwayat Stock In / Pembelian')

@section('content')
<header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-base-300/60 pb-5">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral tracking-tight">Riwayat Stock In (Restock)</h1>
        <p class="text-xs text-gray-500 font-medium mt-1">Daftar transaksi barang masuk dari supplier & pembelian bahan baku.</p>
    </div>
    <a href="{{ route('stock-in.create') }}" class="btn btn-primary text-white btn-sm flex items-center gap-2 px-5 py-2.5 h-auto shadow-md">
        <i data-lucide="plus-circle" class="w-4 h-4"></i>
        Input Restock Baru
    </a>
</header>

<!-- Filter Search Bar -->
<div class="card-premium p-4 shadow-xs">
    <form method="GET" action="{{ route('stock-in.index') }}" class="flex gap-3">
        <div class="flex-1 relative">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari berdasarkan No. Referensi Pembelian..." class="input w-full pl-10 pr-4 py-2.5 text-xs rounded-xl font-medium" />
            <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3.5 top-3"></i>
        </div>
        <button type="submit" class="btn btn-neutral text-white btn-sm px-6 py-2.5 h-auto rounded-xl font-bold">Cari</button>
        @if(request('search'))
            <a href="{{ route('stock-in.index') }}" class="btn btn-ghost btn-sm text-gray-500 font-semibold">Reset</a>
        @endif
    </form>
</div>

<!-- Table Stock In -->
<div class="card-premium overflow-hidden shadow-xs">
    <div class="overflow-x-auto">
        <table class="table-premium">
            <thead>
                <tr>
                    <th>No. Referensi</th>
                    <th>Tanggal Transaksi</th>
                    <th>Supplier</th>
                    <th>Input By</th>
                    <th class="text-center">Jumlah Item</th>
                    <th class="text-right">Total Biaya (Rp)</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stockIns as $stk)
                <tr>
                    <td><span class="code-chip">{{ $stk->reference_no }}</span></td>
                    <td class="text-xs text-neutral font-medium whitespace-nowrap">{{ $stk->transaction_date->format('d/m/Y') }}</td>
                    <td class="text-xs font-extrabold text-neutral">{{ $stk->supplier?->name ?? 'Tanpa Supplier' }}</td>
                    <td class="text-xs text-gray-500 font-medium">{{ $stk->user?->name ?? 'Staff' }}</td>
                    <td class="text-center">
                        <span class="badge badge-neutral text-xs font-semibold">{{ $stk->items->count() }} item</span>
                    </td>
                    <td class="text-right font-black text-sm text-neutral">
                        Rp {{ number_format($stk->total_cost, 0, ',', '.') }}
                    </td>
                    <td class="text-xs text-gray-500 max-w-xs truncate font-medium">{{ $stk->notes ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-12 text-gray-400 text-xs font-medium">
                        <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3 border border-emerald-200/60 shadow-xs">
                            <i data-lucide="arrow-down-left" class="w-5 h-5"></i>
                        </div>
                        <div class="font-bold text-neutral text-sm">Belum ada data transaksi Stock In.</div>
                        <p class="text-xs text-gray-400 mt-1">Klik tombol "Input Restock Baru" untuk mencatat barang masuk.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($stockIns->hasPages())
    <div class="p-4 border-t border-base-200">
        {{ $stockIns->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
